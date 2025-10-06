<?php

namespace App\Http\Controllers;

use App\Mail\CreatedAccountMail;
use App\Mail\NotificationHasNewUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public static function createdNewAccount(User $user)
    {
        try {
            // User
            Mail::to('fx.tiennguyen92@gmail.com')->send(new CreatedAccountMail($user->email, $user));
            // Mail::to(setting('mail.notification_email'))->send(new CreatedAccountMail($user->email, $user));
            // Log::channel('mail')->info('Send email after create account to ' . $user->email);

            // Admin
            if (setting('mail.notification_email')) {
                Mail::to(array_map('trim', explode(';', setting('mail.notification_email'))))->send(new NotificationHasNewUserMail($user));
            }
        } catch (\Throwable $e) {
            Log::channel('mail')->emergency($e->getMessage());
        }
    }
}
