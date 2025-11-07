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
}
