<?php

namespace App\Http\Controllers;

use App\Models\HealthcareProfessional;
use Illuminate\Http\Request;

class HealthcareProfessionalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$request->filled('pp') && !$request->filled('name') && !$request->filled('first_name')) {
            return view('healthcare.index');
        }

        $query = HealthcareProfessional::query();

        if ($request->filled('pp')) {
            $query->where('pp', 'LIKE', '%' . $request->pp . '%');
        }

        if ($request->filled('name')) {
            $query->where('name', $request->name);
        }

        if ($request->filled('first_name')) {
            $query->where('first_name', $request->first_name);
        }

        $query->with(['diplomes', 'activities']);

        $hcps = $query->latest('id')->paginate(10);
        $hcps->appends($request->query());

        return view('healthcare.index', compact('hcps'));
    }
}