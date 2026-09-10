@php
    if (!isset($users)) {
        $users = \App\Models\User::where('id', '!=', auth()->id())->get();
    }
@endphp

@extends('layouts.app')

@section('title', 'ลงทะเบียนหนังสือเข้าใหม่')

@section('content')
{{-- 🌟 นำเข้าฟอนต์จาก Google Fonts ตามดีไซน์ 🌟 --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&family=Sarabun:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">

<div class="container-fluid px-4 py-4 custom-bg" style="min-height: 100vh;">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 custom-heading">
                <i class="fas fa-file-import me-2" style="color: var(--green-500);"></i>ลงทะเบียนหนังสือเข้า
            </h4>
            <small style="color: var(--ink); opacity: 0.7;">อัปโหลดเอกสารต้นฉบับเพื่อให้ AI ช่วยแยกแยะข้อมูลอัตโนมัติ</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger shadow-sm border-0 border-start border-4 border-danger rounded-3 mb-4">
            <h6 class="fw-bold text-danger mb-2"><i class="fas fa-exclamation-triangle me-2"></i>พบข้อผิดพลาด:</h6>
            <ul class="mb-0 small text-danger">
                @foreach($errors->all() as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('documents.store_incoming') }}" method="POST" enctype="multipart/form-data" id="docForm">
        @csrf

        {{-- ========================================== --}}
        {{-- 🌟 ส่วนบน: อัปโหลดเอกสาร & AI (เต็มความกว้าง) --}}
        {{-- ========================================== --}}
        <div class="upload-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap">
                <h5 class="fw-bold m-0" style="color: var(--green-900);">ไฟล์เอกสารต้นฉบับ</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn-qr" id="scan_document_button">
                        <i class="fas fa-camera"></i> สแกนเอกสาร
                    </button>
                    <button type="button" class="btn-qr" onclick="chooseQrScanSource()">
                        <i class="fas fa-qrcode"></i> สแกน QR
                    </button>
                </div>
            </div>

            {{-- เปิดกล้องหลังบนมือถือ และคืนภาพที่ถ่ายเป็นไฟล์แนบในฟอร์ม --}}
            <input type="file" id="scan_document_input" accept="image/*" capture="environment" class="d-none">
            {{-- HTTP บนวง LAN เปิดกล้องสดไม่ได้ จึงใช้กล้องมือถือถ่าย QR แล้วอ่านจากภาพแทน --}}
            <input type="file" id="scan_qr_image_input" accept="image/*" capture="environment" class="d-none">

            {{-- พื้นที่อัปโหลด --}}
            <div class="upload-area" id="upload_area">
                <input type="file" name="file" id="file_input" accept=".pdf,.jpg,.jpeg,.png" data-file-preview="custom">
                <div class="upload-icon"><i class="fas fa-file-pdf"></i></div>
                <div class="upload-text" id="upload_text_default">
                    ลากไฟล์ PDF/รูปภาพ มาวางที่นี่<br>หรือ <span>คลิกเพื่อเลือกไฟล์</span>
                </div>
                <div class="upload-hint" id="upload_hint">รองรับไฟล์ PDF, JPG, PNG ขนาดไม่เกิน 10MB</div>

                {{-- แสดงเมื่อเลือกไฟล์แล้ว --}}
                <div id="file_name_display" class="mt-3 file-success-text" style="display: none;"></div>

                {{-- 🌟 ปุ่ม AI อัจฉริยะ (จะโผล่มาตอนเลือกไฟล์แล้ว) --}}
                <div id="ai_action_area" class="mt-3" style="display: none; position: relative; z-index: 10;">
                    <button type="button" class="btn-ai" onclick="extractFromAttached()">
                        ✨ ให้ AI ช่วยดึงข้อมูลจากไฟล์นี้
                    </button>
                    <div id="ai_loading" class="mt-2 text-center" style="display: none; color: var(--green-700);">
                        <i class="fas fa-circle-notch fa-spin me-2"></i> <span class="small fw-bold">กำลังประมวลผล...</span>
                    </div>
                </div>
            </div>

            {{-- ตัวอย่างเอกสารหลังเลือกไฟล์ --}}
            <div id="document_preview_card" class="document-preview-card mt-4" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-eye text-primary me-2"></i>ตัวอย่างเอกสาร</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                        <span id="preview_file_type" class="badge bg-primary-subtle text-primary-emphasis rounded-pill"></span>
                        <button type="button" id="change_document_button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold">
                            <i class="fas fa-file-arrow-up me-1"></i>เปลี่ยนเอกสาร
                        </button>
                        <button type="button" id="remove_document_button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold">
                            <i class="fas fa-trash-alt me-1"></i>นำไฟล์ออก
                        </button>
                    </div>
                </div>
                <div id="document_preview" class="document-preview"></div>
            </div>

            <div id="qr_document_preview_card" class="document-preview-card mt-4" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-qrcode text-success me-2"></i>เอกสารสิ่งที่ส่งมาด้วยจาก QR Code</h6>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="qr_preview_file_type" class="badge bg-success-subtle text-success-emphasis rounded-pill"></span>
                        <a id="open_qr_document_button" href="#" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-success rounded-pill fw-bold">
                            <i class="fas fa-up-right-from-square me-1"></i>เปิดต้นฉบับ
                        </a>
                        <button type="button" id="rescan_qr_button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold">
                            <i class="fas fa-camera me-1"></i>สแกนใหม่
                        </button>
                        <button type="button" id="remove_qr_button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold">
                            <i class="fas fa-trash-alt me-1"></i>นำออก
                        </button>
                    </div>
                </div>
                <div id="qr_document_preview" class="document-preview"></div>
            </div>

            {{-- ซ่อนกล้อง QR ไว้ในนี้ (จะโชว์ตอนกดปุ่มสแกน QR) --}}
            <div id="qr_url_area" class="mt-3" style="display: none;">
                <div id="reader" class="bg-white border rounded-3 overflow-hidden shadow-sm"></div>
                <div class="mt-2">
                    <label class="small fw-bold text-muted">ลิงก์ที่สแกนได้:</label>
                    <input type="url" name="external_url" id="external_url" class="form-control form-control-sm" readonly>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger w-100 mt-2" onclick="stopScanner()">ปิดกล้อง</button>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- 🌟 ส่วนล่าง: ฟอร์มข้อมูล (เต็มความกว้าง) --}}
        {{-- ========================================== --}}
        <div class="form-card">
            <div class="section-title">
                <span>1</span> ข้อมูลหนังสือ
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="field-group">
                        <label>เลขที่รับ <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            <input type="hidden" name="running_number" id="running_number" value="{{ old('running_number') }}">
                            <input type="text" name="receive_number" id="receive_number" required value="{{ old('receive_number') }}" class="flex-grow-1" placeholder="ยล 77301/1" readonly style="background: var(--paper);">
                            <button type="button" class="btn-run-no" onclick="autoReceiveNo()">รันเลข</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field-group">
                        <label>วันที่รับเอกสาร <span class="text-danger">*</span></label>
                        <input type="text" name="receive_date" class="thai-datepicker" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field-group">
                        <label>ที่ (เลขหนังสือต้นทาง) <span class="text-danger">*</span></label>
                        <input type="text" name="doc_number" id="doc_number" value="{{ old('doc_number') }}" placeholder="เช่น กค 0405/ว 1234" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field-group">
                        <label>ลงวันที่ (บนหนังสือ) <span class="text-danger">*</span></label>
                        <input type="text" name="doc_date" id="doc_date" class="thai-datepicker" value="{{ old('doc_date') ?? date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="col-12">
                    <div class="field-group">
                        <label>เรื่อง <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="เรื่องของหนังสือ" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field-group">
                        <label>จาก (หน่วยงาน/บุคคล) <span class="text-danger">*</span></label>
                        <input type="text" name="doc_from" id="doc_from" value="{{ old('doc_from') }}" placeholder="เช่น กระทรวงมหาดไทย" required>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="field-group">
                        <label>ประเภทหนังสือ <span class="text-danger">*</span></label>
                        <select name="doc_type_category" required>
                            <option value="">-- เลือกประเภท --</option>
                            <option value="หนังสือภายนอก (กระดาษตราครุฑ)">หนังสือภายนอก (กระดาษตราครุฑ)</option>
                            <option value="หนังสือประทับตรา">หนังสือประทับตรา</option>
                            <option value="หนังสือสั่งการ">หนังสือสั่งการ</option>
                            <option value="หนังสือประชาสัมพันธ์">หนังสือประชาสัมพันธ์</option>
                            <option value="หนังสืออื่น / เอกสารรับเข้าอื่นๆ">หนังสืออื่น / ใบคำร้อง</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-title">
                <span>2</span> ระดับความสำคัญ & ความลับ
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="field-group">
                        <label>ชั้นความเร็ว</label>
                        <select name="doc_speed">
                            <option value="ปกติ">ปกติ</option>
                            <option value="ด่วน">ด่วน</option>
                            <option value="ด่วนมาก">ด่วนมาก</option>
                            <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="field-group">
                        <label>ชั้นความลับ</label>
                        <select name="doc_secret" id="doc_secret">
                            <option value="ไม่มีชั้นความลับ">ไม่มีชั้นความลับ (ปกติ)</option>
                            <option value="ลับ">ลับ</option>
                            <option value="ลับมาก">ลับมาก</option>
                            <option value="ลับที่สุด">ลับที่สุด</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-title">
                <span>3</span> ผู้รับผิดชอบ (Workflow)
            </div>
            <div class="row g-3 mb-5">
                <div class="col-md-6">
                    <div class="field-group">
                        <label>ผู้ลงทะเบียน (ธุรการ)</label>
                        <input type="text" value="{{ auth()->user()->name ?? 'ไม่ระบุ' }}" readonly style="background: var(--paper); color: var(--green-900);">
                    </div>
                </div>

            </div>

            @include('documents.partials.route_selector')

            <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save me-2"></i> บันทึกและส่งอนุมัติ
                </button>
            </div>
        </div>
        
    </form>
</div>

{{-- ========================================== --}}
{{-- 🌟 ส่วนของ CSS (นำมาจากดีไซน์ของคุณ) 🌟 --}}
{{-- ========================================== --}}
<style>
    :root {
        --ink: #334155;
        --paper: #f8fafc;
        --paper-2: #f4f7f9;
        --green-900: #164f51;
        --green-800: #155e75;
        --green-700: #0369a1;
        --green-500: #0284c7;
        --green-100: #e0f2fe;
        --gold: #0284c7;
        --gold-light: #e0f2fe;
        --red: #a63d3d;
        --red-light: #f6e4e0;
        --line: #cbd5e1;
    }

    body {
        font-family: 'Sarabun', sans-serif;
        color: var(--ink);
    }

    .custom-bg { background-color: var(--paper-2); }
    .custom-heading { font-family: 'Kanit', sans-serif; color: var(--green-900); }

    /* Cards */
    .upload-card, .form-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 10px 30px -12px rgba(14,61,43,0.08);
        border: 1px solid rgba(216,210,193,0.5);
    }

    /* Upload Area — เปลี่ยนจากโทนน้ำตาลทองเป็นโทนเขียวให้เข้ากับธีมหลัก */
    .upload-area {
        border: 2px dashed var(--green-500);
        background: var(--green-100);
        border-radius: 12px;
        padding: 2.5rem 1.5rem;
        text-align: center;
        position: relative;
        transition: all 0.3s ease;
    }
    .upload-area:hover { background: #dbeafe; }
    .upload-area input[type="file"] {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        opacity: 0; cursor: pointer; z-index: 5;
    }
    .upload-icon {
        font-size: 2.5rem;
        color: var(--green-700);
        margin-bottom: 1rem;
    }
    .upload-text {
        font-family: 'Kanit', sans-serif;
        font-weight: 500;
        color: var(--green-900);
        margin-bottom: 0.5rem;
    }
    .upload-text span { color: var(--green-500); text-decoration: underline; }
    .upload-hint { font-size: 0.85rem; color: var(--ink); opacity: 0.6; }

    .file-success-text {
        color: var(--green-700);
        font-family: 'Kanit', sans-serif;
        background: white;
        padding: 0.5rem;
        border-radius: 8px;
        border: 1px solid var(--green-100);
        position: relative;
        z-index: 10;
    }

    .document-preview-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 1rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .document-preview {
        width: 100%;
        min-height: 560px;
        max-height: 75vh;
        overflow: auto;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #e2e8f0;
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }
    .document-preview iframe {
        width: 100%;
        height: 70vh;
        min-height: 560px;
        border: 0;
        background: #ffffff;
    }
    .document-preview img {
        display: block;
        max-width: 100%;
        height: auto;
        margin: 1rem auto;
        border-radius: 6px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.16);
    }
    .document-preview.is-external-link {
        min-height: 230px;
        padding: 2rem;
        align-items: center;
        background: #f8fafc;
    }
    .qr-link-placeholder {
        max-width: 640px;
        text-align: center;
        color: #475569;
    }
    .qr-link-placeholder .qr-link-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        display: grid;
        place-items: center;
        background: #dcfce7;
        color: #15803d;
        font-size: 1.6rem;
    }
    @media (max-width: 768px) {
        .document-preview, .document-preview iframe { min-height: 420px; height: 60vh; }
    }

    /* Buttons */
    .btn-qr {
        background: var(--paper);
        border: 1px solid var(--line);
        color: var(--green-900);
        padding: 0.4rem 1rem;
        border-radius: 8px;
        font-family: 'Kanit', sans-serif;
        font-size: 0.9rem;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-qr:hover { background: var(--green-100); color: var(--green-700); border-color: var(--green-500); }

    .btn-ai {
        background: linear-gradient(135deg, #1c6b3c 0%, #0e3d2b 100%);
        color: #fff;
        border: none;
        padding: 0.8rem 1.5rem;
        border-radius: 50px;
        font-family: 'Kanit', sans-serif;
        font-size: 0.95rem;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-ai:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14,61,43,0.3); }

    .btn-run-no {
        background: var(--gold);
        color: white;
        border: none;
        padding: 0 1rem;
        border-radius: 8px;
        font-family: 'Kanit', sans-serif;
        font-weight: 500;
    }
    .btn-run-no:hover { background: #a38233; }

    .btn-submit {
        background: var(--gold);
        color: white;
        border: none;
        padding: 0.8rem 2.5rem;
        border-radius: 8px;
        font-family: 'Kanit', sans-serif;
        font-size: 1.1rem;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-submit:hover { background: var(--green-900); transform: translateY(-2px); }

    /* Form Fields */
    .section-title {
        font-family: 'Kanit', sans-serif;
        font-weight: 600;
        color: var(--green-900);
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .section-title span {
        display: flex; justify-content: center; align-items: center;
        width: 28px; height: 28px;
        background: var(--gold); color: white;
        border-radius: 50%; font-size: 0.9rem;
    }
    .field-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .field-group label {
        font-family: 'Kanit', sans-serif;
        font-size: 0.9rem;
        color: var(--green-900);
        font-weight: 500;
    }
    .field-group input, .field-group select {
        font-family: 'Sarabun', sans-serif;
        font-size: 1rem;
        padding: 0.7rem 1rem;
        border: 1px solid var(--line);
        border-radius: 8px;
        background: #ffffff;
        color: var(--ink);
        transition: all 0.2s;
    }
    .field-group input:focus, .field-group select:focus {
        outline: none;
        border-color: var(--green-500);
        box-shadow: 0 0 0 3px rgba(47,158,91,0.15);
    }
    .field-group input.font-mono { font-family: 'IBM Plex Mono', monospace; }

    /* Secret level alert */
    .secret-alert { background: var(--red-light)!important; border-color: var(--red)!important; color: var(--red)!important; font-weight: bold; }

    /* Select2 Dropdown — ปรับให้เข้ากับธีมของฟอร์ม */
    .select2-container--default .select2-selection--multiple {
        border: 1px solid var(--line);
        border-radius: 8px;
        min-height: 46px;
        padding: 0.3rem 0.5rem;
        font-family: 'Sarabun', sans-serif;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: var(--green-500);
        box-shadow: 0 0 0 3px rgba(47,158,91,0.15);
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background: var(--green-100);
        border: 1px solid var(--green-500);
        color: var(--green-900);
        border-radius: 6px;
        font-family: 'Sarabun', sans-serif;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: var(--green-700);
        margin-right: 4px;
    }
    .select2-dropdown {
        border-color: var(--green-500);
        border-radius: 8px;
        font-family: 'Sarabun', sans-serif;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--green-500);
    }

    /* 🌟 แก้ปัญหา scrollbar ซ้อนกัน 2 อัน — บังคับซ่อน select ต้นฉบับที่ select2 ไม่ยอมซ่อนให้สนิท */
    select.select2-hidden-accessible {
        display: none !important;
    }
    /* จำกัดความสูงรายการ dropdown ให้มี scrollbar แค่จุดเดียว */
    .select2-results__options {
        max-height: 260px;
        overflow-y: auto;
    }
</style>
@endsection

@section('scripts')

<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

{{-- 🌟 Select2 ต้องพึ่ง jQuery — เช็คก่อนว่ามีโหลดอยู่แล้วหรือยัง (กันโหลดซ้ำถ้า layout มีอยู่แล้ว) --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script>
    if (typeof window.jQuery === 'undefined') {
        document.write('<script src="https://code.jquery.com/jquery-3.7.1.min.js"><\/script>');
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
function initRoutingUsersSelect2() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#routing_users').select2({
            theme: 'default',
            width: '100%',
            placeholder: 'คลิกเพื่อเลือกผู้พิจารณาตามลำดับ...',
            allowClear: true,
            closeOnSelect: false
        });
    } else {
        // jQuery/Select2 ยังโหลดไม่เสร็จ ลองใหม่อีกครั้งใน 100ms
        setTimeout(initRoutingUsersSelect2, 100);
    }
}

document.addEventListener('DOMContentLoaded', function() {

    // 🌟 0. ตั้งค่า Dropdown เลือกผู้พิจารณา (Select2)
    initRoutingUsersSelect2();

    // 🌟 1. ตั้งค่าปฏิทินไทย
    flatpickr(".thai-datepicker", {
        locale: "th",
        altInput: true,
        altFormat: "d/m/Y",
        dateFormat: "Y-m-d",
        formatDate: function(date, format, locale) {
            let d = ("0" + date.getDate()).slice(-2);
            let m = ("0" + (date.getMonth() + 1)).slice(-2);
            let y = date.getFullYear();
            if (format === "d/m/Y") return `${d}/${m}/${y + 543}`;
            if (format === "Y-m-d") return `${y}-${m}-${d}`;
            return date.toLocaleDateString('th-TH');
        }
    });

    // 🌟 2. อัปโหลด & โชว์ปุ่ม AI
    const fileInput = document.getElementById('file_input');
    const scanDocumentInput = document.getElementById('scan_document_input');
    const scanQrImageInput = document.getElementById('scan_qr_image_input');
    const scanDocumentButton = document.getElementById('scan_document_button');
    const fileNameDisplay = document.getElementById('file_name_display');
    const aiActionArea = document.getElementById('ai_action_area');
    const previewCard = document.getElementById('document_preview_card');
    const previewArea = document.getElementById('document_preview');
    const previewFileType = document.getElementById('preview_file_type');
    const changeDocumentButton = document.getElementById('change_document_button');
    const removeDocumentButton = document.getElementById('remove_document_button');
    const externalUrlInput = document.getElementById('external_url');
    const qrPreviewCard = document.getElementById('qr_document_preview_card');
    const qrPreviewArea = document.getElementById('qr_document_preview');
    const qrPreviewFileType = document.getElementById('qr_preview_file_type');
    const openQrDocumentButton = document.getElementById('open_qr_document_button');
    const rescanQrButton = document.getElementById('rescan_qr_button');
    const removeQrButton = document.getElementById('remove_qr_button');
    let previewObjectUrl = null;

    function clearSelectedDocument() {
        fileInput.value = '';
        document.getElementById('upload_text_default').style.display = 'block';
        document.getElementById('upload_hint').style.display = 'block';
        fileNameDisplay.style.display = 'none';
        fileNameDisplay.textContent = '';
        aiActionArea.style.display = 'none';
        previewCard.style.display = 'none';
        previewArea.innerHTML = '';
        previewFileType.textContent = '';
        changeDocumentButton.innerHTML = '<i class="fas fa-file-arrow-up me-1"></i>เปลี่ยนเอกสาร';
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
    }

    changeDocumentButton.addEventListener('click', function() {
        fileInput.click();
    });

    scanDocumentButton.addEventListener('click', function() {
        // ล้างค่าเดิมเพื่อให้สามารถถ่ายเอกสารฉบับเดิมซ้ำได้
        scanDocumentInput.value = '';
        scanDocumentInput.click();
    });

    scanDocumentInput.addEventListener('change', async function() {
        if (!this.files || !this.files.length) return;

        const scannedFile = this.files[0];
        Swal.fire({
            title: 'กำลังสร้างเอกสารสแกน...',
            text: 'ระบบกำลังปรับภาพและแปลงเป็นไฟล์ PDF',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const pdfFile = await convertScanToPdf(scannedFile);
            const transfer = new DataTransfer();
            transfer.items.add(pdfFile);
            fileInput.files = transfer.files;
            fileInput.dispatchEvent(new Event('change', { bubbles: true }));

            // ใช้ภาพต้นฉบับความละเอียดเต็มตรวจ QR ก่อน ไม่ใช้ภาพที่ถูกย่อใน PDF
            const qrDetected = await detectQrFromDocumentImage(scannedFile);

            Swal.fire({
                icon: 'success',
                title: 'สแกนเอกสารสำเร็จ',
                text: qrDetected
                    ? 'สร้าง PDF และพบเอกสารสิ่งที่ส่งมาด้วยจาก QR Code แล้ว'
                    : 'สร้าง PDF แล้ว หาก QR ในหนังสือมีขนาดเล็ก กรุณากด “สแกน QR” เพื่อถ่ายใกล้ ๆ',
                timer: 1200,
                showConfirmButton: false
            });
        } catch (error) {
            console.error(error);
            Swal.fire('ไม่สามารถสร้างไฟล์สแกนได้', 'กรุณาถ่ายใหม่หรือเลือกไฟล์จากเครื่อง', 'error');
        }
    });

    async function detectQrFromDocumentImage(imageFile) {
        if (typeof Html5Qrcode === 'undefined') return false;

        const qrArea = document.getElementById('qr_url_area');
        let imageScanner;
        try {
            qrArea.style.display = 'block';
            // การอ่าน QR จากภาพกล้องความละเอียดเต็มใช้เวลามากและกินหน่วยความจำ
            // ภาพย่อ 1,600px ยังเพียงพอสำหรับ QR บนเอกสารทั่วไป
            const qrImageFile = await resizeImageFile(imageFile, 1600, 0.82);
            imageScanner = new Html5Qrcode('reader');
            const decodedText = await imageScanner.scanFile(qrImageFile, false);
            return await handleDecodedQr(decodedText, { showSuccess: false });
        } catch (error) {
            // ไม่พบ QR ในภาพทั้งหน้าไม่ถือเป็นข้อผิดพลาด ผู้ใช้ยังสแกนระยะใกล้ได้
            console.info('No QR code detected in the scanned document image.');
            return false;
        } finally {
            if (imageScanner) {
                try { imageScanner.clear(); } catch (error) { console.error(error); }
            }
            qrArea.style.display = 'none';
        }
    }

    scanQrImageInput.addEventListener('change', async function() {
        if (!this.files || !this.files.length) return;
        const imageFile = this.files[0];
        document.getElementById('qr_url_area').style.display = 'block';

        Swal.fire({
            title: 'กำลังอ่าน QR Code...',
            text: 'ระบบกำลังตรวจหารหัสจากภาพที่ถ่าย',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        let imageScanner;
        try {
            imageScanner = new Html5Qrcode('reader');
            const decodedText = await imageScanner.scanFile(imageFile, true);
            await handleDecodedQr(decodedText);
        } catch (error) {
            console.error(error);
            Swal.fire(
                'ยังอ่าน QR Code ไม่ได้',
                'กรุณาถ่ายใหม่ให้ QR อยู่เต็มภาพ ภาพคมชัด ไม่มีแสงสะท้อน และเห็นขอบสีขาวรอบรหัส',
                'warning'
            );
        } finally {
            if (imageScanner) {
                try { imageScanner.clear(); } catch (error) { console.error(error); }
            }
            this.value = '';
            document.getElementById('qr_url_area').style.display = 'none';
        }
    });

    async function convertScanToPdf(imageFile) {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            throw new Error('PDF library is unavailable');
        }

        const imageUrl = URL.createObjectURL(imageFile);
        try {
            const image = await new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = imageUrl;
            });

            // จำกัดความละเอียดให้ตัวหนังสือยังชัด แต่ไฟล์ไม่ใหญ่เกินไป
            const maxSide = 2000;
            const scale = Math.min(1, maxSide / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(image.naturalWidth * scale);
            canvas.height = Math.round(image.naturalHeight * scale);
            const context = canvas.getContext('2d', { alpha: false });
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.filter = 'contrast(1.16) brightness(1.04)';
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            context.filter = 'none';

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4', compress: true });
            const pageWidth = 210;
            const pageHeight = 297;
            const margin = 7;
            const availableWidth = pageWidth - (margin * 2);
            const availableHeight = pageHeight - (margin * 2);
            const ratio = Math.min(availableWidth / canvas.width, availableHeight / canvas.height);
            const width = canvas.width * ratio;
            const height = canvas.height * ratio;
            const x = (pageWidth - width) / 2;
            const y = (pageHeight - height) / 2;

            // toBlob ทำงานแบบ asynchronous จึงไม่ล็อกหน้าจอเหมือน toDataURL
            const jpegBlob = await canvasToBlob(canvas, 'image/jpeg', 0.84);
            const jpegBytes = new Uint8Array(await jpegBlob.arrayBuffer());
            pdf.addImage(jpegBytes, 'JPEG', x, y, width, height, undefined, 'FAST');
            const blob = pdf.output('blob');
            return new File([blob], `scanned-document-${Date.now()}.pdf`, {
                type: 'application/pdf',
                lastModified: Date.now()
            });
        } finally {
            URL.revokeObjectURL(imageUrl);
        }
    }

    function canvasToBlob(canvas, type, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('ไม่สามารถประมวลผลภาพได้')), type, quality);
        });
    }

    async function resizeImageFile(imageFile, maxSide, quality) {
        const imageUrl = URL.createObjectURL(imageFile);
        try {
            const image = await new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = imageUrl;
            });
            const scale = Math.min(1, maxSide / Math.max(image.naturalWidth, image.naturalHeight));
            if (scale === 1 && imageFile.size <= 2 * 1024 * 1024) return imageFile;

            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const context = canvas.getContext('2d', { alpha: false });
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            const blob = await canvasToBlob(canvas, 'image/jpeg', quality);
            return new File([blob], 'qr-scan.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        } finally {
            URL.revokeObjectURL(imageUrl);
        }
    }

    removeDocumentButton.addEventListener('click', clearSelectedDocument);

    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            const file = this.files[0];
            // ซ่อนข้อความลากไฟล์เดิม
            document.getElementById('upload_text_default').style.display = 'none';
            document.getElementById('upload_hint').style.display = 'none';

            // โชว์ชื่อไฟล์และปุ่ม AI
            fileNameDisplay.style.display = 'block';
            fileNameDisplay.innerHTML = `<i class="fas fa-check-circle me-1"></i> ไฟล์: <b>${file.name}</b>`;
            aiActionArea.style.display = 'block';

            if (previewObjectUrl) URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = URL.createObjectURL(file);
            previewArea.innerHTML = '';

            if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
                const frame = document.createElement('iframe');
                frame.src = previewObjectUrl + '#toolbar=1&navpanes=0';
                frame.title = 'ตัวอย่างเอกสาร PDF';
                previewArea.appendChild(frame);
                previewFileType.textContent = 'PDF';
            } else if (file.type.startsWith('image/')) {
                const image = document.createElement('img');
                image.src = previewObjectUrl;
                image.alt = 'ตัวอย่างเอกสารที่อัปโหลด';
                previewArea.appendChild(image);
                previewFileType.textContent = 'รูปภาพ';
            }
            previewCard.style.display = 'block';
        } else {
            clearSelectedDocument();
        }
    });

    window.showQrDocumentPreview = function(url) {
        qrPreviewArea.innerHTML = '';
        qrPreviewArea.classList.remove('is-external-link');
        openQrDocumentButton.href = url;
        const parsedUrl = new URL(url);
        const pathname = parsedUrl.pathname.toLowerCase();
        if (/\.(png|jpe?g|gif|webp|bmp|svg)$/.test(pathname)) {
            const image = document.createElement('img');
            image.src = url;
            image.alt = 'ตัวอย่างเอกสารจาก QR Code';
            image.referrerPolicy = 'no-referrer';
            qrPreviewArea.appendChild(image);
            qrPreviewFileType.textContent = 'QR · รูปภาพ';
        } else if (pathname.endsWith('.pdf')) {
            const frame = document.createElement('iframe');
            frame.src = url;
            frame.title = 'ตัวอย่างเอกสารจาก QR Code';
            frame.referrerPolicy = 'no-referrer';
            qrPreviewArea.appendChild(frame);
            qrPreviewFileType.textContent = 'QR · PDF';
        } else {
            // ลิงก์ย่อและหน้าเว็บจำนวนมากห้ามแสดงใน iframe ด้วย CSP/X-Frame-Options
            // จึงไม่ฝังหน้าเว็บ เพื่อตัดข้อความ “refused to connect” ที่ทำให้เข้าใจผิด
            qrPreviewArea.classList.add('is-external-link');
            const placeholder = document.createElement('div');
            placeholder.className = 'qr-link-placeholder';
            placeholder.innerHTML = `
                <div class="qr-link-icon"><i class="fas fa-link"></i></div>
                <h6 class="fw-bold text-dark mb-2">ตรวจพบลิงก์เอกสารออนไลน์</h6>
                <p class="mb-2">เว็บไซต์ต้นทางอาจไม่อนุญาตให้แสดงภายในระบบ กรุณากด “เปิดต้นฉบับ” เพื่อตรวจสอบหรือดาวน์โหลดเอกสาร</p>
                <div class="small text-muted text-break"></div>
            `;
            placeholder.querySelector('.text-break').textContent = parsedUrl.href;
            qrPreviewArea.appendChild(placeholder);
            qrPreviewFileType.textContent = 'QR · ลิงก์ออนไลน์';
        }
        qrPreviewCard.style.display = 'block';
    };

    rescanQrButton.addEventListener('click', chooseQrScanSource);
    removeQrButton.addEventListener('click', function() {
        externalUrlInput.value = '';
        qrPreviewCard.style.display = 'none';
        qrPreviewArea.innerHTML = '';
        qrPreviewArea.classList.remove('is-external-link');
        qrPreviewFileType.textContent = '';
        openQrDocumentButton.href = '#';
    });

    // 🌟 3. แจ้งเตือนสีแดง เมื่อเลือกชั้นความลับ
    const secretSelect = document.getElementById('doc_secret');
    secretSelect.addEventListener('change', function() {
        if (this.value !== 'ไม่มีชั้นความลับ') {
            this.classList.add('secret-alert');
        } else {
            this.classList.remove('secret-alert');
        }
    });
});

