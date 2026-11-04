<?php

namespace App\Support\Congress;

use App\Models\CongressBoard;
use App\Models\CongressBoardTheme;
use App\Models\CongressParticipant;
use App\Models\CongressQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Règles communes au public, à l'écran de projection et au CMS.
 */
class CongressBoardService
{
    public const MAX_THEMES = 6;
    public const QUESTION_MAX_LENGTH = 120;

    /** Teintes de constants/Colors.ts, attribuées dans l'ordre : claires pour rester lisibles au vidéoprojecteur. */
    public const COLORS = ['#FFAF32', '#84D8FF', '#A5ED6E', '#FFB2B2', '#FBE56D', '#C5BAD5'];

    /** Pas d'I, O, 0 ni 1 : le code se lit de loin et se saisit à la main. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const CODE_LENGTH = 5;

    /* ===================== MISE EN PAGE ===================== */

    /**
     * Cadre d'un thème sur la grille de 6 colonnes de l'écran : moins il y a de thèmes,
     * plus chaque cadre est grand et plus il affiche de questions.
     *
     * @return array{span:int, cols:int, rows:int, capacity:int}
     */
    public static function layout(int $themeCount, int $index): array
    {
        [$span, $cols, $rows] = match (true) {
            $themeCount <= 1                 => [6, 3, 4],
            $themeCount === 2                => [3, 2, 3],
            $themeCount === 3                => [2, 1, 4],
            $themeCount === 4                => [3, 2, 2],
            $themeCount === 5 && $index >= 3 => [3, 2, 2],
            default                          => [2, 1, 2],
        };

        return ['span' => $span, 'cols' => $cols, 'rows' => $rows, 'capacity' => $cols * $rows];
    }

    /* ===================== TABLEAUX ===================== */

    public function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (CongressBoard::where('code', $code)->exists());

