<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Holiday; // 🌟 เพิ่มบรรทัดนี้เพื่อเรียกใช้ตารางวันหยุด
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

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
        $usedLeaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'APPROVED')
            ->whereYear('start_date', $currentYear)
            ->selectRaw('leave_type, SUM(total_days) as total_used')
            ->groupBy('leave_type')
            ->pluck('total_used', 'leave_type');

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

        // 1. ป้องกันไม่ให้คนอื่นมายกเลิก
        if ($leave->user_id !== auth()->id()) {
            return back()->with('error', 'คุณไม่มีสิทธิ์ยกเลิกใบลาของผู้อื่น');
        }

        // 2. ป้องกันไม่ให้ยกเลิกใบลาที่พิจารณาจบไปแล้ว
        if (in_array($leave->status, ['APPROVED', 'REJECTED', 'CANCELED'])) {
            return back()->with('error', 'ไม่สามารถยกเลิกใบลาที่ถูกพิจารณาไปแล้ว หรือถูกยกเลิกไปแล้วได้');
        }

        // 3. อัปเดตสถานะเป็นยกเลิก
        $leave->update([
            'status' => 'CANCELED',
            'workflow_status' => 'canceled',
        ]);

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

        LeaveRequest::create([
            'user_id'      => Auth::id(),
            'leave_type'   => $request->leave_type,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'total_days'   => $request->total_days,
            'reason'       => $request->reason,
            'contact_info' => $request->contact_info,
            'delegate_id'  => $request->delegate_id, // บันทึก ID ผู้รับมอบงาน
            
            // ตั้งสถานะเริ่มต้น
            'status'          => 'PENDING',
            // ถ้าระบุผู้รับมอบงาน ให้ไปรอด่านรับมอบงาน ถ้าไม่ระบุ ให้ข้ามไปหาธุรการเลย
            'workflow_status' => $request->delegate_id ? 'pending_delegate' : 'pending_inspector',
        ]);

        return redirect()->route('leaves.index')->with('success', 'ยื่นใบลาเรียบร้อยแล้ว ระบบกำลังเข้าสู่ขั้นตอนการอนุมัติ');
    }

    // ==========================================
    // 4. ฟังก์ชันสำหรับรับมอบหมายงาน (ด่านพิเศษ)
    // ==========================================
    public function delegateAction(Request $request, $id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $user = auth()->user();

        if ($leave->delegate_id == $user->id && $leave->workflow_status == 'pending_delegate') {
            $leave->update([
                'delegate_status' => 'accepted',
                'workflow_status' => 'pending_inspector' // ส่งไม้ต่อให้ธุรการ
            ]);
            return back()->with('success', 'คุณได้กดยอมรับการปฏิบัติหน้าที่แทนเรียบร้อยแล้ว');
        }

        return back()->with('error', 'ไม่สามารถดำเนินการได้');
    }

    // ==========================================
    // 5. รายการใบลาที่รออนุมัติ (แยกตาม 4 ด่าน)
    // ==========================================
    public function approveList()
    {
        /** @var User $user */
        $user = auth()->user();
        $query = LeaveRequest::with('user')->orderBy('created_at', 'desc');

        // 🌟 เปลี่ยนจาก officer เป็น saraban
        if ($user->hasRole('saraban')) {
            $query->where('workflow_status', 'pending_inspector'); // ด่านที่ 2: ธุรการ
        } elseif ($user->hasRole('head')) {
            $query->where('workflow_status', 'pending_head');      // ด่านที่ 3: หัวหน้าสำนัก/ผอ.
        // 🌟 รองรับสิทธิ์รองปลัดควบคู่ปลัด
        } elseif ($user->hasAnyRole(['palad', 'deputy-palad'])) {
            $query->where('workflow_status', 'pending_palad');     // ด่านที่ 4: ปลัด/รองปลัด
        } elseif ($user->hasRole('executive')) {
            $query->whereIn('workflow_status', ['pending_nayok', 'approved']); // ด่านที่ 5: นายก
        } else {
            $query->where('id', 0); // ไม่มีสิทธิ์
        }

        $leaves = $query->get();
        return view('leaves.approve_list', compact('leaves'));
    }

    // ==========================================
    // 6. ดูรายละเอียดใบลา
    // ==========================================
    public function show($id)
    {
        $leave = LeaveRequest::with(['user', 'delegate', 'inspector', 'head', 'palad', 'nayok'])->findOrFail($id);
        return view('leaves.show', compact('leave'));
    }

    // ==========================================
    // 7. จัดการการกด อนุมัติ / ตีกลับ (ด้วยรหัส PIN)
    // ==========================================
    public function reviewAction(Request $request, $id)
    {
        $request->validate([
            'is_approved'   => 'required|boolean',
            'pin'           => 'required_if:is_approved,1|nullable|string',
            'reject_reason' => 'required_if:is_approved,0|nullable|string',
        ]);

        $leave = LeaveRequest::findOrFail($id);

        /** @var User $user */
        $user = auth()->user();

        // กรณีตีกลับไม่อนุมัติ
        if (!$request->is_approved) {
            $leave->update([
                'status'          => 'REJECTED',
                'workflow_status' => 'rejected',
                'reject_reason'   => "[$user->position] : " . $request->reject_reason
            ]);
            return redirect()->route('leaves.approve_list')->with('error', 'คุณได้ทำการตีกลับใบลาเรียบร้อยแล้ว');
        }

        // ตรวจสอบ PIN ถ้ายืนยันอนุมัติ
        if (!Hash::check($request->pin, $user->pin)) {
            return back()->with('error', 'รหัส PIN ไม่ถูกต้อง กรุณาลองอีกครั้ง');
        }

        // บันทึกการอนุมัติตามด่านที่ล็อกอินอยู่
        // 🌟 เปลี่ยนจาก officer เป็น saraban
        if ($user->hasRole('saraban') && $leave->workflow_status === 'pending_inspector') {
            $leave->update([
                'workflow_status'     => 'pending_head', // ส่งต่อให้หัวหน้า
                'inspector_id'        => $user->id,
                'inspector_status'    => 'approved',
                'inspector_signature' => $user->signature,
                'inspector_at'        => now(),
            ]);
        } elseif ($user->hasRole('head') && $leave->workflow_status === 'pending_head') {
            $leave->update([
                'workflow_status' => 'pending_palad', // ส่งต่อให้ปลัด
                'head_id'         => $user->id,
                'head_status'     => 'approved',
                'head_signature'  => $user->signature,
                'head_at'         => now(),
            ]);
        // 🌟 รองรับสิทธิ์รองปลัดควบคู่ปลัด
        } elseif ($user->hasAnyRole(['palad', 'deputy-palad']) && $leave->workflow_status === 'pending_palad') {
            $leave->update([
                'workflow_status' => 'pending_nayok', // ส่งต่อให้นายก
                'palad_id'        => $user->id,
                'palad_status'    => 'approved',
                'palad_signature' => $user->signature,
                'palad_at'        => now(),
            ]);
        } elseif ($user->hasRole('executive') && $leave->workflow_status === 'pending_nayok') {
            $leave->update([
                'status'          => 'APPROVED', // สิ้นสุดกระบวนการ!
                'workflow_status' => 'approved',
                'nayok_id'        => $user->id,
                'nayok_status'    => 'approved',
                'nayok_signature' => $user->signature,
                'nayok_at'        => now(),
            ]);
        }

        return redirect()->route('leaves.approve_list')->with('success', 'ลงนามตรวจสอบ/อนุมัติใบลาเรียบร้อยแล้ว');
    }
}