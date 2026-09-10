<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Models\Document;
use App\Models\Holiday;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationDispatcher;
use App\Services\RoomBookingService;
use App\Http\Requests\StoreRoomBookingRequest;
use Illuminate\Validation\Rule;

class RoomBookingController extends Controller
{
    // หน้าปฏิทิน / รายการการจอง
    public function index()
    {
        // ดึงข้อมูลการจองทั้งหมดมาแสดง พร้อมข้อมูลห้องและคนจอง
        $bookings = RoomBooking::with(['room', 'creator', 'invitees', 'document'])->latest()->get();
        $holidays = Holiday::orderBy('holiday_date')->get(['holiday_date', 'name']);
        return view('bookings.index', compact('bookings', 'holidays'));
    }

    // หน้าฟอร์มสำหรับสร้างการจอง / นัดประชุม
    public function create()
    {
        // ดึงรายชื่อห้องที่เปิดใช้งานอยู่
        $rooms = Room::whereIn('status', ['active', 'ACTIVE'])->get();
        // ดึงรายชื่อพนักงานทั้งหมด (ยกเว้นตัวเอง) เพื่อเอาไว้เชิญเข้าประชุม
        $users = User::where('id', '!=', Auth::id())->get(); 

        $assignedDocuments = $this->assignedDocumentsFor(Auth::user())->get();
        $holidays = Holiday::orderBy('holiday_date')->get(['holiday_date', 'name']);

        return view('bookings.create', compact('rooms', 'users', 'assignedDocuments', 'holidays'));
    }

    // ฟังก์ชันรับข้อมูลจากฟอร์มและบันทึกลงฐานข้อมูล
    public function store(StoreRoomBookingRequest $request, RoomBookingService $bookings, NotificationDispatcher $notifications)
    {
        // 1. ตรวจสอบความถูกต้องของข้อมูลที่ส่งมา
        $data = $request->validated();

        if ($request->filled('document_id')) {
            $canAttach = $this->assignedDocumentsFor(Auth::user())
                ->whereKey($request->document_id)
                ->exists();

            if (!$canAttach) {
                return back()->withErrors(['document_id' => 'แนบได้เฉพาะเอกสารที่คุณได้รับมอบหมายเท่านั้น'])->withInput();
            }
        }

        $booking = $bookings->create($data, Auth::user());

        $booking->load(['room', 'creator', 'invitees']);
        if ($booking->booking_type === 'meeting') {
            $message = "📅 ขอเชิญเข้าร่วมประชุม\nเรื่อง: {$booking->title}\nห้อง: " . $booking->room_display_name
                . "\nเริ่ม: " . $booking->start_time->format('d/m/Y H:i')
                . "\nสิ้นสุด: " . $booking->end_time->format('d/m/Y H:i')
                . "\nผู้เชิญ: " . ($booking->creator?->name ?: '-')
                . "\n\nตอบรับและดูรายละเอียดการประชุม:\n" . route('bookings.show', $booking);
            $notifications->toUsers($booking->invitees, $message);
        }

        return redirect()->route('bookings.index')->with('success', '✅ บันทึกการจองห้อง / นัดประชุมเรียบร้อยแล้ว!');
    }

    public function show(RoomBooking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load(['room', 'creator', 'invitees', 'document']);
        $currentInvitation = $booking->invitees->firstWhere('id', Auth::id());

        return view('bookings.show', compact('booking', 'currentInvitation'));
    }

