@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3" style="background-color: #f0f4f7; min-height: 90vh;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0"><i class="fas fa-history me-2 text-teal"></i> ประวัติการลาของฉัน</h4>
        <a href="{{ route('leaves.create') }}" class="btn btn-teal fw-bold rounded-pill shadow-sm px-4">
            + ยื่นใบลาใหม่
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0">
            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        @forelse($leaves as $leave)
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm" style="border-radius: 15px;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-3">
                            <div>
                                <h5 class="fw-bold text-primary mb-1">{{ $leave->leave_type }} ({{ $leave->total_days }} วัน)</h5>
                                <span class="text-muted small">
                                    <i class="fas fa-calendar-alt me-1"></i> 
                                    ตั้งแต่วันที่ {{ \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y') }} 
                                    ถึง {{ \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y') }}
                                </span>
                            </div>
                            <div class="text-end">
                                {{-- 🌟 ปรับแต่งการแสดงผลสถานะ --}}
                                @php
                                    $badgeClass = 'bg-warning text-dark';
                                    $statusText = 'กำลังดำเนินการ';
                                    
                                    if($leave->status == 'APPROVED') { $badgeClass = 'bg-success'; $statusText = 'อนุมัติแล้ว'; }
                                    elseif($leave->status == 'REJECTED') { $badgeClass = 'bg-danger'; $statusText = 'ไม่อนุมัติ'; }
                                    elseif($leave->status == 'CANCELED') { $badgeClass = 'bg-secondary'; $statusText = 'ยกเลิกแล้ว'; }
                                @endphp
                                
                                <span class="badge {{ $badgeClass }} fs-6 px-3 py-2 rounded-pill">
                                    {{ $statusText }}
                                </span>
                                <div class="mt-2 text-muted" style="font-size: 0.75rem;">ยื่นเมื่อ: {{ $leave->created_at->format('d/m/Y H:i') }}</div>
                                
                                {{-- 🌟 ปุ่มยกเลิก จะโชว์เฉพาะใบลาที่ยังดำเนินการอยู่ --}}
                                @if(!in_array($leave->status, ['APPROVED', 'REJECTED', 'CANCELED']))
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold" onclick="confirmCancel({{ $leave->id }})">
                                            <i class="fas fa-times-circle"></i> ยกเลิกการลา
                                        </button>
                                        <form id="cancel-form-{{ $leave->id }}" action="{{ route('leaves.cancel', $leave->id) }}" method="POST" style="display: none;">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- ส่วนของ Timeline 5 ด่าน --}}
                        <div class="timeline-stepper mt-4 pt-2 {{ $leave->status == 'CANCELED' ? 'opacity-50' : '' }}">
                            <div class="step {{ $leave->delegate_status == 'accepted' ? 'completed' : ($leave->delegate_status == 'pending' ? 'active' : 'pending') }}">
                                <div class="step-icon"><i class="fas fa-user-friends"></i></div>
                                <div class="step-label">ผู้รับมอบงาน</div>
                                @if($leave->delegate_status === 'declined')<small class="text-danger">ปฏิเสธแล้ว</small>@endif
                            </div>

                            <div class="step-line {{ $leave->delegate_status == 'accepted' ? 'completed' : '' }}"></div>

                            <div class="step {{ $leave->inspector_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_inspector' ? 'active' : 'pending') }}">
                                <div class="step-icon"><i class="fas fa-search"></i></div>
                                <div class="step-label">นักทรัพยากรบุคคลตรวจสิทธิ์</div>
                            </div>

                            <div class="step-line {{ $leave->inspector_status == 'approved' ? 'completed' : '' }}"></div>

                            <div class="step {{ $leave->head_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_head' ? 'active' : 'pending') }}">
                                <div class="step-icon"><i class="fas fa-user-tie"></i></div>
                                <div class="step-label">หัวหน้า/ผอ.กอง</div>
                            </div>

                            <div class="step-line {{ $leave->head_status == 'approved' ? 'completed' : '' }}"></div>

                            <div class="step {{ $leave->palad_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_palad' ? 'active' : 'pending') }}">
                                <div class="step-icon"><i class="fas fa-stamp"></i></div>
                                <div class="step-label">ปลัด อบต.</div>
                            </div>

                            <div class="step-line {{ $leave->palad_status == 'approved' ? 'completed' : '' }}"></div>

                            <div class="step {{ $leave->nayok_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_nayok' ? 'active' : 'pending') }}">
                                <div class="step-icon"><i class="fas fa-signature"></i></div>
                                <div class="step-label">นายก อบต.</div>
                            </div>

                            <div class="step-line {{ $leave->numbered_at ? 'completed' : '' }}"></div>

                            <div class="step {{ $leave->numbered_at ? 'completed' : ($leave->workflow_status == 'pending_numbering' ? 'active' : 'pending') }}">
                                <div class="step-icon"><i class="fas fa-hashtag"></i></div>
                                <div class="step-label">ธุรการลงเลข</div>
                            </div>
                        </div>
                        
                        @if($leave->status == 'REJECTED')
                        <div class="alert alert-danger mt-4 mb-0 py-2">
                            <strong>เหตุผลที่ไม่อนุมัติ:</strong> {{ $leave->reject_reason ?? 'ไม่ระบุเหตุผล' }}
                        </div>
                        @endif

                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486831.png" width="120" class="mb-3 opacity-50">
                <h5 class="text-muted">ยังไม่มีประวัติการยื่นใบลา</h5>
                <a href="{{ route('leaves.create') }}" class="btn btn-outline-teal mt-2 rounded-pill">ยื่นใบลาครั้งแรกเลย!</a>
            </div>
        @endforelse
    </div>
</div>

{{-- 🌟 นำเข้า SweetAlert2 สำหรับแจ้งเตือนก่อนกดยกเลิก --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmCancel(id) {
        Swal.fire({
            title: 'ยืนยันการยกเลิกใบลา?',
            text: "หากยกเลิกแล้ว จะไม่สามารถกลับมาดำเนินการต่อได้",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ใช่, ยกเลิกเลย',
            cancelButtonText: 'ปิด'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('cancel-form-' + id).submit();
            }
        })
    }
</script>

<style>
    .text-teal { color: #164f51; }
    .bg-teal { background-color: #164f51; }
    .btn-teal { background-color: #164f51; color: white; border: none; }
    .btn-teal:hover { background-color: #0f3638; color: white; }
    .btn-outline-teal { border-color: #164f51; color: #164f51; }
    .btn-outline-teal:hover { background-color: #164f51; color: white; }

    /* CSS สำหรับ Timeline Stepper */
    .timeline-stepper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        position: relative;
        overflow-x: auto;
        padding-bottom: 10px;
    }
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 2;
        width: 80px;
    }
    .step-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background-color: #e2e8f0;
        color: #94a3b8;
        border: 3px solid #fff;
        box-shadow: 0 0 0 2px #e2e8f0;
        transition: all 0.3s ease;
    }
    .step-label {
        margin-top: 8px;
        font-size: 0.75rem;
        font-weight: bold;
        color: #64748b;
        text-align: center;
    }
    .step-line {
        flex-grow: 1;
        height: 4px;
        background-color: #e2e8f0;
        margin: 0 -15px;
        transform: translateY(-12px);
        transition: all 0.3s ease;
    }

    /* สถานะสำเร็จ (เขียว) */
    .step.completed .step-icon { background-color: #10b981; color: white; box-shadow: 0 0 0 2px #10b981; }
    .step.completed .step-label { color: #10b981; }
    .step-line.completed { background-color: #10b981; }

    /* สถานะกำลังรอ (ส้ม/เหลืองกระพริบ) */
    .step.active .step-icon {
        background-color: #f59e0b; color: white; box-shadow: 0 0 0 2px #f59e0b;
        animation: pulse 1.5s infinite;
    }
    .step.active .step-label { color: #f59e0b; }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7); }
        70% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
        100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
    }
</style>
@endsection
