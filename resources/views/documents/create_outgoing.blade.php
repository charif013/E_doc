@extends('layouts.app')
@section('title', 'สร้างหนังสือส่งออก')

@section('content')

{{-- ✅ Flatpickr CSS + Thai locale --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.min.css">

<style>
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

    /* 🌟 Step Indicator (แถบสถานะด้านบน) */
    .step-circle {
        width: 36px; height: 36px; border-radius: 50%; font-size: 15px; font-weight: bold;
        display: flex; align-items: center; justify-content: center; transition: 0.3s;
    }
    .step-active { background: var(--primary); color: white; border: 2px solid var(--primary); box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25); }
    .step-inactive { background: #f8fafc; color: #94a3b8; border: 2px solid #e2e8f0; }
    .step-completed { background: #10b981; color: white; border: 2px solid #10b981; }

    /* 🌟 Input & Dropzone UI */
    .form-control, .form-select { border-radius: 8px; font-size: 14.5px; padding: 10px 16px; border-color: #cbd5e1; }
    .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1); }
    
    #dropzone {
        border: 2px dashed #cbd5e1; background: #f8fafc; cursor: pointer; transition: 0.3s;
    }
    #dropzone:hover, #dropzone.dragover {
        border-color: var(--primary); background: #f0f9ff;
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

        {{-- 🌟 Step Indicator --}}
        <div class="d-flex align-items-center justify-content-center mb-4 bg-white p-3 rounded-pill shadow-sm border" style="max-width: 500px; margin: 0 auto;">
            <div class="d-flex align-items-center">
                <div id="step1-icon" class="step-circle step-active">1</div>
                <span id="step1-text" class="ms-2 fw-bold" style="color:var(--primary-dark); font-size:14px;">กรอกข้อมูลเอกสาร</span>
            </div>
            <div style="width:50px; height:2px; background:#e2e8f0; margin:0 15px;"></div>
            <div class="d-flex align-items-center">
                <div id="step2-icon" class="step-circle step-inactive">2</div>
                <span id="step2-text" class="ms-2 fw-bold text-muted" style="font-size:14px;">ตรวจสอบ (Preview)</span>
            </div>
        </div>

        {{-- 🌟 Main Form --}}
        <form action="{{ route('documents.store_outgoing') }}" method="POST" enctype="multipart/form-data" id="outgoingForm">
            @csrf
            {{-- Hidden field ส่งวันที่แบบ ค.ศ. เข้าฐานข้อมูล --}}
            <input type="hidden" name="doc_date" id="doc_date_hidden">

            <div id="step1">

                {{-- 📍 Section 1: ข้อมูลพื้นฐาน --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-list-ol text-primary me-2"></i>1. ข้อมูลพื้นฐานหนังสือ</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-2">เลขที่หนังสือออก <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <input type="hidden" name="running_number" id="running_number" value="{{ old('running_number') }}">
                                    <input type="text" name="doc_number" id="doc_number" class="form-control border-0 bg-light" placeholder="ยล 77301/..." required value="{{ old('doc_number') }}">
                                    <button type="button" onclick="autoDocNo()" class="btn btn-primary fw-bold px-3" title="ดึงเลขรันนิ่งล่าสุด"><i class="fas fa-magic"></i></button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-2">วันที่ออกเอกสาร <span class="text-danger">*</span></label>
                                <div class="date-input-wrapper">
                                    <input type="text" id="doc_date_picker" class="form-control shadow-sm" placeholder="เลือกวันที่..." readonly required value="{{ old('doc_date') ? \Carbon\Carbon::parse(old('doc_date'))->format('d/m/') . (\Carbon\Carbon::parse(old('doc_date'))->year + 543) : '' }}">
                                    <i class="fas fa-calendar-alt cal-icon"></i>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-2">ส่วนราชการเจ้าของเรื่อง</label>
                                <input type="text" class="form-control shadow-sm bg-light text-muted fw-bold" value="{{ auth()->user()->department }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 📍 Section 2: ประเภทและชั้นความสำคัญ --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-tags text-primary me-2"></i>2. ประเภทและชั้นความสำคัญ</h6>
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
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-pen-nib text-primary me-2"></i>3. เนื้อหาและการลงนาม</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-2">เรื่อง <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control shadow-sm" placeholder="ระบุชื่อเรื่องของหนังสือ..." required value="{{ old('title') }}">
                            </div>
                            
                            <div class="col-12 position-relative">
                                <label class="form-label small fw-bold text-muted mb-2">เรียน (ผู้รับ) <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <input type="text" name="doc_to" id="doc_to" class="form-control border-0 bg-light" placeholder="เช่น ผู้ว่าราชการจังหวัดยะลา" required value="{{ old('doc_to') }}">
                                    <button type="button" onclick="togglePopup('addrBookPopup')" class="btn btn-outline-primary fw-bold bg-white px-4"><i class="fas fa-address-book me-1"></i> สมุดที่อยู่</button>
                                </div>

                                {{-- Pop-up สมุดที่อยู่ --}}
                                <div id="addrBookPopup" class="floating-popup">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div style="font-size:14px;font-weight:700;color:var(--primary-dark);"><i class="fas fa-address-book me-1"></i> สมุดที่อยู่หน่วยงาน</div>
                                        <button type="button" class="btn-close" style="font-size: 10px;" onclick="togglePopup('addrBookPopup')"></button>
                                    </div>
                                    <input type="text" id="addrSearch" class="form-control form-control-sm mb-3 shadow-none border-primary" placeholder="ค้นหาชื่อผู้รับ..." onkeyup="filterAddr()">
                                    <div id="addrList"></div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-2">ผู้ลงนาม <span class="text-danger">*</span></label>
                                <select name="signer_name" id="signer" class="form-select shadow-sm" required>
                                    <option value="" disabled selected>-- เลือกผู้ลงนามในเอกสาร --</option>
                                    @foreach($signers as $person)
                                        @php
                                            $suffix = '';
                                            if ($person->hasRole('executive')) { $suffix = ' — นายก อบต.พร่อน'; } 
                                            elseif ($person->hasRole('palad') || $person->hasRole('deputy-palad')) { $suffix = ' — ปลัด อบต.พร่อน ปฏิบัติราชการแทน'; } 
                                            else { $suffix = ' — หัวหน้าสำนักปลัด รักษาราชการแทน'; }
                                            $fullNameWithPosition = $person->name . $suffix;
                                        @endphp
                                        <option value="{{ $fullNameWithPosition }}">{{ $fullNameWithPosition }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 📍 Section 4: แนบไฟล์ + อ้างอิง --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-paperclip text-primary me-2"></i>4. แนบไฟล์และการอ้างอิง</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-4">
                            
                            {{-- Dropzone แนบไฟล์ --}}
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-2">ไฟล์หนังสือฉบับเต็ม (PDF ที่ลงนามแล้ว) <span class="text-danger">*</span></label>
                                <div id="dropzone" onclick="document.getElementById('file').click()" class="rounded-3 p-5 text-center shadow-sm">
                                    <div id="file-display">
                                        <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width: 60px; height: 60px;">
                                            <i class="fas fa-cloud-upload-alt fs-3"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1">คลิกเพื่อเลือกไฟล์ หรือ ลากไฟล์ PDF มาวางที่นี่</h6>
                                        <small class="text-muted">รองรับเฉพาะไฟล์ .PDF ขนาดไม่เกิน 20 MB</small>
                                    </div>
                                </div>
                                <input type="file" name="file" id="file" accept=".pdf" style="display:none;" required onchange="handleFileChange(this)">
                            </div>

                            <div class="col-md-6 position-relative">
                                <label class="form-label small fw-bold text-muted mb-2">อ้างอิงหนังสือรับเข้า (กรณีตอบกลับ)</label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <input type="text" name="reference_doc" id="reference_doc" class="form-control border-0 bg-light" placeholder="ระบุเลขที่หนังสืออ้างอิง...">
                                    <button type="button" onclick="togglePopup('refPopup')" class="btn btn-outline-primary fw-bold bg-white px-4"><i class="fas fa-search me-1"></i> เลือกจากประวัติ</button>
                                </div>

                                {{-- Pop-up อ้างอิงรับเข้า --}}
                                <div id="refPopup" class="floating-popup">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div style="font-size:14px;font-weight:700;color:var(--primary-dark);"><i class="fas fa-inbox me-1"></i> ประวัติหนังสือรับเข้าล่าสุด</div>
                                        <button type="button" class="btn-close" style="font-size: 10px;" onclick="togglePopup('refPopup')"></button>
                                    </div>
                                    
                                    @forelse($incomingDocs as $inDoc)
                                        @php
                                            $refNumber = $inDoc->receive_number ?? $inDoc->doc_number ?? 'ไม่ระบุเลข';
                                            $safeTitle = addslashes($inDoc->title);
                                            $safeNumber = addslashes($refNumber);
                                        @endphp
                                        <div class="popup-item d-flex align-items-center gap-3" onclick="selectRef('{{ $safeNumber }} — {{ $safeTitle }}')">
                                            <div class="bg-success-subtle text-success rounded px-2 py-1 small fw-bold text-nowrap">{{ $refNumber }}</div>
                                            <div style="font-size:13px; color:var(--color-text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">{{ $inDoc->title }}</div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-4 small">
                                            <i class="fas fa-folder-open fs-3 mb-2 opacity-50"></i><br>ไม่มีประวัติหนังสือรับเข้าในระบบ
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-2">สิ่งที่ส่งมาด้วย</label>
                                <input type="text" name="attachment" id="attachment" class="form-control shadow-sm" placeholder="เช่น บัญชีรายชื่อ จำนวน ๑ ฉบับ...">
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
                <div class="card border-0 mb-4 shadow" style="border-radius:16px; border:1px solid #cbd5e1; background: #fff;">
                    <div class="card-body p-5 text-dark" style="font-size: 16px;">

                        <div class="text-center border-bottom pb-4 mb-4">
                            <div class="text-muted small mb-2"><i class="fas fa-file-alt me-1"></i> โครงร่างกระดาษตราครุฑ</div>
                            <h4 id="prev_docType" class="fw-bold" style="color: #1e293b;">หนังสือภายนอก</h4>
                        </div>

                        <div class="row mb-4" style="line-height: 2.2;">
                            <div class="col-2 text-muted fw-bold text-end pe-3">ที่</div>
                            <div class="col-10 fw-bold text-primary" id="prev_docNo">—</div>

                            <div class="col-2 text-muted fw-bold text-end pe-3">วันที่</div>
                            <div class="col-10" id="prev_date">—</div>

                            <div class="col-2 text-muted fw-bold text-end pe-3 mt-2">เรื่อง</div>
                            <div class="col-10 fw-bold mt-2" id="prev_subject">—</div>

                            <div class="col-2 text-muted fw-bold text-end pe-3">เรียน</div>
                            <div class="col-10" id="prev_to">—</div>

                            <div class="col-2 text-muted fw-bold text-end pe-3" id="lbl_prev_ref">อ้างถึง</div>
                            <div class="col-10 text-secondary" id="prev_ref"></div>

                            <div class="col-2 text-muted fw-bold text-end pe-3" id="lbl_prev_att">ส่งมาด้วย</div>
                            <div class="col-10" id="prev_att"></div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                                <span style="width:4px;height:18px;background:var(--primary);border-radius:4px;display:inline-block;"></span>
                                <span style="font-size:14px;font-weight:700;color:var(--primary-dark);">เนื้อหา / ไฟล์แนบ</span>
                            </div>
                            
                            {{-- กรอบสำหรับโชว์ PDF --}}
                            <div id="pdf_preview_container" class="rounded-3 overflow-hidden shadow-sm border" style="display: none; height: 600px; background: #525659;">
                                <iframe id="pdf_preview_frame" src="" width="100%" height="100%" style="border: none;"></iframe>
                            </div>
                        </div>

                        <div class="text-center mt-5">
                            <div class="d-inline-block text-center">
                                <div id="prev_signer_name1" class="fw-bold text-primary" style="font-size:16px;">—</div>
                                <div style="border-top: 1px dotted #94a3b8; width: 280px; margin: 15px auto 5px;"></div>
                                <div style="font-size:14px;">( <span id="prev_signer_name2">—</span> )</div>
                                <div id="prev_signer_pos" class="text-muted mt-1" style="font-size:14px;">—</div>
                            </div>
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

<script>
    const THAI_MONTHS = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
    let selectedDateObj = null;

    // ✅ Flatpickr Init
    flatpickr("#doc_date_picker", {
        locale: "th",
        dateFormat: "d/m/Y",
        allowInput: false,
        disableMobile: true,
        defaultDate: new Date(),
        onReady: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) { _updateThaiDisplay(selectedDates[0], instance); _syncHiddenField(selectedDates[0]); }
        },
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                selectedDateObj = selectedDates[0];
                _updateThaiDisplay(selectedDates[0], instance);
                _syncHiddenField(selectedDates[0]);
            }
        },
        onMonthChange: function(s, d, instance) { _patchCalendarHeader(instance); },
        onYearChange: function(s, d, instance) { _patchCalendarHeader(instance); },
        onOpen: function(s, d, instance) { _patchCalendarHeader(instance); }
    });

    function _updateThaiDisplay(dateObj, instance) {
        const d   = dateObj.getDate().toString().padStart(2, '0');
        const m   = (dateObj.getMonth() + 1).toString().padStart(2, '0');
        const yBE = dateObj.getFullYear() + 543;
        instance.input.value = `${d}/${m}/${yBE}`;
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
    document.getElementById('outgoingForm').addEventListener('submit', function(e) {
        const hidden = document.getElementById('doc_date_hidden').value;
        if (!hidden) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'กรุณาเลือกวันที่', confirmButtonColor: '#0284c7' });
        }
    });

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
            document.getElementById('doc_number').value   = data.formatted;
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
        bSpeed.innerText  = speed;
        bSpeed.className  = speed === 'ปกติ' ? 'badge bg-secondary rounded-pill px-3 py-2 shadow-sm' : 'badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm';
        
        const bSecret = document.getElementById('displaySecret');
        if (secret === 'ไม่มีชั้นความลับ' || secret === 'ปกติ') { 
            bSecret.classList.add('d-none'); 
            secretSelect.classList.remove('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
            secretSelect.classList.add('text-dark');
        } else { 
            bSecret.classList.remove('d-none'); 
            bSecret.innerText = '🔒 ' + secret; 
            secretSelect.classList.remove('text-dark');
            secretSelect.classList.add('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
        }
    }

    // ✅ Step Transition
    function goToPreview() {
        const docNo        = document.getElementById('doc_number').value;
        const title        = document.getElementById('title').value;
        const to           = document.getElementById('doc_to').value;
        const file         = document.getElementById('file').files[0];
        const signerSelect = document.getElementById('signer');
        const dateHidden   = document.getElementById('doc_date_hidden').value;

        if (!docNo || !title || !to || !file || !signerSelect.value || !dateHidden) {
            Swal.fire({ icon:'warning', title:'ข้อมูลไม่ครบถ้วน', text:'กรุณากรอกข้อมูลที่มีเครื่องหมาย * และแนบไฟล์ PDF ให้ครบถ้วน', confirmButtonColor:'#0284c7' });
            return;
        }

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
            document.getElementById('prev_date').innerText = `${d} ${THAI_MONTHS[m]} ${yBE}`;
        }

        const ref = document.getElementById('reference_doc').value;
        const att = document.getElementById('attachment').value;
        document.getElementById('prev_ref').innerText = ref;
        document.getElementById('lbl_prev_ref').style.display = ref ? 'block' : 'none';
        document.getElementById('prev_att').innerText = att;
        document.getElementById('lbl_prev_att').style.display = att ? 'block' : 'none';

        if (file) {
            const fileUrl = URL.createObjectURL(file);
            document.getElementById('pdf_preview_container').style.display = 'block';
            document.getElementById('pdf_preview_frame').src = fileUrl;
        } else {
            document.getElementById('pdf_preview_container').style.display = 'none';
            document.getElementById('pdf_preview_frame').src = '';
        }

        const signerRaw = signerSelect.value.split('—');
        document.getElementById('prev_signer_name1').innerText = signerRaw[0].trim();
        document.getElementById('prev_signer_name2').innerText = signerRaw[0].trim();
        document.getElementById('prev_signer_pos').innerText   = signerRaw[1] ? signerRaw[1].trim() : '';

        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        document.getElementById('step1-icon').classList.replace('step-active','step-completed');
        document.getElementById('step1-icon').innerHTML = '<i class="fas fa-check"></i>';
        document.getElementById('step2-icon').classList.replace('step-inactive','step-active');
        document.getElementById('step2-text').classList.remove('text-muted');
        document.getElementById('step2-text').style.color = 'var(--primary-dark)';
        
        window.scrollTo(0, 0);
    }

    function backToEdit() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';
        document.getElementById('step1-icon').classList.replace('step-completed','step-active');
        document.getElementById('step1-icon').innerHTML = '1';
        document.getElementById('step2-icon').classList.replace('step-active','step-inactive');
        document.getElementById('step2-text').classList.add('text-muted');
        window.scrollTo(0, 0);
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#addrBookPopup') && !e.target.closest('button[onclick*="addrBookPopup"]'))
            document.getElementById('addrBookPopup').style.display = 'none';
        if (!e.target.closest('#refPopup') && !e.target.closest('button[onclick*="refPopup"]'))
            document.getElementById('refPopup').style.display = 'none';
    });
</script>
@endsection
