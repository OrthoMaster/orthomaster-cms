<?php

namespace App\Http\Controllers;

use App\Jobs\SendExpoNotificationJob;
use Illuminate\Http\Request;

class TestingController extends Controller
{
    public function testSendNotification(Request $request)
    {
        SendExpoNotificationJob::dispatch(
            userIds: [4],
            title: 'Noti Test Title',
            body: 'Content Noti Test',
            data: ['action' => 'daily-question'],
            options: ['badge' => 1]
        );
    }
}
