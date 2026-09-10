@extends('layouts.app')

@section('content')
@php
    $bookingStatus = strtoupper($booking->status ?? 'APPROVED');
    $lifecycleStatus = $booking->lifecycle_status;
    $isCanceled = $lifecycleStatus === 'CANCELED';
    $isMeeting = $booking->booking_type === 'meeting';
    $canViewResponses = $booking->created_by === Auth::id() || Auth::user()->hasRole('super-admin');
    $myResponse = strtoupper($currentInvitation?->pivot?->status ?? 'PENDING');
    $responseStyles = [
        'ACCEPTED' => ['success', 'ตอบรับแล้ว', 'fa-check-circle'],
        'DECLINED' => ['danger', 'ไม่สะดวกเข้าร่วม', 'fa-times-circle'],
        'PENDING' => ['warning', 'รอการตอบรับ', 'fa-clock'],
    ];
    $myResponseStyle = $responseStyles[$myResponse] ?? $responseStyles['PENDING'];
    $responseCounts = [
        'ACCEPTED' => $booking->invitees->filter(fn ($user) => strtoupper($user->pivot->status ?? 'PENDING') === 'ACCEPTED')->count(),
        'DECLINED' => $booking->invitees->filter(fn ($user) => strtoupper($user->pivot->status ?? 'PENDING') === 'DECLINED')->count(),
        'PENDING' => $booking->invitees->filter(fn ($user) => strtoupper($user->pivot->status ?? 'PENDING') === 'PENDING')->count(),
    ];
@endphp

