<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyJoinRequest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompanyJoinRequestController extends Controller
{
    public function apply(string $code, $userId)
    {
        $company = Company::findByJoinCode($code);
        $user = User::find($userId);
        if (!$company || !$user) {
            Log::warning('Not found ' . $code, ['apply-join-company', $userId]);
            return 'not-found';
        }

        // Request to apply
        $companyRequest = CompanyJoinRequest::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'invite_flg' => false,
            'created_by' => $user->id
        ]);

        // Notification to managers
        foreach ($company->managers as $manager) {
            SystemNotification::create([
                'user_id' => $manager->id,
                'actor_id' => $user->id,
                'type' => 'confirm',
                'entity_type' => 'CompanyJoinRequest',
                'entity_id' => $companyRequest->id,
                'title' => 'Demande d’accès',
                'content' => 'Vous avez reçu une nouvelle demande d’accès votre Espace Pro.'
            ]);
        }

        return 'success';
    }

    public function invite($companyId, $createdId, $inviteId)
    {
        try {
            // $company = Company::findByJoinCode($code);
            // if (!$company) {
            //     Log::warning('Not found ' . $code, ['apply-join-company', $createdId]);
            //     return 'not-found';
            // }


            // Check invitation request exists
            $companyRequest = CompanyJoinRequest::where('invite_flg', true)
                ->where('user_id', $inviteId)
                ->where('company_id', $companyId)
                ->where('status', Status::PENDING->value)
                ->first();
            if ($companyRequest) {
                return 'success';
            }

            // Invite
            $companyRequest = CompanyJoinRequest::create([
                'user_id' => $inviteId,
                'company_id' => $companyId,
                'invite_flg' => true,
                'status' => Status::PENDING->value,
                'created_by' => $createdId
            ]);

            // Notification to user
            SystemNotification::create([
                'user_id' => $inviteId,
                'actor_id' => $createdId,
                'type' => 'confirm',
                'entity_type' => 'CompanyJoinRequest',
                'entity_id' => $companyRequest->id,
                'title' => 'Invitation reçue',
                'content' => 'Le responsable vous invite à rejoindre son groupe.'
            ]);

            return 'success';
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['invite-member', 'company-'.$companyId, $createdId]);
            return 'error';
        }
    }

    public function accept($id)
    {
        $companyRequest = CompanyJoinRequest::find($id);
        if (!$companyRequest || $companyRequest->status == Status::ACCEPTED->value) {
            return false;
        }

        $companyRequest->status = Status::ACCEPTED->value;
        $companyRequest->save();

        // Add user to company
        $company = Company::find($companyRequest->company_id);
        $company->users()->attach($companyRequest->user_id);
        $company->save();

        // Notification
        SystemNotification::create([
            'user_id' => $companyRequest->invite_flg ? $companyRequest->created_by : $companyRequest->user_id,
            'actor_id' => $companyRequest->invite_flg ? $companyRequest->user_id : Auth::id(),
            'type' => 'success',
            'entity_type' => 'CompanyJoinRequest',
            'entity_id' => $companyRequest->id,
            'title' => $companyRequest->invite_flg ? 'Invitation acceptée' : 'Demande acceptée',
            'content' => $companyRequest->invite_flg
                ? 'Le membre a rejoint votre groupe.'
                : 'Le responsable a accepté votre demande.'
        ]);

        // Connect Odoo
        dispatch(function () use ($company) {
            (new \App\Http\Controllers\CompanyController())->connectOdoo($company->id);
        })->afterResponse();

        return true;
    }

    public function reject($id)
    {
        $companyRequest = CompanyJoinRequest::find($id);
        if (!$companyRequest || $companyRequest->status == Status::DENIED->value) {
            return false;
        }

        $companyRequest->status = Status::DENIED->value;
        $companyRequest->save();

        // Notification
        SystemNotification::create([
            'user_id' => $companyRequest->invite_flg ? $companyRequest->created_by : $companyRequest->user_id,
            'actor_id' => $companyRequest->invite_flg ? $companyRequest->user_id : Auth::id(),
            'type' => 'warning',
            'entity_type' => 'CompanyJoinRequest',
            'entity_id' => $companyRequest->id,
            'title' => $companyRequest->invite_flg ? 'Invitation refusée' : 'Demande refusée',
            'content' => $companyRequest->invite_flg
                ? 'L’utilisateur a refusé votre invitation.'
                : 'Le responsable n’a pas validé votre demande pour rejoindre le groupe.'
        ]);

        return true;
    }
}
