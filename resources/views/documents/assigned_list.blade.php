@extends('layouts.app')

@section('title', 'งานที่ได้รับมอบหมาย')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- 🌟 Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                <i class="fas fa-clipboard-list text-info me-2"></i>งานที่ได้รับมอบหมาย
            </h4>
            <small class="text-muted">หนังสือสั่งการจากผู้บริหารที่มอบหมายให้ส่วนราชการของคุณดำเนินการ</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('home') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm bg-white fw-bold">
                ← แดชบอร์ด
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; border-left: 5px solid #0ea5e9 !important;">
        <div class="card-header bg-white d-flex align-items-center py-3 border-bottom" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-inbox text-primary me-2"></i>หนังสือรับเข้าถึง: {{ auth()->user()->department }}</h6>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="text-secondary small" style="background-color: #f0f9ff;">
                        <tr>
                            <th width="12%" class="py-3">วันที่รับเรื่อง</th>
                            <th width="13%" class="py-3">เลขที่รับ</th>
                            <th width="30%" class="text-start py-3">เรื่อง / คำสั่งการ</th>
                            <th width="15%" class="py-3">จากหน่วยงาน</th>
                            <th width="10%" class="py-3">สถานะ</th>
                            <th width="10%" class="py-3">เปิดดู</th>
                            <th width="10%" class="py-3">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                        <tr style="background-color: {{ $doc->acknowledged_at ? '#ffffff' : '#f8fafc' }};">
                            <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->receive_date)->addYears(543)->format('d/m/Y') }}</td>
                            <td class="fw-semibold text-primary text-nowrap">{{ $doc->receive_number ?? '-' }}</td>
                            <td class="text-start">
                                {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ชื่อเรื่องเป็น UUID --}}
                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="text-decoration-none text-dark fw-bold">
                                    {{ $doc->title }}
                                </a>
                                <div class="small text-danger mt-1">
                                    <i class="fas fa-arrow-turn-down me-1"></i>สั่งการ: {{ Str::limit($doc->nayok_comment ?? $doc->palad_comment, 50) }}
                                </div>
                            </td>
                            <td class="text-secondary small">{{ $doc->doc_from ?? '-' }}</td>
                            <td class="text-nowrap">
                                @if($doc->acknowledged_at)
                                    <span class="badge bg-success text-white rounded-pill px-3 py-2 fw-bold shadow-sm">
                                        <i class="fas fa-check-double me-1"></i>รับทราบเรื่องแล้ว
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                                        <i class="fas fa-exclamation-circle me-1"></i>รอดำเนินการ
                                    </span>
                                @endif
                            </td>
                            
                            {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ปุ่มเปิดดูเป็น UUID --}}
                            <td class="text-nowrap">
                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="btn btn-info btn-sm text-white fw-bold shadow-sm rounded-pill px-3 text-nowrap">
                                    <i class="fas fa-eye me-1"></i> เปิดดู
                                </a>
                            </td>

                            {{-- 🌟 จุดที่ 3: เปลี่ยน Action ฟอร์มกดยืนยันรับทราบเป็น UUID --}}
                            <td class="text-nowrap">
                                @if(!$doc->acknowledged_at)
                                    <form action="{{ route('documents.acknowledge', $doc->uuid ?? $doc->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm rounded-pill px-3 text-nowrap"
                                                onclick="confirmAcknowledge(this.closest('form'), '{{ addslashes($doc->title) }}')">
                                            <i class="fas fa-check me-1"></i> รับเรื่อง
                                        </button>
                                    </form>
                                @else
                                    {{-- ปรับดีไซน์ปุ่มกรณีรับทราบแล้ว --}}
                                    <button class="btn btn-sm btn-light text-success fw-bold rounded-pill px-3 border border-success-subtle text-nowrap" style="background-color: #f0fdf4; cursor: default;" disabled>
                                        <i class="fas fa-check-double me-1"></i> เรียบร้อย
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="py-5 text-muted">
                                <i class="fas fa-clipboard-check fa-3x mb-3 text-success opacity-25"></i><br>
                                ยังไม่มีงานที่ได้รับมอบหมายในขณะนี้
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // 🌟 ย้ายระบบแจ้งเตือน (Success/Error) มาแสดงเป็น SweetAlert2
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '{{ session('success') }}',
                showConfirmButton: false,
                timer: 2500,
                customClass: { popup: 'rounded-4 shadow' }
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'ผิดพลาด!',
                text: '{{ session('error') }}',
                confirmButtonColor: '#dc2626',
                customClass: { popup: 'rounded-4 shadow' }
            });
        @endif
    });

    function confirmAcknowledge(form, title) {
        Swal.fire({
            title: 'ยืนยันการรับทราบ?',
            html: `คุณต้องการลงรับทราบเอกสารเรื่อง<br><b class="text-primary">"${title}"</b><br>ใช่หรือไม่?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981', // สีเขียว Success
            cancelButtonColor: '#64748b',  // สีเทา Slate
            confirmButtonText: '<i class="fas fa-check me-1"></i> ยืนยันรับทราบ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true, // สลับให้ปุ่มยืนยันอยู่ขวา ปุ่มยกเลิกอยู่ซ้าย (ตามมาตรฐาน UI)
            customClass: {
                popup: 'rounded-4 shadow'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // แสดง Loading ระหว่างส่งข้อมูล
                Swal.fire({
                    title: 'กำลังบันทึกข้อมูล...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                // ส่งฟอร์มจริงๆ
                form.submit();
            }
        });
    }
</script>
@endsection