    public function respond(Request $request, RoomBooking $booking, NotificationDispatcher $notifications)
    {
        $data = $request->validate([
            'response' => ['required', Rule::in(['accepted', 'declined'])],
        ]);

        $this->authorize('respond', $booking);

        $responseStatus = strtoupper($data['response']);
        $booking->invitees()->updateExistingPivot($request->user()->id, [
            'status' => $responseStatus,
            'responded_at' => now(),
            'updated_at' => now(),
        ]);

        $booking->load(['room', 'creator']);
        $responseLabel = $responseStatus === 'ACCEPTED' ? 'ตอบรับเข้าร่วม' : 'ไม่สะดวกเข้าร่วม';
        $notifications->toUser(
            $booking->creator,
            "📩 มีการตอบรับคำเชิญประชุม\n{$request->user()->name}: {$responseLabel}\nเรื่อง: {$booking->title}\nห้อง: {$booking->room_display_name}\n\nดูผลตอบรับ:\n" . route('bookings.show', $booking)
        );

        return redirect()->route('bookings.show', $booking)
            ->with('success', $responseStatus === 'ACCEPTED'
                ? 'ตอบรับเข้าร่วมการประชุมเรียบร้อยแล้ว'
                : 'บันทึกว่าไม่สะดวกเข้าร่วมเรียบร้อยแล้ว');
    }

    public function rooms()
    {
        $rooms = Room::orderByDesc('status')->orderBy('name')->get();
        return view('bookings.rooms', compact('rooms'));
    }

    public function storeRoom(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:rooms,name',
            'capacity' => 'nullable|integer|min:1|max:1000',
            'status' => 'required|in:active,inactive',
        ]);

        Room::create($data);
        return back()->with('success', 'เพิ่มห้องประชุมเรียบร้อยแล้ว');
    }

    public function updateRoom(Request $request, Room $room)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:rooms,name,' . $room->id,
            'capacity' => 'nullable|integer|min:1|max:1000',
            'status' => 'required|in:active,inactive',
        ]);

        $room->update($data);
        return back()->with('success', 'แก้ไขห้องประชุมเรียบร้อยแล้ว');
    }

    public function destroyRoom(Room $room)
    {
        $room->update(['status' => 'inactive']);
        $room->delete();

        return back()->with('success', 'ปิดใช้งานและเก็บห้องประชุมไว้ในประวัติเรียบร้อยแล้ว');
    }

    private function assignedDocumentsFor(User $user)
    {
        if (config('edoc.v2.document_reads')) {
            $names = array_values(array_filter([$user->department, $user->division, $user->work_unit]));
            return \App\Models\V2\Document::whereIn('status', ['APPROVED', 'COMPLETED'])
                ->where(function ($query) use ($user, $names) {
                    $query->where('created_by', $user->id)
                        ->orWhereHas('assignments', fn ($assignments) => $assignments->where('assigned_user_id', $user->id));
                    if ($user->hasRole('head')) {
                        $query->orWhereHas('assignments.unit', fn ($units) => $units->whereIn('name', $names));
                    }
                })->orderByDesc('created_at');
        }
        return Document::where('status', 'APPROVED')
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhere('assigned_user_id', $user->id);

                if ($user->hasRole('head')) {
                    $query->orWhere(function ($headQuery) use ($user) {
                        $headQuery->where('assigned_to', $user->department)
                            ->whereNull('assigned_user_id');
                    });
                }
            })
            ->orderByDesc('assigned_at');
    }

   // ฟังก์ชันสำหรับยกเลิกการจองห้อง
    public function destroy($id, NotificationDispatcher $notifications, RoomBookingService $bookings)
    {
        $booking = RoomBooking::findOrFail($id);

        // 🌟 ดึงข้อมูล User และบอก VS Code ว่ามันคือคลาส App\Models\User
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->authorize('delete', $booking);

        if ($booking->status === 'CANCELED') {
            return back()->with('error', 'รายการนี้ถูกยกเลิกไปแล้ว');
        }

        $booking->load(['room', 'creator', 'invitees']);
        $bookings->cancel($booking, $user);

        if ($booking->booking_type === 'meeting') {
            $message = "❌ ยกเลิกการประชุม\nเรื่อง: {$booking->title}\nห้อง: " . $booking->room_display_name
                . "\nกำหนดเดิม: " . $booking->start_time->format('d/m/Y H:i')
                . "\nผู้ยกเลิก: {$user->name}";
            $notifications->toUsers($booking->invitees, $message);
        }

        return back()->with('success', '🗑️ ยกเลิกการจองห้องและคืนคิวเรียบร้อยแล้ว โดยยังเก็บประวัติไว้ตรวจสอบ');
    }
    
}
