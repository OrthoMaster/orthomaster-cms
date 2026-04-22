<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PreRegDiplomaRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PreRegDiplomaRequestController extends Controller
{
    public function preReg(Request $request)
    {
        try {
            $preReg = PreRegDiplomaRequest::findByUser($request->user()->id);

            // Processing
            if ($preReg && $preReg->status == '0') {
                return response()->json([
                    'success' => true,
                    'message' => 'Votre demande est actuellement en cours de traitement. Merci de votre patience.',
                ]);
            }
            if (!$preReg || ($preReg && $preReg->status != '0')) {
                $created = PreRegDiplomaRequest::create([
                    'user_id' => $request->user()->id,
                ]);

                if ($created) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Votre demande a bien été envoyée. Merci de patienter pendant son traitement.',
                    ]);
                }
            }



            return response()->json([
                'success' => false,
                'message' => 'Votre demande a échoué, veuillez réessayer ultérieurement.',
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e, ['preinscription-diploma']);

            return response()->json([
                'success' => false,
                'message' => 'Votre demande a échoué, veuillez réessayer ultérieurement.',
            ]);
        }
    }
}
