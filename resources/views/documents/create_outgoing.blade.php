@extends('layouts.app')
@section('title', 'สร้างหนังสือส่งออก')

@section('content')

@php
    $initialDocumentSource = old('document_source', 'system');
@endphp

{{-- ✅ Flatpickr CSS + Thai locale --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.min.css">

<style>
    @font-face {
        font-family: 'TH Sarabun New';
        src: url('{{ asset('fonts/THSarabunNew.ttf') }}') format('truetype');
        font-weight: normal;
        font-style: normal;
        font-display: swap;
    }
    @font-face {
        font-family: 'TH Sarabun New';
        src: url('{{ asset('fonts/THSarabunNew_Bold.ttf') }}') format('truetype');
        font-weight: bold;
        font-style: normal;
        font-display: swap;
    }
    /* 🌟 จัดระเบียบ Pop-up ให้สวยงามและมี Scrollbar เรียบร้อย */
    .floating-popup {
        position: absolute; 
        z-index: 1000; 
        width: 100%; 
        max-height: 280px; 
        overflow-y: auto;
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        background: white;
        padding: 12px; 
        margin-top: 8px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.1); 
        display: none;
    }
    .popup-item {
        padding: 10px 14px; 
        border-radius: 8px; 
        border: 1px solid transparent;
        cursor: pointer; 
        transition: all 0.2s; 
        margin-bottom: 6px;
        background: #f8fafc;
    }
    .popup-item:hover { 
        background: #e0f2fe; 
        border-color: #bae6fd; 
        transform: translateY(-1px);
    }

    /* 🌟 Input & Dropzone UI */
    .form-control, .form-select { border-radius: 8px; font-size: 14.5px; padding: 10px 16px; border-color: #cbd5e1; }
    .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1); }
    
    #dropzone {
        border: 2px dashed #cbd5e1; background: #f8fafc; cursor: pointer; transition: 0.3s;
    }
    #dropzone:hover, #dropzone.dragover {
        border-color: var(--primary); background: #f0f9ff;
    }

    /* ซ่อนส่วนจัดทำเนื้อหาทั้งชุดทันทีเมื่อใช้ PDF ที่จัดทำไว้แล้ว แม้ JavaScript ยังโหลดไม่เสร็จ */
    #outgoingForm:has(input[name="document_source"][value="upload"]:checked) #systemComposeSection {
        display: none !important;
    }

    /* 🌟 Flatpickr UI Customization */
    .flatpickr-input { background: white !important; cursor: pointer !important; }
    .flatpickr-calendar { border-radius: 16px !important; box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important; font-family: inherit !important; }
    .flatpickr-day.selected, .flatpickr-day.selected:hover { background: var(--primary) !important; border-color: var(--primary) !important; }
    .flatpickr-day:hover { background: #e0f2fe !important; }
    .flatpickr-months .flatpickr-month { background: var(--primary) !important; color: white !important; border-radius: 16px 16px 0 0; }
    .flatpickr-current-month .flatpickr-monthDropdown-months, .flatpickr-current-month input.cur-year { color: white !important; background: transparent !important; }
    .date-input-wrapper { position: relative; }
    .date-input-wrapper .cal-icon {
        position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
        color: #94a3b8; pointer-events: none; font-size: 15px;
    }
    .source-option { border: 2px solid #e2e8f0; border-radius: 14px; cursor: pointer; transition: .2s; }
    .source-option:has(input:checked) { border-color: var(--primary); background: #f0f9ff; box-shadow: 0 0 0 3px rgba(2,132,199,.08); }
    .outgoing-paper { width: 100%; max-width: 794px; min-height: 1040px; margin: 0 auto; padding: 56px 64px 60px 88px; background: #fff; color: #111; font-family: 'TH Sarabun New', Tahoma, sans-serif; font-size: 22px; line-height: 1.35; box-shadow: 0 8px 30px rgba(15,23,42,.12); }
    .outgoing-paper__header { min-height:125px; text-align:center; }
    .outgoing-paper__garuda { width:100px; margin-top:-12px; }
    .outgoing-paper__topline { display:grid; grid-template-columns:1fr 1fr; gap:35px; align-items:start; margin-bottom:14px; }
    .outgoing-paper__address { white-space:pre-line; }
    .outgoing-paper__row { display:grid; grid-template-columns:78px 1fr; gap:8px; margin-bottom:7px; }
    .outgoing-paper__body { margin-top:22px; white-space:pre-line; text-align:justify; min-height:250px; }
    .outgoing-paper__signature { width:52%; margin-left:auto; text-align:center; margin-top:26px; white-space:pre-line; }
    .outgoing-paper__contact { margin-top:48px; white-space:pre-line; }
    .draft-indicator { color:#047857; font-size:13px; font-weight:700; }
    .outgoing-compose-wrap { background:#eef2f6; border-radius:0 0 16px 16px; padding:24px; }
    .outgoing-compose-paper {
        max-width:794px; margin:0 auto; padding:36px 54px 44px; background:#fff; color:#111;
        border:1px solid #d7dde5; box-shadow:0 8px 24px rgba(15,23,42,.08);
        font-family:'TH Sarabun New', 'MySarabun', Tahoma, sans-serif; font-size:21px; line-height:1.25;
    }
    .outgoing-compose-heading { text-align:center; min-height:112px; }
    .outgoing-compose-heading img { width:92px; height:auto; }
    .outgoing-compose-note { margin:0 0 22px; padding:9px 12px; border-radius:8px; background:#eff6ff; color:#1e40af; font-family:inherit; font-size:16px; }
    .outgoing-compose-topline { display:grid; grid-template-columns:1fr 1fr; gap:36px; align-items:start; margin:2px 0 12px; }
    .outgoing-compose-docno { padding-top:6px; white-space:nowrap; }
    .outgoing-compose-number-line { display:flex; align-items:center; flex-wrap:nowrap; gap:6px; min-width:0; }
    .outgoing-compose-number-line > strong { flex:0 0 auto; }
    .outgoing-compose-number-pending {
        display:block; flex:1 1 auto; min-width:220px; padding:4px 10px 5px;
        border-bottom:1px dotted #777; color:#8a8f98; font-weight:400; white-space:nowrap;
    }
    .outgoing-compose-number-line .input-group { display:flex; flex:0 0 126px; width:126px; margin:0; flex-wrap:nowrap !important; }
    .outgoing-compose-number-line .outgoing-paper-control { flex:1 1 auto; width:1%; min-width:0; text-align:center; }
    .outgoing-compose-number-line .input-group .btn { flex:0 0 auto; }
    .outgoing-compose-number-help { display:block; margin:6px 0 0 20px; color:#64748b; font-size:15px; white-space:normal; }
    .outgoing-compose-address { min-width:0; }
    .outgoing-compose-date { display:flex; align-items:center; justify-content:center; gap:10px; margin:8px 0 18px; text-align:center; }
    .outgoing-form-row { display:grid; grid-template-columns:115px minmax(0,1fr); gap:10px; align-items:start; margin-bottom:13px; }
    .outgoing-form-row > label { padding-top:7px; font-weight:700; white-space:nowrap; }
    .outgoing-paper-control {
        width:100%; min-height:39px; padding:5px 8px; border:0; border-bottom:1px dotted #111;
        border-radius:0; background:transparent; color:#111; box-shadow:none !important;
        font-family:inherit; font-size:20px; line-height:1.2;
    }
    .outgoing-paper-control:focus { border-bottom:2px solid var(--primary); outline:0; background:#f8fbff; }
    textarea.outgoing-paper-control { resize:vertical; }
    .outgoing-content-label { display:flex; align-items:center; justify-content:space-between; margin:22px 0 8px; padding-top:14px; border-top:1px solid #cbd5e1; }
    .outgoing-paragraph-block { display:grid; grid-template-columns:145px minmax(0,1fr); gap:12px; align-items:stretch; margin-bottom:10px; }
    .outgoing-paragraph-label { display:flex; flex-direction:column; justify-content:center; padding:12px; border-left:4px solid #38bdf8; background:#f0f9ff; color:#075985; font-family:system-ui,sans-serif; font-size:13px; line-height:1.35; }
    .outgoing-paragraph-label strong { font-size:14px; }
    .outgoing-paragraph-block .outgoing-content-editor { min-height:125px; padding:14px 18px; }
    .outgoing-content-editor {
        width:100%; min-height:300px; padding:22px; border:1px dashed #94a3b8; border-radius:6px;
        background:#fafafa; color:#111; font-family:inherit; font-size:21px; line-height:1.45; resize:vertical;
    }
    .outgoing-content-editor:focus { outline:0; border-color:var(--primary); background:#fff; box-shadow:0 0 0 3px rgba(2,132,199,.1); }
    .outgoing-signature-area { width:55%; margin:26px 0 4px auto; text-align:center; }
    .outgoing-signature-area .outgoing-paper-control { text-align:center; }
    .outgoing-signature-placeholder { height:54px; display:flex; align-items:end; justify-content:center; color:#64748b; font-size:16px; }
    .outgoing-contact-area { margin-top:34px; max-width:52%; }
    .outgoing-compose-paper .input-group { border-bottom:1px dotted #111; }
    .outgoing-compose-paper .input-group .outgoing-paper-control { border-bottom:0; }
    .outgoing-compose-paper .input-group .btn { border:0; border-left:1px solid #dbeafe; border-radius:0; font-family:system-ui,sans-serif; font-size:13px; }
    @media (max-width: 768px) { .outgoing-paper { padding:35px 28px; font-size:18px; min-height:850px; } }
    @media (max-width: 768px) {
        .outgoing-compose-wrap { padding:12px; }
        .outgoing-compose-paper { padding:24px 18px; font-size:18px; }
        .outgoing-form-row { grid-template-columns:82px minmax(0,1fr); gap:5px; }
        .outgoing-compose-topline { grid-template-columns:1fr; gap:10px; }
        .outgoing-paragraph-block { grid-template-columns:1fr; }
        .outgoing-paper-control, .outgoing-content-editor { font-size:18px; }
        .outgoing-signature-area, .outgoing-contact-area { width:100%; max-width:none; }
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mx-auto" style="max-width: 950px;">

        {{-- 🌟 Header & Badges --}}
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                    <i class="fas fa-paper-plane text-primary me-2"></i>สร้างหนังสือส่งออก
                </h4>
                <div class="text-muted small">กรอกข้อมูลให้ครบถ้วนและแนบไฟล์ PDF เพื่อเตรียมเสนอลงนาม</div>
            </div>
            <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก
            </a>
        </div>
        </div>

        {{-- 🌟 แจ้งเตือน Validation --}}
        @if ($errors->any())
            <div class="alert alert-danger shadow-sm mb-4" style="border-radius: 12px; border-left: 5px solid #dc2626;">
                <div class="fw-bold mb-2"><i class="fas fa-exclamation-circle me-1"></i> พบข้อผิดพลาด:</div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- 🌟 Main Form --}}
        <form action="{{ route('documents.store_outgoing') }}" method="POST" enctype="multipart/form-data" id="outgoingForm" novalidate>
            @csrf
            {{-- Hidden field ส่งวันที่แบบ ค.ศ. เข้าฐานข้อมูล --}}
            <input type="hidden" name="doc_date" id="doc_date_hidden" required data-required-label="วันที่ออกเอกสาร" data-validation-target="#doc_date_picker">

            <div id="step1">

                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-file-circle-plus text-primary me-2"></i>วิธีจัดทำหนังสือ</h6>
                            <span id="draftIndicator" class="draft-indicator"><i class="fas fa-cloud me-1"></i>บันทึกร่างอัตโนมัติในเครื่องนี้</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="source-option d-flex gap-3 p-3 h-100">
                                    <input class="form-check-input mt-1" type="radio" name="document_source" value="system" {{ $initialDocumentSource === 'system' ? 'checked' : '' }}>
                                    <span><strong class="d-block text-dark">สร้างหนังสือในระบบ</strong><small class="text-muted">กรอกเนื้อหา ดูตัวอย่าง A4 และให้ระบบสร้าง PDF</small></span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="source-option d-flex gap-3 p-3 h-100">
                                    <input class="form-check-input mt-1" type="radio" name="document_source" value="upload" {{ $initialDocumentSource === 'upload' ? 'checked' : '' }}>
                                    <span><strong class="d-block text-dark">อัปโหลด PDF ที่จัดทำแล้ว</strong><small class="text-muted">ใช้ไฟล์จาก Word หรือระบบภายนอก</small></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 📍 Section 2: ประเภทและชั้นความสำคัญ --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-tags text-primary me-2"></i>1. ประเภทและชั้นความสำคัญ</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted mb-2">ประเภทหนังสือ <span class="text-danger">*</span></label>
                                <select name="doc_type_category" id="doc_type_category" class="form-select shadow-sm fw-bold text-dark">
                                    <option value="หนังสือภายนอก (กระดาษตราครุฑ)">หนังสือภายนอก (กระดาษตราครุฑ)</option>
                                    <option value="หนังสือภายใน / บันทึกข้อความ">หนังสือภายใน / บันทึกข้อความ</option>
                                    <option value="หนังสือประทับตรา">หนังสือประทับตรา</option>
                                    <option value="หนังสือสั่งการ (คำสั่ง / ระเบียบ / ข้อบังคับ)">หนังสือสั่งการ (คำสั่ง / ระเบียบ / ข้อบังคับ)</option>
                                    <option value="หนังสือประชาสัมพันธ์ (ประกาศ / แถลงการณ์)">หนังสือประชาสัมพันธ์ (ประกาศ / แถลงการณ์)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-2">ชั้นความเร็ว</label>
                                <select name="doc_speed" id="doc_speed" class="form-select shadow-sm" onchange="updateBadges()">
                                    <option value="ปกติ">ปกติ</option>
                                    <option value="ด่วน">ด่วน</option>
                                    <option value="ด่วนมาก">ด่วนมาก</option>
                                    <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-2">ชั้นความลับ</label>
                                <select name="doc_secret" id="doc_secret" class="form-select shadow-sm text-dark" style="transition: 0.3s;" onchange="updateBadges()">
                                    <option value="ไม่มีชั้นความลับ">ไม่มีชั้นความลับ (ปกติ)</option>
                                    <option value="ลับ">ลับ</option>
                                    <option value="ลับมาก">ลับมาก</option>
                                    <option value="ลับที่สุด">ลับที่สุด</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 📍 Section 3: เนื้อหา และการส่ง --}}
                <div class="card border-0 mb-4 shadow-sm" id="systemComposeSection" style="border-radius:16px;" {{ $initialDocumentSource === 'upload' ? 'hidden' : '' }}>
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-pen-nib text-primary me-2"></i>2. เนื้อหาและการลงนาม</h6>
                    </div>
                    <div class="outgoing-compose-wrap">
                        <div class="outgoing-compose-paper">
                            <div class="outgoing-compose-heading">
                                <img src="{{ asset('images/krut.png') }}" alt="ตราครุฑ">
                            </div>
                            <div class="outgoing-compose-topline">
                                <div class="outgoing-compose-docno">
                                    <input type="hidden" name="running_number" id="running_number" value="{{ old('running_number') }}">
                                    <div class="outgoing-compose-number-line">
                                        <strong>ที่</strong>
                                        <span class="outgoing-compose-number-pending">รอธุรการลงทะเบียนเลข</span>
                                    </div>
                                </div>
                                <div class="outgoing-compose-address system-source-field">
                                    <textarea name="sender_address" id="sender_address" class="outgoing-paper-control" rows="3" aria-label="ส่วนราชการเจ้าของหนังสือ" placeholder="ส่วนราชการเจ้าของหนังสือ&#10;ที่อยู่ และรหัสไปรษณีย์">{{ old('sender_address', config('edoc.organization.outgoing_address')) }}</textarea>
                                </div>
                            </div>
                            <div class="outgoing-compose-date">
                                <div class="date-input-wrapper" style="width:280px;">
                                    <input type="text" id="doc_date_picker" class="outgoing-paper-control system-compose-input text-center fw-bold" placeholder="เลือกวันที่ออกเอกสาร" readonly value="{{ old('doc_date') ? \Carbon\Carbon::parse(old('doc_date'))->format('d/m/') . (\Carbon\Carbon::parse(old('doc_date'))->year + 543) : '' }}">
                                    <i class="fas fa-calendar-alt cal-icon"></i>
                                </div>
                            </div>
                            <div class="outgoing-compose-note"><i class="fas fa-circle-info me-1"></i> แบบร่างนี้จัดตำแหน่งตามหนังสือภายนอก ระบบจะสร้าง PDF ขนาด A4 ให้อัตโนมัติ</div>

                            <div class="outgoing-form-row">
                                <label for="title">เรื่อง <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="outgoing-paper-control system-compose-input" placeholder="ระบุชื่อเรื่องของหนังสือ" required value="{{ old('title') }}" data-required-label="เรื่อง">
                            </div>

                            <div class="outgoing-form-row position-relative">
                                <label for="doc_to">เรียน <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="doc_to" id="doc_to" class="outgoing-paper-control system-compose-input" placeholder="เช่น ผู้ว่าราชการจังหวัดยะลา" required value="{{ old('doc_to') }}" data-required-label="ผู้รับ (เรียน)">
                                    <button type="button" onclick="togglePopup('addrBookPopup')" class="btn btn-outline-primary bg-white px-3"><i class="fas fa-address-book me-1"></i>สมุดที่อยู่</button>
                                </div>
                                <div id="addrBookPopup" class="floating-popup">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div style="font-size:14px;font-weight:700;color:var(--primary-dark);"><i class="fas fa-address-book me-1"></i>สมุดที่อยู่หน่วยงาน</div>
                                        <button type="button" class="btn-close" style="font-size:10px;" onclick="togglePopup('addrBookPopup')"></button>
                                    </div>
                                    <input type="text" id="addrSearch" class="form-control form-control-sm mb-3 shadow-none border-primary" placeholder="ค้นหาชื่อผู้รับ..." onkeyup="filterAddr()">
                                    <div id="addrList"></div>
                                </div>
                            </div>

                            <div class="outgoing-form-row position-relative">
                                <label for="reference_doc">อ้างถึง</label>
                                <div class="input-group">
                                    <input type="text" name="reference_doc" id="reference_doc" class="outgoing-paper-control system-compose-input" placeholder="เลขที่หนังสืออ้างอิง (ถ้ามี)" value="{{ old('reference_doc') }}">
                                    <button type="button" onclick="togglePopup('refPopup')" class="btn btn-outline-primary bg-white px-3"><i class="fas fa-search me-1"></i>เลือกจากประวัติ</button>
                                </div>
                                <div id="refPopup" class="floating-popup">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div style="font-size:14px;font-weight:700;color:var(--primary-dark);"><i class="fas fa-inbox me-1"></i>ประวัติหนังสือรับเข้าล่าสุด</div>
                                        <button type="button" class="btn-close" style="font-size:10px;" onclick="togglePopup('refPopup')"></button>
                                    </div>
                                    @forelse($incomingDocs as $inDoc)
                                        @php
                                            $refNumber = $inDoc->receive_number ?? $inDoc->doc_number ?? 'ไม่ระบุเลข';
                                            $safeTitle = addslashes($inDoc->title);
                                            $safeNumber = addslashes($refNumber);
                                        @endphp
                                        <div class="popup-item d-flex align-items-center gap-3" onclick="selectRef('{{ $safeNumber }} — {{ $safeTitle }}')">
                                            <div class="bg-success-subtle text-success rounded px-2 py-1 small fw-bold text-nowrap">{{ $refNumber }}</div>
                                            <div style="font-size:13px;color:var(--color-text-primary);text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">{{ $inDoc->title }}</div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-4 small"><i class="fas fa-folder-open fs-3 mb-2 opacity-50"></i><br>ไม่มีประวัติหนังสือรับเข้าในระบบ</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="outgoing-form-row">
                                <label for="attachment">สิ่งที่ส่งมาด้วย</label>
                                <input type="text" name="attachment" id="attachment" class="outgoing-paper-control system-compose-input" placeholder="เช่น รายละเอียดโครงการ จำนวน 1 ชุด" value="{{ old('attachment') }}">
                            </div>

                            <div class="system-source-field">
                                @php
                                    $oldContentParts = preg_split('/\n\s*\n/', trim((string) old('content')), 3);
                                @endphp
                                <div class="outgoing-content-label">
                                    <strong><i class="fas fa-align-left text-primary me-1"></i>เนื้อหาหนังสือ <span class="text-danger">*</span></strong>
                                    <small class="text-muted"><span id="contentCount">0</span>/50,000</small>
                                </div>
                                <input type="hidden" name="content" id="content" value="{{ old('content') }}" data-required-label="เนื้อหาหนังสือ" data-validation-target="#content_reason">
                                <div class="outgoing-paragraph-block">
                                    <div class="outgoing-paragraph-label"><strong>ภาคเหตุ</strong><span>ความเป็นมาและเหตุผลของเรื่อง</span></div>
                                    <textarea name="content_reason" id="content_reason" class="outgoing-content-editor structured-content-part" rows="4" maxlength="20000" placeholder="ระบุความเป็นมา ข้อเท็จจริง หรือเหตุผล...">{{ old('content_reason', $oldContentParts[0] ?? '') }}</textarea>
                                </div>
                                <div class="outgoing-paragraph-block">
                                    <div class="outgoing-paragraph-label"><strong>ภาคความประสงค์</strong><span>สิ่งที่ต้องการขอหรือแจ้งให้ดำเนินการ</span></div>
                                    <textarea name="content_request" id="content_request" class="outgoing-content-editor structured-content-part" rows="4" maxlength="20000" placeholder="ระบุความประสงค์หรือการดำเนินการที่ต้องการ...">{{ old('content_request', $oldContentParts[1] ?? '') }}</textarea>
                                </div>
                                <div class="outgoing-paragraph-block">
                                    <div class="outgoing-paragraph-label"><strong>ภาคสรุป</strong><span>ข้อความสรุปหรือข้อความเพื่อโปรดพิจารณา</span></div>
                                    <textarea name="content_conclusion" id="content_conclusion" class="outgoing-content-editor structured-content-part" rows="3" maxlength="10000" placeholder="เช่น จึงเรียนมาเพื่อโปรดพิจารณา...">{{ old('content_conclusion', $oldContentParts[2] ?? '') }}</textarea>
                                </div>
                                <small class="text-muted d-block mt-2">ระบบจะรวมทั้งสามส่วนเป็นย่อหน้าต่อเนื่องใน PDF</small>
                            </div>

                            <div class="outgoing-signature-area">
                                <input type="text" name="closing_text" id="closing_text" class="outgoing-paper-control mb-2 system-compose-input" value="{{ old('closing_text', 'ขอแสดงความนับถือ') }}" aria-label="คำลงท้าย">
                                <div class="outgoing-signature-placeholder">พื้นที่ประทับลายเซ็นหลังอนุมัติ</div>
                                <select name="signer_name" id="signer" class="outgoing-paper-control system-compose-input" required data-required-label="ผู้ลงนาม">
                                    <option value="" disabled {{ old('signer_name') ? '' : 'selected' }}>-- เลือกผู้ลงนาม --</option>
                                    @foreach($signers as $person)
                                        @php
                                            $suffix = '';
                                            if ($person->hasRole('executive')) { $suffix = ' — นายก อบต.พร่อน'; }
                                            elseif ($person->hasRole('palad') || $person->hasRole('deputy-palad')) { $suffix = ' — ปลัด อบต.พร่อน ปฏิบัติราชการแทน'; }
                                            else { $suffix = ' — หัวหน้าสำนักปลัด รักษาราชการแทน'; }
                                            $fullNameWithPosition = $person->name . $suffix;
                                        @endphp
                                        <option value="{{ $fullNameWithPosition }}" data-user-id="{{ $person->id }}" {{ old('signer_name') === $fullNameWithPosition ? 'selected' : '' }}>{{ $fullNameWithPosition }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="signer_user_id" id="signer_user_id" class="system-compose-input" value="{{ old('signer_user_id') }}">
                            </div>

                            <div class="outgoing-contact-area system-source-field">
                                <label for="contact_line" class="fw-bold d-block mb-1">ข้อมูลติดต่อ</label>
                                <textarea name="contact_line" id="contact_line" class="outgoing-paper-control" rows="2" placeholder="สำนักปลัด&#10;โทร. 0 7329 9000">{{ old('contact_line') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 📍 Section 4: แนบไฟล์ + อ้างอิง --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" id="attachmentSectionTitle" style="color:var(--primary-dark);"><i class="fas fa-paperclip text-primary me-2"></i><span>3. แนบไฟล์และการอ้างอิง</span></h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-4">
                            <div class="col-12" id="uploadMetadataFields" style="display:none;">
                                <div class="alert alert-info border-0 mb-4">
                                    <i class="fas fa-circle-info me-1"></i>
                                    PDF จะถูกเก็บไว้ตามไฟล์ต้นฉบับ กรุณาระบุข้อมูลกำกับและเลือกผู้ลงนามสำหรับขั้นตอนยืนยันตัวตน
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">เลขที่หนังสือออก <span class="text-danger">*</span></label>
                                        <div class="input-group shadow-sm">
                                            <input type="text" name="doc_number" id="upload_doc_number" class="form-control upload-metadata-input" value="{{ old('doc_number') }}" placeholder="พิมพ์เลขจอง เช่น ๑๐๑" required disabled data-required-label="เลขที่หนังสือออก">
                                            <button type="button" onclick="autoDocNo()" class="btn btn-primary" title="ดึงเลขจากสมุดคุมเลข"><i class="fas fa-magic"></i></button>
                                        </div>
                                        <small class="text-muted d-block mt-1"><i class="fas fa-link me-1"></i>ระบบจะเชื่อมเลขจองกับสมุดคุมเลขอัตโนมัติ</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">วันที่ออกเอกสาร <span class="text-danger">*</span></label>
                                        <div class="date-input-wrapper">
                                            <input type="text" id="upload_doc_date_picker" class="form-control upload-metadata-input" placeholder="เลือกวันที่ออกเอกสาร" readonly disabled>
                                            <i class="fas fa-calendar-alt cal-icon"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">เรื่อง <span class="text-danger">*</span></label>
                                        <input type="text" name="title" id="upload_title" class="form-control shadow-sm upload-metadata-input" value="{{ old('title') }}" placeholder="ระบุชื่อเรื่องของหนังสือ" required disabled data-required-label="เรื่อง">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">เรียน <span class="text-danger">*</span></label>
                                        <input type="text" name="doc_to" id="upload_doc_to" class="form-control shadow-sm upload-metadata-input" value="{{ old('doc_to') }}" placeholder="ระบุผู้รับหนังสือ" required disabled data-required-label="ผู้รับ (เรียน)">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">อ้างถึง</label>
                                        <input type="text" name="reference_doc" id="upload_reference_doc" class="form-control shadow-sm upload-metadata-input" value="{{ old('reference_doc') }}" placeholder="เลขที่หนังสืออ้างอิง (ถ้ามี)" disabled>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-muted">สิ่งที่ส่งมาด้วย</label>
                                        <input type="text" name="attachment" id="upload_attachment" class="form-control shadow-sm upload-metadata-input" value="{{ old('attachment') }}" placeholder="รายละเอียดสิ่งที่ส่งมาด้วย (ถ้ามี)" disabled>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted">ผู้ลงนามหนังสือ <span class="text-danger">*</span></label>
                                        <select name="signer_name" id="upload_signer" class="form-select shadow-sm upload-metadata-input" required disabled data-required-label="ผู้ลงนาม">
                                            <option value="" disabled {{ old('signer_name') ? '' : 'selected' }}>-- เลือกผู้ลงนาม --</option>
                                            @foreach($signers as $person)
                                                @php
                                                    $uploadSuffix = '';
                                                    if ($person->hasRole('executive')) { $uploadSuffix = ' — นายก อบต.พร่อน'; }
                                                    elseif ($person->hasRole('palad') || $person->hasRole('deputy-palad')) { $uploadSuffix = ' — ปลัด อบต.พร่อน ปฏิบัติราชการแทน'; }
                                                    else { $uploadSuffix = ' — หัวหน้าสำนักปลัด รักษาราชการแทน'; }
                                                    $uploadSignerName = $person->name . $uploadSuffix;
                                                @endphp
                                                <option value="{{ $uploadSignerName }}" data-user-id="{{ $person->id }}" {{ old('signer_name') === $uploadSignerName ? 'selected' : '' }}>{{ $uploadSignerName }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="signer_user_id" id="upload_signer_user_id" class="upload-metadata-input" value="{{ old('signer_user_id') }}" disabled>
                                        <small class="text-muted d-block mt-2"><i class="fas fa-lock me-1"></i>ระบบจะเพิ่มผู้ลงนามเป็นขั้นตอนสุดท้ายและล็อกเส้นทางให้อัตโนมัติ</small>
                                    </div>
                                </div>
                            </div>
                            
                            {{-- Dropzone แนบไฟล์ --}}
                            <div class="col-12" id="uploadSourceField">
                                <label class="form-label small fw-bold text-muted mb-2">ไฟล์หนังสือฉบับเต็ม (PDF) <span class="text-danger">*</span></label>
                                <div id="dropzone" onclick="document.getElementById('file').click()" class="rounded-3 p-5 text-center shadow-sm">
                                    <div id="file-display">
                                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 60px; height: 60px;">
                                            <i class="fas fa-cloud-upload-alt fs-3"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">คลิกเพื่อเลือกไฟล์ หรือ ลากไฟล์ PDF มาวางที่นี่</h6>
                                        <small class="text-muted">รองรับเฉพาะไฟล์ .PDF ขนาดไม่เกิน 20 MB</small>
                                    </div>
                                </div>
                                <input type="file" name="file" id="file" accept=".pdf" style="display:none;" onchange="handleFileChange(this)" data-required-label="ไฟล์หนังสือฉบับเต็ม (PDF)" data-validation-target="#dropzone">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-2">หมายเหตุ (เพื่อช่วยในการค้นหา)</label>
                                <input type="text" name="remark" id="remark" class="form-control shadow-sm" placeholder="บันทึก Keyword หรือข้อมูลเพิ่มเติมสำหรับการค้นหาย้อนหลัง...">
                            </div>
                        </div>
                    </div>
                </div>

                @include('documents.partials.route_selector')

                {{-- 📍 ปุ่มดำเนินการ --}}
                <div class="d-flex justify-content-end pt-2 pb-5">
                    <button type="button" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow" style="font-size:16px;" onclick="goToPreview()">
                        ดำเนินการตรวจสอบเอกสาร <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>

            {{-- 🌟 Step 2: Preview --}}
            <div id="step2" style="display:none;">
                <div class="card border-0 mb-4 shadow" style="border-radius:16px; background:#e2e8f0;">
                    <div class="card-header bg-white border-0 p-3 d-flex justify-content-between align-items-center">
                        <strong><i class="fas fa-file-alt text-primary me-2"></i>ตัวอย่างก่อนสร้าง PDF</strong>
                        <span class="badge bg-light text-dark" id="prev_docType">หนังสือภายนอก</span>
                    </div>
                    <div class="card-body p-3 p-lg-4">
                        <div id="system_preview_container">
                            <div class="outgoing-paper">
                                <div class="outgoing-paper__header">
                                    <img src="{{ asset('images/krut.png') }}" class="outgoing-paper__garuda" alt="ตราครุฑ">
                                </div>
                                <div class="outgoing-paper__topline">
                                    <div><div class="fw-bold text-danger" id="prev_speed"></div><strong>ที่</strong> <span id="prev_docNo">—</span></div>
                                    <div class="outgoing-paper__address" id="prev_address">—</div>
                                </div>
                                <div class="text-center my-3" id="prev_date">—</div>
                                <div class="outgoing-paper__row"><strong>เรื่อง</strong><span id="prev_subject">—</span></div>
                                <div class="outgoing-paper__row"><strong>เรียน</strong><span id="prev_to">—</span></div>
                                <div class="outgoing-paper__row" id="prev_ref_row"><strong>อ้างถึง</strong><span id="prev_ref"></span></div>
                                <div class="outgoing-paper__row" id="prev_att_row"><strong>สิ่งที่ส่งมาด้วย</strong><span id="prev_att"></span></div>
                                <div class="outgoing-paper__body" id="prev_content">—</div>
                                <div class="outgoing-paper__signature">
                                    <div id="prev_closing">ขอแสดงความนับถือ</div>
                                    <div style="height:72px;"></div>
                                    <div>(<span id="prev_signer_name2">—</span>)</div>
                                    <div id="prev_signer_pos"></div>
                                </div>
                                <div class="outgoing-paper__contact" id="prev_contact"></div>
                                <div class="text-center fw-bold text-danger" id="prev_secret"></div>
                            </div>
                        </div>
                        <div id="pdf_preview_container" class="rounded-3 overflow-hidden shadow-sm border" style="display:none;height:800px;background:#525659;">
                            <iframe id="pdf_preview_frame" src="" width="100%" height="100%" style="border:none;"></iframe>
                        </div>
                    </div>
                </div>

                {{-- ปุ่มยืนยัน --}}
                <div class="d-flex align-items-center justify-content-between p-4 bg-white rounded-3 shadow-sm border mb-5">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold" onclick="backToEdit()">
                        <i class="fas fa-arrow-left me-1"></i> กลับไปแก้ไขข้อมูล
                    </button>
                    <button type="submit" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-lg" style="font-size:16px; background: #10b981; border:none;">
                        <i class="fas fa-paper-plane me-2"></i> ยืนยันบันทึกและส่งเรื่อง
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

{{-- ✅ Flatpickr JS + Thai locale --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@include('partials.required-form-validation')

<script>
    const THAI_MONTHS = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    const DEFAULT_DEPARTMENT = @json(auth()->user()->department);
    let selectedDateObj = null;

    // ✅ Flatpickr Init
    const initialDocumentDate = @json(old('doc_date')) || new Date();
    let syncingDatePickers = false;
    let systemDatePicker;
    let uploadDatePicker;

    function datePickerOptions(mode) {
        return {
            locale: "th",
            dateFormat: "d/m/Y",
            allowInput: false,
            disableMobile: true,
            defaultDate: initialDocumentDate,
            onReady: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    _updateThaiDisplay(selectedDates[0], instance);
                    _syncHiddenField(selectedDates[0]);
                }
            },
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 0 || syncingDatePickers) return;
                syncingDatePickers = true;
                selectedDateObj = selectedDates[0];
                _updateThaiDisplay(selectedDates[0], instance);
                _syncHiddenField(selectedDates[0]);
                const peer = mode === 'system' ? uploadDatePicker : systemDatePicker;
                if (peer) {
                    peer.setDate(selectedDates[0], false);
                    _updateThaiDisplay(selectedDates[0], peer);
                }
                syncingDatePickers = false;
            },
            onMonthChange: function(s, d, instance) { _patchCalendarHeader(instance); },
            onYearChange: function(s, d, instance) { _patchCalendarHeader(instance); },
            onOpen: function(s, d, instance) { _patchCalendarHeader(instance); }
        };
    }

    systemDatePicker = flatpickr("#doc_date_picker", datePickerOptions('system'));
    uploadDatePicker = flatpickr("#upload_doc_date_picker", datePickerOptions('upload'));

    function _updateThaiDisplay(dateObj, instance) {
        const d   = dateObj.getDate();
        const month = THAI_MONTHS[dateObj.getMonth()];
        const yBE = dateObj.getFullYear() + 543;
        instance.input.value = toThaiDigitsText(`${d} ${month} ${yBE}`);
    }

    function toThaiDigitsText(value) {
        return String(value).replace(/[0-9]/g, digit => '๐๑๒๓๔๕๖๗๘๙'[Number(digit)]);
    }

    function _syncHiddenField(dateObj) {
        const yyyy = dateObj.getFullYear();
        const mm   = (dateObj.getMonth() + 1).toString().padStart(2, '0');
        const dd   = dateObj.getDate().toString().padStart(2, '0');
        document.getElementById('doc_date_hidden').value = `${yyyy}-${mm}-${dd}`;
    }

    function _patchCalendarHeader(instance) {
        requestAnimationFrame(() => {
            const yearInput = instance.calendarContainer?.querySelector('.cur-year');
            if (yearInput) {
                const ce = parseInt(yearInput.value, 10);
                if (!isNaN(ce) && ce < 2500) {
                    yearInput.value = ce + 543;
                    yearInput.dataset.ce = ce;
                }
            }
        });
    }

    // ✅ ตรวจสอบก่อน Submit
    const outgoingForm = document.getElementById('outgoingForm');
    const draftKey = 'edoc:outgoing-draft:{{ auth()->id() }}';
    const hasServerOldInput = @json(session()->hasOldInput());
    const DEFAULT_OUTGOING_ADDRESS = @json(config('edoc.organization.outgoing_address'));
    let draftTimer = null;

    function selectedSource() {
        return document.querySelector('input[name="document_source"]:checked')?.value || 'system';
    }

    function normalizeReservedDocumentNumber() {
        const isSystem = selectedSource() === 'system';
        const hidden = document.getElementById('running_number');
        if (isSystem) {
            hidden.value = '';
            return;
        }
        const input = document.getElementById('upload_doc_number');
        const arabic = input.value.trim().replace(/[๐-๙]/g, digit => '๐๑๒๓๔๕๖๗๘๙'.indexOf(digit));
        const match = arabic.match(/^(?:ยล\s*\d+\s*\/\s*)?(\d+)$/u);
        if (!match || Number(match[1]) < 1) return;

        const running = String(Number(match[1]));
        const thai = (`ยล 77301/${running}`).replace(/[0-9]/g, digit => '๐๑๒๓๔๕๖๗๘๙'[Number(digit)]);
        input.value = thai;
        hidden.value = running;
    }

    function syncStructuredContent() {
        const content = [...document.querySelectorAll('.structured-content-part')]
            .map(field => field.value.trim())
            .filter(Boolean)
            .join('\n\n');
        document.getElementById('content').value = content;
        document.getElementById('contentCount').innerText = content.length.toLocaleString('th-TH');
    }

    function syncUploadMetadata(isSystem) {
        const pairs = [
            ['title', 'upload_title'],
            ['doc_to', 'upload_doc_to'],
            ['reference_doc', 'upload_reference_doc'],
            ['attachment', 'upload_attachment'],
            ['signer', 'upload_signer'],
        ];
        pairs.forEach(([systemId, uploadId]) => {
            const source = document.getElementById(isSystem ? uploadId : systemId);
            const target = document.getElementById(isSystem ? systemId : uploadId);
            if (source && target && !target.value && source.value) target.value = source.value;
        });
    }

    function syncSignerUserIds() {
        [
            ['signer', 'signer_user_id'],
            ['upload_signer', 'upload_signer_user_id'],
        ].forEach(([selectId, hiddenId]) => {
            const select = document.getElementById(selectId);
            const hidden = document.getElementById(hiddenId);
            if (select && hidden) hidden.value = select.selectedOptions[0]?.dataset.userId || '';
        });
    }

    function applySourceMode() {
        const isSystem = selectedSource() === 'system';
        normalizeReservedDocumentNumber();
        syncUploadMetadata(isSystem);
        const composeSection = document.getElementById('systemComposeSection');
        composeSection.hidden = !isSystem;
        composeSection.style.display = isSystem ? '' : 'none';
        document.getElementById('uploadMetadataFields').style.display = isSystem ? 'none' : '';
        document.querySelectorAll('.system-source-field').forEach(el => el.style.display = isSystem ? '' : 'none');
        document.querySelectorAll('.system-compose-input').forEach(el => el.disabled = !isSystem);
        document.querySelectorAll('.upload-metadata-input').forEach(el => el.disabled = isSystem);
        document.getElementById('uploadSourceField').style.display = isSystem ? 'none' : '';
        document.querySelector('#attachmentSectionTitle span').innerText = isSystem
            ? '3. แนบไฟล์และการอ้างอิง'
            : 'อัปโหลด PDF และกำหนดผู้ลงนาม';
        document.getElementById('content').required = isSystem;
        document.getElementById('file').required = !isSystem;
        document.getElementById('doc_date_hidden').dataset.validationTarget = isSystem
            ? '#doc_date_picker'
            : '#upload_doc_date_picker';
        syncSignerUserIds();
    }

    function saveLocalDraft() {
        const values = {};
        outgoingForm.querySelectorAll('input:not([type="file"]):not([type="hidden"]), textarea, select').forEach(field => {
            if (field.disabled) return;
            if (!field.name || field.name === '_token' || field.name.startsWith('routing_users')) return;
            if (field.type === 'radio' && !field.checked) return;
            values[field.name] = field.value;
        });
        values.doc_date = document.getElementById('doc_date_hidden').value;
        try {
            localStorage.setItem(draftKey, JSON.stringify(values));
            document.getElementById('draftIndicator').innerHTML = '<i class="fas fa-check-circle me-1"></i>บันทึกร่างล่าสุดแล้ว';
        } catch (error) {
            document.getElementById('draftIndicator').innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>ไม่สามารถบันทึกร่างในเครื่องได้';
        }
    }

    function scheduleLocalDraft() {
        clearTimeout(draftTimer);
        document.getElementById('draftIndicator').innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>กำลังบันทึกร่าง';
        draftTimer = setTimeout(saveLocalDraft, 500);
    }

    function restoreLocalDraft() {
        if (hasServerOldInput) return;
        const raw = localStorage.getItem(draftKey);
        if (!raw) return;
        try {
            const values = JSON.parse(raw);
            Object.entries(values).forEach(([name, value]) => {
                if (name === 'doc_date') return;
                if (name === 'sender_address' && !String(value || '').trim()) return;
                const fields = outgoingForm.querySelectorAll(`[name="${CSS.escape(name)}"]`);
                fields.forEach(field => {
                    if (field.type === 'radio') field.checked = field.value === value;
                    else field.value = value;
                });
            });
            const senderAddress = document.getElementById('sender_address');
            if (senderAddress && !senderAddress.value.trim()) senderAddress.value = DEFAULT_OUTGOING_ADDRESS;
            if (values.doc_date) systemDatePicker.setDate(values.doc_date, true, 'Y-m-d');
            document.getElementById('draftIndicator').innerHTML = '<i class="fas fa-history me-1"></i>กู้คืนร่างล่าสุดแล้ว';
        } catch (error) {
            localStorage.removeItem(draftKey);
        }
    }

    outgoingForm.addEventListener('input', scheduleLocalDraft);
    outgoingForm.addEventListener('change', scheduleLocalDraft);
    outgoingForm.addEventListener('submit', function(e) {
        normalizeReservedDocumentNumber();
        syncStructuredContent();
        applySourceMode();
        if (!window.EdocRequiredValidation.validate(this)) {
            e.preventDefault();
            return;
        }
        localStorage.removeItem(draftKey);
    });
    document.querySelectorAll('input[name="document_source"]').forEach(input => input.addEventListener('change', applySourceMode));
    document.getElementById('signer').addEventListener('change', syncSignerUserIds);
    document.getElementById('upload_signer').addEventListener('change', syncSignerUserIds);

    // ✅ Address Book Logic
    const ADDRESS_BOOK = [
        { name:"ผู้ว่าราชการจังหวัดยะลา", org:"จังหวัดยะลา" },
        { name:"นายอำเภอเมืองยะลา", org:"อำเภอเมืองยะลา" },
        { name:"ท้องถิ่นจังหวัดยะลา (สถจ.)", org:"สำนักงานส่งเสริมการปกครองท้องถิ่นจังหวัดยะลา" },
        { name:"ผู้อำนวยการสำนักงานคลังจังหวัดยะลา", org:"คลังจังหวัดยะลา" },
        { name:"ประธานสภา อบต.พร่อน", org:"สภา อบต.พร่อน" },
        { name:"ผู้อำนวยการโรงพยาบาลส่งเสริมสุขภาพตำบลพร่อน", org:"รพ.สต.พร่อน" },
        { name:"หัวหน้าส่วนราชการทุกส่วน", org:"ส่วนราชการภายใน" }
    ];

    function renderAddrList(filter = "") {
        const list = document.getElementById('addrList');
        list.innerHTML = "";
        const filtered = ADDRESS_BOOK.filter(a => a.name.includes(filter) || a.org.includes(filter));
        if (filtered.length === 0) {
            list.innerHTML = `<div class="text-center text-muted py-3 small">ไม่พบรายชื่อที่ค้นหา</div>`;
            return;
        }
        filtered.forEach(a => {
            let div = document.createElement('div');
            div.className = 'popup-item';
            div.innerHTML = `<div style="font-size:14px;font-weight:700;">${a.name}</div>
                             <div style="font-size:12px;color:#64748b;"><i class="fas fa-building text-muted me-1"></i>${a.org}</div>`;
            div.onclick = () => {
                document.getElementById('doc_to').value = a.name;
                document.getElementById('addrBookPopup').style.display = 'none';
            };
            list.appendChild(div);
        });
    }
    renderAddrList();

    function filterAddr() { renderAddrList(document.getElementById('addrSearch').value); }
    function togglePopup(id) {
        const el = document.getElementById(id);
        el.style.display = el.style.display === 'block' ? 'none' : 'block';
    }
    function selectRef(text) {
        document.getElementById('reference_doc').value = text;
        document.getElementById('refPopup').style.display = 'none';
    }

    // ✅ API รันเลขอัตโนมัติ
    async function autoDocNo() {
        try {
            const res  = await fetch("{{ route('documents.api_next_number') }}?type=outgoing");
            const data = await res.json();
            document.getElementById('upload_doc_number').value = data.formatted;
            document.getElementById('running_number').value = data.next_number;
            Swal.fire({ toast:true, position:'top-end', icon:'success', title:'ดึงเลขรันนิ่งล่าสุดสำเร็จ', showConfirmButton:false, timer:1500 });
        } catch(e) {
            Swal.fire('Error','ไม่สามารถเชื่อมต่อระบบรันเลขได้','error');
        }
    }

    // ✅ File Dropzone Interactive
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file');

    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault(); dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; handleFileChange(fileInput); }
    });

    function handleFileChange(input) {
        const display = document.getElementById('file-display');
        if (input.files && input.files[0]) {
            const file   = input.files[0];
            const sizeMB = (file.size / (1024*1024)).toFixed(2);
            dropzone.style.borderColor = '#10b981';
            dropzone.style.background  = '#f0fdf4';
            display.innerHTML = `<i class="fas fa-check-circle text-success mb-3" style="font-size:42px;"></i>
                                 <div style="font-size:16px;color:#065f46;font-weight:bold;">${file.name}</div>
                                 <div class="small text-success mt-1 fw-bold">ขนาดไฟล์: ${sizeMB} MB</div>`;
        }
    }

    // 🌟 ลูกเล่นแจ้งเตือนสายตา (UI) + อัปเดต Badge ด้านบน
    function updateBadges() {
        const speed  = document.getElementById('doc_speed').value;
        const secretSelect = document.getElementById('doc_secret');
        const secret = secretSelect.value;
        
        const bSpeed = document.getElementById('displaySpeed');
        if (bSpeed) {
            bSpeed.innerText  = speed;
            bSpeed.className  = speed === 'ปกติ' ? 'badge bg-secondary rounded-pill px-3 py-2 shadow-sm' : 'badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm';
        }
        
        const bSecret = document.getElementById('displaySecret');
        if (secret === 'ไม่มีชั้นความลับ' || secret === 'ปกติ') { 
            if (bSecret) bSecret.classList.add('d-none');
            secretSelect.classList.remove('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
            secretSelect.classList.add('text-dark');
        } else { 
            if (bSecret) {
                bSecret.classList.remove('d-none');
                bSecret.innerText = '🔒 ' + secret;
            }
            secretSelect.classList.remove('text-dark');
            secretSelect.classList.add('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
        }
    }

    // ✅ Step Transition
    function goToPreview() {
        normalizeReservedDocumentNumber();
        syncStructuredContent();
        applySourceMode();
        if (!window.EdocRequiredValidation.validate(document.getElementById('step1'))) return;

        const isSystem     = selectedSource() === 'system';
        const docNo        = isSystem ? 'รอธุรการลงทะเบียนเลข' : document.getElementById('upload_doc_number').value;
        const title        = document.getElementById(isSystem ? 'title' : 'upload_title').value;
        const to           = document.getElementById(isSystem ? 'doc_to' : 'upload_doc_to').value;
        const file         = document.getElementById('file').files[0];
        const signerSelect = document.getElementById(isSystem ? 'signer' : 'upload_signer');
        const dateHidden   = document.getElementById('doc_date_hidden').value;

        const docType = document.getElementById('doc_type_category').value;
        let typeName = "หนังสือภายนอก";
        if (docType.includes('บันทึก')) typeName = "บันทึกข้อความ";
        else if (docType.includes('ประทับตรา')) typeName = "หนังสือประทับตรา";
        else if (docType.includes('สั่งการ')) typeName = "คำสั่ง / ระเบียบ";
        else if (docType.includes('ประกาศ')) typeName = "ประกาศ";

        document.getElementById('prev_docType').innerText   = typeName;
        document.getElementById('prev_docNo').innerText     = docNo;
        document.getElementById('prev_subject').innerText   = title;
        document.getElementById('prev_to').innerText        = to;

        const parts = dateHidden.split('-');
        if (parts.length === 3) {
            const d = parseInt(parts[2], 10);
            const m = parseInt(parts[1], 10) - 1;
            const yBE = parseInt(parts[0], 10) + 543;
            document.getElementById('prev_date').innerText = toThaiDigitsText(`${d} ${THAI_MONTHS[m]} ${yBE}`);
        }

        const ref = document.getElementById(isSystem ? 'reference_doc' : 'upload_reference_doc').value;
        const att = document.getElementById(isSystem ? 'attachment' : 'upload_attachment').value;
        document.getElementById('prev_ref').innerText = ref;
        document.getElementById('prev_ref_row').style.display = ref ? 'grid' : 'none';
        document.getElementById('prev_att').innerText = att;
        document.getElementById('prev_att_row').style.display = att ? 'grid' : 'none';

        document.getElementById('system_preview_container').style.display = isSystem ? 'block' : 'none';
        if (!isSystem && file) {
            const fileUrl = URL.createObjectURL(file);
            document.getElementById('pdf_preview_container').style.display = 'block';
            document.getElementById('pdf_preview_frame').src = fileUrl;
        } else {
            document.getElementById('pdf_preview_container').style.display = 'none';
            document.getElementById('pdf_preview_frame').src = '';
        }

        const signerRaw = signerSelect.value.split('—');
        document.getElementById('prev_signer_name2').innerText = signerRaw[0].trim();
        document.getElementById('prev_signer_pos').innerText   = signerRaw[1] ? signerRaw[1].trim() : '';
        document.getElementById('prev_address').innerText = document.getElementById('sender_address').value || DEFAULT_DEPARTMENT || 'องค์การบริหารส่วนตำบลพร่อน';
        document.getElementById('prev_content').innerText = document.getElementById('content').value;
        document.getElementById('prev_closing').innerText = document.getElementById('closing_text').value || 'ขอแสดงความนับถือ';
        document.getElementById('prev_contact').innerText = document.getElementById('contact_line').value;
        const speed = document.getElementById('doc_speed').value;
        document.getElementById('prev_speed').innerText = speed === 'ปกติ' ? '' : speed;
        const secret = document.getElementById('doc_secret').value;
        document.getElementById('prev_secret').innerText = ['ปกติ', 'ไม่มีชั้นความลับ'].includes(secret) ? '' : secret;

        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        window.scrollTo(0, 0);
    }

    function backToEdit() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';
        window.scrollTo(0, 0);
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#addrBookPopup') && !e.target.closest('button[onclick*="addrBookPopup"]'))
            document.getElementById('addrBookPopup').style.display = 'none';
        if (!e.target.closest('#refPopup') && !e.target.closest('button[onclick*="refPopup"]'))
            document.getElementById('refPopup').style.display = 'none';
    });

    document.querySelectorAll('.structured-content-part').forEach(field => field.addEventListener('input', syncStructuredContent));
    document.getElementById('upload_doc_number').addEventListener('blur', normalizeReservedDocumentNumber);
    restoreLocalDraft();
    normalizeReservedDocumentNumber();
    syncStructuredContent();
    applySourceMode();
</script>
@endsection
