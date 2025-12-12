<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Training;
use Illuminate\Http\Request;

class ChatbotSearchController extends Controller
{
    public function searchTrainings(Request $request)
    {
        if ($request->has('search')) {
            $lessons = Lesson::where('title', 'LIKE', '%' . $request->search . '%')->get();
            if ($lessons->isNotEmpty()) {
                return response()->json($lessons, 200);
            }

            return response()->json([], 200);
        }

        return response()->json(Lesson::get(), 200);
    }
}
