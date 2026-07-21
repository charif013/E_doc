<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RoomBookingController extends Controller
{
    // หน้าปฏิทิน / รายการการจอง
    public function index()
    {
        // ดึงข้อมูลการจองทั้งหมดมาแสดง พร้อมข้อมูลห้องและคนจอง
        $bookings = RoomBooking::with(['room', 'creator', 'invitees'])->latest()->get();
        return view('bookings.index', compact('bookings'));
    }

    // หน้าฟอร์มสำหรับสร้างการจอง / นัดประชุม
    public function create()
    {
        // ดึงรายชื่อห้องที่เปิดใช้งานอยู่
        $rooms = Room::where('status', 'active')->get();
        // ดึงรายชื่อพนักงานทั้งหมด (ยกเว้นตัวเอง) เพื่อเอาไว้เชิญเข้าประชุม
        $users = User::where('id', '!=', Auth::id())->get(); 
        
        return view('bookings.create', compact('rooms', 'users'));
    }

    // ฟังก์ชันรับข้อมูลจากฟอร์มและบันทึกลงฐานข้อมูล
    public function store(Request $request)
    {
        // 1. ตรวจสอบความถูกต้องของข้อมูลที่ส่งมา
        $request->validate([
            'title' => 'required|string|max:255',
            'room_id' => 'required|exists:rooms,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'booking_type' => 'required|in:meeting,general_use,maintenance',
            'invitees' => 'nullable|array', // รายชื่อคนถูกเชิญ (มีหรือไม่มีก็ได้)
            'invitees.*' => 'exists:users,id'
        ]);

        // 🌟 2. ด่านตรวจสำคัญ: เช็คว่าห้องว่างไหม? (ห้ามเวลาทับซ้อนกัน)
        $isRoomBooked = RoomBooking::where('room_id', $request->room_id)
            ->where(function ($query) use ($request) {
                $query->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>', $request->start_time);
            })->exists();

        if ($isRoomBooked) {
            return back()->with('error', '❌ ห้องประชุมนี้ถูกจองแล้วในช่วงเวลาดังกล่าว กรุณาเลือกเวลาหรือห้องอื่นครับ')
                         ->withInput();
        }

        // 3. ถ้าห้องว่าง -> บันทึกการจอง
        $booking = new RoomBooking();
        $booking->title = $request->title;
        $booking->booking_type = $request->booking_type;
        $booking->description = $request->description;
        $booking->room_id = $request->room_id;
        $booking->start_time = $request->start_time;
        $booking->end_time = $request->end_time;
        $booking->created_by = Auth::id();
        $booking->save();

        // 🌟 4. ถ้าเป็นการ "นัดประชุม" และมีการเลือกคนเชิญ -> บันทึกรายชื่อคนลงตาราง pivot
        if ($request->booking_type === 'meeting' && $request->has('invitees')) {
            // แก้จาก attendees() เป็น invitees() ให้ตรงกับใน Model ครับ
            $booking->invitees()->attach($request->invitees); 
        }

        return redirect()->route('bookings.index')->with('success', '✅ บันทึกการจองห้อง / นัดประชุมเรียบร้อยแล้ว!');
    }

   // ฟังก์ชันสำหรับยกเลิกการจองห้อง
    public function destroy($id)
    {
        $booking = RoomBooking::findOrFail($id);

        // 🌟 ดึงข้อมูล User และบอก VS Code ว่ามันคือคลาส App\Models\User
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // ด่านตรวจความปลอดภัย: เช็คสิทธิ์คนลบ
        // อนุญาตเฉพาะ "เจ้าของการจอง" หรือ "ผู้ดูแลระบบ (super-admin)" เท่านั้น
        if ($booking->created_by !== $user->id && !$user->hasRole('super-admin')) {
            return back()->with('error', '❌ ไม่อนุญาต! คุณสามารถยกเลิกได้เฉพาะรายการที่คุณเป็นผู้จองเท่านั้นครับ');
        }

        // ถ้าระบบจำคนเชิญไว้ (ใน Pivot Table) มันจะถูกลบอัตโนมัติตามที่เราตั้งค่า Cascade ไว้ตอนสร้างตารางครับ
        $booking->delete();

        return back()->with('success', '🗑️ ยกเลิกการจองห้อง และคืนคิวเรียบร้อยแล้วครับ!');
    }
    
}