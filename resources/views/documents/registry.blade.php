@extends('layouts.app')
@section('title', 'สมุดทะเบียนคุมเลขสารบรรณ')

@section('content')
@php
    $allowedTabs = ['pending', 'incoming', 'outgoing', 'internal'];
    $activeTab = in_array(request('tab'), $allowedTabs, true) ? request('tab') : 'pending';
    $selectedYear = (int) ($year ?? request('year', date('Y')));
    $thaiYear = $selectedYear + 543;
    $outgoingCount = collect($outgoingDocuments ?? [])->where('doc_type', 'outgoing')->count();
    $internalCount = collect($outgoingDocuments ?? [])->where('doc_type', 'internal')->count();
@endphp

<style>
    .registry-page { max-width: 1440px; margin: 0 auto; padding: 1.5rem; }
    .registry-hero { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.35rem 1.5rem; margin-bottom: 1.25rem; background: linear-gradient(135deg, #fff 35%, #effcf6); border: 1px solid #dce9e3; border-radius: 18px; box-shadow: var(--shadow-sm); }
    .registry-title-wrap { display: flex; align-items: flex-start; gap: .9rem; min-width: 0; }
    .registry-title-icon { width: 50px; height: 50px; display: grid; place-items: center; flex: 0 0 auto; color: #047857; background: #d1fae5; border-radius: 15px; font-size: 1.25rem; }
    .registry-title { margin: 0 0 .2rem; color: var(--primary-dark); font-size: clamp(1.25rem, 2vw, 1.65rem); font-weight: 800; }
    .registry-subtitle { margin: 0; color: var(--text-muted); }
    .registry-actions { display: flex; align-items: center; gap: .65rem; flex: 0 0 auto; }
    .registry-ledger-link { color: #713f12; background: #fef3c7; border-color: #fcd34d; }
    .registry-ledger-link:hover { color: #713f12; background: #fde68a; border-color: #f59e0b; }
    .registry-summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .registry-stat { width: 100%; display: flex; align-items: center; gap: .85rem; padding: 1rem; text-align: left; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: var(--shadow-sm); transition: .18s ease; }
    .registry-stat:hover { transform: translateY(-2px); border-color: var(--stat-color); box-shadow: var(--shadow-md); }
    .registry-stat:focus-visible { border-color: var(--stat-color); }
    .registry-stat-icon { width: 46px; height: 46px; display: grid; place-items: center; flex: 0 0 auto; color: var(--stat-color); background: var(--stat-bg); border-radius: 14px; font-size: 1.05rem; }
    .registry-stat-label { display: block; color: var(--text-secondary); font-size: .82rem; font-weight: 700; line-height: 1.35; }
    .registry-stat-value { display: block; margin-top: .12rem; color: var(--text-primary); font-size: 1.45rem; font-weight: 800; line-height: 1.2; }
    .registry-stat-unit { color: var(--text-muted); font-size: .78rem; font-weight: 500; }
    .registry-filter { margin-bottom: 1.25rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: var(--shadow-sm); }
    .registry-filter-grid { display: grid; grid-template-columns: minmax(260px, 1fr) minmax(190px, .35fr) auto auto; align-items: end; gap: .75rem; }
    .registry-field-label { display: block; margin-bottom: .35rem; color: var(--text-secondary); font-size: .78rem; font-weight: 700; }
    .registry-search { position: relative; }
    .registry-search i { position: absolute; left: .9rem; top: 50%; color: #94a3b8; transform: translateY(-50%); pointer-events: none; }
    .registry-search input { padding-left: 2.5rem; }
    .registry-tabs-wrap { margin-bottom: 1rem; overflow-x: auto; scrollbar-width: thin; }
    #registryTabs { display: grid; grid-template-columns: repeat(4, minmax(190px, 1fr)); gap: .4rem; min-width: 790px; margin: 0; padding: .4rem; border: 1px solid #e2e8f0; border-radius: 14px; background: #f8fafc; }
    #registryTabs .nav-link { width: 100%; min-height: 44px; display: flex; align-items: center; justify-content: center; gap: .4rem; padding: .65rem .75rem; color: var(--text-secondary); border-radius: 10px; font-size: .86rem; font-weight: 700; white-space: nowrap; }
    #registryTabs .nav-link:hover { color: var(--primary-dark); background: #fff; }
    #registryTabs .nav-link.active { color: #fff; background: var(--primary); box-shadow: 0 4px 10px rgba(2, 132, 199, .22); }
    #registryTabs .tab-count { min-width: 22px; height: 22px; display: inline-grid; place-items: center; padding: 0 6px; color: inherit; background: rgba(148, 163, 184, .18); border-radius: 999px; font-size: .72rem; }
    #registryTabs .nav-link.active .tab-count { background: rgba(255,255,255,.2); }
    .registry-table-card { overflow: hidden; border: 1px solid #e2e8f0 !important; border-radius: 16px !important; box-shadow: var(--shadow-sm) !important; }
    .registry-table-card .table-responsive { scrollbar-width: thin; }
    .registry-table-card table { min-width: 900px; }
    .registry-table-card thead th { padding-top: .85rem !important; padding-bottom: .85rem !important; color: #475569; background: #f8fafc; border-bottom: 1px solid #dfe7ef; font-size: .8rem; font-weight: 800; }
    .registry-table-card tbody td { padding-top: .85rem; padding-bottom: .85rem; border-color: #eef2f6; vertical-align: middle; }
    .registry-table-card tbody tr:hover td { background: #f8fcfa; }
    .registry-empty { padding: 3.5rem 1rem !important; color: #64748b; text-align: center; }
    .registry-empty i { display: block; margin-bottom: .8rem; color: #cbd5e1; font-size: 2.25rem; }
    .registry-view-btn { width: 36px; height: 36px; display: inline-grid; place-items: center; color: var(--primary-dark); border: 1px solid #cbd5e1; border-radius: 10px; text-decoration: none; }
    .registry-view-btn:hover { color: #fff; background: var(--primary); border-color: var(--primary); }
    .registry-type-badge { display: inline-flex; align-items: center; padding: .35rem .65rem; border: 1px solid; border-radius: 999px; font-size: .76rem; font-weight: 800; line-height: 1.2; }
    .registry-type-badge--internal { color: #6d28d9 !important; background: #f3e8ff !important; border-color: #d8b4fe !important; }
    .registry-type-badge--outgoing { color: #075985 !important; background: #e0f2fe !important; border-color: #7dd3fc !important; }
    .registry-internal-number { color: #5b21b6 !important; background: #f3e8ff !important; border-color: #d8b4fe !important; }
    @media (max-width: 991.98px) {
        .registry-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .registry-filter-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 767.98px) {
        .registry-page { padding: 1rem .75rem; }
        .registry-hero { align-items: stretch; flex-direction: column; padding: 1rem; }
        .registry-actions { flex-wrap: wrap; }
        .registry-actions > * { flex: 1 1 auto; }
        .registry-filter-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 479.98px) {
        .registry-summary { grid-template-columns: 1fr; gap: .65rem; }
        .registry-stat { padding: .8rem; }
    }
</style>

<div class="registry-page">
    <header class="registry-hero">
        <div class="registry-title-wrap">
            <div class="registry-title-icon" aria-hidden="true"><i class="fas fa-book-open"></i></div>
            <div>
                <h1 class="registry-title">สมุดทะเบียนคุมเลขสารบรรณ</h1>
                <p class="registry-subtitle">ตรวจสอบทะเบียนรับ–ส่ง ออกเลขเอกสาร และติดตามสถานะในที่เดียว</p>
            </div>
        </div>
        <div class="registry-actions">
            <a href="{{ route('documents.number_ledger') }}" class="ds-btn registry-ledger-link">
                <i class="fas fa-list-ol" aria-hidden="true"></i>เปิดสมุดรันเลขและจองเลข
            </a>
            <a href="{{ route('home') }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก
            </a>
        </div>
    </header>

    <section class="registry-summary" aria-label="สรุปทะเบียน พ.ศ. {{ $thaiYear }}">
        <button type="button" class="registry-stat" style="--stat-color:#d97706;--stat-bg:#fef3c7" data-registry-tab="pending">
            <span class="registry-stat-icon"><i class="fas fa-hourglass-half" aria-hidden="true"></i></span>
            <span><span class="registry-stat-label">รอออกเลขทะเบียน</span><span class="registry-stat-value">{{ $summary['pending'] ?? 0 }} <span class="registry-stat-unit">รายการ</span></span></span>
        </button>
        <button type="button" class="registry-stat" style="--stat-color:#059669;--stat-bg:#d1fae5" data-registry-tab="incoming">
            <span class="registry-stat-icon"><i class="fas fa-inbox" aria-hidden="true"></i></span>
            <span><span class="registry-stat-label">หนังสือรับเข้า พ.ศ. {{ $thaiYear }}</span><span class="registry-stat-value">{{ $summary['incoming'] ?? 0 }} <span class="registry-stat-unit">รายการ</span></span></span>
        </button>
        <button type="button" class="registry-stat" style="--stat-color:#0284c7;--stat-bg:#e0f2fe" data-registry-tab="outgoing">
            <span class="registry-stat-icon"><i class="fas fa-paper-plane" aria-hidden="true"></i></span>
            <span><span class="registry-stat-label">หนังสือส่งออก พ.ศ. {{ $thaiYear }}</span><span class="registry-stat-value">{{ $outgoingCount }} <span class="registry-stat-unit">รายการ</span></span></span>
        </button>
        <button type="button" class="registry-stat" style="--stat-color:#7c3aed;--stat-bg:#ede9fe" data-registry-tab="internal">
            <span class="registry-stat-icon"><i class="fas fa-file-lines" aria-hidden="true"></i></span>
            <span><span class="registry-stat-label">บันทึกข้อความภายใน</span><span class="registry-stat-value">{{ $internalCount }} <span class="registry-stat-unit">รายการ</span></span></span>
        </button>
    </section>

    <section class="registry-filter" aria-label="ค้นหาและกรองทะเบียน">
        <form method="GET" action="{{ route('documents.registry') }}" class="registry-filter-grid">
            <input type="hidden" name="tab" id="registryFilterTab" value="{{ $activeTab }}">
            <div>
                <label for="registrySearch" class="registry-field-label">ค้นหาเอกสาร</label>
                <div class="registry-search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input id="registrySearch" type="search" name="search" class="ds-form-control" placeholder="เลขรับ, เลขหนังสือ, เรื่อง หรือหน่วยงาน" value="{{ request('search') }}">
                </div>
            </div>
            <div>
                <label for="registryYear" class="registry-field-label">ปีที่ลงทะเบียน</label>
                <select id="registryYear" name="year" class="ds-form-control">
                    @php $currentYear = (int) date('Y'); @endphp
                    @for($i = 0; $i <= 4; $i++)
                        <option value="{{ $currentYear - $i }}" {{ $selectedYear === ($currentYear - $i) ? 'selected' : '' }}>พ.ศ. {{ ($currentYear - $i) + 543 }}</option>
                    @endfor
                </select>
            </div>
            <button type="submit" class="ds-btn ds-btn-primary"><i class="fas fa-filter" aria-hidden="true"></i>กรองข้อมูล</button>
            @if(request()->filled('search') || request()->filled('year'))
                <a href="{{ route('documents.registry', ['tab' => $activeTab]) }}" class="ds-btn ds-btn-secondary"><i class="fas fa-rotate-left" aria-hidden="true"></i>ล้างตัวกรอง</a>
            @endif
        </form>
    </section>

    <div class="registry-tabs-wrap">
        <ul class="nav nav-pills" id="registryTabs" role="tablist" aria-label="ประเภททะเบียน">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'pending' ? 'active' : '' }}" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="{{ $activeTab === 'pending' ? 'true' : 'false' }}">
                    <i class="fas fa-keyboard" aria-hidden="true"></i>รอออกเลข <span class="tab-count">{{ $summary['pending'] ?? 0 }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'incoming' ? 'active' : '' }}" id="incoming-tab" data-bs-toggle="pill" data-bs-target="#incoming" type="button" role="tab" aria-controls="incoming" aria-selected="{{ $activeTab === 'incoming' ? 'true' : 'false' }}">
                    <i class="fas fa-inbox" aria-hidden="true"></i>ทะเบียนรับ <span class="tab-count">{{ $summary['incoming'] ?? 0 }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'outgoing' ? 'active' : '' }}" id="outgoing-tab" data-bs-toggle="pill" data-bs-target="#outgoing" type="button" role="tab" aria-controls="outgoing" aria-selected="{{ $activeTab === 'outgoing' ? 'true' : 'false' }}">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>ทะเบียนส่ง <span class="tab-count">{{ $outgoingCount }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'internal' ? 'active' : '' }}" id="internal-tab" data-bs-toggle="pill" data-bs-target="#internal" type="button" role="tab" aria-controls="internal" aria-selected="{{ $activeTab === 'internal' ? 'true' : 'false' }}">
                    <i class="fas fa-file-alt" aria-hidden="true"></i>บันทึกข้อความ <span class="tab-count">{{ $internalCount }}</span>
                </button>
            </li>
        </ul>
    </div>

        <div class="tab-content" id="registryTabsContent">
            
            {{-- 🟢 แท็บ 1: เอกสารรอออกเลขทะเบียน --}}
            <div class="tab-pane fade {{ $activeTab === 'pending' ? 'show active' : '' }}" id="pending" role="tabpanel" aria-labelledby="pending-tab" tabindex="0">
                <div class="card registry-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">วันที่ส่งเรื่อง</th>
                                    <th>ประเภทงาน</th>
                                    <th>ส่วนราชการ (ผู้เสนอ)</th>
                                    <th>เรื่อง</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingDocuments ?? [] as $doc)
                                    <tr>
                                        <td class="ps-4 text-muted small">{{ \Carbon\Carbon::parse($doc->updated_at)->addYears(543)->locale('th')->translatedFormat('j M y / H:i') }} น.</td>
                                        <td>
                                            <span class="registry-type-badge {{ $doc->doc_type === 'internal' ? 'registry-type-badge--internal' : 'registry-type-badge--outgoing' }}">
                                                {{ $doc->doc_type === 'internal' ? 'บันทึกภายใน' : 'หนังสือส่งออก' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $doc->creator->department ?? '-' }}</div>
                                            <div class="small text-muted">{{ $doc->creator->name ?? '-' }}</div>
                                        </td>
                                        <td>
                                            {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ชื่อเรื่องเป็น UUID --}}
                                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="fw-bold text-primary text-decoration-none text-wrap" style="max-width: 300px; display: inline-block;">
                                                {{ $doc->title }}
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#numberingModal{{ $doc->id }}">
                                                <i class="fas fa-pen me-1"></i> ออกเลข
                                            </button>
                                        </td>
                                    </tr>

                                @empty
                                    <tr><td colspan="5" class="registry-empty"><i class="fas fa-circle-check" aria-hidden="true"></i><strong>ไม่มีงานค้าง</strong><br><span class="small">เอกสารที่ผ่านการอนุมัติและรอออกเลขจะแสดงที่นี่</span></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Modal ต้องอยู่นอก table เพื่อป้องกัน browser แยก form ออกจากเนื้อหา modal --}}
                @foreach($pendingDocuments ?? [] as $doc)
                    <div class="modal fade" id="numberingModal{{ $doc->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                                <div class="modal-header border-bottom-0 pb-0">
                                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-keyboard text-success me-2"></i>ลงทะเบียนออกเลขหนังสือ</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                                </div>
                                <form action="{{ route('documents.assign_number', $doc->uuid ?? $doc->id) }}" method="POST" class="js-numbering-form">
                                    @csrf
                                    <div class="modal-body py-4">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small fw-bold">เรื่อง</label>
                                            <div class="p-2 bg-light rounded border fw-bold text-dark">{{ $doc->title }}</div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label fw-bold text-dark">เลขถัดไปจากสมุดคุมเลข</label>
                                            <input type="hidden" name="running_number" class="js-running-number">
                                            <div class="input-group">
                                                <input type="text" name="doc_number" class="form-control form-control-lg text-primary fw-bold js-document-number"
                                                       placeholder="กำลังตรวจสอบสมุดเลข..." required readonly>
                                                <button type="button" class="btn btn-outline-primary js-load-ledger-number"
                                                        data-url="{{ route('documents.api_next_number', ['type' => $doc->doc_type, 'document' => $doc->uuid ?? $doc->id]) }}">
                                                    <i class="fas fa-sync-alt me-1"></i> ดึงเลขใหม่
                                                </button>
                                            </div>
                                            <small class="js-ledger-status text-muted mt-2 d-block">ระบบจะดึงเลขว่างถัดไปจากสมุดคุมเลขชุดเดียวกัน</small>
                                            <a href="{{ route('documents.number_ledger', ['type' => $doc->doc_type]) }}" target="_blank" class="small text-decoration-none d-inline-block mt-1">
                                                <i class="fas fa-book-open me-1"></i> เปิดสมุดคุมเลขเพื่อตรวจสอบ
                                            </a>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-top-0 pt-0 justify-content-center">
                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm js-submit-number" disabled>ยืนยันออกเลขจากสมุด</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 🟢 แท็บ 2: ทะเบียนหนังสือรับ (เข้า) --}}
            <div class="tab-pane fade {{ $activeTab === 'incoming' ? 'show active' : '' }}" id="incoming" role="tabpanel" aria-labelledby="incoming-tab" tabindex="0">
                <div class="card registry-table-card">
                    <div class="bg-white p-3 border-bottom d-flex justify-content-between align-items-center gap-2">
                        <span class="text-secondary small"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>เอกสารรับเข้าที่ลงทะเบียนแล้ว</span>
                        <a href="{{ route('documents.create_incoming') }}" class="ds-btn ds-btn-primary">
                            <i class="fas fa-plus me-1"></i> ลงรับหนังสือใหม่
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">เลขรับ</th>
                                    <th>วันที่รับ</th>
                                    <th>เลขที่หนังสือ</th>
                                    <th>จาก (หน่วยงาน)</th>
                                    <th>เรื่อง</th>
                                    <th>มอบหมายกองงาน</th>
                                    <th class="text-center">ดู</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($incomingDocuments ?? [] as $doc)
                                    <tr>
                                        <td class="ps-4"><span class="badge bg-success-subtle text-success-emphasis border rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">{{ $doc->formatted_receive_number ?? '-' }}</span></td>
                                        <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->receive_date ?? $doc->created_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                        <td><span class="text-dark small">{{ $doc->doc_number ?? '-' }}</span></td>
                                        <td><div class="text-dark small text-wrap" style="max-width: 150px;">{{ $doc->doc_from ?? '-' }}</div></td>
                                        <td>
                                            {{-- 🌟 จุดที่ 3: เปลี่ยนลิงก์ชื่อเรื่องเป็น UUID --}}
                                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="fw-bold text-primary text-decoration-none text-wrap" style="max-width: 250px; display: inline-block;">
                                                {{ $doc->title }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($doc->assigned_to)
                                                <span class="badge bg-warning text-dark border shadow-sm text-xs">{{ $doc->assigned_to }}</span>
                                            @else
                                                <span class="badge bg-light text-secondary border">- รอมอบหมาย -</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{-- 🌟 จุดที่ 4: เปลี่ยนลิงก์ปุ่มดูเป็น UUID --}}
                                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" rel="noopener" class="registry-view-btn" aria-label="ดูเอกสาร {{ $doc->title }}" title="ดูรายละเอียด"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="registry-empty"><i class="fas fa-inbox" aria-hidden="true"></i><strong>ยังไม่มีทะเบียนหนังสือรับ</strong><br><span class="small">ลองเปลี่ยนปีหรือล้างตัวกรอง</span></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 🟢 แท็บ 3: ทะเบียนหนังสือส่ง (ออก) --}}
            <div class="tab-pane fade {{ $activeTab === 'outgoing' ? 'show active' : '' }}" id="outgoing" role="tabpanel" aria-labelledby="outgoing-tab" tabindex="0">
                <div class="card registry-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">เลขที่หนังสือออก</th>
                                    <th>วันที่ส่ง (ลงเลข)</th>
                                    <th>ส่วนราชการผู้เสนอ</th>
                                    <th>เรื่อง</th>
                                    <th>ถึง (ผู้รับ)</th>
                                    <th>สถานะ</th>
                                    <th class="text-center">ดู</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $hasOutgoing = false; @endphp
                                @foreach($outgoingDocuments ?? [] as $doc)
                                    @if($doc->doc_type === 'outgoing')
                                        @php $hasOutgoing = true; @endphp
                                        <tr>
                                            <td class="ps-4"><span class="badge bg-info-subtle text-info-emphasis border rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">{{ $doc->formatted_doc_number }}</span></td>
                                            <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->updated_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                            <td><div class="text-dark small fw-bold">{{ $doc->creator->department ?? '-' }}</div></td>
                                            <td><div class="fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $doc->title }}</div></td>
                                            <td><div class="text-muted small text-wrap" style="max-width: 150px;">{{ $doc->doc_to ?? $doc->send_to ?? '-' }}</div></td>
                                            <td>
                                                @if($doc->status === 'CANCELED')
                                                    <span class="badge bg-dark">ยกเลิก (เลขเสีย)</span>
                                                @else
                                                    <span class="badge bg-success">ออกเลขแล้ว</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{-- 🌟 จุดที่ 5: เปลี่ยนลิงก์ปุ่มดูเป็น UUID --}}
                                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" rel="noopener" class="registry-view-btn" aria-label="ดูเอกสาร {{ $doc->title }}" title="ดูรายละเอียด"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                @if(!$hasOutgoing)
                                    <tr><td colspan="7" class="registry-empty"><i class="fas fa-paper-plane" aria-hidden="true"></i><strong>ยังไม่มีทะเบียนหนังสือส่ง</strong><br><span class="small">ลองเปลี่ยนปีหรือล้างตัวกรอง</span></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 🟢 แท็บ 4: ทะเบียนบันทึกข้อความ (ภายใน) --}}
            <div class="tab-pane fade {{ $activeTab === 'internal' ? 'show active' : '' }}" id="internal" role="tabpanel" aria-labelledby="internal-tab" tabindex="0">
                <div class="card registry-table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">เลขที่บันทึกภายใน</th>
                                    <th>วันที่ลงเลข</th>
                                    <th>หน่วยงานผู้เสนอ</th>
                                    <th>เรื่อง</th>
                                    <th>เรียน (ถึง)</th>
                                    <th>สถานะคุมเล่ม</th>
                                    <th class="text-center">ดู</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $hasInternal = false; @endphp
                                @foreach($outgoingDocuments ?? [] as $doc)
                                    @if($doc->doc_type === 'internal')
                                        @php $hasInternal = true; @endphp
                                        <tr>
                                            <td class="ps-4"><span class="badge registry-internal-number border rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">{{ $doc->formatted_doc_number }}</span></td>
                                            <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->updated_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                            <td><div class="text-dark small fw-bold">{{ $doc->creator->department ?? '-' }}</div></td>
                                            <td><div class="fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $doc->title }}</div></td>
                                            <td><div class="text-muted small text-wrap" style="max-width: 150px;">{{ $doc->doc_to ?? 'นายกองค์การบริหารส่วนตำบล' }}</div></td>
                                            <td><span class="badge bg-success shadow-sm">บันทึกข้อความแล้ว</span></td>
                                            <td class="text-center">
                                                {{-- 🌟 จุดที่ 6: เปลี่ยนลิงก์ปุ่มดูเป็น UUID --}}
                                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" rel="noopener" class="registry-view-btn" aria-label="ดูเอกสาร {{ $doc->title }}" title="ดูรายละเอียด"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                @if(!$hasInternal)
                                    <tr><td colspan="7" class="registry-empty"><i class="fas fa-file-lines" aria-hidden="true"></i><strong>ยังไม่มีทะเบียนบันทึกข้อความ</strong><br><span class="small">ลองเปลี่ยนปีหรือล้างตัวกรอง</span></td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterTab = document.getElementById('registryFilterTab');

        document.querySelectorAll('#registryTabs [data-bs-toggle="pill"]').forEach(function(tabButton) {
            tabButton.addEventListener('shown.bs.tab', function(event) {
                const tabName = event.target.dataset.bsTarget.replace('#', '');
                filterTab.value = tabName;

                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url);
            });
        });

        document.querySelectorAll('[data-registry-tab]').forEach(function(summaryButton) {
            summaryButton.addEventListener('click', function() {
                const target = document.getElementById(`${summaryButton.dataset.registryTab}-tab`);
                if (!target) return;
                bootstrap.Tab.getOrCreateInstance(target).show();
                document.querySelector('.registry-tabs-wrap')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });

        async function loadLedgerNumber(form) {
            const button = form.querySelector('.js-load-ledger-number');
            const numberInput = form.querySelector('.js-document-number');
            const runningInput = form.querySelector('.js-running-number');
            const status = form.querySelector('.js-ledger-status');
            const submit = form.querySelector('.js-submit-number');

            button.disabled = true;
            submit.disabled = true;
            numberInput.value = '';
            runningInput.value = '';
            status.className = 'js-ledger-status text-muted mt-2 d-block';
            status.textContent = 'กำลังตรวจสอบเลขล่าสุดจากสมุดคุมเลข...';

            try {
                const response = await fetch(button.dataset.url, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error('ไม่สามารถอ่านสมุดคุมเลขได้');

                const data = await response.json();
                numberInput.value = data.formatted;
                runningInput.value = data.next_number;
                status.className = 'js-ledger-status text-success mt-2 d-block fw-bold';
                status.textContent = `พร้อมใช้เลขลำดับ ${data.next_number} · ปีงบประมาณ ${Number(data.fiscal_year) + 543} · ชุด ${data.scope}`;
                submit.disabled = false;
            } catch (error) {
                status.className = 'js-ledger-status text-danger mt-2 d-block fw-bold';
                status.textContent = error.message || 'ไม่สามารถเชื่อมต่อสมุดคุมเลขได้';
            } finally {
                button.disabled = false;
            }
        }

        document.querySelectorAll('.js-numbering-form').forEach(function(form) {
            const modal = form.closest('.modal');
            modal.addEventListener('show.bs.modal', function() { loadLedgerNumber(form); });
            form.querySelector('.js-load-ledger-number').addEventListener('click', function() { loadLedgerNumber(form); });
        });

        @if(session('success'))
            Swal.fire({ icon: 'success', title: 'ดำเนินการสำเร็จ', text: @json(session('success')), timer: 2200, showConfirmButton: false });
        @endif
        @if(session('error'))
            Swal.fire({ icon: 'error', title: 'ไม่สามารถดำเนินการได้', text: @json(session('error')), confirmButtonText: 'ตกลง' });
        @endif
    });
</script>
@endsection
