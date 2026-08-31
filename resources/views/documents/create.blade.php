@extends('layouts.app')
@section('title', 'สร้างบันทึกข้อความ')

@section('content')
{{-- 🌟 นำเข้า Select2 CSS 🌟 --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    {{-- 🌟 Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 mx-auto" style="max-width: 900px;">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-file-signature text-primary me-2"></i>สร้างบันทึกข้อความ
            </h4>
            <p class="text-secondary small mb-0">สร้างใหม่ผ่านระบบ หรืออัปโหลดไฟล์ PDF</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="btn btn-light btn-sm rounded-pill px-3 shadow-sm border">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    {{-- 🌟 Toggle สลับโหมด พิมพ์ / อัปโหลด --}}
    <div class="d-flex justify-content-center mb-4 mx-auto" style="max-width: 900px;">
        <div class="bg-white p-1 rounded-pill shadow-sm border d-inline-flex">
            <a href="{{ route('documents.create') }}" class="btn btn-primary rounded-pill fw-bold px-4" style="width: 180px;">
                <i class="fas fa-keyboard me-2"></i>พิมพ์ในระบบ
            </a>
            <a href="{{ route('documents.create_upload') }}" class="btn text-muted rounded-pill fw-bold px-4" style="width: 180px;">
                <i class="fas fa-file-upload me-2"></i>อัปโหลด PDF
            </a>
        </div>
    </div>

    {{-- 🌟 พื้นที่กระดาษ A4 (Editor) --}}
    <div class="mx-auto" style="max-width: 900px;">
        <form action="{{ route('documents.store') }}" method="POST" id="docForm" enctype="multipart/form-data">
            @csrf
            
            <div class="card border-0 shadow-sm doc-paper-container" style="border-radius: 12px; overflow: hidden;">
                
                {{-- แถบสถานะด้านบนกระดาษ --}}
                <div class="bg-light border-bottom px-4 py-3">
                    <span class="badge bg-warning-subtle text-warning-emphasis border rounded-pill px-3 py-2 shadow-sm" style="font-size: 13px;">
                        <i class="fas fa-pen me-1"></i> โหมดร่างเอกสาร
                    </span>
                </div>

                <div class="card-body p-4 p-md-5 bg-white doc-paper">
                    
                    {{-- 🌟 โลโก้ครุฑ และ บันทึกข้อความ --}}
                    <div class="position-relative mb-4" style="min-height: 1.5cm; margin-top: 1cm;">
                        <div class="position-absolute" style="top: -0.5cm; left: 0;">
                            <img src="{{ asset('images/krut.png') }}" alt="ตราครุฑ" style="height: 1.5cm; width: auto; object-fit: contain;">
                        </div>
                        <div class="w-100 text-center" style="padding-top: 10px;">
                            <span style="font-size: 29pt; font-weight: bold; color: #000; letter-spacing: 1px;">บันทึกข้อความ</span>
                        </div>
                    </div>

                    {{-- 🌟 ฟอร์มกรอกข้อมูลสไตล์กระดาษ --}}
                    <div class="mb-2 d-flex align-items-baseline">
                        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">ส่วนราชการ</span>
                        <input type="text" name="doc_from" class="dfv-input flex-grow-1 text-dark" 
                               value="{{ auth()->user()->department }} องค์การบริหารส่วนตำบลพร่อน" required>
                    </div>

                    <div class="row mb-2 g-0">
                        <div class="col-6 d-flex align-items-baseline pe-3">
                            <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">ที่</span>
                            <input type="text" class="dfv-input flex-grow-1 text-muted" value="รอธุรการลงทะเบียนเลข" readonly>
                        </div>
                        <div class="col-6 d-flex align-items-baseline ps-2">
                            <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">วันที่</span>
                            <input type="text" name="doc_date" id="doc_date" class="dfv-input flex-grow-1 fw-bold text-primary" placeholder="คลิกเพื่อเลือกวันที่" required>
                        </div>
                    </div>

                    <div class="mb-3 d-flex align-items-baseline">
                        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">เรื่อง</span>
                        <input type="text" name="title" class="dfv-input flex-grow-1 text-dark" placeholder="ระบุหัวข้อเรื่อง..." required>
                    </div>

                    <div class="mb-3 d-flex align-items-baseline">
                        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">เรียน</span>
                        <div class="flex-grow-1">
                            {{-- 🌟 ระบบ Dropdown (Select2) สไตล์กระดาษ 🌟 --}}
                            <select name="doc_to" id="doc_to" class="form-select select2-tags" required>
                                <option value="">-- เลือกผู้รับ หรือพิมพ์ระบุเอง --</option>
                                <optgroup label="ผู้บริหาร (ส่งขึ้นข้างบน)">
                                    <option value="นายกองค์การบริหารส่วนตำบล">นายกองค์การบริหารส่วนตำบล</option>
                                    <option value="ปลัดองค์การบริหารส่วนตำบล">ปลัดองค์การบริหารส่วนตำบล</option>
                                    <option value="รองปลัดองค์การบริหารส่วนตำบล">รองปลัดองค์การบริหารส่วนตำบล</option>
                                </optgroup>
                                <optgroup label="หัวหน้าส่วนราชการ (ส่งระดับเดียวกัน/ประสานงาน)">
                                    <option value="หัวหน้าสำนักปลัด">หัวหน้าสำนักปลัด</option>
                                    <option value="ผู้อำนวยการกองคลัง">ผู้อำนวยการกองคลัง</option>
                                    <option value="ผู้อำนวยการกองช่าง">ผู้อำนวยการกองช่าง</option>
                                    <option value="ผู้อำนวยการกองการศึกษา ศาสนา และวัฒนธรรม">ผู้อำนวยการกองการศึกษาฯ</option>
                                    <option value="ผู้อำนวยการกองสาธารณสุขและสิ่งแวดล้อม">ผู้อำนวยการกองสาธารณสุขฯ</option>
                                    <option value="ผู้อำนวยการกองสวัสดิการสังคม">ผู้อำนวยการกองสวัสดิการสังคม</option>
                                </optgroup>
                                <optgroup label="สั่งการ / แจ้งเวียน (ส่งลงข้างล่าง)">
                                    <option value="หัวหน้าส่วนราชการทุกส่วน">หัวหน้าส่วนราชการทุกส่วน</option>
                                    <option value="พนักงานส่วนตำบล และพนักงานจ้าง ทุกคน">พนักงานส่วนตำบล และพนักงานจ้าง ทุกคน</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    {{-- 🌟 ปุ่มเลือก Template และ AI --}}
                    <div class="mt-4 mb-2 d-flex justify-content-between align-items-end">
                        <span class="fw-bold" style="color: #1A1A2E; font-size: 15px;"><i class="fas fa-align-left me-1"></i> เนื้อหาบันทึกข้อความ</span>
                        <div class="d-flex gap-2">
                            {{-- ✨ ปุ่ม AI --}}
                            <button type="button" onclick="askAI()" class="btn btn-sm rounded-pill px-3 shadow-sm fw-bold" style="background: linear-gradient(45deg, #8b5cf6, #3b82f6); color: white; border: none; font-family: 'MySarabun', sans-serif; font-size: 16pt;">
                                <i class="fas fa-sparkles me-1"></i> ให้ AI ช่วยร่าง
                            </button>

                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle rounded-pill px-3 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-family: 'MySarabun', sans-serif; font-size: 16pt;">
                                    <i class="fas fa-magic me-1"></i> โครงสร้างมาตรฐาน
                                </button>
                                <ul class="dropdown-menu shadow border-0" style="font-family: 'MySarabun', sans-serif; font-size: 16pt;">
                                    <li>
                                        <a class="dropdown-item py-2" href="#" onclick="loadTemplate('3parts', event)">
                                            <i class="fas fa-file-alt text-primary me-2"></i><strong>แบบ ๓ ย่อหน้า</strong> (เหตุ, ประสงค์, สรุป)
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item py-2" href="#" onclick="loadTemplate('5parts', event)">
                                            <i class="fas fa-list-ol text-success me-2"></i><strong>แบบองค์ ๕</strong> (เรื่องเดิม, ข้อเท็จจริง, กฎหมาย, ข้อพิจารณา, ข้อเสนอ)
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- 🌟 เนื้อหาข้อความ (ช่องกรอก) --}}
                    <div class="mb-4">
                        <textarea name="content" id="doc_content" class="form-control content-textarea" rows="14" placeholder="พิมพ์เนื้อหาบันทึกข้อความที่นี่..." required></textarea>
                    </div>

                    {{-- 🌟 กล่องอัปโหลดไฟล์ --}}
                    <div class="mb-5 bg-light p-3 rounded-3 border" style="font-family: 'MySarabun', sans-serif;">
                        <label class="fw-bold mb-2" style="color: #1A1A2E; font-size: 16pt;">
                            <i class="fas fa-paperclip me-1 text-primary"></i> แนบไฟล์เอกสารเพิ่มเติม (ถ้ามี)
                        </label>
                        <input type="file" name="file" class="form-control bg-white shadow-sm" accept=".pdf,.jpg,.jpeg,.png" style="border-radius: 8px; font-size: 16pt;">
                        <small class="text-muted mt-2 d-block" style="font-size: 14pt;">รองรับไฟล์ PDF, JPG, PNG ขนาดไม่เกิน 5MB</small>
                    </div>

                    {{-- คำลงท้าย --}}
                    <div class="mb-5" style="font-size: 16pt; margin-left: 2.5cm; color: #000;">
                        จึงเรียนมาเพื่อโปรดทราบ
                    </div>

                    {{-- ลายเซ็นจำลอง --}}
                    <div class="row justify-content-end mt-5">
                        <div class="col-md-6 text-center">
                            <div class="d-flex align-items-end justify-content-center mb-2">
                                <span class="me-2" style="font-size: 16pt; color: #000;">(ลงชื่อ)</span>
                                <div style="border-bottom: 1px dotted #9CA3AF; width: 200px; height: 25px;">
                                    <span class="text-muted" style="font-size: 14pt; font-family: 'MySarabun', sans-serif;">(รอลงนามหลังจากบันทึกร่าง)</span>
                                </div>
                            </div>
                            <div style="font-size: 16pt; color: #000; margin-top: 5px;">( {{ auth()->user()->name }} )</div>
                            <div style="font-size: 16pt; color: #000;">{{ auth()->user()->position ?? 'ตำแหน่ง' }}</div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- เลือกเส้นทางหลังกรอกเนื้อหา เพื่อจัดลำดับผู้รับได้สะดวก --}}
            <div class="mt-4">
                @include('documents.partials.route_selector')
            </div>

            {{-- ตั้งค่าการส่งและปุ่มดำเนินการ อยู่ท้ายฟอร์มตามลำดับการใช้งาน --}}
            <div class="card border-0 shadow-sm mb-4 submission-settings" style="border-radius: 14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center me-2" style="width: 34px; height: 34px;">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">ระดับความเร็วและความลับ</h6>
                            <small class="text-muted">ตรวจสอบค่าก่อนส่งเอกสารเข้าสู่เส้นทางพิจารณา</small>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="doc_speed" class="form-label fw-bold text-secondary small">ชั้นความเร็ว</label>
                            <select name="doc_speed" id="doc_speed" class="form-select shadow-sm">
                                <option value="ปกติ" {{ old('doc_speed') === 'ปกติ' ? 'selected' : '' }}>ปกติ</option>
                                <option value="ด่วน" {{ old('doc_speed') === 'ด่วน' ? 'selected' : '' }}>ด่วน</option>
                                <option value="ด่วนมาก" {{ old('doc_speed') === 'ด่วนมาก' ? 'selected' : '' }}>ด่วนมาก</option>
                                <option value="ด่วนที่สุด" {{ old('doc_speed') === 'ด่วนที่สุด' ? 'selected' : '' }}>ด่วนที่สุด</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="doc_secret" class="form-label fw-bold text-secondary small">ชั้นความลับ</label>
                            <select name="doc_secret" id="doc_secret" class="form-select shadow-sm text-dark" style="transition: 0.3s;">
                                <option value="ไม่มีชั้นความลับ" {{ old('doc_secret') === 'ไม่มีชั้นความลับ' ? 'selected' : '' }}>ไม่มีชั้นความลับ</option>
                                <option value="ลับ" {{ old('doc_secret') === 'ลับ' ? 'selected' : '' }}>ลับ</option>
                                <option value="ลับมาก" {{ old('doc_secret') === 'ลับมาก' ? 'selected' : '' }}>ลับมาก</option>
                                <option value="ลับที่สุด" {{ old('doc_secret') === 'ลับที่สุด' ? 'selected' : '' }}>ลับที่สุด</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm" style="background-color: var(--primary);">
                            <i class="fas fa-save me-2"></i>บันทึกร่างและดำเนินการต่อ
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- 🌟 รวม Library ภายนอก 🌟 --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    @font-face { font-family: 'MySarabun'; src: url('{{ asset("fonts/THSarabunIT๙.ttf") }}') format('truetype'); font-weight: normal; font-style: normal; }
    @font-face { font-family: 'MySarabun'; src: url('{{ asset("fonts/THSarabunIT๙_Bold.ttf") }}') format('truetype'); font-weight: bold; font-style: normal; }

    .doc-paper, .doc-paper * {
        font-family: 'MySarabun', sans-serif !important;
        line-height: 1.15 !important; 
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    .dfv-input {
        border: none;
        border-bottom: 1px dotted #000;
        font-family: 'MySarabun', sans-serif !important;
        font-size: 16pt; 
        color: #000;
        background: transparent !important;
        outline: none;
        padding: 0 0 2px 10px;
        transition: all 0.2s;
    }
    .dfv-input:focus:not([readonly]) {
        border-bottom: 1.5px solid var(--primary);
    }
    
    /* 🌟 แต่ง Select2 ให้กลืนไปกับกระดาษ A4 🌟 */
    .doc-paper .select2-container--bootstrap-5 .select2-selection {
        background-color: transparent !important;
        border: none !important;
        border-bottom: 1px dotted #000 !important;
        border-radius: 0 !important;
        padding: 0 0 2px 10px !important;
        min-height: auto !important;
    }
    .doc-paper .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-bottom: 1.5px solid var(--primary) !important;
        box-shadow: none !important;
    }
    .doc-paper .select2-container--bootstrap-5 .select2-selection__rendered {
        font-family: 'MySarabun', sans-serif !important;
        font-size: 16pt !important;
        color: #000 !important;
        padding: 0 !important;
    }
    .doc-paper .select2-container--bootstrap-5 .select2-selection__arrow {
        display: none !important; /* ซ่อนลูกศรให้ดูเหมือนช่องเขียนหนังสือ */
    }
    
    .content-textarea {
        border: 1px dashed #cbd5e1;
        border-radius: 4px;
        padding: 20px;
        font-family: 'MySarabun', sans-serif !important;
        font-size: 16pt;
        line-height: 1.15; 
        color: #000;
        resize: vertical;
        background-color: #fafafa;
        transition: all 0.2s;
    }
    .content-textarea:focus {
        border-color: var(--primary);
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
        outline: none;
    }

    input[type="date"]::-webkit-calendar-picker-indicator { display: none; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // เริ่มต้นใช้งาน Select2 สำหรับช่อง เรียน
    $('.select2-tags').select2({
        theme: 'bootstrap-5',
        tags: true, 
        placeholder: "-- เลือกผู้รับ หรือพิมพ์ระบุเอง --",
        allowClear: true,
        width: '100%'
    });

    flatpickr("#doc_date", {
        locale: "th",
        altInput: true,
        altFormat: "d F Y", 
        dateFormat: "Y-m-d", 
        defaultDate: "today",
        formatDate: function(date, format, locale) {
            let d = date.getDate();
            let m = date.toLocaleString('th-TH', { month: 'long' });
            let y = date.getFullYear() + 543; 
            if (format === "d F Y") { return `${d} ${m} ${y}`; }
            let dd = ("0" + date.getDate()).slice(-2);
            let mm = ("0" + (date.getMonth() + 1)).slice(-2);
            let yyyy = date.getFullYear();
            if (format === "Y-m-d") { return `${yyyy}-${mm}-${dd}`; }
            return date.toLocaleDateString('th-TH');
        }
    });

    // 🌟 ลูกเล่นแจ้งเตือนสายตา (UI) หากเลือกชั้นความลับ "ลับ" ขึ้นไป
    const secretSelect = document.getElementById('doc_secret');
    if (secretSelect) {
        secretSelect.addEventListener('change', function() {
            if (this.value !== 'ไม่มีชั้นความลับ') {
                this.classList.remove('text-dark');
                this.classList.add('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
            } else {
                this.classList.remove('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
                this.classList.add('text-dark');
            }
        });
    }
});

function loadTemplate(type, event) {
    if (event) {
        event.preventDefault();
    }
    const textarea = document.getElementById('doc_content');
    const currentContent = textarea.value.trim();

    let templateContent = "";
    if (type === '3parts') {
        templateContent = "\t\t(ภาคเหตุ)\n\n\t\t(ภาคความประสงค์)\n\n\t\t(ภาคสรุป)";
    } else if (type === '5parts') {
        templateContent = "\t\t๑. เรื่องเดิม\n\t\t\n\n\t\t๒. ข้อเท็จจริง\n\t\t\n\n\t\t๓. ข้อกฎหมาย/ระเบียบที่เกี่ยวข้อง\n\t\tตามระเบียบ\n\n\t\t๔. ข้อพิจารณา\n\t\t\n\n\t\t๕. ข้อเสนอ\n\t\t";
    }

    if (currentContent !== "") {
        Swal.fire({
            title: 'แทนที่เนื้อหาเดิม?',
            text: "การโหลดโครงสร้างใหม่ จะลบข้อความเดิมที่คุณพิมพ์ไว้ทั้งหมด",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ใช่, แทนที่เลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                textarea.value = templateContent;
                textarea.focus();
            }
        });
    } else {
        textarea.value = templateContent;
        textarea.focus();
    }
}

