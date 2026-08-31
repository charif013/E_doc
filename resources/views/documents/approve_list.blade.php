@extends('layouts.app')

@section('title', 'เอกสารรอพิจารณา / อนุมัติ')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- 🌟 Header มาตรฐาน --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                <i class="fas fa-folder-open text-primary me-2"></i>รายการเอกสารรอพิจารณา
            </h4>
            <small class="text-muted">ตรวจสอบและพิจารณาเอกสารบันทึกข้อความภายในองค์กร</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="btn btn-light btn-sm rounded-pill px-3 shadow-sm border">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    {{-- แจ้งเตือนเมื่อทำการอนุมัติหรือตีกลับสำเร็จ --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-4 border-success rounded-3">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-start border-4 border-danger rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-header bg-white d-flex align-items-center py-3 border-bottom" style="border-radius: 12px 12px 0 0;">
            <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-list me-2"></i>เอกสารที่รอการดำเนินการพิจารณา</h6>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="bg-light text-secondary small">
                        <tr>
                            <th width="15%" class="py-3">วันที่ส่งเรื่อง</th>
                            <th width="15%" class="py-3">เลขที่เอกสาร</th>
                            <th width="25%" class="text-start py-3">เรื่อง</th>
                            <th width="15%" class="py-3">สถานะ</th>
                            <th width="15%" class="py-3">ผู้สร้าง (ส่วนราชการ)</th>
                            <th width="15%" class="py-3">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documents as $doc)
                        <tr>
                            {{-- 🌟 อัปเดตวันที่ในตารางเป็น พ.ศ. พร้อมแยกบรรทัดเวลาให้อ่านง่าย 🌟 --}}
                            <td class="text-muted small">
                                <div class="fw-bold text-dark">{{ $doc->created_at->format('d/m/') }}{{ $doc->created_at->year + 543 }}</div>
                                <div style="font-size: 11px;">เวลา {{ $doc->created_at->format('H:i') }} น.</div>
                            </td>
                            
                            <td class="fw-semibold text-dark">{{ $doc->formatted_doc_number ?? '-' }}</td>
                            
                            <td class="text-start">
                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="text-decoration-none text-dark fw-bold">
                                    {{ $doc->title }}
                                </a>
                            </td>
                            
                            {{-- 🌟 ป้ายบอกสถานะเอกสาร 🌟 --}}
                            <td>
                                @php
                                    $badges = [
                                        'WAITING_ADMIN'      => ['bg-secondary text-white', 'รอธุรการรับเรื่อง'],
                                        'WAITING_SUPERVISOR' => ['bg-warning text-dark', 'รอหัวหน้าส่วนราชการ'],
                                        'WAITING_PALAD'      => ['bg-primary text-white', 'รอปลัด อบต.'],
                                        'WAITING_NAYOK'      => ['bg-info text-dark', 'รอนายกฯ อนุมัติ'],
                                        'WAITING_NUMBERING'  => ['bg-dark text-white', 'รอธุรการลงเลข'],
                                        'APPROVED'           => ['bg-success text-white', 'อนุมัติ/ลงเลขเรียบร้อย'],
                                        'REJECTED'           => ['bg-danger text-white', 'ถูกตีกลับ'],
                                        'CANCELED'           => ['bg-dark text-white', 'ยกเลิก / เลขเสีย'],
                                    ];
                                    $badge = $badges[$doc->status] ?? ['bg-secondary text-white', $doc->status];
                                @endphp
                                <span class="badge {{ $badge[0] }} rounded-pill px-3 py-2 fw-bold">{{ $badge[1] }}</span>
                            </td>
                            
                            {{-- ผู้สร้างเอกสาร --}}
                            <td class="text-secondary small">
                                <div>{{ $doc->creator->name ?? 'ไม่ทราบ' }}</div>
                                <div style="font-size: 11px;">({{ $doc->creator->department ?? '-' }})</div>
                            </td>
                            
                            {{-- ปุ่มการจัดการ --}}
                            <td>
                                @if(in_array($doc->status, ['APPROVED', 'REJECTED', 'CANCELED']))
                                    <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="btn btn-outline-secondary btn-sm fw-bold shadow-sm rounded-pill px-3">
                                        <i class="fas fa-search me-1"></i> ดูรายละเอียด
                                    </a>
                                @else
                                    <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="btn btn-primary btn-sm text-white fw-bold shadow-sm rounded-pill px-3">
                                        <i class="fas fa-pen-nib me-1"></i> พิจารณา
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-25"></i><br>
                                ไม่มีเอกสารรอการพิจารณาในขณะนี้
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
