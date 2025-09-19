<?php

namespace App\Http\Controllers;

use App\Models\Block;
use App\Models\Formation;
use App\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ContentController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $formationId, Request $request)
    {
        if (Gate::denies('edit', app('App\Models\Training'))) {
            abort(403, 'Unauthorized action.');
        }

        $formation = Training::findOrFail($formationId);
        $block = Block::getFirstByFormation($formationId);

        return view('content.edit', compact('formation', 'block'));
    }

    /**
     * Update content.
     */
    public function update(string $formationId, Request $request)
    {
        $formation = Training::findOrFail($formationId);
        $block = Block::getFirstByFormation($formation->id);

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
    public function updateAsDraft(string $formationId, Request $request)
    {
        $formation = Training::findOrFail($formationId);
        $block = Block::getFirstByFormation($formation->id);

        // Check request has content
        if (!$request->has('content')) {
            return response()->json([
                'success' => false,
            ]);
        }

        $block->draft = $request->content;
        $block->save();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
