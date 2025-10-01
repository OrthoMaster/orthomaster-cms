<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index() {
        $admins = User::getAdmins();
        
        return view('admin.browse', compact('admins'));
    }
}
