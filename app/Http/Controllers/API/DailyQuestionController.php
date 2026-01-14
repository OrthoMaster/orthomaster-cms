<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DailyQuestion;
use App\Models\DailyQuestionAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyQuestionController extends Controller
{
    public function getTodayQuestionByUser(Request $request)
    {
        $user = $request->user();

        try {
            $dailyQuestion = DailyQuestion::whereDate('published_at', today())
                ->with([
                    'topic',
                    'answers',
                    'answers.users' => function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    }
                ])
                ->first();

            if (!$dailyQuestion) {
                return response()->json([
                    'success' => false
                ]);
            }

            // Find answer if user selected
            $userAnswer = $dailyQuestion->answers->first(function ($answer) {
                return $answer->users->isNotEmpty();
            });

            // Check user has topic
            $hasTopic = $user->topics()->where('topic_id', $dailyQuestion->topic->id)->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'question' => $dailyQuestion,
                    'user_answer_id' => $userAnswer?->id,
                    'has_answered' => !is_null($userAnswer),
                    'has_topic' => $hasTopic
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['daily-question']);
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
    }
}
