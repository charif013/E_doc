@extends('layouts.app')

@section('title', 'จัดการวันหยุดราชการ / วันหยุดพิเศษ')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-calendar-plus text-primary me-2"></i>จัดการวันหยุด
            </h4>
            <p class="text-secondary small mb-0">เพิ่มวันหยุดกรณีพิเศษตามประกาศ มติ ครม. หรือวันหยุดท้องถิ่น</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-dark btn-sm rounded-pill px-4 shadow-sm fw-bold bg-white">
            ← กลับหน้าหลัก
        </a>
    </div>

    {{-- ลบกล่อง Alert แบบเก่าออก เพราะเราจะใช้ SweetAlert2 จัดการแทนทั้งหมด --}}

    <div class="row g-4">
        {{-- ฝั่งซ้าย: ฟอร์มเพิ่มวันหยุด --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                    <h6 class="fw-bold text-primary mb-0"><i class="fas fa-plus-circle me-2"></i>เพิ่มวันหยุดใหม่</h6>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('holidays.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">ชื่อวันหยุด / ประกาศ <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="เช่น วันหยุดพิเศษตามมติ ครม." required style="border-radius: 8px;">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">วันที่ <span class="text-danger">*</span></label>
                            <input type="text" name="holiday_date" id="holiday_date" class="form-control bg-white" placeholder="คลิกเพื่อเลือกวันที่" required style="border-radius: 8px;">
                        </div>
                        <button type="submit" class="btn text-white w-100 fw-bold rounded-pill shadow-sm" style="background-color: var(--primary);">
                            บันทึกวันหยุด
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ฝั่งขวา: ตารางแสดงวันหยุด --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="fas fa-list me-2 text-muted"></i>รายการวันหยุด (ตั้งแต่ปีนี้)</h6>
    
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2">ทั้งหมด {{ $holidays->count() }} วัน</span>
        
                        <form action="{{ route('holidays.sync') }}" method="POST" class="m-0" id="syncForm">
                            @csrf
                            <input type="hidden" name="year" value="{{ now()->year }}">
                            <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold shadow-sm text-white" 
                                    style="background-color: var(--primary);"
                                    onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin me-1\'></i> กำลังซิงค์...'; this.style.opacity='0.7'; this.style.pointerEvents='none'; this.form.submit();">
                                <i class="fab fa-google me-1"></i> ซิงค์วันหยุด (API)
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th class="text-secondary small fw-bold px-4 py-3">วันที่ (พ.ศ.)</th>
                                    <th class="text-secondary small fw-bold py-3">ชื่อวันหยุด</th>
                                    <th class="text-secondary small fw-bold py-3">ที่มา</th>
                                    <th class="text-end text-secondary small fw-bold px-4 py-3">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($holidays as $h)
                                <tr>
                                    <td class="px-4 fw-bold" style="color: var(--primary-dark);">
                                        {{ \Carbon\Carbon::parse($h->holiday_date)->locale('th')->translatedFormat('d M') }} 
                                        {{ \Carbon\Carbon::parse($h->holiday_date)->year + 543 }}
                                    </td>
                                    <td class="fw-bold text-dark">{{ $h->name }}</td>
                                    <td>
                                        @if($h->source == 'google_api')
                                            <span class="badge text-primary border rounded-pill px-2 py-1" style="background-color: var(--primary-light); border-color: var(--primary-border)!important;">
                                                <i class="fab fa-google me-1"></i>ระบบ Auto
                                            </span>
                                        @else
                                            <span class="badge text-warning-emphasis border rounded-pill px-2 py-1" style="background-color: #fef3c7; border-color: #fde68a!important;">
                                                <i class="fas fa-user-edit me-1"></i>เพิ่มเอง (Manual)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end px-4">
                                        <form action="{{ route('holidays.destroy', $h->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold" onclick="confirmDelete(this.form)">
                                                <i class="fas fa-trash-alt me-1"></i> ลบ
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">ยังไม่มีข้อมูลวันหยุดในระบบ</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- นำเข้า Flatpickr --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ตั้งค่า Flatpickr เป็นภาษาไทย และแปลง พ.ศ. ให้สวยงามตอนโชว์
    flatpickr("#holiday_date", {
        locale: "th",
        altInput: true,
        altFormat: "d/m/Y",
        dateFormat: "Y-m-d",
        formatDate: function(date, format, locale) {
            let d = ("0" + date.getDate()).slice(-2);
            let m = ("0" + (date.getMonth() + 1)).slice(-2);
            let y = date.getFullYear();
            if (format === "d/m/Y") { return `${d}/${m}/${y + 543}`; }
            if (format === "Y-m-d") { return `${y}-${m}-${d}`; }
            return date.toLocaleDateString('th-TH');
        }
    });
});
</script>

{{-- 🌟 Script สำหรับ SweetAlert2 🌟 --}}
<script>
    // 1. ฟังก์ชันป็อปอัปยืนยันการลบ
    function confirmDelete(form) {
        Swal.fire({
            title: 'ยืนยันการลบวันหยุด?',
            text: "การลบจะมีผลต่อการคำนวณจำนวนวันลาของพนักงาน!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626', // สีแดง (Danger)
            cancelButtonColor: '#94a3b8',  // สีเทา (Muted)
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true, // สลับปุ่มยกเลิกไว้ซ้าย ปุ่มยืนยันไว้ขวา
            customClass: {
                confirmButton: 'rounded-pill px-4',
                cancelButton: 'rounded-pill px-4'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // ถ้ากดยืนยัน ให้ส่งฟอร์มเพื่อลบข้อมูล
                form.submit(); 
            }
        });
    }

    // 2. ป็อปอัปแจ้งเตือนครอบจักรวาล (ลบสำเร็จ, เพิ่มสำเร็จ, กรอกข้อมูลผิด)
    document.addEventListener('DOMContentLoaded', function() {
        
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '{{ session("success") }}',
                showConfirmButton: false,
                timer: 2000, 
                timerProgressBar: true
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '{{ session("error") }}',
                confirmButtonColor: '#dc2626',
                confirmButtonText: 'ตกลง'
            });
        @endif

        // 🌟 ดักจับ Error จากการกรอกฟอร์ม (เช่น ลืมใส่ชื่อวันหยุด)
        @if($errors->any())
            Swal.fire({
                icon: 'warning',
                title: 'ข้อมูลไม่ครบถ้วน',
                text: '{{ $errors->first() }}',
                confirmButtonColor: '#0284c7',
                confirmButtonText: 'รับทราบ'
            });
        @endif

    });
</script>
@endsection