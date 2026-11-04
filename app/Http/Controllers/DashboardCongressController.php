<?php

namespace App\Http\Controllers;

use App\Models\CongressBoard;
use App\Models\CongressQuestion;
use App\Support\Congress\CongressBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tableaux de questions des tables rondes.
 */
class DashboardCongressController extends Controller
{
    public function __construct(private CongressBoardService $service)
    {
    }

    /* ===================== TABLEAUX ===================== */

    /**
     * GET /admin/dashboard/congres
     */
    public function index()
    {
        $boards = DB::table('congress_boards as b')
            ->leftJoin('past_events as pe', 'pe.id', '=', 'b.past_event_id')
            ->leftJoin('events as e', 'e.id', '=', 'pe.id_event')
            ->select('b.id', 'b.title', 'b.congress_name', 'b.code', 'b.status', 'b.created_at', 'e.name as event_name', 'pe.edition_year')
            ->selectSub(
                DB::table('congress_questions as q')->whereColumn('q.board_id', 'b.id')->selectRaw('COUNT(*)'),
                'questions_count'
            )
            ->orderByDesc('b.id')
            ->get();

        return view('dashboard.congres.index', ['boards' => $boards]);
    }

    /**
     * GET /admin/dashboard/congres/create
     */
    public function create()
    {
        return view('dashboard.congres.form', [
            'board'    => new CongressBoard(),
            'themes'   => collect(),
            'editions' => $this->editions(),
        ]);
    }

    /**
     * POST /admin/dashboard/congres
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        $board = CongressBoard::create([
            'past_event_id' => $data['past_event_id'] ?? null,
            'congress_name' => $data['congress_name'] ?: $this->editionLabel($data['past_event_id'] ?? null),
            'title'         => $data['title'],
            'code'          => $this->service->newCode(),
            'screen_token'  => $this->service->newScreenToken(),
        ]);

        $this->service->syncThemes($board, $data['themes']);

        return redirect()->route('dashboard.congres.live', $board);
    }

    /**
     * GET /admin/dashboard/congres/{board}/edit
     */
    public function edit(CongressBoard $board)
    {
        return view('dashboard.congres.form', [
            'board'    => $board,
            'themes'   => $board->themes()->get(),
            'editions' => $this->editions(),
        ]);
    }

    /**
     * PUT /admin/dashboard/congres/{board}
     */
    public function update(Request $request, CongressBoard $board)
    {
        $data = $this->validated($request);

        $kept = collect($data['themes'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $blocked = $this->service->undeletableThemes($board, $kept);

        if ($blocked) {
            throw ValidationException::withMessages([
                'themes' => 'Ces thèmes contiennent encore des questions : ' . implode(', ', $blocked) . '.',
            ]);
        }

        $board->update([
            'past_event_id' => $data['past_event_id'] ?? null,
            'congress_name' => $data['congress_name'] ?: $this->editionLabel($data['past_event_id'] ?? null),
            'title'         => $data['title'],
        ]);

        $this->service->syncThemes($board, $data['themes']);

        return redirect()->route('dashboard.congres.live', $board);
    }

    /**
     * DELETE /admin/dashboard/congres/{board}
     */
    public function destroy(CongressBoard $board)
    {
        DB::transaction(function () use ($board) {
            $board->questions()->delete();
            $board->participants()->delete();
            $board->themes()->delete();
            $board->delete();
        });

        return redirect()->route('dashboard.congres');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'themes' => collect($request->input('themes', []))
                ->map(fn ($row) => ['id' => $row['id'] ?? null, 'name' => trim((string) ($row['name'] ?? ''))])
                ->filter(fn ($row) => $row['name'] !== '')
                ->values()
                ->all(),
        ]);

        return $request->validate([
            'past_event_id'  => ['nullable', 'integer', 'exists:past_events,id'],
            'congress_name'  => ['nullable', 'string', 'max:150'],
            'title'          => ['required', 'string', 'max:150'],
            'themes'         => ['required', 'array', 'min:1', 'max:' . CongressBoardService::MAX_THEMES],
            'themes.*.id'    => ['nullable', 'integer'],
            'themes.*.name'  => ['required', 'string', 'max:60'],
        ], [
            'themes.required' => 'Ajoutez au moins un thème.',
            'themes.max'      => 'L\'écran affiche ' . CongressBoardService::MAX_THEMES . ' thèmes au maximum.',
        ]);
    }

