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
            $user = $request->user();

            // Packages
            $userPackages = $user->packages()->get()->keyBy('id');

            $trainings = Training::getAll(true);

            $trainings->each(function ($training) use ($user, $userPackages) {
                $training->packages->each(function ($package) use ($user, $userPackages) {
                    // Check available
                    $package->available_flg = $user->student_flg || $userPackages->has($package->id);

                    // Get progress
                    $package->progress = $userPackages->has($package->id)
                        ? $userPackages->get($package->id)->pivot->progress
                        : 0;

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
