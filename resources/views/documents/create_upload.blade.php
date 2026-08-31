@extends('layouts.app')
@section('title', 'อัปโหลดบันทึกข้อความ')

@section('content')
{{-- 🌟 นำเข้า Select2 CSS (สำหรับทำ Dropdown ค้นหา) 🌟 --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    {{-- Header --}}
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

    {{-- Toggle สลับโหมด พิมพ์ / อัปโหลด --}}
    <div class="d-flex justify-content-center mb-4 mx-auto" style="max-width: 900px;">
        <div class="bg-white p-1 rounded-pill shadow-sm border d-inline-flex">
            <a href="{{ route('documents.create') }}" class="btn text-muted rounded-pill fw-bold px-4" style="width: 180px;">
                <i class="fas fa-keyboard me-2"></i>พิมพ์ในระบบ
            </a>
            <a href="{{ route('documents.create_upload') }}" class="btn btn-primary rounded-pill fw-bold px-4" style="width: 180px;">
                <i class="fas fa-file-upload me-2"></i>อัปโหลด PDF
            </a>
        </div>
    </div>

    {{-- พื้นที่อัปโหลด --}}
    <div class="mx-auto" style="max-width: 900px;">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header bg-white border-bottom p-4">
                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>ข้อมูลเอกสารที่ต้องการอัปโหลด</h6>
            </div>
            <div class="card-body p-4 p-md-5 bg-white">
                <form action="{{ route('documents.store_upload') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">ส่วนราชการ <span class="text-danger">*</span></label>
                            <input type="text" name="doc_from" class="form-control form-control-lg text-dark" value="{{ auth()->user()->department }} องค์การบริหารส่วนตำบลพร่อน" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">ที่ (เลขที่เอกสาร)</label>
                            <input type="text" class="form-control form-control-lg bg-light text-muted" value="รอธุรการออกเลขให้เมื่ออนุมัติ" readonly style="border-radius: 10px;">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-dark">เรื่อง <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-lg" placeholder="ระบุชื่อเรื่องเอกสาร..." required style="border-radius: 10px;">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">เรียน (ผู้รับ) <span class="text-danger">*</span></label>
                            <select name="doc_to" id="doc_to" class="form-select form-select-lg select2-tags" required>
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
                            <input type="text" name="doc_date" id="doc_date" class="form-control form-control-lg bg-white" placeholder="คลิกเพื่อเลือกวันที่" required style="border-radius: 10px;">
                        </div>
                    </div>

                    {{-- 🌟 ส่วนที่เพิ่มใหม่: ชั้นความเร็ว และ ชั้นความลับ 🌟 --}}
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">ชั้นความเร็ว</label>
                            <select name="doc_speed" class="form-select form-select-lg" style="border-radius: 10px;">
                                <option value="ปกติ">ปกติ</option>
                                <option value="ด่วน">ด่วน</option>
                                <option value="ด่วนมาก">ด่วนมาก</option>
                                <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold small text-dark">ชั้นความลับ</label>
                            <select name="doc_secret" id="doc_secret" class="form-select form-select-lg text-dark" style="border-radius: 10px; transition: 0.3s;">
                                <option value="ไม่มีชั้นความลับ">ไม่มีชั้นความลับ (ปกติ)</option>
                                <option value="ลับ">ลับ</option>
                                <option value="ลับมาก">ลับมาก</option>
                                <option value="ลับที่สุด">ลับที่สุด</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-5 p-4 rounded-3 text-center" style="background-color: #f8fafc; border: 2px dashed #cbd5e1;">
                        <i class="fas fa-file-pdf text-danger mb-3" style="font-size: 40px;"></i>
                        <h6 class="fw-bold text-dark">อัปโหลดไฟล์เอกสาร (PDF)</h6>
                        <p class="text-muted small mb-3">กรุณาเลือกไฟล์บันทึกข้อความที่เซฟมาจาก MS Word (ไม่เกิน 5MB)</p>
                        <input type="file" name="file" class="form-control mx-auto" accept=".pdf" required style="border-radius: 8px; max-width: 400px;">
                    </div>

                    @include('documents.partials.route_selector')

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm px-5" style="background-color: var(--primary);">
                            <i class="fas fa-cloud-upload-alt me-2"></i>อัปโหลดและไปหน้าลงนาม
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
        height: 48px; /* ขนาดเท่า form-control-lg */
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
    
    // เริ่มต้นใช้งาน Select2 (พิมค้นหาได้ + พิมพ์คำใหม่เพิ่มเองได้)
    $('.select2-tags').select2({
        theme: 'bootstrap-5',
        tags: true, // เปิดฟังก์ชันให้พิมพ์เองได้
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
</script>
@endsection
