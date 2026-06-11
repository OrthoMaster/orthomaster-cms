<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

const STATUS_CREATED = 201;
const STATUS_BAD_REQUEST = 400;


class IntEmpTrackerController extends Controller
{
    const EDIT_WINDOW_DAYS = 3;
    const CUSTOMER_TYPES = ['prospect', 'follow-up', 'member-care'];
    const CALL_RESULTS = ['meeting', 'recall', 'failed'];
    const MEETING_RESULTS = ['success', 'postponed', 'undecided', 'failed'];

    public function receiveData(Request $request)
    {
        if (!$request->has('calls') || !is_array($request->input('calls'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Format de données invalide.'
            ], STATUS_BAD_REQUEST);
        }

        $calls = $request->input('calls');
        $insertedCount = 0;
        $alertCount = 0;

        DB::beginTransaction();

        try {
            foreach ($calls as $call) {
                // TRƯỜNG HỢP CÓ LỖI (Mất quyền Full Disk Access được gửi từ hàm trigger_popup của Python)
                if (isset($call['status']) && $call['status'] === 'permission_denied') {
                    DB::table('int_device_alerts')->insert([
                        'device_serial'  => $call['device_serial'] ?? 'UNKNOWN',
                        'assigned_phone' => $call['assigned_phone'] ?? null,
                        'alert_type'     => 'permission_denied',
                        'message'        => $call['message'] ?? 'Accès complet au disque suspendu par macOS.',
                        'detected_at'    => $call['call_time'] ?? now(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);

                    // Ghi thêm một bản log cứng vào file laravel.log để IT tiện theo dõi qua Terminal Server
                    Log::warning("🚨 ALERT MAC: Máy Mac [{$call['device_serial']}] bị mất quyền Full Disk Access.");
                    $alertCount++;
                    continue;
                }

                // TRƯỜNG HỢP LOG CUỘC GỌI BÌNH THƯỜNG
                // Sử dụng insertOrIgnore để bỏ qua nếu cuộc gọi này đã từng được gửi lên trước đó (tránh trùng lặp)
                $inserted = DB::table('int_emp_call_logs')->insertOrIgnore([
                    'device_serial'     => $call['device_serial'],
                    'assigned_phone'    => $call['assigned_phone'],
                    'partner_phone'     => $call['partner_phone'],
                    'call_type'         => $call['call_type'],
                    'status'            => $call['status'],
                    'duration_seconds'  => $call['duration_seconds'],
                    'timestamp_apple'   => $call['timestamp_apple'],
                    'call_time'         => $call['call_time'],
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                if ($inserted) {
                    $insertedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Données traitées avec succès.',
                'inserted_calls' => $insertedCount,
                'logged_alerts' => $alertCount
            ], STATUS_CREATED);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("🔥 Erreur xử lý dữ liệu MacTracker: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur interne est survenue.'
            ], 500);
        }
    }

    public function receiveHeartbeat(Request $request)
    {
        $data = $request->validate([
            'device_serial'        => ['required', 'string'],
            'assigned_phone'       => ['nullable', 'string'],
            'agent_version'        => ['nullable', 'string'],
            'fda_ok'               => ['nullable', 'boolean'],
            'detail'               => ['nullable', 'string'],
            'latest_call_apple_ts' => ['nullable', 'numeric'],
        ]);

        // Apple CFAbsoluteTime -> Unix epoch (+978307200) -> Carbon. Null nếu không có.
        $lastCallAt = null;
        if (!empty($data['latest_call_apple_ts'])) {
            $lastCallAt = Carbon::createFromTimestamp(
                (int) $data['latest_call_apple_ts'] + 978307200
            );
        }
 
        $fields = [
            'last_seen_at'  => now(),
            'fda_ok'        => $data['fda_ok'] ?? null,
            'last_call_at'  => $lastCallAt,
            'agent_version' => $data['agent_version'] ?? null,
            'last_detail'   => $data['detail'] ?? null,
            'updated_at'    => now(),
        ];
 
        // Upsert theo device_serial. Nếu serial chưa có trong int_devices -> tạo dòng "mồ côi"
        // (user_id null) để admin thấy máy mới và gán nhân viên sau.
        $exists = DB::table('int_devices')->where('device_serial', $data['device_serial'])->exists();
 
        if ($exists) {
            DB::table('int_devices')
                ->where('device_serial', $data['device_serial'])
                ->update($fields);
        } else {
            DB::table('int_devices')->insert(array_merge($fields, [
                'device_serial' => $data['device_serial'],
                'user_id'       => null,
                'active_flg'    => true,
                'created_at'    => now(),
            ]));
        }
 
        return response()->json(['status' => 'success'], STATUS_CREATED);
    }

    

    // =====================================================================
    //  Dashboard quản lý telesales (trang HTML)
    // =====================================================================
    public function dashboard(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');
 
        // Admin: lấy danh sách user ĐANG CÓ thiết bị để lọc cho gọn.
        // Nhân viên thường: không trả về danh sách (UI sẽ không hiển thị filter user).
        $users = collect();
        if ($isAdmin) {
            $userIds = DB::table('int_devices')
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');
 
            $users = User::whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name']);
        }
 
        return view('dashboard.telesales', [
            'isAdmin'      => $isAdmin,
            'users'        => $users,
            'authUserId'   => $authUser->id,
            'today'        => now()->toDateString(),
            'editWindow'   => self::EDIT_WINDOW_DAYS,
            'customerTypes'=> self::CUSTOMER_TYPES,
            'callsDataUrl'        => route('telesales.calls.data'),
            'defineUrlTpl'        => route('telesales.calls.define', ['id' => '__ID__']),
            'recallsDataUrl'      => route('telesales.recalls.data'),
            'meetingsDataUrl'     => route('telesales.meetings.data'),
            'meetingUpdateUrlTpl' => route('telesales.calls.meeting', ['id' => '__ID__']),
            'csrfToken'           => csrf_token(),
        ]);
    }

    // =====================================================================
    //  Nhắc nhở: số điện thoại cần gọi lại trong ngày đang chọn
    //  - Lấy partner_phone từ các bản ghi có call_result='recall'
    //    và (next_follow_up_at NULL HOẶC trùng ngày đang chọn).
    //  - Cột status: "contacted" nếu trong ngày đó có cuộc gọi ĐI tới số này
    //    với call_result đã được set; ngược lại "pending".
    //  - SIDE EFFECT (theo yêu cầu): nếu next_follow_up_at NULL mà đã liên hệ,
    //    cập nhật next_follow_up_at sang ngày đang chọn (dùng giờ thực tế của
    //    cuộc gọi đi để dữ liệu có nghĩa hơn 00:00:00).
    // =====================================================================
    public function recallReminders(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');

        $validated = $request->validate([
            'date'    => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);

        $date         = $validated['date'] ?? now()->toDateString();
        $targetUserId = $isAdmin ? ($validated['user_id'] ?? null) : $authUser->id;

        // Phạm vi serial theo user (giống callLogsData). Null = không lọc (admin xem all).
        $serials = null;
        if (!is_null($targetUserId)) {
            $serials = DB::table('int_devices')
                ->where('user_id', $targetUserId)
                ->pluck('device_serial')
                ->toArray();
            // User không có thiết bị nào -> không có nhắc nhở.
            if (empty($serials)) {
                return response()->json([
                    'data' => [],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 10],
                ]);
            }
        }

        // Bước 1: Lấy các bản ghi recall match filter (NULL hoặc trùng ngày).
        $recallQuery = DB::table('int_emp_call_logs')
            ->where('call_result', 'recall')
            ->where(function ($q) use ($date) {
                $q->whereNull('next_follow_up_at')
                  ->orWhereDate('next_follow_up_at', $date);
            });
        if (!is_null($serials)) {
            $recallQuery->whereIn('device_serial', $serials);
        }
        $recallRows = $recallQuery
            ->orderByDesc('call_time')
            ->get(['id', 'partner_phone', 'next_follow_up_at', 'call_time']);

        // Bước 2: Dedupe theo partner_phone — giữ bản ghi recall MỚI NHẤT làm đại diện.
        $byPhone = [];
        foreach ($recallRows as $r) {
            if (!isset($byPhone[$r->partner_phone])) {
                $byPhone[$r->partner_phone] = $r;
            }
        }

        // Bước 3: Tập số đã "liên hệ" trong ngày = có cuộc gọi ĐI + call_result đã set.
        //         Kèm theo MAX(call_time) để dùng làm next_follow_up_at khi update.
        $contactedQuery = DB::table('int_emp_call_logs')
            ->whereDate('call_time', $date)
            ->where('call_type', 'outgoing')
            ->whereNotNull('call_result')
            ->select('partner_phone', DB::raw('MAX(call_time) as last_out_at'))
            ->groupBy('partner_phone');
        if (!is_null($serials)) {
            $contactedQuery->whereIn('device_serial', $serials);
        }
        $contactedMap = $contactedQuery->get()->keyBy('partner_phone');

        // Bước 4: Xử lý từng nhắc nhở.
        $items = [];
        foreach ($byPhone as $phone => $row) {
            $contacted     = $contactedMap->has($phone);
            $contactedTime = $contacted ? $contactedMap[$phone]->last_out_at : null;
            $nextFollowUp  = $row->next_follow_up_at;

            // Case 1: chưa có lịch + đã liên hệ -> set next_follow_up_at = giờ cuộc gọi đi.
            // Cập nhật cho TẤT CẢ recall null cùng phone+scope để dữ liệu không còn lệch.
            if ($contacted && is_null($nextFollowUp)) {
                $updateQuery = DB::table('int_emp_call_logs')
                    ->where('call_result', 'recall')
                    ->where('partner_phone', $phone)
                    ->whereNull('next_follow_up_at');
                if (!is_null($serials)) {
                    $updateQuery->whereIn('device_serial', $serials);
                }
                $updateQuery->update([
                    'next_follow_up_at' => $contactedTime,
                    'updated_at'        => now(),
                ]);
                $nextFollowUp = $contactedTime;
            }

            $items[] = [
                'partner_phone'     => $phone,
                'next_follow_up_at' => $nextFollowUp,
                'status'            => $contacted ? 'contacted' : 'pending',
            ];
        }

        // Sắp xếp: pending lên trước; trong cùng nhóm, có giờ thì giờ sớm trước, null cuối.
        usort($items, function ($a, $b) {
            if (($a['status'] === 'pending') !== ($b['status'] === 'pending')) {
                return $a['status'] === 'pending' ? -1 : 1;
            }
            $an = is_null($a['next_follow_up_at']);
            $bn = is_null($b['next_follow_up_at']);
            if ($an && $bn) return 0;
            if ($an) return 1;
            if ($bn) return -1;
            return strcmp($a['next_follow_up_at'], $b['next_follow_up_at']);
        });

        // Phân trang 10/page (server-side, thủ công trên mảng đã sort).
        $perPage  = 10;
        $total    = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = $validated['page'] ?? 1;
        if ($page > $lastPage) $page = $lastPage;
        $pageItems = array_slice($items, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $pageItems,
            'meta' => [
                'current_page' => (int) $page,
                'last_page'    => $lastPage,
                'total'        => $total,
                'per_page'     => $perPage,
            ],
        ]);
    }
 
    // =====================================================================
    //  Dữ liệu lịch sử cuộc gọi (JSON, có filter + phân trang 20)
    // =====================================================================
    public function callLogsData(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');
 
        $validated = $request->validate([
            'date'    => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);
 
        $date = $validated['date'] ?? now()->toDateString();
 
        // Xác định user cần lọc:
        //  - Admin   : theo user_id chọn; null => tất cả nhân viên.
        //  - Non-admin: LUÔN ép về chính mình, bỏ qua mọi user_id client gửi.
        $targetUserId = $isAdmin ? ($validated['user_id'] ?? null) : $authUser->id;
 
        $query = DB::table('int_emp_call_logs as c')
            ->whereDate('c.call_time', $date);
 
        if (!is_null($targetUserId)) {
            // Lấy serial của các thiết bị thuộc user này, rồi lọc theo serial.
            $serials = DB::table('int_devices')
                ->where('user_id', $targetUserId)
                ->pluck('device_serial');
 
            // Không có thiết bị => không có cuộc gọi nào.
            $query->whereIn('c.device_serial', $serials);
        }
 
        $paginator = $query
            ->orderByDesc('c.call_time')
            ->paginate(
                20,
                [
                    'c.id', 'c.call_time', 'c.call_type', 'c.status',
                    'c.partner_phone', 'c.duration_seconds',
                    'c.customer_type', 'c.call_result', 'c.next_follow_up_at',
                    'c.meeting_client_name', 'c.google_event_id', 'c.meeting_result',
                ],
                'page',
                $validated['page'] ?? 1
            );
 
        // Vì lọc theo 1 ngày cố định (whereDate) nên mọi dòng cùng tính sửa được/không.
        $editable = $this->isWithinEditWindow($date);
 
        $rows = collect($paginator->items())->map(function ($r) use ($editable) {
            return [
                'id'                => (int) $r->id,
                'call_time'         => $r->call_time,
                'call_type'         => $r->call_type,
                'status'            => $r->status,
                'partner_phone'     => $r->partner_phone,
                'duration_seconds'  => (int) $r->duration_seconds,
                'customer_type'       => $r->customer_type,
                'call_result'         => $r->call_result,
                'next_follow_up_at'   => $r->next_follow_up_at,
                'meeting_client_name' => $r->meeting_client_name,
                'google_event_id'     => $r->google_event_id,
                'meeting_result'      => $r->meeting_result,
                'editable'            => $editable,
            ];
        });
 
        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'editable'     => $editable,
            ],
        ]);
    }
 
