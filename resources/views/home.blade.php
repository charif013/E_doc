@extends('layouts.app')

@section('content')
<div class="dashboard-page container-fluid px-4 py-4">

    {{-- Header --}}
    <header class="dashboard-heading mb-4">
        <div class="dashboard-heading__copy">
            <span class="dashboard-eyebrow">ภาพรวมการทำงาน</span>
            <h1 class="dashboard-title">สวัสดี {{ Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="dashboard-subtitle">ติดตามงานที่ถึงคิว เอกสารล่าสุด และตารางประชุมได้จากหน้านี้</p>
        </div>
        <div class="dashboard-heading__summary">
            <div class="dashboard-date-icon"><i class="far fa-calendar" aria-hidden="true"></i></div>
            <div>
                <span>วันนี้</span>
                <strong>{{ now()->locale('th')->translatedFormat('d M') }} {{ now()->year + 543 }}</strong>
            </div>
        </div>
    </header>

    @if($documentTasks->isEmpty() && $leaveTasks->isEmpty())
        <div class="dashboard-clear-state mb-4" role="status">
            <div class="dashboard-clear-state__icon"><i class="fas fa-check" aria-hidden="true"></i></div>
            <div>
                <strong>ไม่มีงานที่รอคุณดำเนินการ</strong>
                <span>งานเอกสารและใบลาของคุณเป็นปัจจุบันแล้ว</span>
            </div>
        </div>
    @endif

    {{-- การ์ดสถิติ 4 ช่อง — กดเพื่อกรองรายการด้านล่าง --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="{{ route('home', ['filter' => 'all']) }}#document-list" class="stat-card-link {{ $filter === 'all' ? 'is-active' : '' }}" aria-label="แสดงเอกสารทั้งหมด {{ $stats['total'] }} รายการ">
            <div class="card dashboard-stat dashboard-stat--all h-100">
                <div class="card-body">
                    <div class="dashboard-stat__top">
                        <span class="dashboard-stat__icon"><i class="far fa-folder-open" aria-hidden="true"></i></span>
                    </div>
                    <p class="dashboard-stat__label">เอกสารทั้งหมด</p>
                    <h2 class="dashboard-stat__value">{{ number_format($stats['total']) }}</h2>
                    <p class="dashboard-stat__meta">ในรายการที่คุณเข้าถึงได้</p>
                </div>
            </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('home', ['filter' => 'waiting']) }}#document-list" class="stat-card-link {{ $filter === 'waiting' ? 'is-active' : '' }}" aria-label="กรองเอกสารรอดำเนินการ {{ $stats['waiting'] }} รายการ">
            <div class="card dashboard-stat dashboard-stat--waiting h-100">
                <div class="card-body">
                    <div class="dashboard-stat__top">
                        <span class="dashboard-stat__icon"><i class="far fa-clock" aria-hidden="true"></i></span>
                        @if($stats['waiting'] > 0)<span class="dashboard-stat__signal">ต้องทำ</span>@endif
                    </div>
                    <p class="dashboard-stat__label">รอดำเนินการ</p>
                    <h2 class="dashboard-stat__value">{{ number_format($stats['waiting']) }}</h2>
                    <p class="dashboard-stat__meta">เอกสารและใบลาที่ถึงคิว</p>
                </div>
            </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('home', ['filter' => 'approved']) }}#document-list" class="stat-card-link {{ $filter === 'approved' ? 'is-active' : '' }}" aria-label="กรองเอกสารอนุมัติเรียบร้อย {{ $stats['approved'] }} รายการ">
            <div class="card dashboard-stat dashboard-stat--approved h-100">
                <div class="card-body">
                    <div class="dashboard-stat__top">
                        <span class="dashboard-stat__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                    </div>
                    <p class="dashboard-stat__label">อนุมัติเรียบร้อย</p>
                    <h2 class="dashboard-stat__value">{{ number_format($stats['approved']) }}</h2>
                    <p class="dashboard-stat__meta">เอกสารที่ดำเนินการสำเร็จ</p>
                </div>
            </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('home', ['filter' => 'rejected']) }}#document-list" class="stat-card-link {{ $filter === 'rejected' ? 'is-active' : '' }}" aria-label="กรองเอกสารถูกตีกลับ {{ $stats['rejected'] }} รายการ">
            <div class="card dashboard-stat dashboard-stat--rejected h-100">
                <div class="card-body">
                    <div class="dashboard-stat__top">
                        <span class="dashboard-stat__icon"><i class="fas fa-rotate-left" aria-hidden="true"></i></span>
                        @if($stats['rejected'] > 0)<span class="dashboard-stat__signal">แก้ไข</span>@endif
                    </div>
                    <p class="dashboard-stat__label">เอกสารถูกตีกลับ</p>
                    <h2 class="dashboard-stat__value">{{ number_format($stats['rejected']) }}</h2>
                    <p class="dashboard-stat__meta">รายการที่ต้องตรวจสอบอีกครั้ง</p>
                </div>
            </div>
            </a>
        </div>
    </div>

    {{-- คอลัมน์รายการเอกสาร และ ตารางจองห้อง --}}
    <div class="row g-4">
        
        {{-- 🌟 ฝั่งซ้าย: เอกสารเข้าล่าสุด (พื้นที่ 8 ส่วน) --}}
        <div class="col-lg-8" id="document-list">
            <div class="card dashboard-panel recent-documents-card">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3">
                        <span class="panel-title-icon"><i class="fas fa-file-lines" aria-hidden="true"></i></span>
                        <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: 0.5px;">
                            {{ $search !== '' ? 'ผลการค้นหาเอกสาร' : match($filter) { 'waiting' => 'เอกสารรอดำเนินการ', 'approved' => 'เอกสารอนุมัติเรียบร้อย', 'rejected' => 'เอกสารที่ถูกตีกลับ', default => 'เอกสารล่าสุด' } }}
                        </h5>
                        </div>
                        @if($search !== '')
                            <span class="badge bg-primary rounded-pill px-3 py-2">{{ $searchResults->count() }} รายการ</span>
                        @endif
                    </div>

                    <form action="{{ route('home') }}" method="GET" class="mb-4">
                        <input type="hidden" name="filter" value="{{ $filter }}">
                        <div class="input-group dashboard-search">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="search" name="q" value="{{ $search }}" class="form-control border-start-0 home-document-search"
                                   placeholder="ค้นหาเลขเอกสาร ชื่อเรื่อง หรือหน่วยงาน..." aria-label="ค้นหาเอกสาร">
                            @if($search !== '')
                                <a href="{{ route('home', ['filter' => $filter]) }}#document-list" class="btn btn-outline-secondary d-flex align-items-center" title="ล้างการค้นหา">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                            <button type="submit" class="btn btn-search px-3 fw-bold">ค้นหา</button>
                        </div>
                        <div class="small text-muted mt-2"><i class="fas fa-shield-alt me-1"></i>แสดงเฉพาะเอกสารที่คุณมีสิทธิ์เข้าถึง</div>
                    </form>

                    <div class="d-flex flex-column gap-3 recent-documents-scroll">
                        @forelse($search !== '' ? $searchResults : $recentDocs as $doc)
                            <div class="doc-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-bold text-dark">{{ $doc->formatted_doc_number ?? 'ไม่มีเลขที่' }}</span>
                                    @php
                                        $badges = [
                                            'WAITING_SUPERVISOR' => ['ds-status-warning', 'รอหัวหน้า'],
                                            'WAITING_PALAD'      => ['ds-status-info', 'รอปลัด'],
                                            'WAITING_NAYOK'      => ['ds-status-info', 'รอนายก'],
                                            'WAITING_APPROVER'   => ['ds-status-info', 'รออนุมัติ'],
                                            'PROCESSING'         => ['ds-status-warning', 'อยู่ระหว่างพิจารณา'],
                                            'REGISTERED'         => ['ds-status-info', 'ลงทะเบียนแล้ว'],
                                            'IN_REVIEW'          => ['ds-status-warning', 'อยู่ระหว่างพิจารณา'],
                                            'COMPLETED'          => ['ds-status-success', 'เสร็จสิ้น'],
                                            'ARCHIVED'           => ['ds-status-success', 'จัดเก็บแล้ว'],
                                            'APPROVED'           => ['ds-status-success', 'อนุมัติแล้ว'],
                                            'REJECTED'           => ['ds-status-danger', 'ถูกตีกลับ'],
                                        ];
                                        $badge = $badges[$doc->status] ?? ['ds-status-info', 'ฉบับร่าง'];
                                        if ($doc->isAtFinalApprovalStep()) {
                                            $badge = ['ds-status-info', 'รออนุมัติ'];
                                        }
                                    @endphp
                                    <span class="ds-status {{ $badge[0] }}">{{ $badge[1] }}</span>
                                </div>
                                <a href="{{ route('documents.show', $doc->id) }}" class="text-decoration-none text-dark d-block mb-2">
                                    <h6 class="fw-bold mb-0 text-truncate">{{ $doc->title }}</h6>
                                </a>
                                <div class="text-muted small d-flex gap-2 opacity-75">
                                    <span>จาก: {{ $doc->creator->name ?? 'ธุรการ' }}</span>
                                    <span>•</span>
                                    <span>{{ $doc->created_at->format('d/m/y H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0">{{ $search !== '' ? 'ไม่พบเอกสารที่ตรงกับคำค้นหา' : 'ยังไม่มีเอกสารในระบบ' }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- 🌟 ฝั่งขวา: ตารางใช้ห้องวันนี้ + คำเชิญประชุม (พื้นที่ 4 ส่วน) --}}
        <div class="col-lg-4">

            {{-- งานที่ถึงคิว แสดงในคอลัมน์เดียวกับตารางห้องและคำเชิญ --}}
            @include('home.partials.action_queue')

            {{-- ตารางใช้ห้องวันนี้ --}}
            <div class="card dashboard-panel mb-4">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <span class="panel-title-icon panel-title-icon--green"><i class="far fa-calendar" aria-hidden="true"></i></span>
                            <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: 0.5px;">ใช้ห้องวันนี้</h5>
                        </div>
                        <a href="{{ route('bookings.index') }}" class="btn btn-sm" style="background-color: #eaf4f4; color: #164f51; border-radius: 10px; font-weight: bold;">
                            <i class="fas fa-calendar-alt me-1"></i> ปฏิทิน
                        </a>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @forelse($todayBookings as $booking)
                            <div class="doc-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    
                                    {{-- 🌟 เริ่ม: เช็คสิทธิ์การเห็นหัวข้อ --}}
                                    @php
                                        $canSeeTitle = false;
                                        // 1. ถ้าไม่ใช่การประชุม (ซ่อมบำรุง/ทั่วไป) หรือเป็นแอดมิน -> เห็นหัวข้อได้เลย
                                        if ($booking->booking_type !== 'meeting' || Auth::user()->hasRole('super-admin')) {
                                            $canSeeTitle = true;
                                        } 
                                        // 2. ถ้าเป็นคนสร้างการจองนี้เอง -> เห็นได้
                                        elseif ($booking->created_by === Auth::id()) {
                                            $canSeeTitle = true;
                                        } 
                                        // 3. ถ้าเป็นผู้ที่ถูกเชิญเข้าร่วมประชุม -> เห็นได้
                                        elseif ($booking->invitees && $booking->invitees->contains('id', Auth::id())) {
                                            $canSeeTitle = true;
                                        }
                                    @endphp

                                    @if($canSeeTitle)
                                        {{-- โชว์หัวข้อปกติ --}}
                                        <h6 class="fw-bold mb-0 text-dark text-truncate" style="max-width: 65%;">{{ $booking->title }}</h6>
                                    @else
                                        {{-- ซ่อนหัวข้อ โชว์ไอคอนแม่กุญแจ --}}
                                        <h6 class="fw-bold mb-0 text-muted text-truncate fst-italic" style="max-width: 65%;">
                                            <i class="fas fa-lock fa-sm me-1 opacity-50"></i> ---------
                                        </h6>
                                    @endif
                                    {{-- 🌟 จบ: เช็คสิทธิ์การเห็นหัวข้อ --}}

                                    @if($booking->booking_type == 'meeting')
                                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">นัดประชุม</span>
                                    @elseif($booking->booking_type == 'general_use')
                                        <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ทั่วไป</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ปรับปรุง</span>
                                    @endif
                                </div>
                                <div class="text-muted small d-flex flex-column gap-1 opacity-75 mt-2">
                                    <span><i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }} น.</span>
                                    <span><i class="fas fa-door-open me-1"></i> {{ $booking->room->name }}</span>
                                    <span class="text-truncate"><i class="fas fa-user-circle me-1"></i> {{ $booking->creator->name ?? '-' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-calendar-check fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0 fw-bold">ว่างทั้งวัน</p>
                                <p class="small mb-0">ไม่มีการจองห้องในวันนี้</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- กล่องที่ 2: นัดหมายของฉัน (เฉพาะที่ฉันถูกเชิญ) --}}
            <div class="card dashboard-panel">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="panel-title-icon panel-title-icon--indigo"><i class="fas fa-user-group" aria-hidden="true"></i></span>
                        <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: 0.5px;">คำเชิญประชุมของฉัน</h5>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @forelse($myMeetings as $meeting)
                            @php
                                $invitation = $meeting->invitees->firstWhere('id', Auth::id());
                                $invitationStatus = strtoupper($invitation?->pivot?->status ?? 'PENDING');
                                $invitationBadge = match($invitationStatus) {
                                    'ACCEPTED' => ['bg-success-subtle text-success-emphasis', 'ตอบรับแล้ว'],
                                    'DECLINED' => ['bg-danger-subtle text-danger-emphasis', 'ไม่สะดวก'],
                                    default => ['bg-warning-subtle text-warning-emphasis', 'รอตอบรับ'],
                                };
                            @endphp
                            <div class="doc-item p-3" style="border-left: 4px solid #0a58ca;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    {{-- โชว์หัวข้อได้เลย เพราะถ้ามาโผล่ตรงนี้คือเราเป็นคนถูกเชิญแน่นอนครับ --}}
                                    <h6 class="fw-bold mb-0 text-dark text-truncate">{{ $meeting->title }}</h6>
                                    <span class="badge {{ $invitationBadge[0] }} rounded-pill ms-2">{{ $invitationBadge[1] }}</span>
                                </div>
                                <div class="text-muted small d-flex flex-column gap-1 opacity-75">
                                    <span><i class="far fa-calendar me-1"></i> {{ \Carbon\Carbon::parse($meeting->start_time)->format('d/m/Y') }}</span>
                                    <span><i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($meeting->start_time)->format('H:i') }} น.</span>
                                    <span><i class="fas fa-door-open me-1"></i> {{ $meeting->room->name }}</span>
                                    <span><i class="fas fa-user-tie me-1"></i> ผู้เชิญ: {{ $meeting->creator->name ?? '-' }}</span>
                                </div>
                                <a href="{{ route('bookings.show', $meeting) }}" class="btn btn-sm btn-outline-primary rounded-pill mt-3 px-3">
                                    <i class="fas fa-reply me-1"></i> ดูรายละเอียดและตอบรับ
                                </a>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-smile fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0 small fw-bold">ไม่มีคำเชิญประชุม</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .dashboard-page {
        width: 100%;
        max-width: 1500px;
        min-height: 100%;
        margin: 0 auto;
        background: #fff;
    }
    .dashboard-heading {
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1.5rem;
        padding: 1.6rem 1.75rem;
        background: linear-gradient(120deg, #f0f9ff 0%, #f8fffc 58%, #ecfdf5 100%);
        border: 1px solid #dcebe8;
        border-radius: 20px;
    }
    .dashboard-heading::after {
        content: '';
        position: absolute;
        top: -90px;
        right: 15%;
        width: 210px;
        height: 210px;
        border: 38px solid rgba(14, 165, 233, .055);
        border-radius: 50%;
        pointer-events: none;
    }
    .dashboard-heading__copy, .dashboard-heading__summary { position: relative; z-index: 1; }
    .dashboard-eyebrow {
        display: block;
        margin-bottom: .3rem;
        color: #0284c7;
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .dashboard-title { margin: 0; color: #0f3f4b; font-size: clamp(1.55rem, 2.3vw, 2.1rem); font-weight: 800; }
    .dashboard-subtitle { margin: .4rem 0 0; color: #64748b; font-size: .92rem; }
    .dashboard-heading__summary {
        display: flex;
        align-items: center;
        gap: .8rem;
        min-width: 185px;
        padding: .75rem 1rem;
        background: rgba(255, 255, 255, .82);
        border: 1px solid rgba(148, 163, 184, .25);
        border-radius: 14px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .05);
    }
    .dashboard-heading__summary > div:last-child { display: flex; flex-direction: column; }
    .dashboard-heading__summary span { color: #64748b; font-size: .74rem; }
    .dashboard-heading__summary strong { color: #0f172a; font-size: .95rem; white-space: nowrap; }
    .dashboard-date-icon { width: 40px; height: 40px; display: grid; place-items: center; color: #0369a1; background: #e0f2fe; border-radius: 11px; }
    .dashboard-clear-state {
        display: flex;
        align-items: center;
        gap: .9rem;
        padding: 1rem 1.15rem;
        color: #065f46;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 15px;
    }
    .dashboard-clear-state__icon { width: 38px; height: 38px; display: grid; place-items: center; flex: 0 0 auto; color: #fff; background: #10b981; border-radius: 50%; }
    .dashboard-clear-state > div:last-child { display: flex; flex-direction: column; }
    .dashboard-clear-state span { color: #4b7166; font-size: .82rem; }
    .dashboard-action-total {
        display: inline-grid;
        place-items: center;
        min-width: 34px;
        height: 34px;
        padding: 0 .65rem;
        color: #fff;
        background: var(--primary);
        border-radius: 999px;
        font-size: .82rem;
        font-weight: 800;
    }
    .panel-title-icon--action { color: #9a6700; background: #fef3c7; }
    .sidebar-queue-group {
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }
    .sidebar-queue-group__title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .65rem .8rem;
        color: #075985;
        background: #f0f9ff;
        font-size: .76rem;
        font-weight: 800;
    }
    .sidebar-queue-group__title > span:last-child { color: #64748b; font-size: .7rem; }
    .sidebar-queue-group__title--leave { color: #047857; background: #ecfdf5; }
    .sidebar-queue-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .65rem;
        padding: .8rem;
        color: #0f172a;
        text-decoration: none;
        border-bottom: 1px solid #edf2f6;
        transition: background .18s ease;
    }
    .sidebar-queue-item:hover { color: #0f172a; background: #f8fafc; }
    .sidebar-queue-item:last-of-type { border-bottom: 0; }
    .sidebar-queue-item > div:first-child { min-width: 0; display: flex; flex: 1 1 auto; flex-direction: column; }
    .sidebar-queue-item strong, .sidebar-queue-item > div:first-child span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sidebar-queue-item strong { font-size: .83rem; }
    .sidebar-queue-item > div:first-child span { margin-top: .18rem; color: #64748b; font-size: .72rem; }
    .sidebar-queue-item__end { display: flex; align-items: center; gap: .45rem; flex: 0 0 auto; color: #94a3b8; }
    .queue-status { padding: .25rem .55rem; color: #9a6700; background: #fff7d6; border-radius: 999px; font-size: .72rem; font-weight: 700; }
    .queue-status--leave { color: #047857; background: #d1fae5; }
    .sidebar-queue-more { display: block; padding: .62rem .8rem; color: var(--primary-dark); text-align: center; text-decoration: none; font-size: .76rem; font-weight: 700; background: #f8fafc; border-top: 1px solid #edf2f6; }
    .sidebar-queue-more:hover { background: var(--primary-light); }
    .stat-card-link { display: block; height: 100%; color: inherit; text-decoration: none; border-radius: 18px; }
    .dashboard-stat {
        --stat-accent: #0284c7;
        --stat-soft: #e0f2fe;
        overflow: hidden;
        color: #0f172a;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-top: 3px solid var(--stat-accent);
        border-radius: 18px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .dashboard-stat--all { --stat-accent: #0284c7; --stat-soft: #e0f2fe; }
    .dashboard-stat--waiting { --stat-accent: #d97706; --stat-soft: #fef3c7; }
    .dashboard-stat--approved { --stat-accent: #059669; --stat-soft: #d1fae5; }
    .dashboard-stat--rejected { --stat-accent: #dc2626; --stat-soft: #fee2e2; }
    .dashboard-stat .card-body { padding: 1.15rem 1.2rem; }
    .dashboard-stat__top { min-height: 38px; display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
    .dashboard-stat__icon { width: 38px; height: 38px; display: grid; place-items: center; color: var(--stat-accent); background: var(--stat-soft); border-radius: 11px; }
    .dashboard-stat__signal { padding: .2rem .5rem; color: var(--stat-accent); background: var(--stat-soft); border-radius: 999px; font-size: .68rem; font-weight: 800; }
    .dashboard-stat__label { margin: 0 0 .1rem; color: #475569; font-size: .82rem; font-weight: 700; }
    .dashboard-stat__value { margin: 0; color: #0f172a; font-size: 2rem; font-weight: 800; line-height: 1.15; }
    .dashboard-stat__meta { margin: .28rem 0 0; color: #94a3b8; font-size: .72rem; }
    .stat-card-link:hover .dashboard-stat { transform: translateY(-3px); border-color: color-mix(in srgb, var(--stat-accent) 35%, #e2e8f0); box-shadow: 0 10px 24px rgba(15, 23, 42, .09); }
    .stat-card-link.is-active .dashboard-stat { box-shadow: 0 0 0 3px var(--stat-soft), 0 8px 22px rgba(15, 23, 42, .08); border-color: var(--stat-accent); }
    .dashboard-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 5px 18px rgba(15, 23, 42, .055); }
    .panel-title-icon { width: 38px; height: 38px; display: grid; place-items: center; flex: 0 0 auto; color: #0369a1; background: #e0f2fe; border-radius: 11px; }
    .panel-title-icon--green { color: #047857; background: #d1fae5; }
    .panel-title-icon--indigo { color: #4338ca; background: #e0e7ff; }
    .dashboard-search .input-group-text, .dashboard-search .form-control, .dashboard-search .btn { min-height: 44px; border-color: #dbe4ee; }
    .dashboard-search .input-group-text { border-radius: 12px 0 0 12px; }
    .dashboard-search .btn:last-child { border-radius: 0 12px 12px 0; }
    .home-document-search:focus {
        border-color: #164f51;
        box-shadow: none;
    }
    .btn-search {
        background: #164f51;
        border-color: #164f51;
        color: #fff;
    }
    .btn-search:hover { background: #0f3d3f; color: #fff; }
    .recent-documents-card {
        height: 660px;
        overflow: hidden;
    }
    .recent-documents-card .card-body {
        min-height: 0;
    }
    .recent-documents-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 8px;
        scrollbar-gutter: stable;
        overscroll-behavior: contain;
    }
    .recent-documents-scroll::-webkit-scrollbar {
        width: 8px;
    }
    .recent-documents-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    .recent-documents-scroll::-webkit-scrollbar-thumb {
        background: #b8c8d1;
        border-radius: 10px;
    }
    .recent-documents-scroll::-webkit-scrollbar-thumb:hover {
        background: #8fa6b2;
    }
    @media (max-width: 767.98px) {
        .dashboard-page { padding: .75rem !important; }
        .dashboard-heading { align-items: flex-start; flex-direction: column; padding: 1.2rem; border-radius: 16px; }
        .dashboard-heading__summary { width: 100%; min-width: 0; }
        .dashboard-subtitle { font-size: .83rem; }
        .dashboard-stat .card-body { padding: .9rem; }
        .dashboard-stat__value { font-size: 1.65rem; }
        .dashboard-stat__meta { min-height: 2.1em; }
        .sidebar-queue-item__end { align-items: flex-end; flex-direction: column-reverse; }
        .queue-status { max-width: 125px; text-align: center; white-space: normal; }
        .recent-documents-card {
            height: 560px;
        }
    }
    .text-teal { color: #164f51; }
    .text-gold { color: #d18b49; }
    .text-red { color: #ab3333; }
    .accent-line {
        width: 5px;
        height: 22px;
        background-color: #d18b49;
        border-radius: 10px;
        margin-right: 12px;
    }
    .doc-item {
        background-color: #fff;
        border-radius: 13px;
        border: 1px solid #e6edf2;
        transition: all 0.2s ease;
    }
    .doc-item:hover {
        background-color: #f8fbfd;
        border-color: #cbdce6;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.02);
    }
    /* สี Badge Soft */
    .bg-warning-subtle { background-color: #fff3cd !important; }
    .text-warning-emphasis { color: #997404 !important; }
    .bg-info-subtle { background-color: #cff4fc !important; }
    .text-info-emphasis { color: #055160 !important; }
    .bg-primary-subtle { background-color: #cfe2ff !important; }
    .text-primary-emphasis { color: #0a58ca !important; }
    .bg-success-subtle { background-color: #d1e7dd !important; }
    .text-success-emphasis { color: #0f5132 !important; }
    .bg-danger-subtle { background-color: #f8d7da !important; }
    .text-danger-emphasis { color: #842029 !important; }
</style>
@endsection
