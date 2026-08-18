<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DailyQuestion;
use App\Models\DailyQuestionAnswer;
use App\Models\Podcast;
use App\Models\TrkContentViews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyActionController extends Controller
{
    public function getTodayActionByUser(Request $request)
    {
        $user = $request->user();

        try {
            // Daily questions
            if (in_array($user->role->name, ['admin', 'directeur', 'manager'])) {
                $dailyQuestions = DailyQuestion::whereDate('expired_at', '>=', today())
                    ->with([
                        'topic',
                        'answers',
                        'answers.users' => function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        }
                    ])
                    ->get();
            } else {
                $dailyQuestions = DailyQuestion::whereDate('published_at', '<=', today())
                    ->whereDate('expired_at', '>=', today())
                    ->with([
                        'topic',
                        'answers',
                        'answers.users' => function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        }
                    ])
                    ->get();
            }

            // Process each question
            $questionsData = $dailyQuestions->map(function ($question) use ($user) {
                // Find answer if user selected
                $userAnswer = $question->answers->first(function ($answer) {
                    return $answer->users->isNotEmpty();
                });

                // Check user has topic
                $hasTopicWithQuestion = $user->topics()
                    ->where('topic_id', $question->topic->id)
                    ->exists();

                return [
                    'question' => $question,
                    'user_answer_id' => $userAnswer?->id,
                    'has_answered' => !is_null($userAnswer),
                    'has_topic' => $hasTopicWithQuestion,
                ];
            });

            // Podcasts
            if (in_array($user->role->name, ['admin', 'directeur', 'manager'])) {
                $podcasts = Podcast::whereDate('expired_at', '>=', today())
                    ->with('topic')
                    ->orderBy('published_at', 'desc')
                    ->get();
            } else {
                $podcasts = Podcast::whereDate('published_at', '<=', today())
                    ->whereDate('expired_at', '>=', today())
                    ->with('topic')
                    ->orderBy('published_at', 'desc')
                    ->get();
            }

            // Process each podcast
            $podcastsData = $podcasts->map(function ($podcast) use ($user) {
                // Check user has topic
                $hasTopicWithPodcast = $user->topics()
                    ->where('topic_id', $podcast->topic->id)
                    ->exists();

                return [
                    'podcast' => $podcast,
                    'has_topic' => $hasTopicWithPodcast,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'questions' => $questionsData,
                    'podcasts' => $podcastsData,
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['daily-action']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function getPodcast(Request $request, $id)
    {
        try {
            $podcast = Podcast::where('id', $id)
                ->whereDate('published_at', '<=', today())
                ->whereDate('expired_at', '>=', today())
                ->with('topic')
                ->first();

            if (!$podcast) {
                return response()->json([
                    'success' => false,
                ]);
            }

            // Check user has topic
            $user = $request->user();
            $hasTopicWithPodcast = $user->topics()
                ->where('topic_id', $podcast->topic->id)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'podcast' => $podcast,
                    'has_topic' => $hasTopicWithPodcast,
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-podcast', $id]);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function getQuestion(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Daily questions
            $question = DailyQuestion::whereDate('published_at', '<=', today())
                ->whereDate('expired_at', '>=', today())
                ->with([
                    'topic',
                    'answers',
                    'answers.users' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    }
                ])
                ->first();

            if (!$question) {
                return response()->json([
                    'success' => false,
                ]);
            }

            $userAnswer = $question->answers->first(function ($answer) {
                return $answer->users->isNotEmpty();
            });

            $hasTopicWithQuestion = $user->topics()
                ->where('topic_id', $question->topic->id)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'question' => $question,
                    'user_answer_id' => $userAnswer?->id,
                    'has_answered' => !is_null($userAnswer),
                    'has_topic' => $hasTopicWithQuestion,
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-daily-question', $id]);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function answerQuestion(Request $request)
    {
        if (!$request->has('questionId') || !$request->has('answerId')) {
            return response()->json([
                'success' => false,
            ]);
        }

        try {
            DB::table('daily_question_answer_user')->insert([
                'user_id' => $request->user()->id,
                'daily_question_answer_id' => $request->answerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DailyQuestion::where('id', $request->questionId)->increment('count');
            DailyQuestionAnswer::where('id', $request->answerId)->increment('count');
            TrkContentViews::create([
                'user_id' => $request->user()->id,
                'content_id' => $request->questionId,
                'type' => 'question'
            ]);


            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['answer-question']);
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function countTimesPodcast(Request $request)
    {
        if (!$request->has('podcastId')) {
            return response()->json([
                'success' => false,
            ]);
        }

        try {
            Podcast::where('id', $request->podcastId)->increment('count');
            TrkContentViews::create([
                'user_id' => $request->user()->id,
                'content_id' => $request->podcastId,
                'type' => 'podcast'
            ]);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['daily-podcast']);
            return response()->json([
                'success' => false,
            ]);
        }
    }
}
