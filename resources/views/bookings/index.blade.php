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
        <div class="col-12 mb-4 d-flex justify-content-between align-items-center gap-3 flex-wrap border-bottom pb-3">
            <h1 class="h4 fw-bold text-dark mb-0"><i class="fas fa-calendar-alt text-primary me-2" aria-hidden="true"></i> ปฏิทินการจองห้องประชุม</h1>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('home') }}" class="ds-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก</a>
                <a href="{{ route('bookings.create') }}" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4">
                    <i class="fas fa-plus me-1"></i> จองห้อง / นัดประชุม
                </a>
            </div>
        </div>

        {{-- 🌟 ฝั่งซ้าย: กล่องแสดงปฏิทิน --}}
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                <div class="card-body p-4">
                    <div id='calendar'></div>
                </div>
            </div>
            
            {{-- คำอธิบายสี --}}
            <div class="calendar-legend d-flex justify-content-center gap-4 mt-3" aria-label="คำอธิบายสีของปฏิทิน">
                <span class="small fw-bold"><i class="fas fa-circle text-primary me-1"></i> นัดประชุม</span>
                <span class="small fw-bold"><i class="fas fa-circle text-success me-1"></i> ใช้งานทั่วไป</span>
                <span class="small fw-bold"><i class="fas fa-circle text-danger me-1"></i> ปิดปรับปรุง</span>
                <span class="small fw-bold"><i class="fas fa-circle me-1" style="color:#f59e0b"></i> วันหยุด</span>
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

                                    @php
                                        $bookingStatus = strtoupper($booking->status ?? 'APPROVED');
                                        $lifecycleStatus = $booking->lifecycle_status;
                                        $statusBadge = match($lifecycleStatus) {
                                            'PENDING' => ['bg-warning-subtle text-warning-emphasis', 'รอดำเนินการ'],
                                            'CANCELED' => ['bg-secondary-subtle text-secondary', 'ยกเลิกแล้ว'],
                                            'IN_PROGRESS' => ['bg-primary-subtle text-primary-emphasis', 'กำลังดำเนินการ'],
                                            'COMPLETED' => ['bg-success-subtle text-success-emphasis', 'เสร็จสิ้น'],
                                            default => ['bg-info-subtle text-info-emphasis', 'ยืนยันแล้ว'],
                                        };
                                    @endphp

                                    @if($canSeeTitle)
                                        <h6 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 65%;">
                                            @can('view', $booking)
                                                <a href="{{ route('bookings.show', $booking) }}" class="text-dark text-decoration-none">{{ $booking->title }}</a>
                                            @else
                                                {{ $booking->title }}
                                            @endcan
                                        </h6>
                                    @else
                                        <h6 class="mb-0 fw-bold text-muted text-truncate fst-italic" style="max-width: 65%;">
                                            <i class="fas fa-lock fa-sm me-1 opacity-50"></i> --------
                                        </h6>
                                    @endif
                                    
                                    <div class="d-flex flex-column align-items-end gap-1">
                                        {{-- ป้ายประเภทการจอง --}}
                                        @if($booking->booking_type == 'meeting')
                                            <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">นัดประชุม</span>
                                        @elseif($booking->booking_type == 'general_use')
                                            <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ทั่วไป</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ปรับปรุง</span>
                                        @endif
                                        <span class="badge {{ $statusBadge[0] }} rounded-pill px-2 py-1" style="font-size: 0.7rem;">{{ $statusBadge[1] }}</span>
                                    </div>
                                </div>
                                
                                <p class="mb-1 small text-secondary mt-2">
                                    <i class="fas fa-door-open me-1"></i> <strong>{{ $booking->room_display_name }}</strong><br>
                                    <i class="far fa-calendar-check me-1"></i> {{ \Carbon\Carbon::parse($booking->start_time)->addYears(543)->format('d/m/Y') }} <br>
                                    <i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }} น. - {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }} น.
                                </p>

                                @if($canSeeTitle && $booking->document)
                                    <a href="{{ route('documents.show', $booking->document->uuid ?? $booking->document->id) }}" class="small text-decoration-none fw-bold">
                                        <i class="fas fa-paperclip me-1"></i>เอกสารแนบ: {{ Str::limit($booking->document->title, 45) }}
                                    </a>
                                @endif

                                {{-- ส่วนล่าง: ชื่อผู้จอง และปุ่มยกเลิก --}}
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted" style="font-size: 0.75rem;">ผู้จอง: {{ $booking->creator->name ?? 'ไม่ทราบชื่อ' }}</small>

                                    @can('view', $booking)
                                        <a href="{{ route('bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 ms-auto me-2" style="font-size: 0.7rem;">
                                            <i class="fas fa-eye me-1"></i>{{ $booking->invitees->contains('id', Auth::id()) ? 'ดูและตอบรับ' : 'ดูรายละเอียด' }}
                                        </a>
                                    @endcan

                                    {{-- ยกเลิกได้เฉพาะก่อนเริ่ม หลังจากนั้นแสดงสถานะตามเวลาแทน --}}
                                    @if($booking->created_by === Auth::id() || Auth::user()->hasRole('super-admin'))
                                        @if($booking->canBeCanceledNow())
                                            <form action="{{ route('bookings.destroy', $booking->id) }}" method="POST" onsubmit="return confirm('⚠️ คุณแน่ใจหรือไม่ที่จะยกเลิกการจองนี้? \n(การคืนคิวห้องจะไม่สามารถกู้คืนได้)');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-1" style="font-size: 0.7rem;">
                                                    <i class="fas fa-trash-alt me-1"></i> ยกเลิก
                                                </button>
                                            </form>
                                        @elseif($lifecycleStatus === 'IN_PROGRESS')
                                            <span class="btn btn-sm btn-primary disabled rounded-pill px-3 py-1 booking-time-state" aria-disabled="true">
                                                <i class="fas fa-circle-play me-1"></i>กำลังดำเนินการ
                                            </span>
                                        @elseif($lifecycleStatus === 'COMPLETED')
                                            <span class="btn btn-sm btn-success disabled rounded-pill px-3 py-1 booking-time-state" aria-disabled="true">
                                                <i class="fas fa-check me-1"></i>เสร็จสิ้น
                                            </span>
                                        @endif
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
            'status' => strtoupper($booking->status ?? 'APPROVED'),
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'room' => ['name' => $booking->room_display_name],
            'creator' => $booking->creator ? ['name' => $booking->creator->name] : ['name' => '-'],
            'description' => $displayDesc,
            'detail_url' => $canSee && ($booking->created_by === $userId || $isAdmin || $booking->invitees->contains('id', $userId))
                ? route('bookings.show', $booking)
                : null
        ];
    });