// ==========================================
// 🌟 4. ฟังก์ชันเปิดกล้อง QR Code
// ==========================================
let html5QrCode;
let qrScannerStarting = false;
let qrResultHandling = false;

function getQrBoxSize(viewfinderWidth, viewfinderHeight) {
    // เว้นขอบรอบ QR เพื่อช่วยจับ quiet zone ของรหัสที่พิมพ์บนกระดาษ
    const shortestSide = Math.min(viewfinderWidth, viewfinderHeight);
    const size = Math.floor(Math.min(360, shortestSide * 0.82));
    return { width: size, height: size };
}

async function chooseQrScanSource() {
    const attachedFile = document.getElementById('file_input')?.files?.[0];

    if (!attachedFile) {
        await startScanner();
        return;
    }

    const choice = await Swal.fire({
        icon: 'question',
        title: 'เลือกวิธีสแกน QR Code',
        text: `พบไฟล์ที่แนบไว้: ${attachedFile.name}`,
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-file-image me-1"></i> สแกนจากไฟล์ที่แนบ',
        denyButtonText: '<i class="fas fa-camera me-1"></i> เปิดกล้อง / ถ่ายใหม่',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    });

    if (choice.isConfirmed) {
        await scanQrFromAttachedFile(attachedFile);
    } else if (choice.isDenied) {
        await startScanner();
    }
}

