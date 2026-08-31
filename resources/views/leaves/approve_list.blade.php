@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3" style="background-color: #f4f7f9; min-height: 100vh;">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-4 border-success">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-4 border-danger">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                <i class="fas fa-inbox text-warning me-2"></i>{{ auth()->user()->hasRole('hr') ? 'พิจารณาการลา' : 'รายการใบลาที่รออนุมัติ' }}
            </h4>
            <small class="text-muted">{{ auth()->user()->hasRole('hr') ? 'ตรวจสอบสิทธิ์ สถิติ และรายละเอียดการลาก่อนส่งให้หัวหน้าสังกัด' : 'ตรวจสอบและพิจารณาคำร้องขอลาของพนักงาน' }}</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm bg-white fw-bold">
                <i class="fas fa-arrow-left me-1"></i> กลับแดชบอร์ด
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 20px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead style="background-color: #f4ebea;">
                        <tr>
                            <th width="15%" class="py-3 text-teal">วันที่ยื่นเรื่อง</th>
                            <th width="20%" class="py-3 text-teal">ผู้ขอลา</th>
                            <th width="15%" class="py-3 text-teal">ประเภท</th>
                            <th width="20%" class="py-3 text-teal">ช่วงวันที่ลา</th>
                            <th width="15%" class="py-3 text-teal">สถานะ</th>
                            <th width="15%" class="py-3 text-teal">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaves as $leave)
                        <tr>
                            <td class="text-muted small">{{ $leave->created_at->format('d/m/Y H:i') }}</td>
                            <td class="fw-bold text-primary">{{ $leave->user->name ?? 'ไม่ทราบชื่อ' }} <br> <span class="small text-muted fw-normal">{{ $leave->user->position ?? '' }}</span></td>
                            <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3">{{ $leave->leave_type }}</span></td>
                            <td class="small">{{ \Carbon\Carbon::parse($leave->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($leave->end_date)->format('d/m/Y') }} <br> ({{ $leave->total_days }} วัน)</td>
                            <td>
                                {{-- 🌟 เปลี่ยนมาใช้ workflow_status 5 ด่าน --}}
                                @php
                                    $badges = [
                                        'pending_delegate'  => ['bg-secondary-subtle text-secondary-emphasis', 'รอผู้รับมอบงาน'],
                                        'pending_inspector' => ['bg-warning-subtle text-warning-emphasis', 'รอนักทรัพยากรบุคคลตรวจสิทธิ์'],
                                        'pending_head'      => ['bg-info-subtle text-info-emphasis', 'รอหัวหน้า/ผอ.'],
                                        'pending_palad'     => ['bg-primary-subtle text-primary-emphasis', 'รอปลัด อบต.'],
                                        'pending_nayok'     => ['bg-purple-subtle text-purple-emphasis', 'รอนายก อบต.'],
                                        'pending_numbering' => ['bg-info-subtle text-info-emphasis', 'รอธุรการลงเลข'],
                                        'approved'          => ['bg-success-subtle text-success-emphasis', 'อนุมัติแล้ว'],
                                        'rejected'          => ['bg-danger-subtle text-danger-emphasis', 'ถูกตีกลับ'],
                                    ];
                                    $badge = $badges[$leave->workflow_status] ?? ['bg-secondary', 'ไม่ทราบสถานะ'];
                                @endphp
                                <span class="badge {{ $badge[0] }} rounded-pill px-3 py-2 shadow-sm"><i class="fas fa-circle ms-1" style="font-size: 8px;"></i> {{ $badge[1] }}</span>
                            </td>
                            <td>
                                {{-- 🌟 เช็คปุ่มจาก workflow_status --}}
                                @if($leave->workflow_status === 'approved' || $leave->workflow_status === 'rejected')
                                    <a href="{{ route('leaves.show', $leave->id) }}" class="btn btn-outline-secondary btn-sm fw-bold shadow-sm rounded-pill px-3">ดูรายละเอียด</a>
                                @else
                                    <a href="{{ route('leaves.show', $leave->id) }}" class="btn btn-teal btn-sm text-white fw-bold shadow-sm rounded-pill px-3">
                                        <i class="fas fa-search me-1"></i> พิจารณา
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-25"></i><br>
                                ไม่มีใบลาที่รอการพิจารณา ยอดเยี่ยมมาก!
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    /* 🌟 โทนสีหลัก (ปรับให้เข้ากับธีมฟ้า-เขียวอ่อนของระบบ) */
    .text-teal { color: var(--primary-dark, #0369a1); } 
    .btn-teal { background-color: var(--primary, #0284c7); color: #ffffff; border: none; transition: 0.2s; }
    .btn-teal:hover { background-color: var(--primary-dark, #0369a1); color: #ffffff; }

    /* 🌟 สีป้ายสถานะ (Badge) สไตล์พาสเทล ดูมินิมอลและสะอาดตา */
    
    /* ฉบับร่าง */
    .bg-secondary-subtle { background-color: #f1f5f9 !important; }
    .text-secondary-emphasis { color: #475569 !important; }

    /* รอหัวหน้าสำนักปลัด (สีเหลือง/ส้มอ่อน) */
    .bg-warning-subtle { background-color: #fffbeb !important; }
    .text-warning-emphasis { color: #d97706 !important; }

    /* รอปลัด อบต. (สีฟ้าอ่อน) */
    .bg-primary-subtle { background-color: #e0f2fe !important; }
    .text-primary-emphasis { color: #0284c7 !important; }

    /* รอนายกฯ อนุมัติ (สีม่วงอ่อน - เพิ่มตามที่คุณต้องการ) */
    .bg-purple-subtle { background-color: #f3e8ff !important; }
    .text-purple-emphasis { color: #7e22ce !important; }

    /* เผื่อใช้งานสี Info (สีฟ้าอมเขียว) */
    .bg-info-subtle { background-color: #ecfeff !important; }
    .text-info-emphasis { color: #0891b2 !important; }

    /* อนุมัติเรียบร้อย (สีเขียวอ่อน) */
    .bg-success-subtle { background-color: #dcfce7 !important; }
    .text-success-emphasis { color: #16a34a !important; }

    /* ถูกตีกลับ (สีแดงอ่อน) */
    .bg-danger-subtle { background-color: #fee2e2 !important; }
    .text-danger-emphasis { color: #dc2626 !important; }
</style>
@endsection