    // =====================================================================
    //  Cập nhật customer_type + next_follow_up_at cho 1 cuộc gọi
    // =====================================================================
    public function defineCustomerType(Request $request, int $id)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');
 
        $validated = $request->validate([
            'customer_type'       => ['required', Rule::in(self::CUSTOMER_TYPES)],
            'call_result'         => ['nullable', Rule::in(self::CALL_RESULTS)],
            'meeting_client_name' => ['nullable', 'string', 'max:255', 'required_if:call_result,meeting'],
            'next_follow_up_at'   => ['nullable', 'date', 'after:now'],
        ]);
 
        $call = DB::table('int_emp_call_logs')->where('id', $id)->first();
        if (!$call) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Appel introuvable.',
            ], 404);
        }
 
        // Quyền: non-admin chỉ sửa cuộc gọi của thiết bị mình sở hữu.
        if (!$isAdmin) {
            $owns = DB::table('int_devices')
                ->where('user_id', $authUser->id)
                ->where('device_serial', $call->device_serial)
                ->exists();
 
            if (!$owns) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Accès refusé.',
                ], 403);
            }
        }
 
        // Luật 3 ngày — BẮT BUỘC ở server (không tin UID/UI).
        if (!$this->isWithinEditWindow(Carbon::parse($call->call_time)->toDateString())) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cet appel est trop ancien pour être modifié.',
            ], 422);
        }
 
        // Chuẩn hoá theo nghiệp vụ (ép ở server, không tin UI):
        //  - member-care            : không có result, không có lịch follow-up, không có tên khách.
        //  - prospect/follow-up + failed : bỏ next_follow_up_at.
        //  - call_result != meeting : bỏ meeting_client_name (chỉ ý nghĩa với rendez-vous).
        $customerType  = $validated['customer_type'];
        $callResult    = $validated['call_result'] ?? null;
        $meetingClient = $validated['meeting_client_name'] ?? null;
        $nextFollowUp  = $validated['next_follow_up_at'] ?? null;

        if ($customerType === 'member-care') {
            $callResult    = null;
            $nextFollowUp  = null;
            $meetingClient = null;
        } elseif ($callResult === 'failed') {
            $nextFollowUp = null;
        }

        if ($callResult !== 'meeting') {
            $meetingClient = null;
        }

        DB::table('int_emp_call_logs')->where('id', $id)->update([
            'customer_type'       => $customerType,
            'call_result'         => $callResult,
            'meeting_client_name' => $meetingClient,
            'next_follow_up_at'   => $nextFollowUp,
            'updated_at'          => now(),
        ]);

        // Tạo Google Calendar event NẾU đủ điều kiện và chưa từng tạo cho dòng này.
        // Điều kiện: meeting + có ngày suivi + có tên khách + chưa có google_event_id.
        $googleEventId = $call->google_event_id ?? null;
        if (
            $callResult === 'meeting'
            && !empty($nextFollowUp)
            && !empty($meetingClient)
            && empty($call->google_event_id)
        ) {
            $created = app(MeetingController::class)->createMeeting(
                $authUser->email,
                $meetingClient,
                $nextFollowUp
            );

            if (is_string($created) && $created !== '') {
                $googleEventId = $created;
                DB::table('int_emp_call_logs')
                    ->where('id', $id)
                    ->update([
                        'google_event_id' => $googleEventId,
                        'updated_at'      => now(),
                    ]);
            } else {
                // createMeeting đã log lỗi; ở đây chỉ để biết phía endpoint cũng thấy.
                Log::warning('createMeeting échec', [
                    'call_id' => $id,
                    'staff'   => $authUser->email,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                  => $id,
                'customer_type'       => $customerType,
                'call_result'         => $callResult,
                'meeting_client_name' => $meetingClient,
                'next_follow_up_at'   => $nextFollowUp,
                'google_event_id'     => $googleEventId,
            ],
        ]);
    }
 
    // =====================================================================
    //  Cập nhật 1 rendez-vous: đổi ngày RDV (kèm sync Google Calendar) +
    //  chọn meeting_result. Endpoint chung cho cả nút "Modifier RDV" trên
    //  Historique và nút "Modifier" trên card Rendez-vous.
    // =====================================================================
    public function updateMeeting(Request $request, int $id)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');

        $validated = $request->validate([
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
            'meeting_result'    => ['nullable', Rule::in(self::MEETING_RESULTS)],
        ]);

        $call = DB::table('int_emp_call_logs')->where('id', $id)->first();
        if (!$call) {
            return response()->json(['status' => 'error', 'message' => 'Appel introuvable.'], 404);
        }

        if (!$isAdmin) {
            $owns = DB::table('int_devices')
                ->where('user_id', $authUser->id)
                ->where('device_serial', $call->device_serial)
                ->exists();
            if (!$owns) {
                return response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403);
            }
        }

        $newDate   = $validated['next_follow_up_at'] ?? null;
        $newResult = $validated['meeting_result'] ?? null;

        // Đồng bộ Google Calendar NẾU: có ngày mới + đã có event + ngày thực sự đổi.
        // So sánh ở mức phút để né khác biệt định dạng 'HH:MM' vs 'HH:MM:SS'.
        if ($newDate && !empty($call->google_event_id)) {
            $oldFmt = $call->next_follow_up_at
                ? Carbon::parse($call->next_follow_up_at)->format('Y-m-d H:i')
                : null;
            $newFmt = Carbon::parse($newDate)->format('Y-m-d H:i');

            if ($oldFmt !== $newFmt) {
                $ok = app(MeetingController::class)->updateMeetingTime(
                    $authUser->email,
                    $call->google_event_id,
                    $newDate
                );
                if ($ok !== true) {
                    // Log nhưng vẫn cho update DB; user thấy giá trị mới trong app,
                    // chỉ Google không kịp sync (có thể retry sau).
                    Log::warning('updateMeetingTime échec', [
                        'call_id' => $id,
                        'event'   => $call->google_event_id,
                    ]);
                }
            }
        }

        DB::table('int_emp_call_logs')->where('id', $id)->update([
            'next_follow_up_at' => $newDate,
            'meeting_result'    => $newResult,
            'updated_at'        => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                => $id,
                'next_follow_up_at' => $newDate,
                'meeting_result'    => $newResult,
            ],
        ]);
    }

    // =====================================================================
    //  Danh sách rendez-vous: call_result='meeting' và next_follow_up_at
    //  NULL hoặc trùng ngày đang chọn. Dedupe theo partner_phone (giữ bản
    //  ghi mới nhất). Cột "outcome" để trống — bổ sung nghiệp vụ sau.
    // =====================================================================
    public function meetingReminders(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');

        $validated = $request->validate([
            'date'    => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);

        $date         = $validated['date'] ?? now()->toDateString();
        $targetUserId = $isAdmin ? ($validated['user_id'] ?? null) : $authUser->id;

        $serials = null;
        if (!is_null($targetUserId)) {
            $serials = DB::table('int_devices')
                ->where('user_id', $targetUserId)
                ->pluck('device_serial')
                ->toArray();
            if (empty($serials)) {
                return response()->json([
                    'data' => [],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 10],
                ]);
            }
        }

        $q = DB::table('int_emp_call_logs')
            ->where('call_result', 'meeting')
            ->where(function ($w) use ($date) {
                $w->whereNull('next_follow_up_at')
                  ->orWhereDate('next_follow_up_at', $date);
            });
        if (!is_null($serials)) {
            $q->whereIn('device_serial', $serials);
        }
        $rows = $q->orderByDesc('call_time')
            ->get(['id', 'partner_phone', 'next_follow_up_at', 'call_time', 'meeting_result', 'google_event_id']);

        // Dedupe theo phone — giữ bản ghi meeting mới nhất.
        $byPhone = [];
        foreach ($rows as $r) {
            if (!isset($byPhone[$r->partner_phone])) {
                $byPhone[$r->partner_phone] = $r;
            }
        }

        $items = [];
        foreach ($byPhone as $phone => $row) {
            $items[] = [
                'id'                => (int) $row->id,
                'partner_phone'     => $phone,
                'next_follow_up_at' => $row->next_follow_up_at,
                'meeting_result'    => $row->meeting_result,
                'google_event_id'   => $row->google_event_id,
            ];
        }

        // Sắp xếp: có giờ trước (sớm nhất), null cuối.
        usort($items, function ($a, $b) {
            $an = is_null($a['next_follow_up_at']);
            $bn = is_null($b['next_follow_up_at']);
            if ($an && $bn) return 0;
            if ($an) return 1;
            if ($bn) return -1;
            return strcmp($a['next_follow_up_at'], $b['next_follow_up_at']);
        });

        $perPage  = 10;
        $total    = count($items);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = $validated['page'] ?? 1;
        if ($page > $lastPage) $page = $lastPage;
        $pageItems = array_slice($items, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $pageItems,
            'meta' => [
                'current_page' => (int) $page,
                'last_page'    => $lastPage,
                'total'        => $total,
                'per_page'     => $perPage,
            ],
        ]);
    }

    // =====================================================================
    //  Helper
    // =====================================================================
    private function isWithinEditWindow(string $date): bool
    {
        $callDate = Carbon::parse($date)->startOfDay();
        $cutoff   = now()->startOfDay()->subDays(self::EDIT_WINDOW_DAYS);
 
        // Sửa được nếu ngày gọi >= (hôm nay - EDIT_WINDOW_DAYS).
        return $callDate->gte($cutoff);
    }
}