async function askAI(previousTopic = '') {
    const { value: topic } = await Swal.fire({
        title: '✨ ให้ AI ช่วยร่างบันทึกข้อความ',
        input: 'textarea',
        inputLabel: 'พิมพ์รายละเอียดสั้นๆ ที่ต้องการ',
        inputValue: previousTopic, 
        inputPlaceholder: 'เช่น ขออนุมัติจัดซื้อคอมพิวเตอร์ 3 เครื่อง งบ 50,000 บาท...',
        showCancelButton: true,
        confirmButtonText: 'ร่างข้อความ',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#8b5cf6',
        inputValidator: (value) => {
            if (!value) { return 'กรุณาพิมพ์รายละเอียดสักนิดนะครับ!' }
        }
    });

    if (topic) {
        Swal.fire({
            title: '✨ AI กำลังทำงาน...',
            html: '<div id="ai-status" class="fw-bold text-primary mt-2">✍️ กำลังวิเคราะห์เรื่อง...</div>',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                setTimeout(() => {
                    const statusEl = document.getElementById('ai-status');
                    if(statusEl) statusEl.innerHTML = '📝 กำลังเรียบเรียงภาษาราชการ...';
                }, 2000);
                setTimeout(() => {
                    const statusEl = document.getElementById('ai-status');
                    if(statusEl) statusEl.innerHTML = '✅ ใกล้เสร็จแล้ว...';
                }, 5000);
            }
        });

        try {
            const response = await fetch('{{ route("ai.generate_draft") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ topic: topic })
            });

            const data = await response.json();

            if (response.ok) {
                const textArea = document.getElementById('doc_content');
                textArea.value = data.draft;
                
                textArea.scrollIntoView({ behavior: 'smooth', block: 'center' });

                Swal.fire({
                    icon: 'success',
                    title: 'ร่างข้อความสำเร็จ!',
                    text: 'พอใจกับเนื้อหาที่ AI ร่างให้ไหมครับ?',
                    showDenyButton: true,
                    confirmButtonText: 'พอใจแล้ว',
                    denyButtonText: 'ร่างใหม่อีกครั้ง',
                    confirmButtonColor: '#10b981',
                    denyButtonColor: '#6b7280'
                }).then((result) => {
                    if (result.isDenied) {
                        askAI(topic); 
                    }
                });

            } else if (response.status === 429) {
                Swal.fire('AI ยุ่งอยู่ขณะนี้', 'มีการใช้งานคำขอเกินกำหนด กรุณารอสักครู่แล้วลองใหม่อีกครั้ง', 'warning');
            } else {
                Swal.fire('เกิดข้อผิดพลาด', data.error || 'ไม่สามารถติดต่อ AI ได้', 'error');
            }
        } catch (error) {
            Swal.fire('เกิดข้อผิดพลาด', 'ระบบเครือข่ายมีปัญหา กรุณาลองใหม่', 'error');
        }
    }
}
</script>
@endsection