async function decodeQrImageFile(imageFile) {
    const scanner = new Html5Qrcode(
        'reader',
        typeof Html5QrcodeSupportedFormats !== 'undefined' ? [Html5QrcodeSupportedFormats.QR_CODE] : undefined,
        false
    );
    try {
        return await scanner.scanFile(imageFile, false);
    } finally {
        try { scanner.clear(); } catch (error) { console.error(error); }
    }
}

async function scanQrFromAttachedFile(file) {
    if (typeof Html5Qrcode === 'undefined') {
        Swal.fire('เปิดตัวสแกนไม่ได้', 'ไม่สามารถโหลดระบบอ่าน QR Code กรุณาตรวจสอบอินเทอร์เน็ตแล้วลองใหม่', 'error');
        return;
    }

    await stopScanner();
    const qrArea = document.getElementById('qr_url_area');
    qrArea.style.display = 'block';
    Swal.fire({
        title: 'กำลังตรวจ QR Code...',
        text: file.type === 'application/pdf' ? 'กำลังตรวจเอกสาร PDF ทีละหน้า' : 'กำลังตรวจรูปภาพที่แนบ',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        let decodedText = null;
        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');

        if (!isPdf) {
            decodedText = await decodeQrImageFile(file);
        } else {
            if (!window.pdfjsLib) throw new Error('PDF reader is unavailable');
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            const pdf = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;

            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                Swal.update({ text: `กำลังตรวจหน้า ${pageNumber} จาก ${pdf.numPages}` });
                const page = await pdf.getPage(pageNumber);
                const baseViewport = page.getViewport({ scale: 1 });
                const scale = Math.min(2.2, 1800 / baseViewport.width);
                const viewport = page.getViewport({ scale });
                const canvas = document.createElement('canvas');
                canvas.width = Math.ceil(viewport.width);
                canvas.height = Math.ceil(viewport.height);
                await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                const blob = await new Promise((resolve, reject) => {
                    canvas.toBlob(value => value ? resolve(value) : reject(new Error('Cannot render PDF page')), 'image/png');
                });
                const pageImage = new File([blob], `qr-page-${pageNumber}.png`, { type: 'image/png' });

                try {
                    decodedText = await decodeQrImageFile(pageImage);
                    if (decodedText) break;
                } catch (error) {
                    // ไม่พบในหน้านี้ ให้ตรวจหน้าถัดไป
                }
                page.cleanup();
            }
        }

        if (!decodedText) throw new Error('QR code not found');
        await handleDecodedQr(decodedText);
    } catch (error) {
        console.error(error);
        await Swal.fire({
            icon: 'warning',
            title: 'ไม่พบ QR Code ในไฟล์ที่แนบ',
            text: 'ลองเปิดกล้องหรือถ่ายภาพ QR ให้ใกล้และคมชัดขึ้น',
            confirmButtonText: 'ตกลง'
        });
    } finally {
        qrArea.style.display = 'none';
    }
}

