<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContentController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id, Request $request)
    {
        if (Gate::denies('edit', app('App\Models\Training'))) {
            return abort(403, 'Unauthorized action.');
        }

        $lesson = Lesson::findById($id);
        if (!$lesson) {
            return abort(400);
        }

        $block = Block::getFirstByLesson($lesson->id);

        return view('content.edit', compact('lesson', 'block'));
    }

    /**
     * Update content.
     */
    public function update(string $id, Request $request)
    {
        $lesson = Lesson::findOrFail($id);
        $block = Block::getFirstByLesson($lesson->id);

        // Check request has content
        if (!$request->has('content')) {
            return response()->json([
                'success' => false,
            ]);
        }

        $block->content = $request->content;
        $block->save();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Update content as draft.
     */
    public function updateAsDraft(string $id, Request $request)
    {
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
