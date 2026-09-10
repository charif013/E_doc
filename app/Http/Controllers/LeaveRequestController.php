<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Holiday; // 🌟 เพิ่มบรรทัดนี้เพื่อเรียกใช้ตารางวันหยุด
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Services\LeaveWorkflowService;
use App\Services\NotificationDispatcher;
use App\Http\Requests\ReviewLeaveRequest;
use Illuminate\Support\Facades\Schema;

class LeaveRequestController extends Controller
{
    // ==========================================
    // 1. ฟังก์ชันเปิดหน้าฟอร์มกรอกใบลา
    // ==========================================
    public function create()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $currentYear = now()->year;

        // ดึงข้อมูลการลาที่เคยอนุมัติไปแล้วในปีนี้
        $usedLeaves = $this->approvedLeaveUsage($user->id, $currentYear);

        // คัดแยกประเภทพนักงาน
        $pos = $user->position ?? '';
        $generalPositions = ['พนักงานขับรถยนต์', 'คนงาน', 'ภารโรง'];
        $isGeneralWorker = in_array($pos, $generalPositions) || str_contains($pos, 'จ้างทั่วไป');

        $limits = [];

        // กำหนดสิทธิพื้นฐาน
        if ($isGeneralWorker) {
            $limits = [
                'ลาป่วย' => 15,
                'ลาพักผ่อน' => 10,
                'ลาเกณฑ์ทหาร/เตรียมพล' => 30,
            ];
        } else {
            $limits = [
                'ลาป่วย' => 60,
                'ลากิจส่วนตัว' => 45,
                'ลาพักผ่อน' => 10,
                'ลาอุปสมบท/ประกอบพิธีฮัจย์' => 120,
                'ลาเกณฑ์ทหาร/เตรียมพล' => 60,
            ];
        }

        // เช็คเพศ
        $gender = $user->gender ?? 'unknown';
        if ($gender === 'unknown') {
            if (str_starts_with(trim($user->name), 'นาย')) {
                $gender = 'male';
            } elseif (str_starts_with(trim($user->name), 'นาง') || str_starts_with(trim($user->name), 'น.ส.')) {
                $gender = 'female';
            }
        }

        if ($gender === 'female') {
            $limits['ลาคลอดบุตร'] = 90;
        } elseif ($gender === 'male') {
            if (!$isGeneralWorker) {
                $limits['ลาไปช่วยเหลือภริยาที่คลอดบุตร'] = 15;
            }
        } else {
            $limits['ลาคลอดบุตร'] = 90;
            if (!$isGeneralWorker) {
                $limits['ลาไปช่วยเหลือภริยาที่คลอดบุตร'] = 15;
            }
        }

        // คำนวณยอดคงเหลือ
        $balances = [];
        foreach ($limits as $type => $limit) {
            $used = $usedLeaves[$type] ?? 0;
            $balances[$type] = [
                'used'      => $used,
                'limit'     => $limit,
                'remaining' => max(0, $limit - $used),
                'percent'   => $limit > 0 ? ($used / $limit) * 100 : 0
            ];
        }

        // ดึงรายชื่อพนักงานทั้งหมดเพื่อใส่ใน Dropdown "ผู้รับมอบงาน"
        $users = User::where('id', '!=', $user->id)->get();

        // 🌟 ดึงข้อมูลวันหยุดราชการจากตาราง holidays ส่งไปให้ Javascript ใช้คำนวณ
        $publicHolidays = Holiday::pluck('holiday_date')->toArray();

