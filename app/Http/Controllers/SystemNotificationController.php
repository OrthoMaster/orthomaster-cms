<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SystemNotificationController extends Controller
{
    public function markAllAsRead(Request $request)
    {
        try {
            $updated = SystemNotification::where('user_id', $request->user()->id)
                ->where('read_flg', false)
                ->update(['read_flg' => true]);

            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['mark-all-as-read', 'notifications', 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead($id, Request $request)
    {
        try {
            $noti = SystemNotification::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $noti->read_flg = true;
            $noti->save();

            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['mark-as-read', 'notification-' . $id, 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id, Request $request)
    {
        try {
            $noti = SystemNotification::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $noti->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification supprimée avec succès'
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['delete-notification', 'notification-' . $id, 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAll(Request $request)
    {
        try {
            $deleted = SystemNotification::where('user_id', $request->user()->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications ont été supprimées',
                'count' => $deleted
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['delete-all-notifications', 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLastest(Request $request)
    {
        try {
            $notification = $request->user()
                ->notifications()
                ->latest()
                ->first();

            return response()->json([
                'success' => true,
                'data' => $notification
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['get-lastest', 'notification', 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getNotifications(Request $request)
    {
        try {
            $notifications = $request->user()
                ->notifications()
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $notifications
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['notifications', 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnreadCount(Request $request)
    {
        $count = $request->user()
            ->notifications()
            ->whereNull('read_flg', '!=', true)
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function accept($id, Request $request)
    {
        try {
            $noti = SystemNotification::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->where('type', 'confirm')
                ->firstOrFail();

            // Check is accepted
            if ($noti->action == Status::ACCEPTED->value) {
                return response()->json([
                    'success' => true,
                ], 200); 
            }

            // Invite join company
            if ($noti->entity_type == 'CompanyJoinRequest') {
                $controller = new CompanyJoinRequestController();
                $result = $controller->accept($noti->entity_id);
            }

            $noti->action = Status::ACCEPTED->value;
            $noti->save();

            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['mark-as-read', 'notification-' . $id, 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject($id, Request $request)
    {
        try {
            $noti = SystemNotification::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->where('type', 'confirm')
                ->firstOrFail();

            // Check is rejected
            if ($noti->action == Status::DENIED->value) {
                return response()->json([
                    'success' => true,
                ], 200);
            }

            // Invite join company
            if ($noti->entity_type == 'CompanyJoinRequest') {
                $controller = new CompanyJoinRequestController();
                $result = $controller->reject($noti->entity_id);
            }


            $noti->action = Status::DENIED->value;
            $noti->save();
            
            return response()->json([
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            Log::channel('api')->error($e->getMessage(), ['mark-as-read', 'notification-' . $id, 'user-' . $request->user()->id]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
