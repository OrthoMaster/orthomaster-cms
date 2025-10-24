<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    public function getAll(Request $request)
    {
        try {
            $user = User::find(Auth::user()->id);
            $packageIds = $user->packages->pluck('packages.id')->toArray();

            $trainings = Training::getAll(true);

            $trainings->each(function ($training) use ($user, $packageIds) {
                $training->packages->each(function ($package) use ($user, $packageIds) {
                    // If student_flg = true or has permission with package
                    $package->available_flg = $user->student_flg || in_array($package->id, $packageIds);

                    $package->modules->each(function ($module) {
                        $module->demo_flg = $module->hasDemo();
                    });
                });
            });

            return response()->json([
                'success' => true,
                'data' => $trainings
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-trainings']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
