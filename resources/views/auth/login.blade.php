@extends('layouts.app')

@section('content')
<style>
    /* ปรับสีพื้นหลังของหน้าเว็บ */
    body {
        background-color: var(--bg-page, #f1f5f9);
    }
    
    /* จัดกึ่งกลางหน้าจอ */
    .login-container {
        min-height: calc(100vh - 100px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    /* สไตล์ของการ์ดล็อกอิน */
    .login-card {
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        width: 100%;
        max-width: 420px;
        border: 1px solid rgba(255,255,255,0.5);
    }

    /* ส่วนหัวการ์ด (สีน้ำเงิน Gradient) */
    .login-header {
        background: linear-gradient(135deg, var(--primary, #0284c7), var(--primary-dark, #0369a1));
        padding: 40px 30px;
        text-align: center;
        color: white;
    }
    
    .login-header-icon {
        font-size: 55px;
        margin-bottom: 15px;
        text-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    .login-body {
        padding: 40px 30px;
    }

    /* สไตล์ของช่องกรอกข้อมูล */
    .input-group-text {
        background: #f8fafc;
        border-right: none;
        color: #64748b;
        border-radius: 12px 0 0 12px;
        border-color: #cbd5e1;
    }
    
    .form-control.login-input {
        background: #f8fafc;
        border-left: none;
        border-radius: 0 12px 12px 0;
        padding: 12px 15px;
        font-size: 15px;
        border-color: #cbd5e1;
    }
    
    .form-control.login-input:focus {
        background: #ffffff;
        box-shadow: none;
        border-color: var(--primary, #0284c7);
    }
    
    /* เอฟเฟกต์ตอนคลิกช่องกรอกข้อมูล */
    .input-group:focus-within .input-group-text,
    .input-group:focus-within .login-input,
    .input-group:focus-within .btn-eye {
        border-color: var(--primary, #0284c7) !important;
        background: #ffffff;
        color: var(--primary, #0284c7);
    }
    
    .input-group:focus-within {
        box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1);
        border-radius: 12px;
    }

    /* ปุ่มล็อกอิน */
    .btn-login {
        background: linear-gradient(135deg, var(--primary, #0284c7), var(--primary-dark, #0369a1));
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(2, 132, 199, 0.3);
    }

    /* ปุ่มตาแมวดูรหัสผ่าน */
    .btn-eye {
        background: #f8fafc;
        border-color: #cbd5e1;
        border-left: none;
        border-radius: 0 12px 12px 0;
        color: #94a3b8;
        cursor: pointer;
    }
    .btn-eye:hover { color: #475569; }
</style>

<div class="login-container">
    <div class="login-card">
        
        {{-- โซน Header --}}
        <div class="login-header">
            {{-- 🌟 แสดงโลโก้ logo.png ของ อบต. --}}
            <img src="{{ asset('images/logo.png') }}" alt="โลโก้ อบต." class="login-logo">
            
            <h4 class="fw-bold mb-1">ระบบสารบรรณอิเล็กทรอนิกส์</h4>
            <p class="mb-0 opacity-75 small">องค์การบริหารส่วนตำบลพร่อน</p>
        </div>
        
        {{-- โซน Form --}}
        <div class="login-body">
            <h5 class="fw-bold text-center mb-4 text-dark">ลงชื่อเข้าใช้งาน</h5>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email Input --}}
                <div class="mb-4">
                    <label for="email" class="form-label small fw-bold text-muted">อีเมล (Email)</label>
                    <div class="input-group shadow-sm" style="border-radius: 12px;">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input id="email" type="email" class="form-control login-input @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="กรอกอีเมลของคุณ">
                    </div>
                    @error('email')
                        <div class="text-danger small mt-2 fw-bold"><i class="fas fa-exclamation-circle me-1"></i> {{ $message }}</div>
                    @enderror
                </div>

                {{-- Password Input --}}
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="password" class="form-label small fw-bold text-muted mb-0">รหัสผ่าน (Password)</label>
                        @if (Route::has('password.request'))
                            <a class="text-decoration-none small fw-bold" href="{{ route('password.request') }}" style="color: var(--primary, #0284c7);">
                                ลืมรหัสผ่าน?
                            </a>
                        @endif
                    </div>
                    <div class="input-group shadow-sm" style="border-radius: 12px;">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input id="password" type="password" class="form-control login-input border-end-0 @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="กรอกรหัสผ่านของคุณ" style="border-radius: 0;">
                        
                        {{-- ปุ่มกดดูรหัสผ่าน --}}
                        <button class="input-group-text btn-eye" type="button" id="togglePassword">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="text-danger small mt-2 fw-bold"><i class="fas fa-exclamation-circle me-1"></i> {{ $message }}</div>
                    @enderror
                </div>

                {{-- Remember Me --}}
                <div class="mb-4 form-check">
                    <input class="form-check-input shadow-sm" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }} style="cursor: pointer;">
                    <label class="form-check-label text-muted small fw-bold" for="remember" style="cursor: pointer;">
                        จดจำการเข้าสู่ระบบ
                    </label>
                </div>

                {{-- Submit Button --}}
                <button type="submit" class="btn btn-primary w-100 btn-login text-white shadow-sm mt-2">
                    <i class="fas fa-sign-in-alt me-2"></i> เข้าสู่ระบบ
                </button>
            </form>
            
        </div>
    </div>
</div>

{{-- Script สำหรับเปิด/ปิด การมองเห็นรหัสผ่าน --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        // ตรวจสอบว่ามี Element อยู่จริงเพื่อป้องกัน Error ใน Console
        if (togglePasswordBtn && passwordInput && eyeIcon) {
            
            // ตั้งค่าเริ่มต้นของ Title ตอนเอาเมาส์ชี้
            togglePasswordBtn.setAttribute('title', 'แสดงรหัสผ่าน');

            togglePasswordBtn.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                
                // สลับชนิดของ Input (text / password)
                passwordInput.type = isPassword ? 'text' : 'password';
                
                // สลับไอคอน และเปลี่ยนข้อความแจ้งเตือน (Tooltip)
                if (isPassword) {
                    eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
                    togglePasswordBtn.setAttribute('title', 'ซ่อนรหัสผ่าน');
                } else {
                    eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
                    togglePasswordBtn.setAttribute('title', 'แสดงรหัสผ่าน');
                }
            });
        }
    });
</script>
@endsection