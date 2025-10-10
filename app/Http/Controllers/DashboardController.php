<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index() {

        $totalUsers = DB::table('user')->count();

        return view('dashboard.index');
    }
}