        // 🌟 อย่าลืมเพิ่ม 'publicHolidays' เข้าไปใน compact ด้วยครับ
        return view('leaves.create', compact('balances', 'users', 'publicHolidays'));
    }

    // ==========================================
    // ฟังก์ชันยกเลิกใบลา (ทำได้เฉพาะเจ้าของใบลา)
    // ==========================================
    public function cancel($id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $this->authorize('cancel', $leave);

        // 2. ป้องกันไม่ให้ยกเลิกใบลาที่พิจารณาจบไปแล้ว
        if (in_array($leave->status, ['APPROVED', 'REJECTED', 'CANCELED'])) {
            return back()->with('error', 'ไม่สามารถยกเลิกใบลาที่ถูกพิจารณาไปแล้ว หรือถูกยกเลิกไปแล้วได้');
        }

        app(LeaveWorkflowService::class)->cancel($leave, auth()->user());

        if ($leave->delegate) {
            app(NotificationDispatcher::class)->toUser(
                $leave->delegate,
                "❌ ใบลาที่มอบหมายให้คุณปฏิบัติงานแทนถูกยกเลิกแล้ว\nผู้ลา: " . ($leave->user?->name ?: '-') . "\nประเภท: {$leave->leave_type}"
            );
        }

        return back()->with('success', 'ยกเลิกใบลาเรียบร้อยแล้ว');
    }

    // ==========================================
    //  ฟังก์ชันดูประวัติการลาของตัวเอง
    // ==========================================
    public function index()
    {
        $leaves = LeaveRequest::where('user_id', auth()->id())
                    ->orderBy('created_at', 'desc')
                    ->get();

        return view('leaves.index', compact('leaves'));
    }

    // ==========================================
    // 3. ฟังก์ชันบันทึกข้อมูลใบลาลงฐานข้อมูล
    // ==========================================
    public function store(Request $request)
    {
        
        $request->validate([
            'leave_type'   => 'required|string',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'total_days'   => 'required|numeric|min:0.5',
            'reason'       => 'required|string',
            'contact_info' => 'required|string',
            'delegate_id'  => 'nullable|exists:users,id', // เพิ่มตรวจสอบผู้รับมอบงาน
        ]);

        $leave = LeaveRequest::create([
            'user_id'      => Auth::id(),
            'leave_type'   => $request->leave_type,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'total_days'   => $request->total_days,
            'reason'       => $request->reason,
            'contact_info' => $request->contact_info,
            'delegate_user_id' => $request->delegate_id,
        ]);

        $this->notifyNextLeaveStage($leave);

        return redirect()->route('leaves.index')->with('success', 'ยื่นใบลาเรียบร้อยแล้ว ระบบกำลังเข้าสู่ขั้นตอนการอนุมัติ');
    }

    // ==========================================
    // 4. ฟังก์ชันสำหรับรับมอบหมายงาน (ด่านพิเศษ)
    // ==========================================
    public function delegateAction(Request $request, $id)
    {
        $data = $request->validate([
            'action' => ['required', 'in:accept,decline'],
            'decline_reason' => ['required_if:action,decline', 'nullable', 'string', 'max:1000'],
        ]);
        $leave = LeaveRequest::findOrFail($id);
        $user = auth()->user();

        app(LeaveWorkflowService::class)->respondToDelegation(
            $leave, $user, $data['action'], $data['decline_reason'] ?? null
        );

        return back()->with(
            'success',
            $data['action'] === 'accept'
                ? 'ยอมรับการปฏิบัติหน้าที่แทนเรียบร้อยแล้ว'
                : 'ปฏิเสธคำขอและแจ้งผู้ยื่นให้เลือกผู้รับมอบงานคนใหม่แล้ว'
        );
    }

    public function reassignDelegate(Request $request, $id)
    {
        $data = $request->validate([
            'delegate_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.auth()->id()],
        ]);
        $leave = LeaveRequest::findOrFail($id);
        $delegate = User::findOrFail($data['delegate_id']);

        app(LeaveWorkflowService::class)->reassignDelegate($leave, $request->user(), $delegate);

        return back()->with('success', 'เปลี่ยนผู้รับมอบงานและส่งคำขอใหม่เรียบร้อยแล้ว');
    }

    // ==========================================
    // 5. รายการใบลาที่รออนุมัติ (แยกตาม 4 ด่าน)
    // ==========================================
    public function approveList()
    {
        /** @var User $user */
        $user = auth()->user();
        $leaves = LeaveRequest::with('user')
            ->pendingReviewFor($user)
            ->orderBy('created_at', 'desc')
            ->get();
        return view('leaves.approve_list', compact('leaves'));
    }

    // ==========================================
    // 6. ดูรายละเอียดใบลา
    // ==========================================
    public function show($id)
    {
        $leave = LeaveRequest::with([
            'user', 'delegate', 'type', 'numberAllocation',
            'workflow.steps.evidence.actor',
        ])->findOrFail($id);
        $this->authorize('view', $leave);

        $delegateCandidates = $leave->user_id === auth()->id()
            ? User::whereKeyNot(auth()->id())->orderBy('name')->get()
            : collect();

        $usedLeaves = $this->approvedLeaveUsage((int) $leave->user_id, now()->year);
        $sickUsed = (float) ($usedLeaves->get('ลาป่วย') ?? 0);
        $personalUsed = (float) ($usedLeaves->get('ลากิจส่วนตัว') ?? 0);
        $vacationUsed = (float) ($usedLeaves->get('ลาพักผ่อน') ?? 0);

        return view('leaves.show', compact(
            'leave', 'delegateCandidates', 'sickUsed', 'personalUsed', 'vacationUsed'
        ));
    }

    // ==========================================
    // 7. จัดการการกด อนุมัติ / ตีกลับ (ด้วยรหัส PIN)
    // ==========================================
    public function reviewAction(ReviewLeaveRequest $request, $id)
    {
        $leave = LeaveRequest::findOrFail($id);

        /** @var User $user */
        $user = auth()->user();

        $this->authorize('review', $leave);

        // กรณีตีกลับไม่อนุมัติ
        if (!$request->is_approved) {
            app(LeaveWorkflowService::class)->reject($leave, $user, $request->reject_reason);
            return redirect()->route('leaves.approve_list')->with('error', 'คุณได้ทำการตีกลับใบลาเรียบร้อยแล้ว');
        }

        // ตรวจสอบ PIN ถ้ายืนยันอนุมัติ
        if (!Hash::check($request->pin, $user->pin)) {
            return back()->with('error', 'รหัส PIN ไม่ถูกต้อง กรุณาลองอีกครั้ง');
        }

        app(LeaveWorkflowService::class)->approve(
            $leave,
            $user,
            $request->filled('running_number') ? (int) $request->running_number : null
        );

        return redirect()->route('leaves.approve_list')->with('success', 'ลงนามตรวจสอบ/อนุมัติใบลาเรียบร้อยแล้ว');
    }

    private function canReviewStage(User $user, LeaveRequest $leave): bool
    {
        return app(LeaveWorkflowService::class)->canReview($user, $leave);
    }

    private function notifyNextLeaveStage(LeaveRequest $leave): void
    {
        app(LeaveWorkflowService::class)->notifyNext($leave);
    }

    private function approvedLeaveUsage(int $userId, int $year)
    {
        $connection = (new LeaveRequest)->getConnectionName() ?: config('database.default');
        $query = LeaveRequest::where('leave_requests.user_id', $userId)
            ->atCanonicalStatus('APPROVED')
            ->whereYear('leave_requests.start_date', $year);

        // Compatibility path for legacy databases while rollback remains supported.
        if (! Schema::connection($connection)->hasTable('leave_types')
            || ! Schema::connection($connection)->hasColumn('leave_requests', 'leave_type_id')) {
            return $query
                ->selectRaw('leave_requests.leave_type AS leave_type_name, SUM(leave_requests.total_days) AS total_used')
                ->groupBy('leave_requests.leave_type')
                ->pluck('total_used', 'leave_type_name');
        }

        return $query
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->selectRaw('leave_types.name AS leave_type_name, SUM(leave_requests.total_days) AS total_used')
            ->groupBy('leave_types.name')
            ->pluck('total_used', 'leave_type_name');
    }
}