<div class="container py-4 booking-detail-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <a href="{{ route('bookings.index') }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับปฏิทินการจอง
            </a>
            <h3 class="fw-bold text-dark mb-0 mt-2">รายละเอียดการประชุม</h3>
        </div>
        <div class="d-flex gap-2">
            @if($isCanceled)
                <span class="badge rounded-pill bg-secondary px-3 py-2"><i class="fas fa-ban me-1"></i> ยกเลิกแล้ว</span>
            @elseif($lifecycleStatus === 'IN_PROGRESS')
                <span class="badge rounded-pill bg-primary px-3 py-2"><i class="fas fa-circle-play me-1"></i> กำลังดำเนินการ</span>
            @elseif($lifecycleStatus === 'COMPLETED')
                <span class="badge rounded-pill bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i> เสร็จสิ้น</span>
            @else
                <span class="badge rounded-pill bg-success-subtle text-success-emphasis px-3 py-2"><i class="fas fa-check me-1"></i> ยืนยันแล้ว</span>
            @endif
            @if($isMeeting)
                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis px-3 py-2"><i class="fas fa-users me-1"></i> นัดประชุม</span>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="booking-hero p-4 p-md-5">
                    <span class="small fw-semibold text-primary">หัวข้อการประชุม</span>
                    <h2 class="fw-bold text-dark mt-2 mb-3">{{ $booking->title }}</h2>
                    <p class="text-secondary mb-0">{{ $booking->description ?: 'ไม่มีรายละเอียดเพิ่มเติม' }}</p>
                </div>
                <div class="card-body p-4 p-md-5 pt-md-4">
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="detail-item">
                                <span class="detail-icon bg-primary-subtle text-primary"><i class="fas fa-door-open"></i></span>
                                <div><small>ห้องประชุม</small><strong>{{ $booking->room_display_name }}</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-item">
                                <span class="detail-icon bg-info-subtle text-info-emphasis"><i class="fas fa-user-tie"></i></span>
                                <div><small>ผู้เชิญ</small><strong>{{ $booking->creator?->name ?: '-' }}</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-item">
                                <span class="detail-icon bg-success-subtle text-success"><i class="far fa-calendar-alt"></i></span>
                                <div><small>วันที่</small><strong>{{ $booking->start_time->copy()->addYears(543)->format('d/m/Y') }}</strong></div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-item">
                                <span class="detail-icon bg-warning-subtle text-warning-emphasis"><i class="far fa-clock"></i></span>
                                <div><small>เวลา</small><strong>{{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }} น.</strong></div>
                            </div>
                        </div>
                    </div>

                    @if($booking->document)
                        <div class="attached-document mt-4 p-3 rounded-4 d-flex align-items-center gap-3">
                            <span class="detail-icon bg-white text-primary"><i class="fas fa-paperclip"></i></span>
                            <div class="flex-grow-1 overflow-hidden">
                                <small class="text-muted d-block">เอกสารประกอบ</small>
                                <strong class="d-block text-truncate">{{ $booking->document->title }}</strong>
                            </div>
                            <a href="{{ route('documents.show', $booking->document->uuid ?? $booking->document->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">เปิดดู</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if($currentInvitation)
                <div class="card border-0 shadow-sm rounded-4 mb-4 response-card">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <small class="text-muted">สถานะของคุณ</small>
                                <h5 class="fw-bold mb-0 mt-1">ตอบรับคำเชิญ</h5>
                            </div>
                            <span class="badge bg-{{ $myResponseStyle[0] }}-subtle text-{{ $myResponseStyle[0] }}-emphasis rounded-pill px-3 py-2">
                                <i class="fas {{ $myResponseStyle[2] }} me-1"></i>{{ $myResponseStyle[1] }}
                            </span>
                        </div>

                        @if($isCanceled)
                            <div class="alert alert-secondary mb-0 rounded-3">การประชุมนี้ถูกยกเลิกแล้ว จึงไม่สามารถเปลี่ยนคำตอบได้</div>
                        @else
                            @if($myResponse === 'ACCEPTED')
                                <p class="small text-success fw-semibold"><i class="fas fa-check-circle me-1"></i>คุณยืนยันว่าจะเข้าร่วมการประชุมนี้แล้ว</p>
                            @elseif($myResponse === 'DECLINED')
                                <p class="small text-danger fw-semibold"><i class="fas fa-info-circle me-1"></i>คุณแจ้งว่าไม่สะดวกเข้าร่วมการประชุมนี้</p>
                            @else
                                <p class="small text-secondary">กรุณาเลือกคำตอบเพื่อแจ้งผู้เชิญ</p>
                            @endif
                            <form action="{{ route('bookings.respond', $booking) }}" method="POST" class="d-grid d-sm-flex gap-2">
                                @csrf
                                <button type="submit" name="response" value="accepted"
                                        data-testid="response-accepted"
                                        aria-pressed="{{ $myResponse === 'ACCEPTED' ? 'true' : 'false' }}"
                                        class="btn {{ $myResponse === 'ACCEPTED' ? 'btn-success selected-response' : 'btn-outline-success' }} flex-fill rounded-3 py-2 fw-bold"
                                        @disabled($myResponse === 'ACCEPTED')>
                                    <i class="fas {{ $myResponse === 'ACCEPTED' ? 'fa-check-circle' : 'fa-check' }} me-1"></i>
                                    {{ $myResponse === 'ACCEPTED' ? 'ตอบรับแล้ว' : ($myResponse === 'DECLINED' ? 'เปลี่ยนเป็นเข้าร่วม' : 'เข้าร่วม') }}
                                </button>
                                <button type="submit" name="response" value="declined"
                                        data-testid="response-declined"
                                        aria-pressed="{{ $myResponse === 'DECLINED' ? 'true' : 'false' }}"
                                        class="btn {{ $myResponse === 'DECLINED' ? 'btn-danger selected-response' : 'btn-outline-danger' }} flex-fill rounded-3 py-2 fw-bold"
                                        @disabled($myResponse === 'DECLINED')>
                                    <i class="fas {{ $myResponse === 'DECLINED' ? 'fa-times-circle' : 'fa-times' }} me-1"></i>
                                    {{ $myResponse === 'DECLINED' ? 'แจ้งไม่สะดวกแล้ว' : ($myResponse === 'ACCEPTED' ? 'เปลี่ยนเป็นไม่สะดวก' : 'ไม่สะดวก') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @if($canViewResponses && $isMeeting)
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <small class="text-muted">สำหรับผู้เชิญ</small>
                                <h5 class="fw-bold mb-0">ผลตอบรับผู้เข้าร่วม</h5>
                            </div>
                            <span class="badge bg-dark rounded-pill">{{ $booking->invitees->count() }} คน</span>
                        </div>

                        <div class="response-summary row g-2 mb-4 text-center">
                            <div class="col-4"><div class="summary-box accepted"><strong>{{ $responseCounts['ACCEPTED'] }}</strong><small>เข้าร่วม</small></div></div>
                            <div class="col-4"><div class="summary-box declined"><strong>{{ $responseCounts['DECLINED'] }}</strong><small>ไม่สะดวก</small></div></div>
                            <div class="col-4"><div class="summary-box pending"><strong>{{ $responseCounts['PENDING'] }}</strong><small>รอตอบ</small></div></div>
                        </div>

                        <div class="participant-list">
                            @forelse($booking->invitees as $invitee)
                                @php
                                    $inviteeStatus = strtoupper($invitee->pivot->status ?? 'PENDING');
                                    $inviteeStyle = $responseStyles[$inviteeStatus] ?? $responseStyles['PENDING'];
                                @endphp
                                <div class="participant-row d-flex align-items-center gap-3 py-3">
                                    <div class="participant-avatar">{{ mb_substr($invitee->name, 0, 1) }}</div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <strong class="d-block text-truncate">{{ $invitee->name }}</strong>
                                        <small class="text-muted d-block text-truncate">{{ $invitee->position ?: $invitee->department ?: 'ผู้เข้าร่วม' }}</small>
                                        @if($invitee->pivot->responded_at)
                                            <small class="text-muted">ตอบเมื่อ {{ \Carbon\Carbon::parse($invitee->pivot->responded_at)->addYears(543)->format('d/m/Y H:i') }} น.</small>
                                        @endif
                                    </div>
                                    <span class="badge bg-{{ $inviteeStyle[0] }}-subtle text-{{ $inviteeStyle[0] }}-emphasis rounded-pill">{{ $inviteeStyle[1] }}</span>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4"><i class="fas fa-user-plus fs-3 opacity-25 d-block mb-2"></i>ยังไม่มีผู้ได้รับเชิญ</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @elseif($currentInvitation)
                <div class="alert alert-light border rounded-4 text-secondary small mb-0">
                    <i class="fas fa-shield-alt me-1"></i> รายชื่อและคำตอบของผู้เข้าร่วมคนอื่นจะแสดงเฉพาะผู้เชิญเท่านั้น
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .booking-detail-page { max-width: 1180px; }
    .booking-hero { background: linear-gradient(135deg, #eef6ff, #f8fbff); border-bottom: 1px solid #e8eef6; }
    .detail-item { display: flex; gap: .85rem; align-items: center; }
    .detail-item small { display: block; color: #718096; margin-bottom: .1rem; }
    .detail-item strong { display: block; color: #1f2937; }
    .detail-icon { width: 44px; height: 44px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; }
    .attached-document { background: #f7f9fc; border: 1px solid #e8edf4; }
    .response-card { border-top: 4px solid #0d6efd !important; }
    .selected-response:disabled { opacity: 1; cursor: default; box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .35); }
    .summary-box { border-radius: 12px; padding: .7rem .25rem; }
    .summary-box strong { display: block; font-size: 1.35rem; }
    .summary-box small { display: block; font-size: .72rem; }
    .summary-box.accepted { background: #eaf8f0; color: #167546; }
    .summary-box.declined { background: #fff0f1; color: #b42332; }
    .summary-box.pending { background: #fff8e5; color: #936b00; }
    .participant-row + .participant-row { border-top: 1px solid #edf0f4; }
    .participant-avatar { width: 42px; height: 42px; border-radius: 50%; background: #e8f1ff; color: #0d6efd; display: flex; align-items: center; justify-content: center; font-weight: 700; flex: 0 0 42px; }
    @media (max-width: 575.98px) {
        .booking-detail-page { padding-left: 1rem; padding-right: 1rem; }
        .participant-row .badge { max-width: 105px; white-space: normal; text-align: center; }
    }
</style>
@endsection
