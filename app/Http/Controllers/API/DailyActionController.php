<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DailyQuestion;
use App\Models\DailyQuestionAnswer;
use App\Models\Podcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyActionController extends Controller
{
    public function getTodayActionByUser(Request $request)
    {
        $user = $request->user();

        try {
            // Daily question
            $dailyQuestion = DailyQuestion::whereDate('published_at', today())
                ->with([
                    'topic',
                    'answers',
                    'answers.users' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    }
                ])
                ->first();
            
            $userAnswer = null;
            $hasTopicWithQuestion = false;
            if ($dailyQuestion) {
                // Find answer if user selected
                $userAnswer = $dailyQuestion->answers->first(function ($answer) {
                    return $answer->users->isNotEmpty();
                });

                // Check user has topic
                $hasTopicWithQuestion = $user->topics()->where('topic_id', $dailyQuestion->topic->id)->exists();
            }

            // Podcast
            $podcast = Podcast::whereDate('published_at', today())
                ->with('topic')
                ->first();
            
            $hasTopicWithPodcast = false;
            if ($podcast) {
                $hasTopicWithPodcast = $user->topics()->where('topic_id', $podcast->topic->id)->exists();
            }
                

            return response()->json([
                'success' => true,
                'data' => [
                    'question' => $dailyQuestion,
                    'podcast' => $podcast,
                    'user_answer_id' => $userAnswer?->id,
                    'has_answered' => !is_null($userAnswer),
                    'has_topic_question' => $hasTopicWithQuestion,
                    'has_topic_podcast' => $hasTopicWithPodcast,

                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['daily-action']);
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
