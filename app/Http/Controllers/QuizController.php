<?php

namespace App\Http\Controllers;

use App\Models\QuizzSubmission;
use App\Models\QuizzSubmissionAnswer;
use App\Models\QuizzTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    /**
     * Affiche le quiz pour un topic via son slug (lien partageable : /quiz/{slug}).
     */
    public function show(string $slug)
    {
        $topic = QuizzTopic::where('slug', $slug)
            ->where('is_active', true)
            ->with(['questions.answers', 'scoreRanges'])
            ->firstOrFail();

        // Prépare les données pour le frontend, sans exposer les scores des réponses
        // (l'utilisateur ne doit pas connaître les points).
        $questions = $topic->questions->map(function ($q) {
            return [
                'id'      => $q->id,
                'content' => $q->content,
                'image'   => $q->image,
                'answers' => $q->answers->map(fn ($a) => [
                    'id'      => $a->id,
                    'content' => $a->content,
                    // 'score' volontairement omis
                ])->values(),
            ];
        })->values();

        return view('quiz.show', [
            'topic'           => $topic,
            'questionsJson'   => $questions,
            'timePerQuestion' => $topic->time_per_question,
        ]);
    }

    /**
     * Enregistre une participation : réponses + scores + note finale sur 100.
     * Renvoie le résultat (note + verdict) en JSON.
     */
    public function submit(Request $request, string $slug)
    {
        $topic = QuizzTopic::where('slug', $slug)
            ->where('is_active', true)
            ->with(['questions.answers', 'scoreRanges'])
            ->firstOrFail();

        $data = $request->validate([
            'user_name'           => ['required', 'string', 'max:120'],
            'answers'             => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer_id'   => ['nullable', 'integer'], // null = temps écoulé
        ]);

        // Index des questions/réponses du topic pour valider et calculer côté serveur
        // (ne jamais faire confiance au score envoyé par le client).
        $questions = $topic->questions->keyBy('id');

        $rawScore = 0;
        $rows = [];

        foreach ($data['answers'] as $entry) {
            $question = $questions->get($entry['question_id']);
            if (! $question) {
                continue; // question hors topic, ignorée
            }

            $answerId   = $entry['answer_id'] ?? null;
            $scoreEarned = 0;

            if ($answerId !== null) {
                $answer = $question->answers->firstWhere('id', $answerId);
                if ($answer) {
                    $scoreEarned = (int) $answer->score;
                } else {
                    $answerId = null; // réponse invalide -> traitée comme non répondue
                }
            }

            $rawScore += $scoreEarned;
            $rows[] = [
                'question_id'  => $question->id,
                'answer_id'    => $answerId,
                'score_earned' => $scoreEarned,
            ];
        }

        $maxPossible = $topic->maxPossibleScore();
        $finalScore  = $maxPossible > 0 ? round($rawScore / $maxPossible * 100, 2) : 0;

        $submission = DB::transaction(function () use ($topic, $data, $rawScore, $maxPossible, $finalScore, $rows) {
            $submission = QuizzSubmission::create([
                'topic_id'           => $topic->id,
                'user_name'          => $data['user_name'],
                'raw_score'          => $rawScore,
                'max_possible_score' => $maxPossible,
                'final_score'        => $finalScore,
                'completed_at'       => now(),
            ]);

            foreach ($rows as $row) {
                QuizzSubmissionAnswer::create(array_merge($row, [
                    'submission_id' => $submission->id,
                ]));
            }

            return $submission;
        });

        // Détermine le verdict selon la tranche de score
        $range = $topic->scoreRanges
            ->first(fn ($r) => $finalScore >= $r->min_score && $finalScore <= $r->max_score);

        return response()->json([
            'submission_id' => $submission->id,
            'final_score'   => (float) $finalScore,
            'verdict'       => $range ? [
                'label'   => $range->label,
                'title'   => $range->title,
                'comment' => $range->comment,
                'color'   => $range->color,
            ] : null,
        ]);
    }

    /**
     * Met à jour l'e-mail et le consentement de contact d'une participation.
     */
    public function contact(Request $request, QuizzSubmission $submission)
    {
        $data = $request->validate([
            'email'         => ['required', 'email', 'max:180'],
            'allow_contact' => ['nullable', 'boolean'],
        ]);

        $submission->update([
            'email'         => $data['email'],
            'allow_contact' => (bool) ($data['allow_contact'] ?? false),
        ]);

        return response()->json(['ok' => true]);
    }
}