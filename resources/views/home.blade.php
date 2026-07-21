@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3" style="background-color: #f4f9f6; min-height: 100vh;">
    
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">แดชบอร์ด</h4>
            </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
        </div>
    </div>

    {{-- แบนเนอร์แจ้งเตือนหลัก --}}
    @if($stats['waiting'] > 0)
    <div class="card border-0 mb-4 shadow-sm" style="background-color: #164f51; border-radius: 20px;">
        <div class="card-body p-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div style="font-size: 2.5rem;">📬</div>
                <div class="text-white">
                    <h4 class="fw-bold mb-1">มีเอกสารรอดำเนินการ</h4>
                    <p class="mb-0 opacity-75 small">หนังสือเข้าจากระบบกลาง — อัปเดตล่าสุด {{ now()->format('H:i') }} น.</p>
                </div>
            </div>
            <div class="text-warning fw-bold" style="font-size: 3.5rem; line-height: 1; color: #d18b49 !important;">
                {{ $stats['waiting'] }}
            </div>
        </div>
    </div>
    @endif

    {{-- การ์ดสถิติ 4 ช่อง (เพิ่มสีพื้นหลังพาสเทล) --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="background-color: #eaf4f4;">
                <div class="card-body">
                    <p class="text-muted mb-1 small fw-bold">เอกสารทั้งหมด</p>
                    <h2 class="fw-bold text-teal mb-1">{{ $stats['total'] }}</h2>
                    <p class="text-muted small mb-0 opacity-75">รอดำเนินการ {{ $stats['waiting'] }} ฉบับ</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="background-color: #fff5eb;">
                <div class="card-body">
                    <p class="text-muted mb-1 small fw-bold">รอดำเนินการ</p>
                    <h2 class="fw-bold text-gold mb-1">{{ $stats['waiting'] }}</h2>
                    <p class="text-muted small mb-0 opacity-75">รอพิจารณา</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="background-color: #eaf4f4;">
                <div class="card-body">
                    <p class="text-muted mb-1 small fw-bold">อนุมัติเรียบร้อย</p>
                    <h2 class="fw-bold text-teal mb-1">{{ $stats['approved'] }}</h2>
                    <p class="text-muted small mb-0 opacity-75">ฉบับ</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm h-100" style="background-color: #fcebeb;">
                <div class="card-body">
                    <p class="text-muted mb-1 small fw-bold">เอกสารถูกตีกลับ</p>
                    <h2 class="fw-bold text-red mb-1">{{ $stats['rejected'] }}</h2>
                    <p class="text-muted small mb-0 opacity-75">ต้องแก้ไข</p>
                </div>
            </div>
        </div>
    </div>

    {{-- คอลัมน์รายการเอกสาร และ ตารางจองห้อง --}}
    <div class="row g-4">
        
        {{-- 🌟 ฝั่งซ้าย: เอกสารเข้าล่าสุด (พื้นที่ 8 ส่วน) --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 20px; background-color: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="accent-line"></div>
                        <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: 0.5px;">เอกสารเข้าล่าสุด</h5>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @forelse($recentDocs as $doc)
                            <div class="doc-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-bold text-dark">{{ $doc->doc_number ?? 'ไม่มีเลขที่' }}</span>
                                    @php
                                        $badges = [
                                            'WAITING_SUPERVISOR' => ['bg-warning-subtle text-warning-emphasis', 'รอหัวหน้า'],
                                            'WAITING_PALAD'      => ['bg-info-subtle text-info-emphasis', 'รอปลัด'],
                                            'WAITING_NAYOK'      => ['bg-primary-subtle text-primary-emphasis', 'รอนายก'],
                                            'APPROVED'           => ['bg-success-subtle text-success-emphasis', 'อนุมัติแล้ว'],
                                            'REJECTED'           => ['bg-danger-subtle text-danger-emphasis', 'ถูกตีกลับ'],
                                        ];
                                        $badge = $badges[$doc->status] ?? ['bg-secondary-subtle text-secondary-emphasis', 'ฉบับร่าง'];
                                    @endphp
                                    <span class="badge {{ $badge[0] }} rounded-pill px-3 py-1">{{ $badge[1] }}</span>
                                </div>
                                <a href="{{ route('documents.show', $doc->id) }}" class="text-decoration-none text-dark d-block mb-2">
                                    <h6 class="fw-bold mb-0 text-truncate">{{ $doc->title }}</h6>
                                </a>
                                <div class="text-muted small d-flex gap-2 opacity-75">
                                    <span>จาก: {{ $doc->creator->name ?? 'ธุรการ' }}</span>
                                    <span>•</span>
                                    <span>{{ $doc->created_at->format('d/m/y H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0">ยังไม่มีเอกสารในระบบ</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- 🌟 ฝั่งขวา: ตารางใช้ห้องวันนี้ + คำเชิญประชุม (พื้นที่ 4 ส่วน) --}}
        <div class="col-lg-4">
            
            {{-- กล่องที่ 1: ตารางใช้ห้องวันนี้ --}}
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 20px; background-color: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center">
                            <div class="accent-line" style="background-color: #164f51;"></div>
                            <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: 0.5px;">ใช้ห้องวันนี้</h5>
                        </div>
                        <a href="{{ route('bookings.index') }}" class="btn btn-sm" style="background-color: #eaf4f4; color: #164f51; border-radius: 10px; font-weight: bold;">
                            <i class="fas fa-calendar-alt me-1"></i> ปฏิทิน
                        </a>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @forelse($todayBookings as $booking)
                            <div class="doc-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    
                                    {{-- 🌟 เริ่ม: เช็คสิทธิ์การเห็นหัวข้อ --}}
                                    @php
                                        $canSeeTitle = false;
                                        // 1. ถ้าไม่ใช่การประชุม (ซ่อมบำรุง/ทั่วไป) หรือเป็นแอดมิน -> เห็นหัวข้อได้เลย
                                        if ($booking->booking_type !== 'meeting' || Auth::user()->hasRole('super-admin')) {
                                            $canSeeTitle = true;
                                        } 
                                        // 2. ถ้าเป็นคนสร้างการจองนี้เอง -> เห็นได้
                                        elseif ($booking->created_by === Auth::id()) {
                                            $canSeeTitle = true;
                                        } 
                                        // 3. ถ้าเป็นผู้ที่ถูกเชิญเข้าร่วมประชุม -> เห็นได้
                                        elseif ($booking->invitees && $booking->invitees->contains('id', Auth::id())) {
                                            $canSeeTitle = true;
                                        }
                                    @endphp

                                    @if($canSeeTitle)
                                        {{-- โชว์หัวข้อปกติ --}}
                                        <h6 class="fw-bold mb-0 text-dark text-truncate" style="max-width: 65%;">{{ $booking->title }}</h6>
                                    @else
                                        {{-- ซ่อนหัวข้อ โชว์ไอคอนแม่กุญแจ --}}
                                        <h6 class="fw-bold mb-0 text-muted text-truncate fst-italic" style="max-width: 65%;">
                                            <i class="fas fa-lock fa-sm me-1 opacity-50"></i> ---------
                                        </h6>
                                    @endif
                                    {{-- 🌟 จบ: เช็คสิทธิ์การเห็นหัวข้อ --}}

                                    @if($booking->booking_type == 'meeting')
                                        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">นัดประชุม</span>
                                    @elseif($booking->booking_type == 'general_use')
                                        <span class="badge bg-success-subtle text-success-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ทั่วไป</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill px-2 py-1" style="font-size: 0.7rem;">ปรับปรุง</span>
                                    @endif
                                </div>
                                <div class="text-muted small d-flex flex-column gap-1 opacity-75 mt-2">
                                    <span><i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }} น.</span>
                                    <span><i class="fas fa-door-open me-1"></i> {{ $booking->room->name }}</span>
                                    <span class="text-truncate"><i class="fas fa-user-circle me-1"></i> {{ $booking->creator->name ?? '-' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-calendar-check fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0 fw-bold">ว่างทั้งวัน</p>
                                <p class="small mb-0">ไม่มีการจองห้องในวันนี้</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- กล่องที่ 2: นัดหมายของฉัน (เฉพาะที่ฉันถูกเชิญ) --}}
            <div class="card border-0 shadow-sm" style="border-radius: 20px; background-color: #fff;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="accent-line" style="background-color: #0a58ca;"></div>
                        <h5 class="mb-0 fw-bold text-dark" style="letter-spacing: 0.5px;">คำเชิญประชุมของฉัน</h5>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        @forelse($myMeetings as $meeting)
                            <div class="doc-item p-3" style="border-left: 4px solid #0a58ca;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    {{-- โชว์หัวข้อได้เลย เพราะถ้ามาโผล่ตรงนี้คือเราเป็นคนถูกเชิญแน่นอนครับ --}}
                                    <h6 class="fw-bold mb-0 text-dark text-truncate">{{ $meeting->title }}</h6>
                                </div>
                                <div class="text-muted small d-flex flex-column gap-1 opacity-75">
                                    <span><i class="far fa-calendar me-1"></i> {{ \Carbon\Carbon::parse($meeting->start_time)->format('d/m/Y') }}</span>
                                    <span><i class="far fa-clock me-1"></i> {{ \Carbon\Carbon::parse($meeting->start_time)->format('H:i') }} น.</span>
                                    <span><i class="fas fa-door-open me-1"></i> {{ $meeting->room->name }}</span>
                                    <span><i class="fas fa-user-tie me-1"></i> ผู้เชิญ: {{ $meeting->creator->name ?? '-' }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-smile fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0 small fw-bold">ไม่มีคำเชิญประชุม</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .stat-card {
        border-radius: 20px;
        border: none;
    }
    .text-teal { color: #164f51; }
    .text-gold { color: #d18b49; }
    .text-red { color: #ab3333; }
    .accent-line {
        width: 5px;
        height: 22px;
        background-color: #d18b49;
        border-radius: 10px;
        margin-right: 12px;
    }
    .doc-item {
        background-color: #f9fbfd;
        border-radius: 15px;
        border: 1px solid #ebf1f5;
        transition: all 0.2s ease;
    }
    .doc-item:hover {
        background-color: #f0f6f9;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.02);
    }
    /* สี Badge Soft */
    .bg-warning-subtle { background-color: #fff3cd !important; }
    .text-warning-emphasis { color: #997404 !important; }
    .bg-info-subtle { background-color: #cff4fc !important; }
    .text-info-emphasis { color: #055160 !important; }
    .bg-primary-subtle { background-color: #cfe2ff !important; }
    .text-primary-emphasis { color: #0a58ca !important; }
    .bg-success-subtle { background-color: #d1e7dd !important; }
    .text-success-emphasis { color: #0f5132 !important; }
    .bg-danger-subtle { background-color: #f8d7da !important; }
    .text-danger-emphasis { color: #842029 !important; }
</style>
@endsection