<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainingPermissionController extends Controller
{
    public function storeWithUser($id)
    {
        $user = User::where('id', $id)->with('packages')->first();
        if (!$user) {
            return abort(404);
        }
        $trainings = Training::getAll();

        return view('package-permissions.edit-with-user', compact('trainings', 'user'));
    }

    public function updateWithUser($id, Request $request)
    {
        $user = User::where('id', $id)->with('packages')->first();
        if (!$user) {
            return abort(404);
        }

        $user->packages()->sync($request->permissions);

        return back()->with([
            'message'    => 'Mis à jour avec succès !',
            'alert-type' => 'success',
        ]);
    }
}
