@extends('layouts.app')
@section('title', 'แก้ไขเอกสารอัปโหลด')

@section('content')
{{-- 🌟 นำเข้า Select2 CSS 🌟 --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 mx-auto" style="max-width: 900px;">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-edit text-warning me-2"></i>แก้ไขบันทึกข้อความ (แบบอัปโหลด PDF)
            </h4>
            <p class="text-secondary small mb-0">แก้ไขข้อมูลหรือเปลี่ยนไฟล์เอกสาร PDF ใหม่</p>
        </div>
        {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ปุ่มยกเลิกเป็น UUID --}}
        <a href="{{ route('documents.show', $document->uuid ?? $document->id) }}" class="btn btn-outline-dark btn-sm rounded-pill px-4 shadow-sm fw-bold bg-white">
            <i class="fas fa-arrow-left me-1"></i> ยกเลิก
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

        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white border-bottom p-4">
                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>ข้อมูลเอกสารที่ต้องการแก้ไข</h6>
            </div>
            <div class="card-body p-4 p-md-5 bg-white">
                {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ Action ของ Form เป็น UUID --}}
                <form action="{{ route('documents.update', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">ส่วนราชการ <span class="text-danger">*</span></label>
                            <input type="text" name="doc_from" class="form-control form-control-lg text-dark" 
                                   value="{{ $document->doc_from ?? ($document->creator->department ?? auth()->user()->department) . ' องค์การบริหารส่วนตำบลพร่อน' }}" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">ที่ (เลขที่เอกสาร)</label>
                            <input type="text" class="form-control form-control-lg bg-light text-muted" 
                                   value="{{ $document->formatted_doc_number ?? 'รอธุรการออกเลขให้เมื่ออนุมัติ' }}" readonly style="border-radius: 10px;">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-dark">เรื่อง <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-lg" 
                               value="{{ $document->title }}" required style="border-radius: 10px;">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">เรียน (ผู้รับ) <span class="text-danger">*</span></label>
                            <select name="doc_to" id="doc_to" class="form-select form-select-lg select2-tags" required>
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
                            <small class="text-muted mt-1 d-block"><i class="fas fa-search text-primary"></i> เลือกจากเมนู หรือพิมพ์ชื่อเฉพาะเจาะจงแล้วกด Enter</small>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">วันที่ลงในเอกสาร <span class="text-danger">*</span></label>
                            <input type="text" name="doc_date" id="doc_date" class="form-control form-control-lg bg-white" 
                                   value="{{ $document->doc_date }}" required style="border-radius: 10px;">
                        </div>
                    </div>

                    <div class="mb-5 p-4 rounded-3 text-center" style="background-color: #f8fafc; border: 2px dashed #cbd5e1;">
                        <i class="fas fa-file-pdf text-danger mb-3" style="font-size: 40px;"></i>
                        <h6 class="fw-bold text-dark mb-3">ไฟล์เอกสาร PDF</h6>
                        
                        @if($document->attachment_path)
                        <div class="d-inline-flex align-items-center mb-3 p-2 bg-white rounded border shadow-sm">
                            <span class="small text-primary fw-bold me-3"><i class="fas fa-check-circle text-success me-1"></i> ไฟล์เดิม: {{ basename($document->attachment_path) }}</span>
                            <a href="{{ asset('storage/' . $document->attachment_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill">ดูไฟล์เดิม</a>
                        </div>
                        @endif

                        <p class="text-muted small mb-2 mt-3">หากต้องการเปลี่ยนไฟล์ ให้เลือกไฟล์ใหม่ด้านล่าง (ไม่เกิน 5MB)</p>
                        <input type="file" name="file" class="form-control mx-auto" accept=".pdf" style="border-radius: 8px; max-width: 400px;">
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-success btn-lg rounded-pill fw-bold shadow-sm px-5">
                            <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- 🌟 นำเข้า Flatpickr และ Select2 JS 🌟 --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style>
    input[type="date"]::-webkit-calendar-picker-indicator { display: none; }
    
    /* ปรับแต่ง Select2 ให้เข้ากับธีม Bootstrap 5 */
    .select2-container .select2-selection--single {
        height: 48px; 
        border-radius: 10px;
        border: 1px solid #ced4da;
        display: flex;
        align-items: center;
    }
    .select2-container--bootstrap-5 .select2-selection__rendered {
        padding-left: 1rem;
        font-size: 1.125rem;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    
    // เริ่มต้นใช้งาน Select2 
    $('.select2-tags').select2({
        theme: 'bootstrap-5',
        tags: true, 
        placeholder: "-- เลือกผู้รับ หรือพิมพ์ระบุเอง --",
        allowClear: true,
        width: '100%'
    });

    // ตั้งค่าปฏิทิน
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
</script>
@endsection
