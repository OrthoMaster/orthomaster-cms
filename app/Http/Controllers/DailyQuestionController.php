<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesContentTopics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyQuestionController extends Controller
{
    use HandlesContentTopics;

    protected function contentType(): string
    {
        return 'daily-question';
    }

    /** Nombre minimum de réponses par question */
    private const MIN_ANSWERS = 2;

    /** Nombre maximum de réponses par question */
    private const MAX_ANSWERS = 4;

    /**
     * Liste des questions du jour.
     * GET /daily-questions
     */
    public function index()
    {
        // Consultations et spectateurs distincts, depuis le journal de vues
        $views = "(SELECT COUNT(*)
                   FROM trk_content_views v
                   WHERE v.type = 'question-view' AND v.content_id = q.id)";

        $viewers = "(SELECT COUNT(DISTINCT v.user_id)
                     FROM trk_content_views v
                     WHERE v.type = 'question-view' AND v.content_id = q.id)";

        $questions = DB::table('daily_questions as q')
            ->leftJoin('topics as t', 't.id', '=', 'q.topic_id')
            ->select(
                'q.id',
                'q.title',
                'q.question',
                'q.count',
                'q.published_at',
                'q.expired_at',
                't.name as topic_name'
            )
            ->selectRaw("{$views} as views")
            ->selectRaw("{$viewers} as viewers")
            ->orderByDesc('q.published_at')
            ->orderByDesc('q.id')
            ->paginate(25);

        return view('daily-questions.index', [
            'questions' => $questions,
        ]);
    }

    /**
     * Formulaire de création.
     * GET /daily-questions/create
     */
    public function create(Request $request)
    {
        // Création lancée depuis un média : la vidéo est imposée
        $videoId = $request->query('video_id');

        $video = $videoId
            ? DB::table('videos')->where('id', $videoId)->first(['id', 'title', 'code'])
            : null;
        
        return view('daily-questions.create', [
            'topics'        => $this->topicOptions(),
            'question'      => null,
            'answers'       => collect(),
            'selectedVideo' => null,
            'selectedTopics' => [],
            'lockedVideo' => $video,
        ]);
    }

    /**
     * Enregistrement : question + réponses (transaction).
     * POST /daily-questions
     */
    public function store(Request $request)
    {
        $data = $this->validateQuestion($request);

        $id = DB::transaction(function () use ($data) {
            $id = DB::table('daily_questions')->insertGetId(
                $this->questionPayload($data) + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->syncAnswers($id, $data['answers'], $data['correct']);

            return $id;
        });

        $this->syncTopics($id, $request->input('notification_topics', []));

        return $this->afterSave($request, 'voyager.daily-questions.index', 'Quiz enregistré.');
    }

    /**
     * Formulaire d'édition.
     * GET /daily-questions/{id}/edit
     */
    public function edit(int $id)
    {
        $question = $this->questionOrFail($id);

        return view('daily-questions.edit', [
            'topics'        => $this->topicOptions(),
            'question'      => $question,
            'answers'       => $this->answersFor($id),
            'selectedVideo' => $this->videoLabel($question->video_id),
            'selectedTopics' => $this->selectedTopicIds($question->id),
        ]);
    }

    /**
     * Mise à jour : question + réponses (transaction).
     * PUT /daily-questions/{id}
     */
    public function update(Request $request, int $id)
    {
        $question = $this->questionOrFail($id);
        $data     = $this->validateQuestion($request);

        DB::transaction(function () use ($question, $data) {
            DB::table('daily_questions')
                ->where('id', $question->id)
                ->update($this->questionPayload($data) + ['updated_at' => now()]);

            $this->syncAnswers($question->id, $data['answers'], $data['correct']);
        });

        $this->syncTopics($id, $request->input('notification_topics', []));

        return $this->afterSave($request, 'voyager.daily-questions.index', 'Quiz enregistré.');
    }

    /* ===================== HELPERS FORMULAIRE ===================== */

    /**
     * Règles communes création / édition.
     * `correct` désigne l'index de la bonne réponse dans le tableau soumis.
     */
    private function validateQuestion(Request $request): array
    {
        $v = $request->validate([
            'topic_id'           => ['required', 'integer', 'exists:topics,id'],
            'video_id'           => ['nullable', 'integer', 'exists:videos,id'],
            'title'              => ['required', 'string', 'max:120'],
            'question'           => ['required', 'string'],
            'explanation'        => ['nullable', 'string'],
            'published_at'       => ['nullable', 'date'],
            'expired_at'         => ['nullable', 'date', 'after_or_equal:published_at'],
            'notification_title' => ['nullable', 'string', 'max:255'],
            'notification_body'  => ['nullable', 'string', 'max:500'],
            'answers'            => ['required', 'array', 'min:' . self::MIN_ANSWERS, 'max:' . self::MAX_ANSWERS],
            'answers.*'          => ['required', 'string', 'max:500'],
            'correct'            => ['required', 'integer'],
            'notification_topics'   => ['nullable', 'array'],
            'notification_topics.*' => ['integer', 'exists:topics,id'],
        ], [
            'answers.min'      => 'Une question doit comporter au moins :min réponses.',
            'answers.max'      => 'Une question ne peut pas comporter plus de :max réponses.',
            'answers.*.required' => 'Aucune réponse ne peut être laissée vide.',
            'correct.required' => 'Vous devez désigner la bonne réponse.',
            'expired_at.after_or_equal' => 'La date de fin ne peut pas précéder la date de publication.',
        ], [
            'topic_id'           => 'centre d\'intérêt',
            'video_id'           => 'vidéo',
            'title'              => 'titre',
            'question'           => 'question',
            'explanation'        => 'explication',
            'published_at'       => 'date de publication',
            'expired_at'         => 'date de fin',
            'notification_title' => 'titre de la notification',
            'notification_body'  => 'message de la notification',
        ]);

        // Les réponses sont réindexées : un retrait de ligne côté formulaire
        // laisse des trous dans le tableau soumis
        $answers = array_values(array_map('trim', $v['answers']));
        $correct = (int) $v['correct'];

        // L'index désigné doit exister après réindexation
        if (!array_key_exists($correct, $answers)) {
            abort(redirect()->back()->withInput()->withErrors([
                'correct' => 'La bonne réponse désignée est introuvable.',
            ]));
        }

        $v['answers'] = $answers;
        $v['correct'] = $correct;
        $v['video_id'] = $v['video_id'] ?: null;

        return $v;
    }

    /**
     * Colonnes de `daily_questions` communes à l'insert et à l'update.
     */
    private function questionPayload(array $data): array
    {
        return [
            'video_id'           => $data['video_id'],
            'topic_id'           => $data['topic_id'],
            'title'              => $data['title'],
            'question'           => $data['question'],
            'explanation'        => $data['explanation'] ?? null,
            'published_at'       => $this->startOfDay($data['published_at'] ?? null),
            'expired_at'         => $this->endOfDay($data['expired_at'] ?? null),
            'notification_title' => $data['notification_title'] ?? null,
            'notification_body'  => $data['notification_body'] ?? null,
        ];
    }

    /**
     * Remplace les réponses de la question.
     * Une seule réponse peut être correcte.
     */
    private function syncAnswers(int $questionId, array $answers, int $correct): void
    {
        DB::table('daily_question_answers')->where('daily_question_id', $questionId)->delete();

        $rows = [];

        foreach ($answers as $i => $text) {
            $rows[] = [
                'daily_question_id' => $questionId,
                'text'        => $text,
                'correct_flg' => $i === $correct ? 1 : 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        DB::table('daily_question_answers')->insert($rows);
    }

    private function toDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }

    private function questionOrFail(int $id)
    {
        $question = DB::table('daily_questions')->where('id', $id)->first();

        abort_if(!$question, 404);

        return $question;
    }

    private function answersFor(int $questionId)
    {
        return DB::table('daily_question_answers')
            ->where('daily_question_id', $questionId)
            ->orderBy('id')
            ->get(['id', 'text', 'correct_flg']);
    }

    /**
     * Sujets pouvant recevoir un contenu : les sous-sujets, et les sujets
     * qui n'en regroupent aucun. Un sujet parent ne sert que de regroupement.
     */
    private function topicOptions()
    {
        return DB::table('topics as t')
            ->leftJoin('topics as p', 'p.id', '=', 't.parent_id')
            ->select('t.id')
            ->selectRaw("CASE WHEN p.id IS NULL THEN t.name ELSE CONCAT(p.name, ' · ', t.name) END as name")
            ->where('t.active_flg', 1)
            ->whereNotExists(function ($q) {
                $q->from('topics as c')->whereColumn('c.parent_id', 't.id')->selectRaw('1');
            })
            ->orderByRaw('COALESCE(p.order_dsp, t.order_dsp), t.order_dsp')
            ->get();
    }

    private function videoLabel(?int $videoId): ?string
    {
        return $videoId
            ? DB::table('videos')->where('id', $videoId)->value('title')
            : null;
    }

    private function startOfDay(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->startOfDay()->toDateTimeString() : null;
    }

    private function endOfDay(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->endOfDay()->toDateTimeString() : null;
    }
}