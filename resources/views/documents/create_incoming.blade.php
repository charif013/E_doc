@extends('layouts.app')

@section('title', 'บันทึกหนังสือเข้าใหม่')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    {{-- Breadcrumb & Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                <i class="fas fa-file-import text-primary me-2"></i>บันทึกหนังสือเข้าใหม่
            </h4>
            <small class="text-muted">กรอกข้อมูลและแนบไฟล์ หรือสแกน QR Code เพื่อดึงข้อมูลอัตโนมัติ</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="btn btn-light btn-sm rounded-pill px-3 shadow-sm border">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
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

    <form action="{{ route('documents.store_incoming') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- 🌟 ส่วนที่ 1: ข้อมูลหนังสือเข้า (โทนสีฟ้า) --}}
        <div class="card border-0 mb-4 shadow-sm" style="border-radius: 16px; border-top: 4px solid var(--primary) !important;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold text-primary mb-0"><i class="fas fa-list-alt me-2"></i> 1. ข้อมูลหนังสือเข้า</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">เลขที่รับ <span class="text-danger">*</span></label>
                        {{-- 🌟 ปรับปรุงเป็นแบบรันเลขอัตโนมัติจากระบบคุมเลขหลังบ้าน --}}
                        <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                            <input type="hidden" name="running_number" id="running_number" value="{{ old('running_number') }}">
                            <input type="text" name="receive_number" id="receive_number" class="form-control border-0 bg-light" placeholder="คลิกปุ่มขวาเพื่อรันเลขรับ..." required value="{{ old('receive_number') }}">
                            <button type="button" onclick="autoReceiveNo()" class="btn btn-primary fw-bold px-3">รันเลขรับ</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">วันที่รับเอกสาร <span class="text-danger">*</span></label>
                        <input type="text" name="receive_date" class="form-control px-3 py-2 thai-datepicker bg-white" value="{{ date('Y-m-d') }}" required style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">เลขที่หนังสือราชการต้นทาง <span class="text-danger">*</span></label>
                        <input type="text" name="doc_number" class="form-control px-3 py-2" value="{{ old('doc_number') }}" placeholder="เช่น กค 0405/ว 1234" required style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">ลงวันที่บนหนังสือเอกสาร <span class="text-danger">*</span></label>
                        <input type="text" name="doc_date" class="form-control px-3 py-2 thai-datepicker bg-white" value="{{ old('doc_date') ?? date('Y-m-d') }}" required style="border-radius: 8px;">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label text-secondary fw-bold small">เรื่อง <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control px-3 py-2" value="{{ old('title') }}" placeholder="ระบุชื่อเรื่องของหนังสือ" required style="border-radius: 8px;">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">จาก (หน่วยงาน/บุคคล) <span class="text-danger">*</span></label>
                        <input type="text" name="doc_from" class="form-control px-3 py-2" placeholder="เช่น กรมส่งเสริมการปกครองท้องถิ่น" required style="border-radius: 8px;">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">ประเภทหนังสือรับเข้า <span class="text-danger">*</span></label>
                        <select name="doc_type_category" class="form-select px-3 py-2 fw-bold text-dark" required style="border-radius: 8px;">
                            <option value="">-- เลือกประเภท --</option>
                            <option value="หนังสือภายนอก (กระดาษตราครุฑ)">หนังสือภายนอก (กระดาษตราครุฑ)</option>
                            <option value="หนังสือประทับตรา">หนังสือประทับตรา</option>
                            <option value="หนังสือสั่งการ">หนังสือสั่งการ</option>
                            <option value="หนังสือประชาสัมพันธ์">หนังสือประชาสัมพันธ์</option>
                            <option value="หนังสืออื่น / เอกสารรับเข้าอื่นๆ"># หนังสืออื่น / เอกสารรับเข้าอื่นๆ (คำร้อง/ใบคำขอ)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">ชั้นความเร็ว</label>
                        <select name="doc_speed" class="form-select px-3 py-2" style="border-radius: 8px;">
                            <option value="ปกติ">ปกติ</option>
                            <option value="ด่วน">ด่วน</option>
                            <option value="ด่วนมาก">ด่วนมาก</option>
                            <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">ชั้นความลับ</label>
                        <select name="doc_secret" id="doc_secret" class="form-select px-3 py-2 text-dark" style="border-radius: 8px; transition: 0.3s;">
                            <option value="ไม่มีชั้นความลับ">ไม่มีชั้นความลับ (ปกติ)</option>
                            <option value="ลับ">ลับ</option>
                            <option value="ลับมาก">ลับมาก</option>
                            <option value="ลับที่สุด">ลับที่สุด</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- 🌟 ส่วนที่ 2: แนบไฟล์เอกสาร (โทนสีส้ม/เหลือง) --}}
        <div class="card border-0 mb-4 shadow-sm" style="border-radius: 16px; border-top: 4px solid #f59e0b !important;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold mb-0" style="color: #d97706;"><i class="fas fa-paperclip me-2"></i> 2. แนบไฟล์เอกสาร</h5>
            </div>
            <div class="card-body p-4">
                
                {{-- ปุ่มเลือกวิธีอัปโหลด --}}
                <div class="btn-group w-100 mb-4 shadow-sm" role="group">
                    <input type="radio" class="btn-check" name="upload_method" id="method_file" value="file" checked>
                    <label class="btn btn-outline-warning py-2 fw-bold text-dark" for="method_file" style="border-radius: 8px 0 0 8px;">
                        <i class="fas fa-file-upload me-2 text-warning-emphasis"></i> เลือกไฟล์จากเครื่อง
                    </label>

                    <input type="radio" class="btn-check" name="upload_method" id="method_qr" value="qr">
                    <label class="btn btn-outline-warning py-2 fw-bold text-dark" for="method_qr" style="border-radius: 0 8px 8px 0;">
                        <i class="fas fa-qrcode me-2 text-warning-emphasis"></i> สแกน QR Code
                    </label>
                </div>

                {{-- กล่อง Drag & Drop (ไฟล์) --}}
                <div id="upload_area" class="text-center p-5 rounded-3 position-relative" style="border: 2px dashed #fcd34d; background-color: #fffbeb; transition: all 0.3s ease;">
                    <input type="file" name="file" id="file_input" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer;" accept=".pdf,.jpg,.jpeg,.png">
                    
                    <div class="mb-3">
                        <div class="bg-warning bg-opacity-25 text-warning-emphasis rounded-circle d-inline-flex justify-content-center align-items-center" style="width: 70px; height: 70px;">
                            <i class="fas fa-cloud-upload-alt fs-2"></i>
                        </div>
                    </div>
                    <h6 class="text-dark mb-2 fw-bold">ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์</h6>
                    <p class="text-muted small mb-0">รองรับ PDF, JPG, PNG — ขนาดไม่เกิน 5 MB</p>
                    
                    <div id="file_name_display" class="mt-3 text-success fw-bold p-2 bg-white rounded shadow-sm border border-success-subtle d-inline-block" style="display: none;"></div>
                </div>

                {{-- ส่วนสแกน QR Code (กล้อง) --}}
                <div id="qr_url_area" style="display: none;">
                    <div class="row justify-content-center">
                        <div class="col-md-8 text-center">
                            <div id="reader" class="bg-white shadow-sm mx-auto border border-warning" style="width: 100%; max-width: 400px; border-radius: 12px; overflow: hidden;"></div>
                            <p class="mt-3 text-muted small fw-bold"><i class="fas fa-camera text-warning me-1"></i> จ่อ QR Code หน้ากล้องเพื่อดึงลิงก์เอกสาร</p>
                            
                            <div class="mt-4 text-start bg-light p-3 rounded-3 border">
                                <label class="form-label text-dark small fw-bold">🔗 ลิงก์เอกสารที่สแกนได้:</label>
                                <input type="url" name="external_url" id="external_url" class="form-control px-3 py-2 text-primary fw-bold bg-white" placeholder="ลิงก์จะปรากฏที่นี่อัตโนมัติ..." readonly style="border-radius: 8px;">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- 🌟 ส่วนที่ 3: กำหนดผู้รับผิดชอบ (โทนสีเขียว) --}}
        <div class="card border-0 mb-4 shadow-sm" style="border-radius: 16px; border-top: 4px solid #10b981 !important;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold text-success mb-0"><i class="fas fa-user-check me-2"></i> 3. กำหนดผู้รับผิดชอบ</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">ผู้รับเอกสาร (ลงทะเบียน)</label>
                        <input type="text" class="form-control px-3 py-2 bg-light text-muted" value="{{ auth()->user()->name ?? 'ไม่ระบุ' }}" readonly style="border-radius: 8px;">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary fw-bold small">เสนอให้ผู้มีอำนาจลงนามลำดับต่อไป</label>
                        <select name="approver_id" class="form-select px-3 py-2 bg-light text-muted" disabled style="border-radius: 8px;">
                            <option selected>ส่งให้ หัวหน้าสำนักปลัด (อัตโนมัติ)</option>
                        </select>
                        <small class="text-success mt-2 d-block fw-bold"><i class="fas fa-info-circle"></i> ระบบจะส่งเรื่องตามลำดับขั้นให้อัตโนมัติ (ธุรการ -> หัวหน้า -> ปลัด -> นายก)</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- ปุ่ม Action --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 pb-5">
            <div class="text-muted small mb-3 mb-md-0 fw-bold">
                ช่องที่มี <span class="text-danger">*</span> จำเป็นต้องกรอกข้อมูลให้ครบถ้วน
            </div>
            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm rounded-pill">
                    <i class="fas fa-paper-plane me-2"></i> บันทึกและส่งเพื่ออนุมัติ
                </button>
            </div>
        </div>

    </form>
