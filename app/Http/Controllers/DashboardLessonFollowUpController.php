<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Suivi qualité des formations (Qualiopi ind. 2 et 30) : taux de complétion et satisfaction à froid.
 */
class DashboardLessonFollowUpController extends Controller
{
    /** package_user.progress au-delà duquel un paquet compte comme terminé. */
    private const COMPLETION_MIN = 60;

    private const PER_PAGE = 20;

    /**
     * GET /admin/dashboard/lessons/suivi
     */
    public function index(Request $request)
    {
        $f = $this->resolveFilters($request);

        return view('dashboard.lessons.suivi', [
            'filters'    => $f,
            'completion' => $this->completionSummary($this->completionRows($f)),
            'survey'     => $this->surveySummary($f),
            'responses'  => $this->answeredQuery($f)->orderByDesc('s.answered_at')->paginate(self::PER_PAGE)->withQueryString(),
            'minimum'    => self::COMPLETION_MIN,
        ]);
    }

    /**
     * GET /admin/dashboard/lessons/suivi/export
     */
    public function export(Request $request): StreamedResponse
    {
        $f = $this->resolveFilters($request);
        $completionRows = $this->completionRows($f);
        $period = ($f['from'] ? Carbon::parse($f['from'])->format('d/m/Y') : 'début') . ' – ' . Carbon::parse($f['to'])->format('d/m/Y');

        $spreadsheet = new Spreadsheet();

        $this->fillSheet($spreadsheet->getActiveSheet(), 'Synthèse', $period, ['Indicateur', 'Valeur', 'Mode de calcul'],
            $this->summaryRows($this->completionSummary($completionRows), $this->surveySummary($f)));
        $this->fillSheet($spreadsheet->createSheet(), 'Réponses', $period, $this->responseHeaders(),
            $this->answeredQuery($f)->orderBy('s.answered_at')->get()->map(fn ($r) => $this->responseRow($r))->all());
        $this->fillSheet($spreadsheet->createSheet(), 'Complétion', $period,
            ['Date du devis', 'Devis', 'Apprenant', 'E-mail', 'Formation', 'Progression (%)', 'Terminée'],
            $completionRows->map(fn ($r) => [
                $r->purchased_at->format('d/m/Y'), $r->contract_no, $r->learner, $r->email, $r->package,
                $r->progress, $r->completed ? 'Oui' : 'Non',
            ])->all());

        $spreadsheet->setActiveSheetIndex(0);

        return $this->streamXlsx($spreadsheet, 'suivi-qualite-' . ($f['from'] ?? 'debut') . '-' . $f['to'] . '.xlsx');
    }

    /* ===================== FILTRES ===================== */

    /**
     * Par défaut : depuis le 1er janvier de l'année en cours.
     */
    private function resolveFilters(Request $request): array
    {
        $today = Carbon::today($this->displayTz());
        $to = $request->date('to') ?: $today;
        $from = $request->has('from') ? $request->date('from') : $today->copy()->startOfYear();

        if ($from && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from?->toDateString(), 'to' => $to->toDateString()];
    }

    private function displayTz(): string
    {
        return config('app.display_timezone', 'Asia/Ho_Chi_Minh');
    }

    /**
     * Bornes [début, fin] en heure de stockage (UTC) à partir de dates locales.
     */
    private function storageBounds(array $f): array
    {
        $disp = $this->displayTz();
        $app = config('app.timezone', 'UTC');

        return [
            $f['from'] ? Carbon::parse($f['from'] . ' 00:00:00', $disp)->setTimezone($app) : null,
            Carbon::parse($f['to'] . ' 23:59:59', $disp)->setTimezone($app),
        ];
    }

    private function whereBetweenPeriod($query, string $column, array $f)
    {
        [$from, $to] = $this->storageBounds($f);

        return $query->when($from, fn ($q) => $q->where($column, '>=', $from))->where($column, '<=', $to);
    }

    /* ===================== COMPLÉTION ===================== */

