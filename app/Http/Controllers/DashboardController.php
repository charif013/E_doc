<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function executiveDashboard(Request $request)
    {
        // 1. กำหนดเดือนที่เลือก (default เป็นเดือนปัจจุบัน)
        $month = $request->input('month', Carbon::now()->format('m'));
        $year = $request->input('year', Carbon::now()->format('Y'));

        // 2. ดึงข้อมูลสรุปตามช่วงเวลาที่เลือก
        $stats = [
            'total'     => Document::whereMonth('created_at', $month)->whereYear('created_at', $year)->count(),
            'waiting'   => Document::whereMonth('created_at', $month)->whereYear('created_at', $year)
                                    ->whereIn('status', ['WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK'])->count(),
            'approved'  => Document::whereMonth('created_at', $month)->whereYear('created_at', $year)
                                    ->where('status', 'APPROVED')->count(),
            'urgent'    => Document::whereMonth('created_at', $month)->whereYear('created_at', $year)
                                    ->whereIn('doc_speed', ['ด่วน', 'ด่วนมาก', 'ด่วนที่สุด'])
                                    ->where('status', '!=', 'APPROVED')->count(),
        ];

        // 3. สรุปตามกอง (Bar Chart Data)
        $byDept = Document::whereMonth('created_at', $month)->whereYear('created_at', $year)
            ->select('assigned_to', DB::raw('count(*) as total'))
            ->groupBy('assigned_to')
            ->get();

        // 4. รายการเอกสารด่วนที่สุดที่ยังค้างอยู่ (ไม่จำกัดช่วงเวลา เพื่อให้ท่านนายกฯ เห็นงานค้างเก่า)
        $urgentDocs = Document::whereIn('doc_speed', ['ด่วนมาก', 'ด่วนที่สุด'])
            ->where('status', '!=', 'APPROVED')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard.executive', compact('stats', 'byDept', 'urgentDocs', 'month', 'year'));
    }
}