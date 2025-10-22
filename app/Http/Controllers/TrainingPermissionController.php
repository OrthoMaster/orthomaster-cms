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

        return view('training-permissions.edit-with-user', compact('trainings', 'user'));
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

    public function storeWithCompany($id)
    {
        $company = Company::where('id', $id)->with('packages')->first();
        if (!$company) {
            return abort(404);
        }
        $trainings = Training::getAll();

        return view('training-permissions.edit-with-company', compact('trainings', 'company'));
    }

    public function updateWithCompany($id, Request $request)
    {
        $company = Company::where('id', $id)->with(['packages', 'users'])->first();
        if (!$company) {
            return abort(404);
        }

        DB::beginTransaction();
        try {
            $company->packages()->sync($request->permissions);
            
            // Sync with all users of company
            foreach ($company->users as $user) {
                $user->packages()->sync($request->permissions);
            }

            DB::commit();
            return back()->with([
                'message'    => 'Mis à jour avec succès !',
                'alert-type' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with([
                'message'    => $e->getMessage(),
                'alert-type' => 'error',
            ]);
        }

        
    }
}
