<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\RoomBooking;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $query = Document::query();

        // 🌟 1. กำหนดสิทธิ์การมองเห็นเอกสารในหน้า Dashboard
        if (!$user->hasRole('super-admin')) {
            $query->where(function($q) use ($user) {
                // กฎข้อ 1: ทุกคนจะเห็นเอกสารที่ตัวเองเป็นคนสร้าง
                $q->where('created_by', $user->id);

                // กฎข้อ 2: ถ้าเป็นผู้บริหาร หรือสารบรรณ ให้มองเห็นเอกสารที่ส่งมาถึงด่านตัวเองและด่านถัดไป
                if ($user->hasRole('executive')) {
                    $q->orWhereIn('status', ['WAITING_NAYOK', 'APPROVED']);
                } elseif ($user->hasAnyRole(['palad', 'deputy-palad'])) {
                    $q->orWhereIn('status', ['WAITING_PALAD', 'WAITING_NAYOK', 'APPROVED']);
                } elseif ($user->hasRole('head')) {
                    $q->orWhereIn('status', ['WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'APPROVED']);
                } elseif ($user->hasRole('saraban')) {
                    // 🌟 สารบรรณ จะเห็นเอกสารที่เข้าระบบมาแล้วทั้งหมด (ยกเว้น Draft ของคนอื่น)
                    $q->orWhereIn('status', ['WAITING_ADMIN', 'WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED', 'REJECTED']);
                }
            });
        }

        // 🌟 2. คำนวณตัวเลขสถิติช่อง "กำลังรอดำเนินการ" (นับเฉพาะที่รอคนๆ นั้นเซ็น/จัดการ)
        $waitingCount = 0;
        if ($user->hasRole('super-admin')) {
            // แอดมินเห็นเอกสารที่กำลังวิ่งอยู่ในระบบทั้งหมด
            $waitingCount = Document::whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED'])->count();
        } elseif ($user->hasRole('executive')) {
            $waitingCount = Document::where('status', 'WAITING_NAYOK')->count();
        } elseif ($user->hasAnyRole(['palad', 'deputy-palad'])) {
            $waitingCount = Document::where('status', 'WAITING_PALAD')->count();
        } elseif ($user->hasRole('head')) {
            $waitingCount = Document::where('status', 'WAITING_SUPERVISOR')->count();
        } elseif ($user->hasRole('saraban')) {
            // 🌟 สารบรรณ จะขึ้นแจ้งเตือนเฉพาะด่านที่ 1 (รอรับเรื่อง) และด่านสุดท้าย (รอลงเลข)
            $waitingCount = Document::whereIn('status', ['WAITING_ADMIN', 'WAITING_NUMBERING'])->count();
        } else {
            // สำหรับพนักงานทั่วไป (นับเอกสารของตัวเองที่ยังไม่เสร็จสิ้น)
            $waitingCount = (clone $query)->whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED'])->count();
        }

        // 🌟 3. สรุปตัวเลขทั้งหมดส่งไปให้หน้าเว็บ
        $stats = [
            'total'    => (clone $query)->count(),
            'waiting'  => $waitingCount,
            'approved' => (clone $query)->where('status', 'APPROVED')->count(),
            'rejected' => (clone $query)->where('status', 'REJECTED')->count(),
        ];

        // 🌟 4. ดึงประวัติเอกสารล่าสุด 10 รายการ
        $recentDocs = $query->latest()->take(10)->get();

        // 🌟 5. ดึงข้อมูลการจองห้องเฉพาะของ "วันนี้" เรียงตามเวลาจากเช้าไปเย็น
        $todayBookings = RoomBooking::with(['room', 'creator', 'invitees']) 
            ->whereDate('start_time', Carbon::today())
            ->orderBy('start_time', 'asc')
            ->get();
            
        // 🌟 ดึงนัดประชุมที่ "ฉัน (User ที่ล็อกอินอยู่)" ถูกเชิญ
        $myMeetings = RoomBooking::with('room', 'creator')
            ->whereHas('invitees', function($q) use ($user) {
                $q->where('user_id', $user->id); // กรองเอาเฉพาะที่มีชื่อเราในนั้น
            })
            ->whereDate('start_time', '>=', Carbon::today()) // เอาตั้งแต่วันนี้เป็นต้นไป
            ->orderBy('start_time', 'asc')
            ->get();

        // 🌟 6. รวมตัวแปรทั้งหมดส่งไปพร้อมกันใน return เดียว
        return view('home', compact('stats', 'recentDocs', 'todayBookings', 'myMeetings'));
    }
}