<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Holiday;
use Illuminate\Support\Facades\Artisan;

class HolidayController extends Controller
{
    // เปิดหน้าจัดการวันหยุด (ดึงข้อมูลของปีปัจจุบันเป็นต้นไป)
    public function index()
    {
        $currentYear = now()->year;
        
        $holidays = Holiday::whereYear('holiday_date', '>=', $currentYear)
            ->orderBy('holiday_date', 'asc')
            ->get();

        return view('holidays.index', compact('holidays'));
    }

    // บันทึกวันหยุดพิเศษลง Database
    public function store(Request $request)
    {
        $request->validate([
            'holiday_date' => 'required|date|unique:holidays,holiday_date',
            'name'         => 'required|string|max:255',
        ], [
            'holiday_date.unique' => 'วันนี้ถูกตั้งเป็นวันหยุดในระบบไปแล้วครับ'
        ]);

        Holiday::create([
            'holiday_date' => $request->holiday_date,
            'name'         => $request->name,
            'source'       => 'manual' // 🌟 ระบุว่ามาจากการเพิ่มเอง (ไม่ใช่ API)
        ]);

        return back()->with('success', 'เพิ่มวันหยุดกรณีพิเศษเรียบร้อยแล้ว');
    }

    // ลบวันหยุด
    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();

        return back()->with('success', 'ลบวันหยุดออกจากระบบเรียบร้อยแล้ว');
    }

    //  ฟังก์ชันสำหรับดึง API
    public function sync(Request $request)
    {
        try {
            // ดึงปีปัจจุบัน หรือปีที่ส่งมา
            $year = $request->input('year', now()->year);
            
            // สั่งรัน php artisan holidays:sync ผ่านโค้ด
            Artisan::call('holidays:sync', ['year' => $year]);
            
            return back()->with('success', 'ซิงค์ข้อมูลวันหยุดจาก Google Calendar ปี ' . $year . ' สำเร็จแล้ว!');
        } catch (\Exception $e) {
            return back()->with('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ API: ' . $e->getMessage());
        }
    }

}