@endphp

{{-- 🌟 Script ควบคุมปฏิทิน --}}
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var bookings = @json($safeBookings);
    var holidays = @json($holidays);
    
    var events = bookings.map(function(booking) {
        let eventColor = '#10b981'; 
        if(booking.booking_type === 'meeting') eventColor = '#3b82f6'; 
        if(booking.booking_type === 'maintenance') eventColor = '#ef4444'; 
        if(booking.status === 'CANCELED') eventColor = '#94a3b8';

        return {
            title: (booking.status === 'CANCELED' ? '[ยกเลิก] ' : '') + booking.title + ' (' + booking.room.name + ')',
            start: booking.start_time,
            end: booking.end_time,
            color: eventColor,
            extendedProps: {
                room: booking.room.name,
                creator: booking.creator.name,
                description: booking.description,
                status: booking.status,
                detailUrl: booking.detail_url
            }
        };
    });

    holidays.forEach(function(holiday) {
        events.push({
            title: 'วันหยุด: ' + holiday.name,
            start: holiday.holiday_date,
            allDay: true,
            color: '#f59e0b',
            textColor: '#422006',
            display: 'block',
            extendedProps: {
                isHoliday: true,
                holidayName: holiday.name
            }
        });
    });

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
      locale: 'th', 
      height: 'auto',
      buttonText: { today: 'วันนี้', month: 'เดือน', week: 'สัปดาห์', day: 'วัน', list: 'รายการ' },
      headerToolbar: window.innerWidth < 768
        ? { left: 'prev,next', center: 'title', right: 'today' }
        : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
      footerToolbar: window.innerWidth < 768
        ? { center: 'listMonth,dayGridMonth,timeGridDay' }
        : false,
      events: events, 
      eventClick: function(info) {
          if (info.event.extendedProps.isHoliday) {
              alert('วันหยุด: ' + info.event.extendedProps.holidayName + '\nวันที่: ' + info.event.start.toLocaleDateString('th-TH'));
              return;
          }
          if (info.event.extendedProps.detailUrl) {
              window.location.href = info.event.extendedProps.detailUrl;
              return;
          }
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
    .fc .fc-button { min-height: 42px; font-size: .95rem; }
    .fc-event:focus { outline: 3px solid rgba(2, 132, 199, .45); outline-offset: 2px; }
    @media (max-width: 767.98px) {
        #calendar .fc-header-toolbar { align-items: center; gap: .5rem; }
        #calendar .fc-toolbar-title { font-size: 1.05rem !important; text-align: center; }
        #calendar .fc-toolbar-chunk { display: flex; }
        #calendar .fc-footer-toolbar { margin-top: 1rem; }
        #calendar .fc-footer-toolbar .fc-toolbar-chunk,
        #calendar .fc-footer-toolbar .fc-button-group { width: 100%; }
        #calendar .fc-footer-toolbar .fc-button { flex: 1 1 0; white-space: nowrap; padding-inline: .45rem; }
        #calendar .fc-list-event-title a { white-space: normal; font-size: .95rem; }
        #calendar .fc-list-event-time { white-space: nowrap; }
        .calendar-legend { flex-wrap: wrap; gap: .6rem 1rem !important; justify-content: flex-start !important; }
    }
</style>
@endsection
