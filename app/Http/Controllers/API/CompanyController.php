<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\CompanyJoinRequestController;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function getPreFromData()
    {
        try {
            $estabs = Establishment::getAll();

            return response()->json([
                'success' => true,
                'data' => [
                    'estabs' => $estabs
                ]
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['pre-form-data', 'company']);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCompany(Request $request)
    {
        $company = $request->user()->companiesUserIsManager()
            ->with('establishment:id,name')
            ->first();

        $managers = $company->managers();
        $members = $company->members()->count() > 0 ? $company->members() : null;

        // Pre-data if move to edit screen
        $estabs = Establishment::getAll();

        return response()->json([
            'success' => true,
            'data' => [
                'company' => $company,
                'managers' => $managers,
                'members' => $members,
                'estabs' => $estabs
            ]
        ]);
    }

    public function createCompany(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'establishment_id' => 'required|exists:establishments,id',
            'email' => 'required|email|max:40',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:100',
            'postal_code' => 'required|string|max:6',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'tva' => 'nullable|string|max:15',
            'siret' => 'nullable|string|max:14',
        ]);

        try {
            // Check is manager of other company
            $existingCompany = $request->user()->companiesUserIsManager()->first();

            if ($existingCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous êtes déjà responsable d\'une équipe'
                ], 403);
            }

            // Create company
            $company = Company::create($validated);

            // Add user
            $company->users()->attach($request->user()->id, [
                'manager_flg' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre Espace Pro créée avec succès',
                'data' => $company
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCompany($id, Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'establishment_id' => 'required|exists:establishments,id',
            'email' => 'required|email|max:40',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:100',
            'postal_code' => 'required|string|max:6',
            'city' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'tva' => 'nullable|string|max:15',
            'siret' => 'nullable|string|max:14',
        ]);

        try {
            // Get company
            $company = $request->user()->companiesUserIsManager()->first();

            if (!$company || $company->id !== $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune équipe trouvée'
                ], 404);
            }

            // Update
            $company->update($validated);

            // Load relationship
            $company->load('establishment:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Entreprise mise à jour avec succès',
                'data' => $company
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateManager(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            // Get company
            $company = $request->user()->companiesUserIsManager()->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune équipe trouvée'
                ], 404);
            }

            // Check user is on company
            $userInCompany = $company->users()->where('user_id', $validated['user_id'])->first();

            if (!$userInCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé dans l\'équipe'
                ], 404);
            }

            // Current manager flg
            $currentManagerFlg = $userInCompany->pivot->manager_flg;

            // Check user is manager of other company
            if ($currentManagerFlg == true && $userInCompany->companiesUserIsManager()->first()) {
                return response()->json([
                    'success' => false,
                    'message' => "L'utilisateur est responsable d'un autre équipe."
                ], 404);
            }

            // Toggle manager flg
            $company->users()->updateExistingPivot($validated['user_id'], [
                'manager_flg' => !$currentManagerFlg
            ]);

            // Update
            $updatedUser = $company->users()
                ->where('user_id', $validated['user_id'])
                ->first();

            return response()->json([
                'success' => true,
                'message' => $updatedUser->pivot->manager_flg
                    ? 'Utilisateur promu responsable'
                    : 'Responsable rétrogradé en membre',
                'data' => [
                    'user_id' => $validated['user_id'],
                    'manager_flg' => $updatedUser->pivot->manager_flg
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['update-manager', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeMember(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            // Get company
            $company = $request->user()->companiesUserIsManager()->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune équipe trouvée'
                ], 404);
            }

            // Check user is on company
            $userInCompany = $company->users()->where('user_id', $validated['user_id'])->first();

            if (!$userInCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé dans l\'équipe'
                ], 404);
            }

            // Can not delete other managers
            if ($validated['user_id'] != $request->user()->id && $userInCompany->pivot->manager_flg) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de retirer un responsable. Veuillez d\'abord rétrograder ce membre.'
                ], 422);
            }

            // Check number of remaining manager
            if ($userInCompany->pivot->manager_flg) {
                $managerCount = $company->managers()->count();

                if ($managerCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible de retirer le dernier responsable. Veuillez d\'abord promouvoir un autre membre en tant que responsable.'
                    ], 422);
                }
            }

            // Remove user
            $company->users()->detach($validated['user_id']);

            return response()->json([
                'success' => true,
                'message' => 'Membre retiré avec succès'
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['remove-member', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function inviteMember(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        try {
            // Get company
            $company = $request->user()->companiesUserIsManager()->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune équipe trouvée'
                ], 404);
            }

            // Email of auth user
            if ($validated['email'] == $request->user()->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ceci est votre adresse e-mail'
                ], 404);
            }

            // Check user is on company
            $userInCompany = $company->users()->where('email', $validated['email'])->first();
            if ($userInCompany) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur est déjà membre de l\'équipe.'
                ], 404);
            }

            $user = User::where('email', $validated['email'])->first();
            if (!$user) {
                // TODO: Envoyer email to register and join
            }

            // User is member of other company
            if ($user->companiesUserIsMember()->first()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur appartenait à un autre équipe.'
                ], 422);
            }

            // Invite user
            $joinRequestController = new CompanyJoinRequestController();
            
            $result = $joinRequestController->invite($company->join_code, $request->user()->id, $user->id);

            if ($result == 'success') {
                return response()->json([
                    'success' => true,
                    'message' => 'Inviter de membre avec succès'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => "L'invitation du membre a échoué",
            ], 404);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['remove-member', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function quitCompany(Request $request) {
        try {
            // Get company
            $company = $request->user()->companiesUserIsMember()->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune équipe trouvée'
                ], 404);
            }

            // Remove user
            $company->users()->detach($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => "Quitter l'équipe avec succès"
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['quit-company', $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMembers(Request $request)
    {
        try {
            $user = User::find($request->user()->id);
            $members = null;

            $company = $user->companiesUserIsManager()->first();
            if ($company) {
                $members = $company->users()
                    ->wherePivot('manager_flg', false)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $members
            ]);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-company-members']);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