async function startScanner() {
    const qrArea = document.getElementById('qr_url_area');
    if (qrScannerStarting || (html5QrCode && html5QrCode.isScanning)) return;
    if (typeof Html5Qrcode === 'undefined') {
        qrArea.style.display = 'none';
        Swal.fire('เปิดตัวสแกนไม่ได้', 'ไม่สามารถโหลดระบบอ่าน QR Code กรุณาตรวจสอบอินเทอร์เน็ตแล้วลองใหม่', 'error');
        return;
    }

    const isSecure = window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname);
    if (!isSecure) {
        const imageInput = document.getElementById('scan_qr_image_input');
        imageInput.value = '';
        imageInput.click();
        return;
    }

    qrArea.style.display = 'block';

    qrScannerStarting = true;
    qrResultHandling = false;
    html5QrCode = new Html5Qrcode(
        "reader",
        typeof Html5QrcodeSupportedFormats !== 'undefined' ? [Html5QrcodeSupportedFormats.QR_CODE] : undefined,
        false
    );

    try {
        await html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 15,
                qrbox: getQrBoxSize,
                aspectRatio: 4 / 3,
                disableFlip: false,
                experimentalFeatures: { useBarCodeDetectorIfSupported: true }
            },
            async (decodedText) => {
                if (qrResultHandling) return;
                qrResultHandling = true;
                const handled = await handleDecodedQr(decodedText);
                if (!handled) qrResultHandling = false;
            },
            () => {
                // การอ่านไม่เจอในแต่ละเฟรมเป็นสถานะปกติ
            }
        );
    } catch (err) {
        console.error(err);
        html5QrCode = null;
        qrArea.style.display = 'none';
        const isSecure = window.isSecureContext || ['localhost', '127.0.0.1'].includes(window.location.hostname);
        const message = !isSecure
            ? 'กล้องใช้งานได้เมื่อเปิดเว็บไซต์ผ่าน HTTPS เท่านั้น'
            : 'กรุณาอนุญาตใช้กล้อง ตรวจสอบว่ากล้องไม่ถูกแอปอื่นใช้งาน แล้วลองอีกครั้ง';
        Swal.fire('ไม่สามารถเปิดกล้องได้', message, 'error');
    } finally {
        qrScannerStarting = false;
    }
}

