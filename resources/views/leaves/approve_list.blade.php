{{-- View: หน้ารายการใบลาที่รอพิจารณาและอนุมัติ --}}
@extends('layouts.app')

@section('title', 'พิจารณาใบลา')

@section('content')
@php
    $currentUser = auth()->user();
    $isAdminOverview = $currentUser->hasRole('super-admin');
    $isHrOverview = $currentUser->hasRole('hr');
    $isOverview = $isHrOverview || $isAdminOverview;
    $leavePageTitle = $isOverview ? 'ความคืบหน้าการอนุมัติใบลา' : 'รายการใบลาที่รออนุมัติ';
    $leavePageDescription = match (true) {
        $isHrOverview => 'ติดตามใบลาที่ส่งเข้าระบบทั้งหมด และดำเนินการเมื่อถึงขั้นงานบุคคล',
        $isAdminOverview => 'ติดตามใบลาทั้งหมดในระบบ (ดูข้อมูลได้อย่างเดียว ไม่สามารถอนุมัติได้)',
        default => 'แสดงเฉพาะใบลาที่ถึงลำดับพิจารณาของคุณแล้ว',
    };
    $leaveStatuses = [
        'pending_delegate'  => ['gray', 'รอผู้รับมอบงาน'],
        'delegate_declined' => ['red', 'ผู้รับมอบงานปฏิเสธ'],
        'delegate_escalated'=> ['amber', 'ผู้รับมอบงานยังไม่ตอบรับ'],
        'pending_head'      => ['cyan', 'รอหัวหน้าส่วนราชการ'],
        'pending_inspector' => ['amber', 'รอบุคคลตรวจสิทธิ์'],
        'pending_palad'     => ['blue', 'รอปลัด อบต.'],
        'pending_nayok'     => ['purple', 'รอนายก อบต.'],
        'approved'          => ['green', 'อนุมัติแล้ว'],
        'rejected'          => ['red', 'ถูกตีกลับ'],
        'canceled'          => ['gray', 'ยกเลิกแล้ว'],
        'draft'             => ['gray', 'ฉบับร่าง'],
    ];
@endphp

