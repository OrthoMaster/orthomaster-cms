<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmedRegRequestMail;
use App\Mail\CreatedAccountMail;
use App\Mail\NotificationHasNewUserMail;
use App\Models\User;
use App\Status;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public static function createdNewAccount(User $user, bool $isAdmin = false)
    {
        try {
            // Mail to user
            if ($user->status == Status::ACTIVE->value) {
                // Active automatically
                Mail::to($user->email)->send(new CreatedAccountMail($user->email, $user));
            } else {
                // Pending to verify
                Mail::to($user->email)->send(new ConfirmedRegRequestMail($user->email, $user));

                // Notification admin
                if (!$isAdmin && setting('mail.notification_email')) {
                    $notiEmails = array_map('trim', array_filter(explode(';', setting('mail.notification_email'))));
                    Mail::to($notiEmails)->send(new NotificationHasNewUserMail($user));
                }
            }

            Log::channel('mail')->info('After registered: ' . $user->email, ['register']);
        } catch (\Throwable $e) {
            Log::channel('mail')->emergency($e->getMessage(), ['register']);
        }
    }
}
