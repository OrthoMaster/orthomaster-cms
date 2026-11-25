<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionSurvey;
use App\Services\Satisfaction\SatisfactionSurveyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Questionnaire de satisfaction, page publique : auth.orthomaster.fr/satisfaction/{token}.
 * Le jeton suffit : l'apprenant arrive depuis l'e-mail, sans se connecter.
 */
class SatisfactionPublicController extends Controller
{
    /**
     * Formulaire, ou remerciements s'il a déjà répondu.
     * GET /satisfaction/{token}
     */
    public function show(string $token)
    {
        $survey = SatisfactionSurvey::where('token', $token)->with('user')->firstOrFail();

        if ($survey->answered_at) {
            return view('satisfaction.thanks');
        }

        return view('satisfaction.form', [
            'survey'   => $survey,
            'name'     => $survey->recipientName(),
            'packages' => explode("\n", $survey->packages_label),
        ]);
    }

    /**
     * POST /satisfaction/{token}
     */
    public function submit(Request $request, string $token, SatisfactionSurveyService $service)
    {
        $survey = SatisfactionSurvey::where('token', $token)->firstOrFail();

        // Une seule réponse : un second envoi (double clic, retour arrière) n'écrase pas la première.
        if ($survey->answered_at) {
            return redirect()->route('satisfaction.show', $token);
        }

        $rules = array_fill_keys(array_keys(SatisfactionSurvey::RATINGS), 'required|integer|between:1,5');

        $data = $request->validate($rules + [
            'practice'          => ['required', Rule::in(array_keys(SatisfactionSurvey::PRACTICE))],
            'recommend'         => 'required|integer|between:0,10',
            'difficulties_flg'  => 'required|boolean',
            'difficulties_text' => 'nullable|string|max:2000',
            'liked_text'        => 'nullable|string|max:2000',
            'suggestions_text'  => 'nullable|string|max:2000',
            'recontact_flg'     => 'nullable|boolean',
        ], [
            'required' => 'Merci de répondre à cette question.',
        ]);

        $service->answer($survey, $data);

        return redirect()->route('satisfaction.show', $token);
    }
}
