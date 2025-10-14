<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use TCG\Voyager\Models\Role;

class CompanyController extends Controller
{
    public function show(string $id)
    {
        $company = Company::findById($id);
        if (!$company) {
            return abort(404);
        }
        return view('companies.browse-accounts', compact('company'));
    }

    public function updateManager(string $id, string $userId)
    {
        $user = User::where('id', $userId)->where('company_id', $id)->first();

        if (!$user) {
            return abort(404);
        }

        $managerRole = Role::where('name', 'user-manager')->first();
        if ($managerRole && $user->role_id !== $managerRole->id) {
            $user->role_id = $managerRole->id;
            $user->save();

            return back()->with([
                'message' => 'Mise à jour réussie',
                'alert-type' => 'success'
            ]);
        }
        
        if ($user->role_id == $managerRole->id) {
            $userRole = Role::where('name', 'user')->first();
            $user->role_id = $userRole->id;
            $user->save();

            return back()->with([
                'message' => 'Mise à jour réussie',
                'alert-type' => 'success'
            ]);
        }

        return back()->with([
            'message' => "Il n'y a rien à mettre à jour",
            'alert-type' => 'warning'
        ]);
    }
}
