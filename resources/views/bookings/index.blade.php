@extends('layouts.app')

@section('content')
<!-- โหลด Library ปฏิทิน (FullCalendar) -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>

<div class="container-fluid px-4 py-4">
    
    {{-- 🌟 แจ้งเตือนเมื่อจองสำเร็จ หรือ ยกเลิกสำเร็จ --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-4 border-success mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2 fs-4"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- 🌟 แจ้งเตือน Error (กรณีไม่มีสิทธิ์ลบ) --}}
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-4 border-danger mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2 fs-4"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- ส่วนหัว --}}
        <div class="col-12 mb-4 d-flex justify-content-between align-items-center border-bottom pb-3">
            <h4 class="fw-bold text-dark mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i> ปฏิทินการจองห้องประชุม</h4>
            <a href="{{ route('bookings.create') }}" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4">
                <i class="fas fa-plus me-1"></i> จองห้อง / นัดประชุม
            </a>
        </div>

        {{-- 🌟 ฝั่งซ้าย: กล่องแสดงปฏิทิน --}}
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <div id='calendar'></div>
                </div>
            </div>
            
            {{-- คำอธิบายสี --}}
            <div class="d-flex justify-content-center gap-4 mt-3">
                <span class="small fw-bold"><i class="fas fa-circle text-primary me-1"></i> นัดประชุม</span>
                <span class="small fw-bold"><i class="fas fa-circle text-success me-1"></i> ใช้งานทั่วไป</span>
                <span class="small fw-bold"><i class="fas fa-circle text-danger me-1"></i> ปิดปรับปรุง</span>
            </div>
        </div>

        {{-- 🌟 ฝั่งขวา: รายการจองล่าสุดแบบ List พร้อมปุ่มยกเลิก --}}
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-header bg-white border-0 py-3 pb-0">
                    <h5 class="mb-0 fw-bold text-secondary">รายการจองล่าสุด</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush mt-2">
                        @forelse($bookings->take(6) as $booking)
                            <div class="list-group-item border-bottom py-3 px-4">
                                <div class="d-flex w-100 justify-content-between align-items-start mb-1">
                                    
                                    {{-- 🌟 เช็คสิทธิ์การเห็นหัวข้อสำหรับ List ขวามือ --}}
                                    @php
                                        $canSeeTitle = false;
                                        if ($booking->booking_type !== 'meeting' || Auth::user()->hasRole('super-admin')) {
                                            $canSeeTitle = true;
                                        } elseif ($booking->created_by === Auth::id()) {
                                            $canSeeTitle = true;
                                        } elseif ($booking->invitees && $booking->invitees->contains('id', Auth::id())) {
                                            $canSeeTitle = true;
                                        }
                                    @endphp

                                    @if($canSeeTitle)
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 65%;">{{ $booking->title }}</h6>
                                    @else
                                        <h6 class="mb-0 fw-bold text-muted text-truncate fst-italic" style="max-width: 65%;">
                                            <i class="fas fa-lock fa-sm me-1 opacity-50"></i> --------
                                        </h6>
                                    @endif
                                    
                                    {{-- ป้าย Tag สี --}}
                                    @if($booking->booking_type == 'meeting')
                                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">นัดประชุม</span>
                                    @elseif($booking->booking_type == 'general_use')
                                        <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ทั่วไป</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ปรับปรุง</span>
                                    @endif
                                </div>
                                
                                <p class="mb-1 small text-secondary mt-2">
                                    <i class="fas fa-door-open me-1"></i> <strong>{{ $booking->room->name }}</strong><br>
                                    <i class="far fa-calendar-check me-1"></i> {{ \Carbon\Carbon::parse($booking->start_time)->addYears(543)->format('d/m/Y') }} <br>
                                    <i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }} น. - {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }} น.
                                </p>

                                {{-- ส่วนล่าง: ชื่อผู้จอง และปุ่มยกเลิก --}}
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted" style="font-size: 0.75rem;">ผู้จอง: {{ $booking->creator->name ?? 'ไม่ทราบชื่อ' }}</small>
                                    
                                    {{-- 🌟 ปุ่มยกเลิกการจอง (โชว์เฉพาะเจ้าของ หรือ Admin) --}}
                                    @if($booking->created_by === Auth::id() || Auth::user()->hasRole('super-admin'))
                                        <form action="{{ route('bookings.destroy', $booking->id) }}" method="POST" onsubmit="return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะยกเลิกการจองนี้? \n(การคืนคิวห้องจะไม่สามารถกู้คืนได้)');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1" style="font-size: 0.7rem;">
                                                <i class="fas fa-trash-alt me-1"></i> ยกเลิก
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-5 text-center text-muted">
                                <i class="fas fa-calendar-times fs-1 opacity-25 mb-3"></i><br>
                                ยังไม่มีการจองห้องประชุม
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 🌟 เตรียมข้อมูลที่ปลอดภัยส่งให้ Javascript --}}
@php
    $userId = Auth::id();
    $isAdmin = Auth::user()->hasRole('super-admin');

    $safeBookings = $bookings->map(function($booking) use ($userId, $isAdmin) {
        $canSee = false;
        if ($booking->booking_type !== 'meeting' || $isAdmin) {
            $canSee = true;
        } elseif ($booking->created_by === $userId) {
            $canSee = true;
        } elseif ($booking->invitees && $booking->invitees->contains('id', $userId)) {
            $canSee = true;
        }

        $displayTitle = $canSee ? $booking->title : '🔒 --------';
        $displayDesc = $canSee ? $booking->description : '-';

        return [
            'id' => $booking->id,
            'title' => $displayTitle,
            'booking_type' => $booking->booking_type,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'room' => ['name' => $booking->room->name],
            'creator' => $booking->creator ? ['name' => $booking->creator->name] : ['name' => '-'],
            'description' => $displayDesc
        ];
    });
@endphp

{{-- 🌟 Script ควบคุมปฏิทิน --}}
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var bookings = @json($safeBookings);
    
    var events = bookings.map(function(booking) {
        let eventColor = '#10b981'; 
        if(booking.booking_type === 'meeting') eventColor = '#3b82f6'; 
        if(booking.booking_type === 'maintenance') eventColor = '#ef4444'; 

        return {
            title: booking.title + ' (' + booking.room.name + ')',
            start: booking.start_time,
            end: booking.end_time,
            color: eventColor,
            extendedProps: {
                room: booking.room.name,
                creator: booking.creator.name,
                description: booking.description
            }
        };
    });

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth', 
      locale: 'th', 
      height: 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay' 
      },
      events: events, 
      eventClick: function(info) {
          let msg = "หัวข้อ: " + info.event.title.replace(/ \(.+\)$/, '') + "\n";
          msg += "ห้อง: " + info.event.extendedProps.room + "\n";
          msg += "ผู้จอง: " + info.event.extendedProps.creator + "\n";
          msg += "เริ่ม: " + info.event.start.toLocaleString('th-TH') + "\n";
          if(info.event.end) {
              msg += "สิ้นสุด: " + info.event.end.toLocaleString('th-TH');
          }
          alert(msg); 
      }
    });

    calendar.render();
  });
</script>

<style>
    .fc-toolbar-title { font-size: 1.25rem !important; font-weight: bold; color: #333; }
    .fc-button-primary { background-color: #f8f9fa !important; color: #333 !important; border-color: #ddd !important; }
    .fc-button-primary:hover { background-color: #e9ecef !important; }
    .fc-button-active { background-color: #e2e8f0 !important; font-weight: bold; }
    .fc-event { cursor: pointer; padding: 2px 4px; border: none !important; border-radius: 4px;}
</style>
@endsection