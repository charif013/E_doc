@extends('layouts.app')

@section('title', 'ปลดล็อกเอกสารลับ')

@section('content')
<div class="container-fluid px-4 py-5 d-flex align-items-center justify-content-center" style="min-height: 80vh; background-color: var(--bg-page);">
    <div class="card border-0 shadow-lg" style="border-radius: 20px; max-width: 450px; width: 100%;">
        <div class="card-body p-5 text-center">
            
            {{-- ไอคอนแม่กุญแจ --}}
            <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center text-white shadow" style="width: 80px; height: 80px; background: linear-gradient(135deg, #ef4444, #b91c1c); font-size: 32px;">
                <i class="fas fa-lock"></i>
            </div>
            
            <h4 class="fw-bold mb-1 text-dark">เอกสารชั้นความลับ</h4>
            <p class="text-muted small mb-4">เรื่อง: {{ $document->title }}<br><span class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>({{ $document->doc_secret }})</span></p>
            
            <p class="mb-4" style="font-size: 14.5px; color: #475569;">
                ระบบจำกัดสิทธิ์การเข้าถึงเอกสารฉบับนี้<br>กรุณากรอกรหัส <strong>PIN 6 หลัก</strong> ของคุณเพื่อยืนยันตัวตน
            </p>
            
            {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ Action ของ Form เป็น UUID --}}
            <form action="{{ route('documents.unlock_secret', $document->uuid ?? $document->id) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <input type="password" name="pin" maxlength="6" pattern="[0-9]*" inputmode="numeric" placeholder="• • • • • •" required autocomplete="off" autofocus
                           style="border: 2px solid #cbd5e1; border-radius: 12px; padding: 12px 20px; font-size: 24px; letter-spacing: 12px; text-align: center; width: 100%; max-width: 250px; outline: none; background: #f8fafc; color: #0f172a; transition: 0.3s;"
                           onfocus="this.style.borderColor='#ef4444'; this.style.background='#fff';" 
                           onblur="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';">
                </div>
                
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <a href="{{ route('documents.approve_list') }}" class="ds-back-link">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้ารายการ
                    </a>
                    <button type="submit" class="btn rounded-pill fw-bold text-white px-4 shadow-sm" style="background: #ef4444; border: none;">
                        <i class="fas fa-key me-2"></i> ปลดล็อก
                    </button>
                </div>
            </form>
            
        </div>
    </div>
</div>

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('error_pin'))
            Swal.fire({ icon: 'error', title: 'รหัสไม่ถูกต้อง', text: '{{ session("error_pin") }}', confirmButtonColor: '#ef4444' });
        @endif
        @if(session('error_signature'))
            Swal.fire({ icon: 'warning', title: 'ยังไม่ตั้งรหัส', text: '{{ session("error_signature") }}', confirmButtonText: 'ไปตั้งค่าโปรไฟล์', confirmButtonColor: '#0284c7' })
            .then((res) => { if (res.isConfirmed) window.location.href = "{{ route('profile.index') }}"; });
        @endif
    });
</script>
@endsection
@endsection
