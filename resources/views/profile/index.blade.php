@extends('layouts.app')
@section('title', 'โปรไฟล์และการตั้งค่า')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-user-cog text-primary me-2"></i>โปรไฟล์และการตั้งค่า
            </h4>
            <p class="text-secondary small mb-0">จัดการข้อมูลส่วนตัว ลายเซ็นอิเล็กทรอนิกส์ การแจ้งเตือน และรหัสผ่าน</p>
        </div>
    </div>

    <div class="row g-4">
        
        {{-- ================= ฝั่งซ้าย: ข้อมูลผู้ใช้ ================= --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="bg-light p-4 text-center border-bottom">
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm mb-3" 
                         style="width: 90px; height: 90px; background-color: #dcfce7; color: #166534; font-size: 36px; border: 3px solid #fff;">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                    <h5 class="fw-bold mb-1" style="color: var(--primary-dark);">{{ $user->name }}</h5>
                    <span class="badge bg-primary-subtle text-primary border rounded-pill px-3 py-1">
                        {{ $user->position ?? 'เจ้าหน้าที่' }}
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold mb-1">อีเมล (เข้าสู่ระบบ)</small>
                        <div class="fw-bold" style="color: #1A1A2E;"><i class="fas fa-envelope text-secondary me-2"></i>{{ $user->email }}</div>
                    </div>
                    <hr style="border-color: #e2e8f0;">
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold mb-1">หน่วยงาน / สังกัด</small>
                        <div class="fw-bold" style="color: #1A1A2E;"><i class="fas fa-building text-secondary me-2"></i>{{ $user->department ?? 'สำนักปลัด' }}</div>
                    </div>
                    <hr style="border-color: #e2e8f0;">
                    <div class="mb-3">
                        <small class="text-muted d-block fw-bold mb-1">สิทธิ์การใช้งาน (Role)</small>
                        <div class="fw-bold" style="color: #1A1A2E;"><i class="fas fa-user-shield text-secondary me-2"></i>{{ $user->roles->first()->name ?? 'ผู้ใช้งานทั่วไป' }}</div>
                    </div>
                    <hr style="border-color: #e2e8f0;">
                    {{-- 🌟 เพิ่มสถานะ LINE ID ฝั่งซ้าย 🌟 --}}
                    <div class="mb-2">
                        <small class="text-muted d-block fw-bold mb-1">รับการแจ้งเตือน (LINE)</small>
                        <div class="fw-bold">
                            @if($user->line_id)
                                <span class="text-success"><i class="fab fa-line me-2"></i>เปิดใช้งานแล้ว</span>
                            @else
                                <span class="text-secondary"><i class="fab fa-line me-2"></i>ยังไม่ได้ตั้งค่า</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= ฝั่งขวา ================= --}}
        <div class="col-lg-8">
            
            {{-- 🌟 ส่วนที่ 1: ตั้งค่าบัญชี LINE (อัปเกรดเป็น LINE Login) 🌟 --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; border-top: 4px solid #06C755!important; overflow: hidden;">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h6 class="fw-bold text-dark mb-0"><i class="fab fa-line text-success me-2" style="font-size: 1.2rem;"></i>รับการแจ้งเตือนผ่าน LINE</h6>
                </div>
                <div class="card-body p-4 text-center">
                    
                    @if($user->line_id)
                        <div class="mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3 shadow-sm" style="width: 60px; height: 60px; font-size: 30px;">
                                <i class="fab fa-line"></i>
                            </div>
                            <h5 class="text-success fw-bold">บัญชีของคุณเชื่อมต่อ LINE แล้ว</h5>
                            <p class="text-muted small">ระบบจะส่งข้อความแจ้งเตือนเอกสารไปที่ LINE ของคุณอัตโนมัติ</p>
                        </div>
                        <form action="{{ route('line.unlink') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm" onclick="return confirm('คุณแน่ใจหรือไม่ที่จะยกเลิกการเชื่อมต่อ? คุณจะไม่ได้รับการแจ้งเตือนเอกสารผ่าน LINE อีก')">
                                <i class="fas fa-unlink me-1"></i> ยกเลิกการเชื่อมต่อ LINE
                            </button>
                        </form>
                    @else
                        <div class="mb-3">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/LINE_logo.svg" alt="LINE" style="width: 60px;" class="mb-3">
                            <h5 class="fw-bold text-dark">ยังไม่ได้ผูกบัญชี LINE</h5>
                            <p class="text-muted small">ผูกบัญชีเพื่อรับการแจ้งเตือนเมื่อมีเอกสารรอให้คุณพิจารณาอนุมัติ</p>
                        </div>
                        <a href="{{ route('line.login') }}" class="btn text-white rounded-pill px-5 py-2 fw-bold shadow-sm" style="background-color: #06C755; font-size: 16px;">
                            <i class="fab fa-line me-1" style="font-size: 20px; vertical-align: text-bottom;"></i> ผูกบัญชีด้วย LINE
                        </a>
                    @endif

                </div>
            </div>

            {{-- 🌟 ส่วนที่ 2: ตั้งค่า PIN และ ลายเซ็น 🌟 --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; border-top: 4px solid var(--primary)!important; overflow: hidden;">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h6 class="fw-bold text-dark mb-0"><i class="fas fa-signature text-primary me-2"></i>ตั้งค่าลายเซ็น และ PIN อนุมัติเอกสาร</h6>
                </div>
                <div class="card-body p-4">
                    
                    {{-- VIEW MODE --}}
                    @if($user->signature)
                    <div id="viewSection" class="p-4 bg-light rounded-3 text-center border">
                        <h5 class="text-success fw-bold mb-3"><i class="fas fa-check-circle me-1"></i> ตั้งค่าเรียบร้อยแล้ว</h5>
                        <p class="mb-1"><strong>รหัส PIN สำหรับอนุมัติ:</strong> ••••••</p>
                        
                        <div class="mt-3">
                            <p class="text-muted small mb-2 fw-bold">ลายเซ็นที่ใช้งานอยู่</p>
                            <div class="d-inline-block bg-white border rounded-3 p-2 shadow-sm">
                                <img src="{{ $user->signature }}" style="max-height: 100px; mix-blend-mode: multiply;">
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-center gap-2">
                            <button class="btn rounded-pill px-4 fw-bold shadow-sm" style="background-color: #fef08a; color: #854d0e; border: 1px solid #fde047;" onclick="toggleEdit(true)">
                                <i class="fas fa-edit me-1"></i> แก้ไขลายเซ็น / PIN
                            </button>

                            <form action="{{ route('profile.signature.delete') }}" method="POST" id="deleteSigForm">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn rounded-pill px-4 fw-bold shadow-sm" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;" onclick="confirmDeleteSig()">
                                    <i class="fas fa-trash-alt me-1"></i> ลบลายเซ็น
                                </button>
                            </form>
                        </div>
                    </div>
                    @endif

                    {{-- EDIT MODE --}}
                    <div id="editSection" style="{{ $user->signature ? 'display:none;' : '' }}">
                        <form action="{{ route('profile.update') }}" method="POST" id="profileForm">
                            @csrf
                            
                            <h6 class="fw-bold text-secondary border-bottom pb-2">1. รหัส PIN อนุมัติเอกสาร (6 หลัก)</h6>
                            <div class="mb-4">
                                @if($user->pin)
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-danger mb-1">รหัส PIN ปัจจุบันของคุณ <span class="text-danger">*</span></label>
                                    <input type="password" name="current_pin" class="form-control border-danger-subtle" maxlength="6" pattern="\d{6}" placeholder="กรอกรหัส PIN เดิม 6 หลัก เพื่อยืนยันตัวตน" style="max-width: 300px; border-radius: 8px;">
                                    
                                    <div class="mt-2" style="max-width: 300px;">
                                        @if($user->pin_reset_requested)
                                            <div class="alert alert-warning py-1 px-2 small mb-0 fw-bold border-warning">
                                                <i class="fas fa-clock me-1"></i> ส่งคำขอล้างรหัสแล้ว รอแอดมินดำเนินการ
                                            </div>
                                        @else
                                            <button type="button" class="btn btn-link text-danger small p-0 text-decoration-none fw-bold" onclick="confirmPinResetRequest()">
                                                <i class="fas fa-question-circle me-1"></i> ลืมรหัส PIN ปัจจุบัน?
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                <div class="mb-3 mt-3">
                                    <label class="form-label small fw-bold text-muted mb-1">{{ $user->pin ? 'รหัส PIN ใหม่ที่ต้องการเปลี่ยน' : 'ตั้งรหัส PIN อนุมัติเอกสาร' }}</label>
                                    <p class="text-muted small mb-2">ใช้สำหรับยืนยันตัวตนเวลาลงนามเอกสาร (หากไม่ต้องการเปลี่ยนให้เว้นว่างไว้)</p>
                                    <input type="password" name="pin" class="form-control" maxlength="6" pattern="\d{6}" placeholder="กรอกตัวเลข 6 หลัก" style="max-width: 300px; border-radius: 8px;">
                                </div>
                            </div>

                            <h6 class="fw-bold text-secondary border-bottom pb-2 mt-4">2. ลายมือชื่ออิเล็กทรอนิกส์</h6>
                            <ul class="nav nav-pills mb-3">
                                <li class="nav-item">
                                    <button class="nav-link active btn-sm border fw-bold px-3 rounded-pill" id="pills-draw-tab" data-bs-toggle="pill" data-bs-target="#pills-draw" type="button">
                                        <i class="fas fa-pen me-1"></i> วาดลายเซ็น
                                    </button>
                                </li>
                                <li class="nav-item ms-2">
                                    <button class="nav-link btn-sm border fw-bold px-3 rounded-pill" id="pills-upload-tab" data-bs-toggle="pill" data-bs-target="#pills-upload" type="button">
                                        <i class="fas fa-upload me-1"></i> อัปโหลดไฟล์
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mb-4">
                                {{-- DRAW --}}
                                <div class="tab-pane fade show active" id="pills-draw">
                                    <div class="border rounded-3 bg-light text-center overflow-hidden">
                                        <canvas id="signature-pad" width="500" height="200" style="background:#fff; border-bottom: 1px dashed #ccc; width: 100%; max-width: 500px;"></canvas>
                                    </div>
                                    <div class="mt-2 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" id="clear-signature">
                                            <i class="fas fa-eraser me-1"></i> ล้างลายเซ็น
                                        </button>
                                    </div>
                                </div>

                                {{-- UPLOAD --}}
                                <div class="tab-pane fade" id="pills-upload">
                                    <input class="form-control" type="file" id="signature-upload" accept="image/png, image/jpeg" style="border-radius: 8px;">
                                    <div class="mt-3 text-center" id="upload-preview-box" style="display:none;">
                                        <img id="upload-preview-img" class="border rounded-3 p-2 bg-white shadow-sm" style="max-height:120px;">
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="signature" id="signature64">

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm" onclick="saveSignature()" style="background-color: var(--primary);">
                                    <i class="fas fa-save me-1"></i> บันทึกข้อมูลลายเซ็น
                                </button>
                                @if($user->signature)
                                <button type="button" class="btn btn-light border fw-bold rounded-pill px-4" onclick="toggleEdit(false)">
                                    ยกเลิก
                                </button>
                                @endif
                            </div>
                        </form>
                        
                        {{-- 🌟 ฟอร์มซ่อนสำหรับส่งคำขอล้างรหัส PIN 🌟 --}}
                        @if($user->pin && !$user->pin_reset_requested)
                        <form action="{{ route('profile.request_pin_reset') }}" method="POST" id="requestPinResetForm" style="display: none;">
                            @csrf
                        </form>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 🌟 ส่วนที่ 3: เปลี่ยนรหัสผ่านเข้าระบบ 🌟 --}}
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h6 class="fw-bold text-dark mb-0"><i class="fas fa-key text-warning me-2"></i>เปลี่ยนรหัสผ่านเข้าสู่ระบบ</h6>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('profile.change_password') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: var(--text-secondary); font-size: 14px;">รหัสผ่านปัจจุบัน <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control px-3 py-2" placeholder="กรอกรหัสผ่านเดิมเพื่อยืนยันตัวตน" required style="border-radius: 8px; max-width: 400px;">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold" style="color: var(--text-secondary); font-size: 14px;">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control px-3 py-2" placeholder="อย่างน้อย 8 ตัวอักษร" required style="border-radius: 8px;">
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label fw-bold" style="color: var(--text-secondary); font-size: 14px;">ยืนยันรหัสผ่านใหม่ <span class="text-danger">*</span></label>
                                <input type="password" name="new_password_confirmation" class="form-control px-3 py-2" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required style="border-radius: 8px;">
                            </div>
                        </div>

                        <div class="text-end mt-2">
                            <button type="submit" class="btn btn-dark rounded-pill px-4 py-2 fw-bold shadow-sm">
                                <i class="fas fa-lock me-1"></i> อัปเดตรหัสผ่าน
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- นำเข้าไลบรารี SignaturePad --}}
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ===== Signature Pad =====
    var canvas = document.getElementById('signature-pad');
    var signaturePad = canvas ? new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' }) : null;

    if (signaturePad) {
        document.getElementById('clear-signature').addEventListener('click', function () {
            signaturePad.clear();
        });
    }

    // ===== Mode =====
    var sigMode = 'draw';
    document.getElementById('pills-draw-tab')?.addEventListener('click', () => sigMode = 'draw');
    document.getElementById('pills-upload-tab')?.addEventListener('click', () => sigMode = 'upload');

    // ===== Upload =====
    var uploadedBase64 = '';
    document.getElementById('signature-upload')?.addEventListener('change', function(e){
        var file = e.target.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function(event){
            uploadedBase64 = event.target.result;
            document.getElementById('upload-preview-img').src = uploadedBase64;
            document.getElementById('upload-preview-box').style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    // ===== Save =====
    window.saveSignature = function(){
        var hidden = document.getElementById('signature64');

        if (sigMode === 'draw' && signaturePad && !signaturePad.isEmpty()) {
            hidden.value = signaturePad.toDataURL('image/png');
        }

        if (sigMode === 'upload' && uploadedBase64 !== '') {
            hidden.value = uploadedBase64;
        }
    }

    // ===== แจ้งเตือน SweetAlert2 =====
    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '{{ session("success") }}', confirmButtonColor: '#10b981', timer: 3000 });
    @endif

    @if(session('error'))
        Swal.fire({ icon: 'error', title: 'ผิดพลาด!', text: '{{ session("error") }}', confirmButtonColor: '#dc2626' });
    @endif

    @if($errors->any())
        Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ถูกต้อง', text: '{{ $errors->first() }}', confirmButtonColor: '#f59e0b' });
    @endif

});

// ===== Toggle View/Edit =====
function toggleEdit(showEdit) {
    const edit = document.getElementById('editSection');
    const view = document.getElementById('viewSection');
    if (showEdit) {
        edit.style.display = 'block';
        if (view) view.style.display = 'none';
    } else {
        edit.style.display = 'none';
        if (view) view.style.display = 'block';
    }
}

// ===== Confirm Delete Signature =====
function confirmDeleteSig() {
    Swal.fire({
        title: 'ยืนยันการลบลายเซ็น?',
        text: "หากลบแล้ว คุณจะต้องตั้งค่าลายเซ็นใหม่เพื่อใช้ลงนามเอกสาร",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#9ca3af',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteSigForm').submit();
        }
    });
}

// 🌟 ฟังก์ชันยืนยันส่งคำขอล้างรหัส PIN 🌟
function confirmPinResetRequest() {
    Swal.fire({
        title: 'ยืนยันการส่งคำขอ?',
        text: "คุณต้องการแจ้งให้แอดมินล้างรหัส PIN เดิมของคุณใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#eab308',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ใช่, ส่งคำขอ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('requestPinResetForm').submit();
        }
    });
}
</script>
@endsection