<div class="review-queue-page container-fluid px-3 px-lg-4 py-4">
    <div class="mx-auto" style="max-width: 1320px;">
        <header class="review-queue-heading">
            <div class="review-queue-heading__copy">
                <div class="review-queue-heading__icon review-queue-heading__icon--leave" aria-hidden="true"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <span class="review-queue-eyebrow review-queue-eyebrow--leave">แฟ้มพิจารณาการลา</span>
                    <h1>{{ $leavePageTitle }}</h1>
                    <p>{{ $leavePageDescription }}</p>
                </div>
            </div>
            <div class="review-queue-heading__actions">
                <span class="review-queue-date"><i class="far fa-calendar" aria-hidden="true"></i>{{ now()->locale('th')->translatedFormat('d M') }} {{ now()->year + 543 }}</span>
                <a href="{{ route('home') }}" class="ds-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก</a>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show review-queue-alert" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show review-queue-alert" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
            </div>
        @endif

        <section class="review-queue-summary" aria-labelledby="leave-queue-title">
            <div class="review-queue-summary__copy">
                <span class="review-queue-summary__icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></span>
                <div>
                    <h2 id="leave-queue-title">{{ $isOverview ? 'ใบลาทั้งหมดในระบบ' : 'ใบลาที่ต้องติดตามหรือดำเนินการ' }}</h2>
                    <p>{{ $leaves->count() > 0 ? 'เรียงจากใบลาที่ส่งเข้ามาล่าสุด' : ($isOverview ? 'ยังไม่มีใบลาที่ส่งเข้าระบบ' : 'ขณะนี้ไม่มีใบลาค้างในลำดับของคุณ') }}</p>
                </div>
            </div>
            <span class="review-queue-count"><strong>{{ number_format($leaves->count()) }}</strong> รายการ</span>
        </section>

        <div class="review-queue-list">
            @forelse($leaves as $leave)
                @php
                    $leaveStatus = $leaveStatuses[$leave->workflow_status] ?? ['gray', 'ไม่ทราบสถานะ'];
                    $leaveIsFinished = in_array($leave->workflow_status, ['approved', 'rejected'], true);
                    $leaveCanAct = $currentUser->can('review', $leave);
                    $leaveIsOwned = $leave->user_id === $currentUser->id;
                    $leaveIsDraft = $leave->is_draft;
                    $startDate = \Carbon\Carbon::parse($leave->start_date);
                    $endDate = \Carbon\Carbon::parse($leave->end_date);
                    $dateRange = $startDate->isSameDay($endDate)
                        ? $startDate->format('d/m/').($startDate->year + 543)
                        : $startDate->format('d/m/').($startDate->year + 543).' – '.$endDate->format('d/m/').($endDate->year + 543);
                    $totalDays = (float) $leave->total_days;
                    $totalDaysLabel = $totalDays === floor($totalDays)
                        ? number_format($totalDays, 0)
                        : rtrim(rtrim(number_format($totalDays, 2), '0'), '.');
                @endphp

                <article class="review-queue-item">
                    <div class="review-queue-item__icon review-queue-item__icon--leave" aria-hidden="true"><i class="fas fa-user-clock"></i></div>
                    <div class="review-queue-item__main">
                        <div class="review-queue-item__eyebrow">
                            <span class="review-queue-type">{{ $leave->leave_type }}</span>
                            <span>ใบลา #{{ $leave->id }}</span>
                        </div>
                        <h3><a href="{{ route('leaves.show', $leave->id) }}">{{ $leave->user->name ?? 'ไม่ทราบชื่อผู้ขอลา' }}</a></h3>
                        <div class="review-queue-item__meta">
                            <span><i class="fas fa-briefcase" aria-hidden="true"></i>{{ $leave->user->position ?? 'ไม่ระบุตำแหน่ง' }}</span>
                            <span><i class="far fa-calendar-alt" aria-hidden="true"></i>{{ $dateRange }}</span>
                            <span><i class="fas fa-hourglass-half" aria-hidden="true"></i>{{ $totalDaysLabel }} วัน</span>
                            <span><i class="far fa-clock" aria-hidden="true"></i>ยื่น {{ $leave->created_at ? $leave->created_at->locale('th')->translatedFormat('d M').' '.($leave->created_at->year + 543).' '.$leave->created_at->format('H:i') : '-' }}</span>
                        </div>
                    </div>
                    <div class="review-queue-item__state">
                        <span class="review-queue-status review-queue-status--{{ $leaveStatus[0] }}">{{ $leaveStatus[1] }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('leaves.show', $leave->id) }}" class="review-queue-item__action {{ !$leaveCanAct ? 'review-queue-item__action--secondary' : '' }}">
                            <i class="fas {{ $leaveCanAct ? 'fa-pen-nib' : ($leaveIsDraft ? 'fa-file-lines' : 'fa-chart-line') }} me-1" aria-hidden="true"></i>{{ $leaveCanAct ? ($currentUser->hasRole('hr') ? 'ตรวจสอบสิทธิ์' : 'พิจารณา') : ($leaveIsDraft ? 'ตรวจฉบับร่าง' : (($isOverview || $leaveIsOwned) ? 'ดูความคืบหน้า' : 'ดูรายละเอียด')) }}
                        </a>
                        @if($leaveIsOwned && $currentUser->can('cancel', $leave))
                            <form id="cancel-form-{{ $leave->id }}" action="{{ route('leaves.cancel', $leave->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PUT')
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold" onclick="confirmCancelQueue({{ $leave->id }})" title="ยกเลิกใบลา">
                                    <i class="fas fa-times me-1"></i>ยกเลิก
                                </button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="review-queue-empty" role="status">
                    <span class="review-queue-empty__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                    <h2>{{ $isHrOverview ? 'ยังไม่มีข้อมูลใบลา' : 'ไม่มีใบลารอการพิจารณา' }}</h2>
                    <p>{{ $isHrOverview ? 'เมื่อมีการส่งใบลา รายการจะแสดงที่หน้านี้' : 'ใบลาที่ถึงลำดับของคุณได้รับการดำเนินการครบแล้ว' }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<script>
function confirmCancelQueue(id) {
    const text = 'คุณต้องการยกเลิกคำขอลาฉบับนี้ใช่หรือไม่?';
    if (window.Swal) {
        Swal.fire({
            title: 'ยืนยันการยกเลิกใบลา?',
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ยืนยันยกเลิกใบลา',
            cancelButtonText: 'ปิด'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('cancel-form-' + id).submit();
            }
        });
    } else if (confirm(text)) {
        document.getElementById('cancel-form-' + id).submit();
    }
}
</script>

@include('partials.review_queue_styles')
@endsection