        return $code;
    }

    public function newScreenToken(): string
    {
        return Str::random(48);
    }

    /**
     * Enregistre la liste des thèmes telle que saisie : un thème existant garde sa couleur,
     * un nouveau prend la première couleur libre. Un thème qui contient encore des questions
     * n'est jamais supprimé ; l'appelant le vérifie avec undeletableThemes().
     *
     * @param array<int, array{id:?int, name:string}> $rows
     */
    public function syncThemes(CongressBoard $board, array $rows): void
    {
        DB::transaction(function () use ($board, $rows) {
            $existing = CongressBoardTheme::where('board_id', $board->id)->get()->keyBy('id');
            $keptIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            CongressBoardTheme::where('board_id', $board->id)->whereNotIn('id', $keptIds ?: [0])->delete();

            $used = $existing->only($keptIds)->pluck('color')->all();
            $free = array_values(array_diff(self::COLORS, $used));

            foreach (array_values($rows) as $i => $row) {
                $theme = $row['id'] ? $existing->get((int) $row['id']) : null;

                if ($theme) {
                    $theme->update(['name' => $row['name'], 'order_dsp' => $i + 1]);
                    continue;
                }

                CongressBoardTheme::create([
                    'board_id'  => $board->id,
                    'name'      => $row['name'],
                    'color'     => array_shift($free),
                    'order_dsp' => $i + 1,
                ]);
            }
        });

        $this->touch($board);
    }

    /**
     * Noms des thèmes retirés de la liste alors qu'ils contiennent des questions.
     *
     * @param int[] $keptIds
     * @return string[]
     */
    public function undeletableThemes(CongressBoard $board, array $keptIds): array
    {
        return CongressBoardTheme::where('board_id', $board->id)
            ->whereNotIn('id', $keptIds ?: [0])
            ->whereExists(fn ($q) => $q->from('congress_questions as q')
                ->whereColumn('q.theme_id', 'congress_board_themes.id')
                ->selectRaw('1'))
            ->pluck('name')
            ->all();
    }

    public function setStatus(CongressBoard $board, string $status): void
    {
        $board->update([
            'status'     => $status,
            'started_at' => $status === CongressBoard::STATUS_LIVE ? ($board->started_at ?? now()) : $board->started_at,
            'closed_at'  => $status === CongressBoard::STATUS_CLOSED ? now() : null,
        ]);

        $this->touch($board);
    }

    /** Signale à l'écran et au CMS qu'il y a quelque chose à relire. */
    public function touch(CongressBoard $board): void
    {
        $board->increment('revision');
    }

    /* ===================== PUBLIC ===================== */

    /**
     * @return array{0: CongressParticipant, 1: string} participant et jeton en clair (rendu une seule fois)
     */
    public function join(CongressBoard $board, string $name): array
    {
        $token = Str::random(48);

        $participant = CongressParticipant::create([
            'board_id'   => $board->id,
            'name'       => $name,
            'token_hash' => hash('sha256', $token),
        ]);

        return [$participant, $token];
    }

    public function participantFor(CongressBoard $board, ?string $token): ?CongressParticipant
    {
        if (!$token) {
            return null;
        }

        return CongressParticipant::where('board_id', $board->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * Une nouvelle question va en fin de file de son thème.
     */
    public function addQuestion(CongressBoard $board, CongressParticipant $participant, int $themeId, string $text): CongressQuestion
    {
        $question = DB::transaction(function () use ($board, $participant, $themeId, $text) {
            // Verrou sur le tableau : deux envois simultanés ne doivent pas prendre la même position.
            CongressBoard::whereKey($board->id)->lockForUpdate()->first();

            return CongressQuestion::create([
                'board_id'       => $board->id,
                'theme_id'       => $themeId,
                'participant_id' => $participant->id,
                'text'           => $text,
                'position'       => $this->nextPosition($themeId),
            ]);
        }, 5);

        $this->touch($board);

        return $question;
    }

    private function nextPosition(int $themeId): int
    {
        return (int) CongressQuestion::where('theme_id', $themeId)->max('position') + 1;
    }

    /* ===================== MODÉRATION ===================== */

    /**
     * Fixe l'ordre d'un thème. Une question venue d'un autre thème y est déplacée.
     * Les questions arrivées pendant que l'admin déplaçait une carte, absentes de la liste
     * reçue, restent derrière : elles ne doivent pas se perdre ni passer devant.
     *
     * @param int[] $questionIds
     */
    public function arrange(CongressBoard $board, int $themeId, array $questionIds): void
    {
        DB::transaction(function () use ($board, $themeId, $questionIds) {
            CongressBoard::whereKey($board->id)->lockForUpdate()->first();

            $known = CongressQuestion::where('board_id', $board->id)
                ->whereIn('id', $questionIds)
                ->pluck('id')
                ->all();
            $ordered = array_values(array_intersect($questionIds, $known));

            $others = CongressQuestion::where('theme_id', $themeId)
                ->whereNotIn('id', $ordered ?: [0])
                ->orderBy('position')
                ->pluck('id')
                ->all();

            foreach (array_merge($ordered, $others) as $i => $id) {
                CongressQuestion::whereKey($id)->update(['theme_id' => $themeId, 'position' => $i + 1]);
            }
        }, 5);

        $this->touch($board);
    }

    public function deleteQuestion(CongressBoard $board, CongressQuestion $question): void
    {
        $question->delete();
        $this->touch($board);
    }

    /* ===================== LECTURE ===================== */

    /**
     * Thèmes avec leur file complète (CMS) ou seulement le début visible (écran).
     */
    public function snapshot(CongressBoard $board, bool $visibleOnly): array
    {
        $themes = $board->themes()->get();
        $count = $themes->count();

        $questions = DB::table('congress_questions as q')
            ->join('congress_participants as p', 'p.id', '=', 'q.participant_id')
            ->where('q.board_id', $board->id)
            ->orderBy('q.position')
            ->orderBy('q.id')
            ->select('q.id', 'q.theme_id', 'q.text', 'p.name as author')
            ->get()
            ->groupBy('theme_id');

        return $themes->values()->map(function ($theme, $index) use ($questions, $count, $visibleOnly) {
            $layout = self::layout($count, $index);
            $queue = ($questions->get($theme->id) ?? collect())->values();

            return [
                'id'        => $theme->id,
                'name'      => $theme->name,
                'color'     => $theme->color,
                'layout'    => $layout,
                'total'     => $queue->count(),
                'questions' => ($visibleOnly ? $queue->take($layout['capacity']) : $queue)
                    ->map(fn ($q) => ['id' => $q->id, 'text' => $q->text, 'author' => $q->author])
                    ->values()
                    ->all(),
            ];
        })->all();
    }
}
