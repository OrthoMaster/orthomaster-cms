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
    public static function createdNewAccount(User $user)
    {
        try {
            // Mail to user
            if ($user->status == Status::ACTIVE->value) {
                // Active automatically
                Mail::to('fx.tiennguyen92@gmail.com')->send(new CreatedAccountMail($user->email, $user));
            } else {
                // Pending to verify
                Mail::to('fx.tiennguyen92@gmail.com')->send(new ConfirmedRegRequestMail($user->email, $user));
            }

            // Mail::to($user->email)->send(new CreatedAccountMail($user->email, $user));
            Log::channel('mail')->info('After registered: ' . $user->email, ['register']);

            // Notification to Admin
            if (setting('mail.notification_email')) {
                Mail::to(array_map('trim', explode(';', setting('mail.notification_email'))))->send(new NotificationHasNewUserMail($user));
            }
        } catch (\Throwable $e) {
            Log::channel('mail')->emergency($e->getMessage(), ['register']);
        }
    }
}
