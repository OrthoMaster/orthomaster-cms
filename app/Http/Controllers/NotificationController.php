<?php

namespace App\Http\Controllers;

use App\Jobs\SendExpoNotificationJob;
use App\Models\DeviceToken;
use App\Services\ExpoNotificationService;
use Illuminate\Http\Request;
use Kreait\Laravel\Firebase\Facades\Firebase;

class NotificationController extends Controller
{
  public function __construct(
    private ExpoNotificationService $expoService
  ) {}

  public function checkReceipt(Request $request)
  {
    // $validated = $request->validate([
    //   'ticket_id' => 'required|string',
    // ]);

    $ticketId = '019ba38d-ff9b-7736-9378-e3a925cacdd0';

    $result = $this->expoService->getReceipts([$request->id]);

    return response()->json($result);
  }

  public function sendTest(Request $request)
  {
    $fcm = 'ExponentPushToken[h3UZy-KXE10BiNnrcOyITs]';

    $result = $this->expoService->sendToDevice(
      $fcm,
      'Title',
      'Hello',
      ['a' => 'a']
    );

    return response()->json($result);
  }

  public function sendAsync(Request $request)
  {
    $validated = $request->validate([
      'user_ids' => 'required|array',
      'user_ids.*' => 'exists:users,id',
      'title' => 'required|string|max:255',
      'body' => 'required|string|max:500',
      'data' => 'array',
    ]);

    // Lấy tokens của users
    $tokens = DeviceToken::whereIn('user_id', $validated['user_ids'])
      ->active()
      ->pluck('fcm_token')
      ->toArray();

    if (empty($tokens)) {
      return response()->json([
        'success' => false,
        'message' => 'No active devices found',
      ], 404);
    }

    // Dispatch job
    SendExpoNotificationJob::dispatch(
      $tokens,
      $validated['title'],
      $validated['body'],
      $validated['data'] ?? []
    );

    return response()->json([
      'success' => true,
      'message' => 'Notification queued successfully',
      'devices_count' => count($tokens),
    ]);
  }
}
