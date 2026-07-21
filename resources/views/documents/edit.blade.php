@extends('layouts.app')
@section('title', 'แก้ไขบันทึกข้อความ')

@section('content')
{{-- 🌟 นำเข้า Select2 CSS 🌟 --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    {{-- 🌟 Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 mx-auto" style="max-width: 900px;">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-edit text-warning me-2"></i>แก้ไขบันทึกข้อความ
            </h4>
            <p class="text-secondary small mb-0">แก้ไขข้อมูลเอกสารเพื่อให้ถูกต้องตามระเบียบ</p>
        </div>
        {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ปุ่มยกเลิกเป็น UUID --}}
        <a href="{{ route('documents.show', $document->uuid ?? $document->id) }}" class="btn btn-outline-dark btn-sm rounded-pill px-4 shadow-sm fw-bold bg-white">
            <i class="fas fa-arrow-left me-1"></i> ยกเลิกและกลับไปหน้าเดิม
        </a>
    </div>

    <div class="mx-auto" style="max-width: 900px;">
        
        {{-- 🚩 แสดงเหตุผลการตีกลับ (ถ้ามี) --}}
        @if($document->status === 'REJECTED')
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="border-radius: 12px; border-left: 5px solid #dc2626 !important;">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1">เอกสารฉบับนี้ถูกตีกลับให้แก้ไข</h6>
                    <p class="mb-0 small text-dark"><strong>เหตุผลจากผู้พิจารณา:</strong> {{ $document->reject_reason }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ Action ของ Form เป็น UUID --}}
        <form action="{{ route('documents.update', $document->uuid ?? $document->id) }}" method="POST" id="docForm" enctype="multipart/form-data">
            @csrf
            @method('PUT') {{-- บังคับใช้ PUT สำหรับการ Update --}}
            
            <div class="card border-0 shadow-sm doc-paper-container" style="border-radius: 12px; overflow: hidden;">
                {{-- Toolbar --}}
                <div class="bg-light border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <span class="badge bg-primary-subtle text-primary border rounded-pill px-3 py-2 shadow-sm">
                        <i class="fas fa-sync-alt me-1"></i> กำลังปรับปรุงข้อมูล
                    </span>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                    </button>
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
                               value="{{ $document->doc_from ?? ($document->creator->department ?? auth()->user()->department) . ' องค์การบริหารส่วนตำบลพร่อน' }}" required>
                    </div>

                    <div class="row mb-2 g-0">
                        <div class="col-6 d-flex align-items-baseline pe-3">
                            <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">ที่</span>
                            {{-- 🌟 ปรับเป็นอ่านอย่างเดียว --}}
                            <input type="text" name="doc_number" class="dfv-input flex-grow-1 text-muted" 
                                   value="{{ $document->doc_number ?? 'รอธุรการลงทะเบียนเลข' }}" readonly style="background-color: transparent;">
                        </div>
                        <div class="col-6 d-flex align-items-baseline ps-2">
                            <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">วันที่</span>
                            <input type="text" name="doc_date" id="doc_date" class="dfv-input flex-grow-1 fw-bold text-primary" 
                                   value="{{ $document->doc_date }}" required>
                        </div>
                    </div>

                    <div class="mb-3 d-flex align-items-baseline">
                        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">เรื่อง</span>
                        <input type="text" name="title" class="dfv-input flex-grow-1 text-dark" 
                               value="{{ $document->title }}" placeholder="ระบุหัวข้อเรื่อง..." required>
                    </div>

                    <div class="mb-3 d-flex align-items-baseline">
                        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; white-space: nowrap; flex-shrink: 0; color: #000;">เรียน</span>
                        <div class="flex-grow-1">
                            {{-- 🌟 ระบบ Dropdown (Select2) สไตล์กระดาษ 🌟 --}}
                            <select name="doc_to" id="doc_to" class="form-select select2-tags" required>
                                {{-- ดึงค่าเก่ามาโชว์เป็น default --}}
                                @if($document->doc_to)
                                    <option value="{{ $document->doc_to }}" selected>{{ $document->doc_to }}</option>
                                @endif
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

                    {{-- 🌟 ปุ่มเลือก Template --}}
                    <div class="mt-4 mb-2 d-flex justify-content-between align-items-end">
                        <span class="fw-bold" style="color: #1A1A2E; font-size: 15px;"><i class="fas fa-align-left me-1"></i> เนื้อหาบันทึกข้อความ</span>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle rounded-pill px-3 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-family: 'MySarabun', sans-serif;">
                                <i class="fas fa-magic me-1"></i> โหลดโครงสร้างมาตรฐาน
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

                    {{-- 🌟 เนื้อหาข้อความ (ช่องกรอก) --}}
                    <div class="mb-4">
                        <textarea name="content" id="doc_content" class="form-control content-textarea" rows="14" required>{{ $document->content }}</textarea>
                    </div>

                    {{-- 🌟 กล่องไฟล์แนบ (แสดงไฟล์เดิมและให้อัปโหลดใหม่) --}}
                    <div class="mb-5 bg-light p-3 rounded-3 border" style="font-family: 'MySarabun', sans-serif;">
                        <label class="fw-bold mb-2" style="color: #1A1A2E; font-size: 16pt;">
                            <i class="fas fa-paperclip me-1 text-primary"></i> ไฟล์เอกสารแนบ
                        </label>
                        
                        @if($document->attachment_path)
                        <div class="mb-3 p-2 bg-white rounded border d-flex justify-content-between align-items-center">
                            <span class="small text-primary"><i class="fas fa-file-pdf me-1"></i> มีไฟล์เดิมแนบอยู่: {{ basename($document->attachment_path) }}</span>
                            <a href="{{ asset('storage/' . $document->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">เปิดดูไฟล์เดิม</a>
                        </div>
                        @endif

                        <input type="file" name="file" class="form-control bg-white shadow-sm" accept=".pdf,.jpg,.jpeg,.png" style="border-radius: 8px; font-size: 16pt;">
                        <small class="text-muted mt-2 d-block" style="font-size: 14pt;">หากต้องการเปลี่ยนไฟล์ ให้เลือกไฟล์ใหม่ (รองรับ PDF, JPG, PNG ขนาดไม่เกิน 5MB)</small>
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
                                    <span class="text-muted" style="font-size: 14pt; font-family: 'MySarabun', sans-serif;">(ต้องลงนามใหม่หลังแก้ไขเสร็จ)</span>
                                </div>
                            </div>
                            <div style="font-size: 16pt; color: #000; margin-top: 5px;">( {{ $document->creator->name }} )</div>
                            <div style="font-size: 16pt; color: #000;">{{ $document->creator->position }}</div>
                        </div>
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

{{-- 🌟 ส่วนของการตกแต่ง CSS 🌟 --}}
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
        display: none !important; 
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

{{-- 🌟 ส่วนของ JavaScript การทำงาน 🌟 --}}
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

    // ตั้งค่าปฏิทินให้เป็นภาษาไทย และใช้ พ.ศ.
    flatpickr("#doc_date", {
        locale: "th",
        altInput: true,
        altFormat: "d F Y",
        dateFormat: "Y-m-d",
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
});

// 🌟 ฟังก์ชันโหลดโครงสร้าง Template
function loadTemplate(type, event) {
    if(event) event.preventDefault(); 
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
</script>
@endsection