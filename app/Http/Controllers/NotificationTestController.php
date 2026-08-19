<?php

namespace App\Http\Controllers;

use App\Services\ExpoNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationTestController extends Controller
{
    /** Domaine autorisé pour les comptes de test */
    private const TEST_EMAIL_DOMAIN = '@orthomaster.fr';

    /** Types de contenu pouvant être notifiés */
    private const TARGET_TYPES = ['podcast', 'daily-question'];

    public function __construct(private ExpoNotificationService $expo) {}

    /**
     * Formulaire d'envoi de notification de test.
     * GET /notifications/test
     */
    public function index()
    {
        return view('notifications.test', [
            'users' => $this->testUsers(),
        ]);
    }

    /**
     * Appareils rattachés à un utilisateur.
     * GET /notifications/test/devices/{userId}
     */
    public function devices(int $userId)
    {
        $devices = DB::table('device_tokens')
            ->where('user_id', $userId)
            ->orderByDesc('last_used_at')
            ->get(['id', 'fcm_token', 'device_id', 'device_type', 'last_used_at']);

        return response()->json(
            $devices->map(fn($d) => [
                'id'    => $d->id,
                'text'  => $this->deviceLabel($d),
                'token' => $d->fcm_token,
            ])
        );
    }

    /**
     * Contenus disponibles pour un type donné.
     * GET /notifications/test/targets/{type}
     */
    public function targets(string $type)
    {
        if (!in_array($type, self::TARGET_TYPES, true)) {
            return response()->json([]);
        }

        $rows = $type === 'podcast'
            ? DB::table('podcasts')
            ->select('id', 'title', 'notification_title', 'notification_body')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            : DB::table('daily_questions')
            ->select('id', 'question as title', 'notification_title', 'notification_body')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(
            $rows->map(fn($r) => [
                'id'    => $r->id,
                'text'  => '#' . $r->id . ' — ' . \Illuminate\Support\Str::limit($r->title, 70),
                'title' => $r->notification_title,
                'body'  => $r->notification_body,
            ])
        );
    }

    /**
     * Envoi de la notification à l'appareil sélectionné.
     * POST /notifications/test
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'user_id'     => ['required', 'integer', 'exists:users,id'],
            'device_id'   => ['required', 'integer', 'exists:device_tokens,id'],
            'target_type' => ['required', 'string', 'in:podcast,daily-question'],
            'target_id'   => ['required', 'integer'],
            'title'       => ['required', 'string', 'max:255'],
            'body'        => ['required', 'string', 'max:500'],
        ], [], [
            'user_id'     => 'utilisateur',
            'device_id'   => 'appareil',
            'target_type' => 'type de contenu',
            'target_id'   => 'contenu',
            'title'       => 'titre',
            'body'        => 'message',
        ]);

        // L'appareil doit bien appartenir à l'utilisateur choisi
        $device = DB::table('device_tokens')
            ->where('id', $data['device_id'])
            ->where('user_id', $data['user_id'])
            ->first();

        if (!$device) {
            return back()->withInput()
                ->with('message', 'Cet appareil n\'appartient pas à l\'utilisateur sélectionné.')
                ->with('alert-type', 'error');
        }

        // Le contenu visé doit exister
        $table = $data['target_type'] === 'podcast' ? 'podcasts' : 'daily_questions';

        if (!DB::table($table)->where('id', $data['target_id'])->exists()) {
            return back()->withInput()
                ->with('message', 'Le contenu sélectionné est introuvable.')
                ->with('alert-type', 'error');
        }

        try {
            $result = $this->expo->sendToDevice(
                $device->fcm_token,
                $data['title'],
                $data['body'],
                [
                    'action' => $data['target_type'],
                    'id'     => (int) $data['target_id'],
                ]
            );

            if ($result === false) {
                Log::channel('api')->error('Test notification : jeton invalide', [
                    'user_id'   => $data['user_id'],
                    'device_id' => $data['device_id'],
                ]);

                return back()->withInput()
                    ->with('message', 'Échec de l\'envoi : le jeton de cet appareil n\'est pas valide.')
                    ->with('alert-type', 'error');
            }

            // Expo renvoie un ticket par message ; un ticket en erreur signifie
            // que la notification n'a pas été acceptée
            $error = $this->firstTicketError($result);

            if ($error) {
                Log::channel('api')->error('Test notification : ticket en erreur', [
                    'user_id'   => $data['user_id'],
                    'device_id' => $data['device_id'],
                    'action'    => $data['target_type'],
                    'target_id' => $data['target_id'],
                    'error'     => $error,
                ]);

                return back()->withInput()
                    ->with('message', 'Échec de l\'envoi. Consultez les journaux pour le détail.')
                    ->with('alert-type', 'error');
            }

            return back()->withInput()
                ->with('message', 'Notification envoyée.')
                ->with('alert-type', 'success');
        } catch (\Throwable $e) {
            Log::channel('api')->error('Test notification : exception', [
                'message'   => $e->getMessage(),
                'user_id'   => $data['user_id'],
                'device_id' => $data['device_id'],
            ]);

            return back()->withInput()
                ->with('message', 'Une erreur est survenue lors de l\'envoi.')
                ->with('alert-type', 'error');
        }
    }

    /* ===================== HELPERS ===================== */

    /**
     * Première erreur remontée par les tickets Expo, s'il y en a une.
     */
    private function firstTicketError($result): ?array
    {
        foreach ($result['results'] ?? [] as $batch) {
            if (($batch['success'] ?? false) === false) {
                return ['batch' => $batch['error'] ?? 'unknown'];
            }

            foreach ($batch['data'] ?? [] as $ticket) {
                if (($ticket['status'] ?? null) === 'error') {
                    return $ticket;
                }
            }
        }

        return null;
    }

    private function testUsers()
    {
        return DB::table('users')
            ->where('email', 'like', '%' . self::TEST_EMAIL_DOMAIN)
            ->orderBy('email')
            ->get(['id', 'email']);
    }

    private function deviceLabel($device): string
    {
        $parts = array_filter([
            $device->device_type ?: null,
            $device->device_id ?: null,
            $device->last_used_at ?: null,
        ]);

        $label = $parts ? implode(' — ', $parts) : 'Appareil #' . $device->id;

        return $label . ' (' . \Illuminate\Support\Str::limit($device->fcm_token, 22) . ')';
    }
}