    /**
     * Une ligne par apprenant × paquet acheté, pour les devis datés dans la période.
     */
    private function completionRows(array $f): Collection
    {
        $surveys = $this->whereBetweenPeriod(SatisfactionSurvey::query(), 'purchased_at', $f)
            ->with('user:id,name,first_name,email')
            ->orderBy('purchased_at')
            ->get();

        $userIds = $surveys->pluck('user_id')->unique();

        $progress = DB::table('package_user')
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'package_id', 'progress'])
            ->keyBy(fn ($r) => $r->user_id . '-' . $r->package_id);

        $names = DB::table('packages as p')
            ->leftJoin('trainings as t', 't.id', '=', 'p.training_id')
            ->whereIn('p.id', $surveys->pluck('package_ids')->flatten()->unique())
            ->selectRaw("p.id, CASE WHEN t.name IS NULL OR t.name = p.name THEN p.name ELSE CONCAT(t.name, ' — ', p.name) END as label")
            ->pluck('label', 'id');

        return $surveys->flatMap(fn ($s) => collect($s->package_ids)->map(function ($packageId) use ($s, $progress, $names) {
            $value = (int) ($progress->get($s->user_id . '-' . $packageId)?->progress ?? 0);

            return (object) [
                'purchased_at' => $s->purchased_at->copy()->setTimezone($this->displayTz()),
                'contract_no'  => $s->contract_no,
                'learner'      => trim(($s->user?->first_name ?? '') . ' ' . ($s->user?->name ?? '')),
                'email'        => $s->user?->email,
                'package'      => $names[$packageId] ?? ('#' . $packageId),
                'progress'     => $value,
                'completed'    => $value > self::COMPLETION_MIN,
            ];
        }))->values();
    }

    private function completionSummary(Collection $rows): array
    {
        $total = $rows->count();
        $done = $rows->where('completed', true)->count();

        return [
            'total' => $total,
            'done'  => $done,
            'rate'  => $total ? round($done * 100 / $total, 1) : null,
        ];
    }

    /* ===================== SATISFACTION ===================== */

    /**
     * Réponses reçues dans la période (date de réponse).
     */
    private function answeredQuery(array $f)
    {
        return $this->answeredBase($f)
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->select('s.*', 'u.email')
            ->selectRaw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.name, ''))) as learner");
    }

    private function answeredBase(array $f)
    {
        return $this->whereBetweenPeriod(
            DB::table('satisfaction_surveys as s')->whereNotNull('s.answered_at'),
            's.answered_at',
            $f
        );
    }

    private function surveySummary(array $f): array
    {
        $ratings = array_keys(SatisfactionSurvey::RATINGS);

        $stats = $this->answeredBase($f)
            ->selectRaw('COUNT(*) as answered, AVG(s.score) as score')
            ->selectRaw('SUM(s.rating_global >= ?) as satisfied', [SatisfactionSurvey::SATISFIED_MIN])
            ->selectRaw('SUM(s.recommend >= 9) as promoters, SUM(s.recommend <= 6) as detractors')
            ->selectRaw(implode(', ', array_map(fn ($c) => "AVG(s.{$c}) as {$c}", $ratings)))
            ->first();

        $sent = $this->whereBetweenPeriod(DB::table('satisfaction_surveys'), 'sent_at', $f)->count();
        $sentAnswered = $this->whereBetweenPeriod(DB::table('satisfaction_surveys'), 'sent_at', $f)->whereNotNull('answered_at')->count();
        $answered = (int) $stats->answered;

        return [
            'answered'      => $answered,
            'score'         => $answered ? round((float) $stats->score, 2) : null,
            'satisfaction'  => $answered ? round($stats->satisfied * 100 / $answered, 1) : null,
            'nps'           => $answered ? (int) round(($stats->promoters - $stats->detractors) * 100 / $answered) : null,
            'sent'          => $sent,
            'response_rate' => $sent ? round($sentAnswered * 100 / $sent, 1) : null,
            'questions'     => collect(SatisfactionSurvey::RATINGS)->map(fn ($label, $col) => [
                'label'   => $label,
                'average' => $answered ? round((float) $stats->{$col}, 2) : null,
            ])->values()->all(),
        ];
    }

    /* ===================== EXCEL ===================== */

    private function summaryRows(array $c, array $s): array
    {
        $fmt = fn ($v, $suffix = '') => $v === null ? '—' : str_replace('.', ',', (string) $v) . $suffix;

        $rows = [
            ['Taux de complétion', $fmt($c['rate'], ' %'), "Paquets dont la progression dépasse " . self::COMPLETION_MIN . " % / paquets achetés (devis datés dans la période) : {$c['done']} / {$c['total']}"],
            ['Score de satisfaction', $fmt($s['score'], ' / 5'), 'Moyenne des 6 questions notées de 1 à 5, réponses reçues dans la période'],
            ['Taux de satisfaction', $fmt($s['satisfaction'], ' %'), 'Part des répondants ayant noté la satisfaction globale ' . SatisfactionSurvey::SATISFIED_MIN . ' ou 5'],
            ['Net Promoter Score', $fmt($s['nps']), '% de notes 9–10 moins % de notes 0–6 (recommandation)'],
            ['Réponses', (string) $s['answered'], 'Questionnaires remplis dans la période'],
            ['Taux de réponse', $fmt($s['response_rate'], ' %'), "Questionnaires envoyés dans la période et remplis / envoyés ({$s['sent']})"],
            ['', '', ''],
        ];

        foreach ($s['questions'] as $q) {
            $rows[] = [$q['label'], $fmt($q['average'], ' / 5'), 'Moyenne'];
        }

        return $rows;
    }

    private function responseHeaders(): array
    {
        return array_merge(
            ['Date de réponse', 'Date du devis', 'Devis', 'Apprenant', 'E-mail', 'Formation(s)', 'Score'],
            array_values(SatisfactionSurvey::RATINGS),
            ['Mise en pratique', 'Recommandation (0–10)', 'Difficultés', 'Précisions', 'Points appréciés', 'Suggestions', 'À recontacter']
        );
    }

    private function responseRow(object $r): array
    {
        $local = fn ($v) => Carbon::parse($v, config('app.timezone', 'UTC'))->setTimezone($this->displayTz());

        return array_merge(
            [
                $local($r->answered_at)->format('d/m/Y H:i'),
                $local($r->purchased_at)->format('d/m/Y'),
                $r->contract_no,
                $r->learner,
                $r->email,
                str_replace("\n", ' ; ', $r->packages_label),
                (float) $r->score,
            ],
            array_map(fn ($c) => (int) $r->{$c}, array_keys(SatisfactionSurvey::RATINGS)),
            [
                SatisfactionSurvey::PRACTICE[$r->practice] ?? $r->practice,
                (int) $r->recommend,
                $r->difficulties_flg ? 'Oui' : 'Non',
                $r->difficulties_text,
                $r->liked_text,
                $r->suggestions_text,
                $r->recontact_flg ? 'Oui' : 'Non',
            ]
        );
    }

    private function fillSheet($sheet, string $title, string $period, array $headers, array $rows): void
    {
        $sheet->setTitle($title);
        $sheet->setCellValue('A1', 'Suivi qualité — ' . $title);
        $sheet->setCellValue('A2', 'Période : ' . $period);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->fromArray($headers, null, 'A4');
        $sheet->fromArray($rows ?: [['Aucune donnée']], null, 'A5', true);

        $last = $sheet->getHighestColumn();
        $sheet->getStyle("A4:{$last}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EB7B0F']],
        ]);
        $sheet->getStyle("A4:{$last}4")->getAlignment()->setWrapText(true);

        foreach (range(1, count($headers)) as $i) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            // Les colonnes de texte libre et de libellés de questions sont larges ; le reste s'ajuste.
            mb_strlen($headers[$i - 1]) > 30 || in_array($headers[$i - 1], ['Précisions', 'Points appréciés', 'Suggestions', 'Mode de calcul', 'Formation(s)', 'Indicateur'], true)
                ? $sheet->getColumnDimension($column)->setWidth(40)
                : $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A5');
    }

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
