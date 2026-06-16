<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PhoneNormalizer;
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

    /** Quá số phút này không có heartbeat thì coi máy là offline. */
    const OFFLINE_AFTER_MINUTES = 5;

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
                    // Đảm bảo số này có trong danh bạ; auto-fill từ users/companies nếu match.
                    $this->ensurePhoneContact($call['partner_phone'] ?? null);
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
            'customerTypes' => self::CUSTOMER_TYPES,
            'callsDataUrl'        => route('telesales.calls.data'),
            'defineUrlTpl'        => route('telesales.calls.define', ['id' => '__ID__']),
            'recallsDataUrl'      => route('telesales.recalls.data'),
            'meetingsDataUrl'     => route('telesales.meetings.data'),
            'meetingUpdateUrlTpl' => route('telesales.calls.meeting', ['id' => '__ID__']),
            'summaryDataUrl'      => route('telesales.summary.data'),
            'contactStoreUrl'     => route('telesales.contacts.store'),
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
        // Bỏ qua cuộc gọi có status='missed' — không phải nhắc nhở hợp lệ.
        $recallQuery = DB::table('int_emp_call_logs')
            ->where('call_result', 'recall')
            ->where('status', '!=', 'missed')
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

        // Batch lookup danh bạ cho toàn bộ phone xuất hiện.
        $contactMap = $this->buildContactMap(array_keys($byPhone));

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

            $c = $contactMap[PhoneNormalizer::normalize($phone) ?? ''] ?? null;
            $items[] = [
                'partner_phone'     => $phone,
                'next_follow_up_at' => $nextFollowUp,
                'status'            => $contacted ? 'contacted' : 'pending',
                'contact_name'      => $c['display_name'] ?? null,
                'contact_company'   => $c['company_name'] ?? null,
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
                    'c.id',
                    'c.call_time',
                    'c.call_type',
                    'c.status',
                    'c.partner_phone',
                    'c.duration_seconds',
                    'c.customer_type',
                    'c.call_result',
                    'c.next_follow_up_at',
                    'c.meeting_client_name',
                    'c.meeting_client_company',
                    'c.google_event_id',
                    'c.meeting_result',
                ],
                'page',
                $validated['page'] ?? 1
            );

        // Vì lọc theo 1 ngày cố định (whereDate) nên mọi dòng cùng tính sửa được/không.
        $editable = $this->isWithinEditWindow($date);

        // Batch lookup danh bạ cho toàn bộ partner_phone của trang này.
        $contactMap = $this->buildContactMap(
            collect($paginator->items())->pluck('partner_phone')->all()
        );

        $rows = collect($paginator->items())->map(function ($r) use ($editable, $contactMap) {
            $contact = $contactMap[PhoneNormalizer::normalize($r->partner_phone) ?? ''] ?? null;
            return [
                'id'                     => (int) $r->id,
                'call_time'              => $r->call_time,
                'call_type'              => $r->call_type,
                'status'                 => $r->status,
                'partner_phone'          => $r->partner_phone,
                'duration_seconds'       => (int) $r->duration_seconds,
                'customer_type'          => $r->customer_type,
                'call_result'            => $r->call_result,
                'next_follow_up_at'      => $r->next_follow_up_at,
                'meeting_client_name'    => $r->meeting_client_name,
                'meeting_client_company' => $r->meeting_client_company,
                'google_event_id'        => $r->google_event_id,
                'meeting_result'         => $r->meeting_result,
                'contact_name'           => $contact['display_name'] ?? null,
                'contact_company'        => $contact['company_name'] ?? null,
                'editable'               => $editable,
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
            'customer_type'          => ['required', Rule::in(self::CUSTOMER_TYPES)],
            'call_result'            => ['nullable', Rule::in(self::CALL_RESULTS)],
            'meeting_client_name'    => ['nullable', 'string', 'max:255', 'required_if:call_result,meeting'],
            'meeting_client_company' => ['nullable', 'string', 'max:255'],
            'next_follow_up_at'      => ['nullable', 'date', 'after:now'],
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
        //  - member-care            : không có result, không có lịch follow-up, không có tên khách/công ty.
        //  - prospect/follow-up + failed : bỏ next_follow_up_at.
        //  - call_result != meeting : bỏ meeting_client_name/_company (chỉ ý nghĩa với rendez-vous).
        $customerType   = $validated['customer_type'];
        $callResult     = $validated['call_result'] ?? null;
        $meetingClient  = $validated['meeting_client_name'] ?? null;
        $meetingCompany = $validated['meeting_client_company'] ?? null;
        $nextFollowUp   = $validated['next_follow_up_at'] ?? null;

        if ($customerType === 'member-care') {
            $callResult     = null;
            $nextFollowUp   = null;
            $meetingClient  = null;
            $meetingCompany = null;
        } elseif ($callResult === 'failed') {
            $nextFollowUp = null;
        }

        if ($callResult !== 'meeting') {
            $meetingClient  = null;
            $meetingCompany = null;
        }

        DB::table('int_emp_call_logs')->where('id', $id)->update([
            'customer_type'          => $customerType,
            'call_result'            => $callResult,
            'meeting_client_name'    => $meetingClient,
            'meeting_client_company' => $meetingCompany,
            'next_follow_up_at'      => $nextFollowUp,
            'updated_at'             => now(),
        ]);

        // Đồng bộ danh bạ: khi nhân viên đã định danh khách (meeting + có tên/công ty),
        // promote contact của số này lên source='manual' (mạnh nhất, không bị auto-fill ghi đè).
        if ($callResult === 'meeting' && ($meetingClient || $meetingCompany)) {
            $this->upsertContactManual($call->partner_phone, $meetingClient, $meetingCompany);
        }

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
                $meetingCompany,
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
                'id'                     => $id,
                'customer_type'          => $customerType,
                'call_result'            => $callResult,
                'meeting_client_name'    => $meetingClient,
                'meeting_client_company' => $meetingCompany,
                'next_follow_up_at'      => $nextFollowUp,
                'google_event_id'        => $googleEventId,
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
    //  Số liệu tổng quan trong ngày — cho các ô màu phía trên dashboard.
    //  Tách 3 nhóm:
    //    1) Appels typés        : đếm theo customer_type trên cuộc gọi ngày đó.
    //    2) Appels avec résultat: đếm theo call_result trên cuộc gọi ngày đó.
    //    3) Rendez-vous du jour : đếm theo logic CARD Rendez-vous (dedupe phone),
    //                              breakdown theo meeting_result.
    // =====================================================================
    public function summaryData(Request $request)
    {
        $authUser = Auth::user();
        $isAdmin  = $authUser->hasPermission('edit_admins');

        $validated = $request->validate([
            'date'    => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $date         = $validated['date'] ?? now()->toDateString();
        $targetUserId = $isAdmin ? ($validated['user_id'] ?? null) : $authUser->id;

        // Phạm vi serial theo user (giống các endpoint khác).
        $serials = null;
        if (!is_null($targetUserId)) {
            $serials = DB::table('int_devices')
                ->where('user_id', $targetUserId)
                ->pluck('device_serial')
                ->toArray();
            if (empty($serials)) {
                return response()->json([
                    'typed'    => ['total' => 0, 'prospect' => 0, 'follow_up' => 0, 'member_care' => 0],
                    'result'   => ['total' => 0, 'meeting'  => 0, 'recall'    => 0, 'failed'      => 0],
                    'meetings' => ['total' => 0, 'success'  => 0, 'postponed' => 0, 'undecided'   => 0, 'failed' => 0],
                ]);
            }
        }

        // ---- Nhóm 1 & 2 : trên CUỘC GỌI của ngày đã chọn ---------------
        $callBase = function () use ($date, $serials) {
            $q = DB::table('int_emp_call_logs')->whereDate('call_time', $date);
            if (!is_null($serials)) {
                $q->whereIn('device_serial', $serials);
            }
            return $q;
        };

        $byType = $callBase()
            ->whereNotNull('customer_type')
            ->select('customer_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('customer_type')
            ->pluck('cnt', 'customer_type')
            ->toArray();

        $typed = [
            'total'       => (int) array_sum($byType),
            'prospect'    => (int) ($byType['prospect']    ?? 0),
            'follow_up'   => (int) ($byType['follow-up']   ?? 0),
            'member_care' => (int) ($byType['member-care'] ?? 0),
        ];

        $byResult = $callBase()
            ->whereNotNull('call_result')
            ->select('call_result', DB::raw('COUNT(*) as cnt'))
            ->groupBy('call_result')
            ->pluck('cnt', 'call_result')
            ->toArray();

        $result = [
            'total'   => (int) array_sum($byResult),
            'meeting' => (int) ($byResult['meeting'] ?? 0),
            'recall'  => (int) ($byResult['recall']  ?? 0),
            'failed'  => (int) ($byResult['failed']  ?? 0),
        ];

        // ---- Nhóm 3 : meetings PHẢI làm trong ngày (giống card Rendez-vous) ----
        // Filter: call_result='meeting' AND (next_follow_up_at NULL OR DATE matches).
        // Dedupe theo partner_phone, giữ bản ghi mới nhất.
        $meetQ = DB::table('int_emp_call_logs')
            ->where('call_result', 'meeting')
            ->where(function ($q) use ($date) {
                $q->whereNull('next_follow_up_at')
                    ->orWhereDate('next_follow_up_at', $date);
            });
        if (!is_null($serials)) {
            $meetQ->whereIn('device_serial', $serials);
        }
        $meetRows = $meetQ->orderByDesc('call_time')
            ->get(['partner_phone', 'meeting_result']);

        $byPhone = [];
        foreach ($meetRows as $r) {
            if (!isset($byPhone[$r->partner_phone])) {
                $byPhone[$r->partner_phone] = $r->meeting_result;
            }
        }

        $meetings = [
            'total'     => count($byPhone),
            'success'   => 0,
            'postponed' => 0,
            'undecided' => 0,
            'failed'    => 0,
        ];
        foreach ($byPhone as $mr) {
            if (isset($meetings[$mr])) {
                $meetings[$mr]++;
            }
        }

        return response()->json([
            'typed'    => $typed,
            'result'   => $result,
            'meetings' => $meetings,
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

        // Batch lookup danh bạ.
        $contactMap = $this->buildContactMap(array_keys($byPhone));

        $items = [];
        foreach ($byPhone as $phone => $row) {
            $c = $contactMap[PhoneNormalizer::normalize($phone) ?? ''] ?? null;
            $items[] = [
                'id'                => (int) $row->id,
                'partner_phone'     => $phone,
                'next_follow_up_at' => $row->next_follow_up_at,
                'meeting_result'    => $row->meeting_result,
                'google_event_id'   => $row->google_event_id,
                'contact_name'      => $c['display_name'] ?? null,
                'contact_company'   => $c['company_name'] ?? null,
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
    //  TRANG QUẢN LÝ APPAREILS (admin-only)
    //  - Liệt kê int_devices với trạng thái online/offline/no_permission
    //  - CRUD assign user, toggle active
    //  - Hiển thị int_device_alerts gần đây để biết máy nào có vấn đề
    // =====================================================================
    public function devices(Request $request)
    {
        $this->ensureAdmin();

        // Dropdown user trong modal: chỉ user có role_id != 2 (ép cả ở server khi save).
        $users = DB::table('users')
            ->where('role_id', '!=', 2)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('dashboard.devices', [
            'users'              => $users,
            'devicesDataUrl'     => route('telesales.devices.data'),
            'alertsDataUrl'      => route('telesales.devices.alerts'),
            'alertsClearUrl'     => route('telesales.devices.alerts.clear'),
            'alertDeleteUrlTpl'  => route('telesales.devices.alerts.destroy', ['id' => '__ID__']),
            'deviceStoreUrl'     => route('telesales.devices.store'),
            'deviceUpdateUrlTpl' => route('telesales.devices.update', ['id' => '__ID__']),
            'deviceDeleteUrlTpl' => route('telesales.devices.destroy', ['id' => '__ID__']),
            'csrfToken'          => csrf_token(),
            'offlineAfter'       => self::OFFLINE_AFTER_MINUTES,
        ]);
    }

    public function devicesData(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'page'   => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = DB::table('int_devices as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->select([
                'd.id',
                'd.device_serial',
                'd.user_id',
                'u.name as user_name',
                'd.active_flg',
                'd.last_seen_at',
                'd.fda_ok',
                'd.last_call_at',
                'd.agent_version',
                'd.last_detail',
            ]);

        if (!empty($validated['search'])) {
            $s = $validated['search'];
            $query->where(function ($q) use ($s) {
                $q->where('d.device_serial', 'like', "%$s%")
                    ->orWhere('u.name', 'like', "%$s%");
            });
        }

        // Sort: máy đang sống lên trước (last_seen_at desc, null cuối).
        $paginator = $query
            ->orderByRaw('d.last_seen_at IS NULL ASC')
            ->orderByDesc('d.last_seen_at')
            ->paginate(20, ['*'], 'page', $validated['page'] ?? 1);

        $threshold = now()->subMinutes(self::OFFLINE_AFTER_MINUTES);

        $rows = collect($paginator->items())->map(function ($d) use ($threshold) {
            return [
                'id'            => (int) $d->id,
                'device_serial' => $d->device_serial,
                'user_id'       => $d->user_id ? (int) $d->user_id : null,
                'user_name'     => $d->user_name,
                'active_flg'    => (bool) $d->active_flg,
                'status'        => $this->computeDeviceStatus($d, $threshold),
                'last_seen_at'  => $d->last_seen_at,
                'last_call_at'  => $d->last_call_at,
                'agent_version' => $d->agent_version,
                'last_detail'   => $d->last_detail,
            ];
        });

        // Counts theo toàn bộ bảng (không chỉ trang hiện tại) — cho thanh tổng quan.
        $counts = $this->computeDeviceCounts($threshold);

        return response()->json([
            'data'   => $rows,
            'counts' => $counts,
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function deviceAlertsData(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = DB::table('int_device_alerts as a')
            ->leftJoin('int_devices as d', 'd.device_serial', '=', 'a.device_serial')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->select([
                'a.id',
                'a.device_serial',
                'a.assigned_phone',
                'a.alert_type',
                'a.message',
                'a.detected_at',
                'u.name as user_name',
            ])
            ->orderByDesc('a.detected_at')
            ->paginate(15, ['*'], 'page', $validated['page'] ?? 1);

        $rows = collect($paginator->items())->map(function ($a) {
            return [
                'id'             => (int) $a->id,
                'device_serial'  => $a->device_serial,
                'user_name'      => $a->user_name,
                'assigned_phone' => $a->assigned_phone,
                'alert_type'     => $a->alert_type,
                'message'        => $a->message,
                'detected_at'    => $a->detected_at,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function deviceStore(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'device_serial' => ['required', 'string', 'max:255', 'unique:int_devices,device_serial'],
            'user_id'       => ['nullable', 'integer', 'exists:users,id'],
            'active_flg'    => ['nullable', 'boolean'],
        ]);

        $this->ensureAssignableUser($validated['user_id'] ?? null);

        $id = DB::table('int_devices')->insertGetId([
            'device_serial' => $validated['device_serial'],
            'user_id'       => $validated['user_id'] ?? null,
            'active_flg'    => $validated['active_flg'] ?? true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json(['status' => 'success', 'data' => ['id' => $id]], STATUS_CREATED);
    }

    public function deviceUpdate(Request $request, int $id)
    {
        $this->ensureAdmin();

        $device = DB::table('int_devices')->where('id', $id)->first();
        if (!$device) {
            return response()->json(['status' => 'error', 'message' => 'Appareil introuvable.'], 404);
        }

        $validated = $request->validate([
            'device_serial' => [
                'required',
                'string',
                'max:255',
                Rule::unique('int_devices', 'device_serial')->ignore($id),
            ],
            'user_id'       => ['nullable', 'integer', 'exists:users,id'],
            'active_flg'    => ['nullable', 'boolean'],
        ]);

        $this->ensureAssignableUser($validated['user_id'] ?? null);

        DB::table('int_devices')->where('id', $id)->update([
            'device_serial' => $validated['device_serial'],
            'user_id'       => $validated['user_id'] ?? null,
            'active_flg'    => $validated['active_flg'] ?? true,
            'updated_at'    => now(),
        ]);

        return response()->json(['status' => 'success']);
    }

    public function deviceDestroy(int $id)
    {
        $this->ensureAdmin();

        $deleted = DB::table('int_devices')->where('id', $id)->delete();

        return response()->json([
            'status' => $deleted ? 'success' : 'error',
        ], $deleted ? 200 : 404);
    }

    // Xoá MỘT alert.
    public function deviceAlertDestroy(int $id)
    {
        $this->ensureAdmin();

        $deleted = DB::table('int_device_alerts')->where('id', $id)->delete();

        return response()->json([
            'status' => $deleted ? 'success' : 'error',
        ], $deleted ? 200 : 404);
    }

    // Xoá TẤT CẢ alerts (admin chủ ý dọn bảng sau khi xử lý xong).
    public function deviceAlertsClear()
    {
        $this->ensureAdmin();

        $count = DB::table('int_device_alerts')->delete();

        return response()->json([
            'status'  => 'success',
            'deleted' => $count,
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers riêng cho phần devices
    // ---------------------------------------------------------------------
    private function ensureAdmin(): void
    {
        if (!Auth::user() || !Auth::user()->hasPermission('edit_admins')) {
            abort(403, 'Réservé aux administrateurs.');
        }
    }

    private function ensureAssignableUser(?int $userId): void
    {
        if (is_null($userId)) return;

        $ok = DB::table('users')
            ->where('id', $userId)
            ->where('role_id', '!=', 2)
            ->exists();
        if (!$ok) {
            // Không tin client: client có thể nhét id của user role=2 vào.
            abort(422, 'Utilisateur invalide pour assignation.');
        }
    }

    private function computeDeviceStatus($d, Carbon $threshold): string
    {
        if (!$d->last_seen_at) {
            return 'never';
        }
        $lastSeen = Carbon::parse($d->last_seen_at);
        if ($lastSeen->lt($threshold)) {
            return 'offline';
        }
        if (empty($d->fda_ok)) {
            return 'no_permission';
        }
        return 'online';
    }

    private function computeDeviceCounts(Carbon $threshold): array
    {
        $all = DB::table('int_devices')->get(['last_seen_at', 'fda_ok']);
        $c = ['total' => 0, 'online' => 0, 'no_permission' => 0, 'offline' => 0, 'never' => 0];
        foreach ($all as $d) {
            $c['total']++;
            if (is_null($d->last_seen_at)) {
                $c['never']++;
                continue;
            }
            $ls = Carbon::parse($d->last_seen_at);
            if ($ls->lt($threshold)) {
                $c['offline']++;
            } elseif (empty($d->fda_ok)) {
                $c['no_permission']++;
            } else {
                $c['online']++;
            }
        }
        return $c;
    }

    // =====================================================================
    //  ĐỒNG BỘ DANH BẠ (phone_contacts)
    //  - syncContacts: route admin chạy 1 phát để backfill toàn bộ.
    //  - storeContact: từ dashboard, nhân viên click "Inconnu" để định danh khách.
    //  - ensurePhoneContact: gọi khi có call mới đến chưa có trong danh bạ.
    //  - upsertContactManual: gọi khi nhân viên define meeting (đã định danh khách).
    //  - buildContactMap: batch lookup cho các endpoint hiển thị.
    // =====================================================================

    /**
     * Tạo/cập nhật 1 contact từ UI dashboard. Bất kỳ nhân viên đăng nhập nào
     * cũng được phép — danh bạ là chia sẻ chung trong hệ thống.
     * Phải có ÍT NHẤT 1 trong 2: tên khách hoặc tên công ty.
     */
    public function storeContact(Request $request)
    {
        if (!Auth::check()) {
            abort(401);
        }

        $validated = $request->validate([
            'phone'        => ['required', 'string', 'max:50'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
        ]);

        $name    = trim($validated['display_name'] ?? '');
        $company = trim($validated['company_name'] ?? '');

        if ($name === '' && $company === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Veuillez saisir au moins le nom du client ou de la société.',
            ], 422);
        }

        $normalized = PhoneNormalizer::normalize($validated['phone']);
        if ($normalized === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Numéro de téléphone invalide.',
            ], 422);
        }

        $this->upsertContactManual($validated['phone'], $name ?: null, $company ?: null);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'phone'        => $normalized,
                'display_name' => $name ?: null,
                'company_name' => $company ?: null,
            ],
        ]);
    }

    public function syncContacts(Request $request)
    {
        $this->ensureAdmin();

        $stats = [
            'created_from_calls'     => 0,
            'enriched_from_users'    => 0,
            'enriched_from_companies' => 0,
        ];

        // (1) Mọi partner_phone trong int_emp_call_logs chưa có contact -> tạo.
        $rawPhones = DB::table('int_emp_call_logs')
            ->whereNotNull('partner_phone')
            ->distinct()
            ->pluck('partner_phone')
            ->all();

        foreach ($rawPhones as $raw) {
            if ($this->ensurePhoneContact($raw, /*returnCreated*/ true)) {
                $stats['created_from_calls']++;
            }
        }

        // (2) Enrich từ users.phone cho các contact CHƯA có display_name + chưa manual.
        $stats['enriched_from_users'] = $this->enrichFromTable(
            'users',
            'name',
            'display_name',
            'source_user_id',
            'user'
        );

        // (3) Enrich từ companies.phone cho các contact CHƯA có company_name + chưa manual.
        $stats['enriched_from_companies'] = $this->enrichFromTable(
            'companies',
            'name',
            'company_name',
            'source_company_id',
            'company'
        );

        return response()->json(['status' => 'success', 'stats' => $stats]);
    }

    /**
     * Đảm bảo có 1 row trong phone_contacts cho số $rawPhone.
     * Nếu chưa có -> tạo (source='unknown') và auto-fill từ users/companies.
     * Trả về true nếu vừa tạo mới, false nếu đã tồn tại hoặc số không hợp lệ.
     */
    private function ensurePhoneContact(?string $rawPhone, bool $returnCreated = false): bool
    {
        $normalized = PhoneNormalizer::normalize($rawPhone);
        if ($normalized === null) {
            return false; // Số ẩn / rác -> bỏ qua.
        }

        $exists = DB::table('phone_contacts')->where('phone', $normalized)->exists();
        if ($exists) {
            return false;
        }

        // Tạo row mới, sau đó cố gắng enrich từ users/companies.
        DB::table('phone_contacts')->insert([
            'phone'      => $normalized,
            'source'     => 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Auto-fill từ users (nếu có user với phone match).
        $user = $this->findRowByNormalizedPhone('users', $normalized);
        if ($user) {
            DB::table('phone_contacts')
                ->where('phone', $normalized)
                ->update([
                    'display_name'   => $user->name ?? null,
                    'source'         => 'user',
                    'source_user_id' => $user->id,
                    'updated_at'     => now(),
                ]);
        }

        // Auto-fill từ companies (nếu có company với phone match) — không đè lên user.
        $company = $this->findRowByNormalizedPhone('companies', $normalized);
        if ($company) {
            $current = DB::table('phone_contacts')->where('phone', $normalized)->first();
            DB::table('phone_contacts')
                ->where('phone', $normalized)
                ->update([
                    'company_name'      => $company->name ?? null,
                    // Nếu đã có thông tin từ user -> merged; nếu chưa -> company.
                    'source'            => ($current && $current->display_name) ? 'merged' : 'company',
                    'source_company_id' => $company->id,
                    'updated_at'        => now(),
                ]);
        }

        return true;
    }

    /**
     * Nhân viên đã định danh khách qua modal Define -> ghi đè/upsert với source='manual'.
     * 'manual' là nguồn mạnh nhất, không bị các sync sau ghi đè (xem enrichFromTable).
     */
    private function upsertContactManual(?string $rawPhone, ?string $name, ?string $company): void
    {
        $normalized = PhoneNormalizer::normalize($rawPhone);
        if ($normalized === null) return;

        $exists = DB::table('phone_contacts')->where('phone', $normalized)->exists();
        $fields = [
            'display_name' => $name,
            'company_name' => $company,
            'source'       => 'manual',
            'updated_at'   => now(),
        ];

        if ($exists) {
            DB::table('phone_contacts')->where('phone', $normalized)->update($fields);
        } else {
            DB::table('phone_contacts')->insert(array_merge($fields, [
                'phone'      => $normalized,
                'created_at' => now(),
            ]));
        }
    }

    /**
     * Lookup batch: nhận danh sách raw phones, trả map [normalized => contact array].
     * Dùng ở callLogsData / recallReminders / meetingReminders để gắn tên & công ty.
     */
    private function buildContactMap(array $rawPhones): array
    {
        $normalized = [];
        foreach ($rawPhones as $raw) {
            $n = PhoneNormalizer::normalize($raw);
            if ($n !== null) $normalized[$n] = true;
        }
        if (empty($normalized)) return [];

        return DB::table('phone_contacts')
            ->whereIn('phone', array_keys($normalized))
            ->get(['phone', 'display_name', 'company_name'])
            ->keyBy('phone')
            ->map(fn($r) => (array) $r)
            ->all();
    }

    /**
     * Tra cứu 1 row trong $table (users / companies) có phone match $normalized.
     * Vì column lưu raw, phải chuẩn hoá runtime -> không index được, chậm khi data lớn.
     * Đủ dùng cho scale telesales hiện tại.
     */
    private function findRowByNormalizedPhone(string $table, string $normalized)
    {
        return DB::table($table)
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone'])
            ->first(fn($row) => PhoneNormalizer::normalize($row->phone) === $normalized);
    }

    /**
     * Enrich phone_contacts từ users/companies. Bỏ qua các contact đã 'manual'
     * (nhân viên đã định danh tay -> không tự đè).
     * Trả về số lượng đã enrich.
     */
    private function enrichFromTable(string $table, string $nameCol, string $targetField, string $sourceIdField, string $sourceLabel): int
    {
        $count = 0;
        // Quét toàn bộ rows có phone -> chuẩn hoá -> nếu contact tương ứng còn trống thì enrich.
        $rows = DB::table($table)->whereNotNull('phone')->get(['id', $nameCol, 'phone']);

        foreach ($rows as $row) {
            $normalized = PhoneNormalizer::normalize($row->phone);
            if ($normalized === null) continue;

            $contact = DB::table('phone_contacts')->where('phone', $normalized)->first();
            if (!$contact || $contact->source === 'manual') {
                continue; // không có contact, hoặc đã manual -> bỏ qua.
            }
            // Đã có giá trị ở field đích -> không ghi đè (giữ data đầu tiên).
            if (!empty($contact->{$targetField})) {
                continue;
            }

            // Tính source mới: nếu contact đã có field bên kia -> merged.
            $hasOther = ($targetField === 'display_name')
                ? !empty($contact->company_name)
                : !empty($contact->display_name);
            $newSource = $hasOther ? 'merged' : $sourceLabel;

            DB::table('phone_contacts')->where('phone', $normalized)->update([
                $targetField     => $row->{$nameCol},
                $sourceIdField   => $row->id,
                'source'         => $newSource,
                'updated_at'     => now(),
            ]);
            $count++;
        }
        return $count;
    }

    public function installGuide()
    {
        return view('dashboard.install-guide', [
            'downloadUrl'  => route('telesales.install.download'),
            'downloadSize' => '30 Mo',
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