</div>

{{-- CSS เพิ่มเติมสำหรับปุ่ม Toggle --}}
<style>
    .btn-check:checked + .btn-outline-warning {
        background-color: #f59e0b;
        border-color: #f59e0b;
        color: #ffffff !important;
    }
    .btn-check:checked + .btn-outline-warning .text-warning-emphasis {
        color: #ffffff !important;
    }
    #upload_area:hover {
        border-color: #d97706 !important;
        background-color: #fef3c7 !important;
    }
</style>
@endsection

@section('scripts')
{{-- 🌟 นำเข้า Library สำหรับปฏิทินไทย (Flatpickr) และ QR Code 🌟 --}}
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 🌟 1. ตั้งค่าปฏิทินไทย (พ.ศ.)
    flatpickr(".thai-datepicker", {
        locale: "th", 
        altInput: true,
        altFormat: "d/m/Y", 
        dateFormat: "Y-m-d",
        formatDate: function(date, format, locale) {
            let d = ("0" + date.getDate()).slice(-2);
            let m = ("0" + (date.getMonth() + 1)).slice(-2);
            let y = date.getFullYear();
            
            if (format === "d/m/Y") {
                return `${d}/${m}/${y + 543}`;
            }
            if (format === "Y-m-d") {
                return `${y}-${m}-${d}`;
            }
            return date.toLocaleDateString('th-TH');
        }
    });

    // 🌟 2. ตัวแปรจัดการ QR Code และ อัปโหลดไฟล์
    const fileInput = document.getElementById('file_input');
    const fileNameDisplay = document.getElementById('file_name_display');
    const dropZone = document.getElementById('upload_area');
    const methodRadios = document.querySelectorAll('input[name="upload_method"]');
    const qrUrlArea = document.getElementById('qr_url_area');
    const externalUrlInput = document.getElementById('external_url');

    let html5QrCode;

    // แสดงชื่อไฟล์เวลาเลือก
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            fileNameDisplay.style.display = 'inline-block';
            fileNameDisplay.innerHTML = `<i class="fas fa-check-circle me-1 text-success"></i> แนบไฟล์: <b>${this.files[0].name}</b>  สำเร็จ`;
        } else {
            fileNameDisplay.style.display = 'none';
        }
    });

    // เอฟเฟกต์ลากไฟล์ (Drag & Drop)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }));
    dropZone.addEventListener('drop', e => {
        fileInput.files = e.dataTransfer.files;
        fileInput.dispatchEvent(new Event('change'));
    });

    // ฟังก์ชันเปิดกล้องสแกน QR Code
    function startScanner() {
        if (html5QrCode) return; 
        html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start(
            { facingMode: "environment" }, 
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                externalUrlInput.value = decodedText;
                stopScanner(); 
                
                Swal.fire({ 
                    icon: 'success', 
                    title: 'สแกนสำเร็จ!', 
                    text: 'ดึงลิงก์เอกสารเรียบร้อยแล้ว', 
                    timer: 2000, 
                    showConfirmButton: false 
                });
            }
        ).catch(err => {
            console.error(err);
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเปิดกล้องได้ กรุณาตรวจสอบการอนุญาตใช้งานกล้อง', 'error');
        });
    }

    function stopScanner() {
        if(html5QrCode && html5QrCode.isScanning) {
            html5QrCode.stop().then(() => { html5QrCode = null; }).catch(err => console.log(err));
        }
    }

    // สลับโหมดอัปโหลดไฟล์ / สแกน QR
    methodRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'qr') {
                dropZone.style.display = 'none';
                fileInput.required = false;
                qrUrlArea.style.display = 'block';
                externalUrlInput.required = true;
                startScanner(); 
            } else {
                dropZone.style.display = 'block';
                qrUrlArea.style.display = 'none';
                externalUrlInput.required = false;
                stopScanner(); 
                externalUrlInput.value = ''; 
            }
        });
    });

    // 🌟 ลูกเล่นแจ้งเตือนสายตา (UI) หากเลือกชั้นความลับ "ลับ" ขึ้นไป
    const secretSelect = document.getElementById('doc_secret');
    secretSelect.addEventListener('change', function() {
        if (this.value !== 'ไม่มีชั้นความลับ') {
            this.classList.remove('text-dark');
            this.classList.add('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
        } else {
            this.classList.remove('border-danger', 'text-danger', 'fw-bold', 'bg-danger-subtle');
            this.classList.add('text-dark');
        }
    });
});

// 🌟 3. ฟังก์ชันรันเลขรับอัตโนมัติผ่านระบบ API สารบรรณ
async function autoReceiveNo() {
    try {
        const res = await fetch("{{ route('documents.api_next_number') }}?type=incoming");
        const data = await res.json();
        
        // บันทึกค่าที่ส่งกลับลงสู่ฟิลด์ในฟอร์ม
        document.getElementById('receive_number').value = data.formatted;
        document.getElementById('running_number').value = data.next_number;
        
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'รันเลขรับล่าสุดสำเร็จ',
            showConfirmButton: false,
            timer: 1500
        });
    } catch (e) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบทะเบียนคุมเลขได้', 'error');
    }
}
</script>
@endsection