async function handleDecodedQr(decodedText, options = {}) {
    let documentUrl;
    try {
        documentUrl = new URL(decodedText.trim());
        if (!['http:', 'https:'].includes(documentUrl.protocol)) throw new Error('Unsupported protocol');
    } catch (error) {
        await Swal.fire('QR Code ไม่ใช่ลิงก์เอกสาร', 'QR Code ต้องมีลิงก์ที่ขึ้นต้นด้วย http:// หรือ https://', 'warning');
        return false;
    }

    document.getElementById('external_url').value = documentUrl.href;
    await stopScanner();
    window.showQrDocumentPreview(documentUrl.href);
    if (options.showSuccess !== false) {
        await Swal.fire({
            icon: 'success',
            title: 'สแกนสำเร็จ!',
            text: 'แสดงตัวอย่างเอกสารแล้ว',
            timer: 1500,
            showConfirmButton: false
        });
    }
    return true;
}

async function stopScanner() {
    const scanner = html5QrCode;
    html5QrCode = null;
    try {
        if (scanner && scanner.isScanning) await scanner.stop();
        if (scanner) scanner.clear();
    } catch (err) {
        console.error(err);
    } finally {
        document.getElementById('qr_url_area').style.display = 'none';
    }
}

// ==========================================
// 🌟 5. รันเลขรับอัตโนมัติ
// ==========================================
async function autoReceiveNo() {
    try {
        const res = await fetch("{{ route('documents.api_next_number') }}?type=incoming");
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();

        if (!data.formatted || !data.next_number) throw new Error('Invalid numbering response');

        document.getElementById('receive_number').value = data.formatted;
        document.getElementById('running_number').value = data.next_number;
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'รันเลขรับสำเร็จ', showConfirmButton: false, timer: 1500 });
        return true;
    } catch (e) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบคุมเลขได้', 'error');
        return false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('docForm');
    const receiveNumber = document.getElementById('receive_number');

    if (!receiveNumber.value) autoReceiveNo();

    form.addEventListener('submit', async (event) => {
        if (receiveNumber.value) return;

        event.preventDefault();
        if (await autoReceiveNo()) form.requestSubmit();
    });
});