    private function editions()
    {
        return DB::table('past_events as pe')
            ->join('events as e', 'e.id', '=', 'pe.id_event')
            ->select('pe.id')
            ->selectRaw("CONCAT(e.name, ' ', pe.edition_year) as label")
            ->orderByDesc('pe.edition_year')
            ->orderBy('e.name')
            ->get();
    }

    private function editionLabel(?int $pastEventId): ?string
    {
        if (!$pastEventId) {
            return null;
        }

        return DB::table('past_events as pe')
            ->join('events as e', 'e.id', '=', 'pe.id_event')
            ->where('pe.id', $pastEventId)
            ->selectRaw("CONCAT(e.name, ' ', pe.edition_year) as label")
            ->value('label');
    }

    /* ===================== EN DIRECT ===================== */

    /**
     * GET /admin/dashboard/congres/{board}/live
     */
    public function live(CongressBoard $board)
    {
        return view('dashboard.congres.live', ['board' => $board]);
    }

    /**
     * Sans changement depuis `rev`, réponse vide.
     * GET /admin/dashboard/congres/{board}/live/data?rev=
     */
    public function liveData(Request $request, CongressBoard $board)
    {
        if ((string) $board->revision === (string) $request->query('rev')) {
            return response()->noContent();
        }

        return response()->json([
            'revision' => $board->revision,
            'status'   => $board->status,
            'themes'   => $this->service->snapshot($board, false),
        ]);
    }

    /**
     * POST /admin/dashboard/congres/{board}/start
     */
    public function start(CongressBoard $board): JsonResponse
    {
        $this->service->setStatus($board, CongressBoard::STATUS_LIVE);

        return response()->json(['status' => $board->status]);
    }

    /**
     * POST /admin/dashboard/congres/{board}/stop
     */
    public function stop(CongressBoard $board): JsonResponse
    {
        $this->service->setStatus($board, CongressBoard::STATUS_CLOSED);

        return response()->json(['status' => $board->status]);
    }

    /**
     * L'ancien lien de projection cesse de répondre.
     * POST /admin/dashboard/congres/{board}/screen-link
     */
    public function regenerateScreenLink(CongressBoard $board): JsonResponse
    {
        $board->update(['screen_token' => $this->service->newScreenToken()]);

        return response()->json(['screen_url' => $board->screenUrl()]);
    }

    /**
     * Ordre d'un thème ; une question venue d'un autre thème y est déplacée.
     * POST /admin/dashboard/congres/{board}/arrange
     */
    public function arrange(Request $request, CongressBoard $board): JsonResponse
    {
        $data = $request->validate([
            'theme_id' => ['required', 'integer'],
            'ids'      => ['present', 'array'],
            'ids.*'    => ['integer'],
        ]);

        abort_unless($board->themes()->whereKey($data['theme_id'])->exists(), 422, 'Thème inconnu.');

        $this->service->arrange($board, (int) $data['theme_id'], array_map('intval', $data['ids']));

        return response()->json(['revision' => $board->fresh()->revision]);
    }

    /**
     * DELETE /admin/dashboard/congres/{board}/questions/{question}
     */
    public function deleteQuestion(CongressBoard $board, CongressQuestion $question): JsonResponse
    {
        abort_unless((int) $question->board_id === (int) $board->id, 404);

        $this->service->deleteQuestion($board, $question);

        return response()->json(['revision' => $board->fresh()->revision]);
    }
}
