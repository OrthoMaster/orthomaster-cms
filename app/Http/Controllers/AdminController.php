<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function index()
    {
        if (!Auth::user()->hasPermission('edit_admins')) {
            return abort(403); 
        }

        $admins = User::getAdmins();
        return view('admin.browse', compact('admins'));
    }

    public function syncNewUserWithOldTrainings(Request $request) {
        if (!Auth::user()->hasPermission('edit_admins')) {
            return abort(403); 
        }

        if ($request->filled('id')) {
            $users = User::where('id', $request->query('id'))->get();
        } else {
            $users = User::get();
        }

        foreach ($users as $user) {
            if ($user->id_user) {
                User::syncOldVersionTraining($user->id, $user->id_user);
            }
        }
    }

    public function syncNewUserWithOldLessons(Request $request) {
        if (!Auth::user()->hasPermission('edit_admins')) {
            return abort(403); 
        }

        if ($request->filled('id')) {
            $users = User::where('id', $request->query('id'))->get();
        } else {
            $users = User::get();
        }

        foreach ($users as $user) {
            if ($user->id_user) {
                User::syncOldVersionLesson($user->id, $user->id_user);
            }
        }
    }
}