// ==========================================
// 🌟 6. ให้ AI ดึงข้อมูลจากไฟล์ที่แนบ
// ==========================================
async function waitForExtractionTask(statusUrl) {
    const maxAttempts = 480; // สูงสุดประมาณ 16 นาที รองรับ OCR เอกสารขนาดใหญ่

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        await new Promise(resolve => setTimeout(resolve, 2000));

        const response = await fetch(statusUrl, {
            headers: { 'Accept': 'application/json' },
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'ไม่สามารถตรวจสอบสถานะงาน OCR ได้');
        }

        if (result.status === 'completed') return result.data;
        if (result.status === 'failed') {
            throw new Error(result.message || 'ระบบไม่สามารถสกัดข้อมูลจากเอกสารนี้ได้');
        }

        const statusText = result.status === 'processing'
            ? 'กำลังอ่านข้อความและวิเคราะห์ข้อมูลด้วย AI...'
            : 'งานอยู่ในคิว รอเริ่มประมวลผล...';
        const container = Swal.getHtmlContainer();
        if (container) container.textContent = statusText;
    }

    throw new Error('งาน OCR ใช้เวลานานเกินกำหนด กรุณาลองใหม่อีกครั้ง');
}

async function extractFromAttached() {
    const fileInput = document.getElementById('file_input');

    if (!fileInput.files.length) {
        Swal.fire({ icon: 'warning', title: 'แจ้งเตือน', text: 'กรุณาแนบไฟล์เอกสารก่อนครับ' });
        return;
    }

    document.getElementById('ai_loading').style.display = 'block';

    Swal.fire({
        title: 'กำลังเตรียมงาน OCR/AI',
        html: 'กำลังอัปโหลดไฟล์เข้าสู่คิว...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
    });

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('_token', '{{ csrf_token() }}');

    try {
        const response = await fetch('{{ route("documents.auto_extract") }}', {
            method: 'POST', body: formData,
        });

        const result = await response.json();

        if (response.ok && result.success) {
            const data = await waitForExtractionTask(result.status_url);

            if(document.getElementById('doc_number')) document.getElementById('doc_number').value = data.doc_number || '';

            if(document.getElementById('doc_date') && data.doc_date) {
                let dateInput = document.getElementById('doc_date');
                dateInput.value = data.doc_date;
                if(dateInput._flatpickr) dateInput._flatpickr.setDate(data.doc_date);
            }

            if(document.getElementById('title')) document.getElementById('title').value = data.title || '';
            if(document.getElementById('doc_from')) document.getElementById('doc_from').value = data.doc_from || '';

            // ไฮไลต์ให้ผู้ใช้เห็นว่าช่องไหนถูกเติม (ลูกเล่น UI)
            ['doc_number', 'title', 'doc_from'].forEach(id => {
                if(document.getElementById(id) && document.getElementById(id).value) {
                    document.getElementById(id).style.borderColor = 'var(--green-500)';
                    document.getElementById(id).style.backgroundColor = '#f0fdf4';
                }
            });

            Swal.fire({ icon: 'success', title: 'ดึงข้อมูลสำเร็จ!', text: 'AI กรอกฟอร์มให้คุณเรียบร้อยแล้ว', timer: 2000 });
        } else {
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: result.message || 'ไม่สามารถเริ่มงาน OCR/AI ได้' });
        }
    } catch (error) {
        console.error(error);
        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: error.message || 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้' });
    } finally {
        document.getElementById('ai_loading').style.display = 'none';
    }
}
</script>
@endsection
