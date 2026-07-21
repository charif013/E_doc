@extends('layouts.app')
@section('title', 'สมุดทะเบียนคุมเลขสารบรรณ')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    
    {{-- 🌟 Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 mx-auto" style="max-width: 1400px;">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-book text-success me-2"></i>สมุดทะเบียนคุมเลข (ธุรการ)
            </h4>
            <p class="text-secondary small mb-0">ระบบลงทะเบียนรับ-ส่ง และออกเลขที่เอกสารแยกตามประเภท</p>
        </div>
        
        <div class="d-flex gap-2">
            <a href="{{ route('documents.number_ledger') }}" class="btn btn-warning btn-sm rounded-pill px-4 shadow-sm fw-bold text-dark border border-warning">
                <i class="fas fa-list-ol me-1"></i> เปิดสมุดรันเลข / จองเลข
            </a>
            <a href="{{ route('home') }}" class="btn btn-outline-dark btn-sm rounded-pill px-4 shadow-sm fw-bold bg-white">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
            </a>
        </div>
    </div>

    <div class="mx-auto" style="max-width: 1400px;">

        {{-- 🌟 Summary Bar (สรุปยอด 4 ช่อง สไตล์โมเดิร์น) --}}
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; border-left: 5px solid #f59e0b !important;">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="bg-warning-subtle text-warning-emphasis rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-hourglass-half fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold">รอออกเลขทะเบียน</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $summary['pending'] ?? 0 }} <span class="fs-6 text-muted fw-normal">รายการ</span></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; border-left: 5px solid #10b981 !important;">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="bg-success-subtle text-success-emphasis rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-inbox fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold">หนังสือรับเข้า (ปีนี้)</div>
                            <h4 class="mb-0 fw-bold text-dark">{{ $summary['incoming'] ?? 0 }} <span class="fs-6 text-muted fw-normal">รายการ</span></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; border-left: 5px solid #0ea5e9 !important;">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="bg-info-subtle text-info-emphasis rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-paper-plane fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold">หนังสือส่งออก (ปีนี้)</div>
                            <h4 class="mb-0 fw-bold text-dark">
                                {{ collect($outgoingDocuments ?? [])->where('doc_type', 'outgoing')->count() }} 
                                <span class="fs-6 text-muted fw-normal">รายการ</span>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; border-left: 5px solid #8b5cf6 !important;">
                    <div class="card-body p-3 d-flex align-items-center">
                        <div class="bg-purple-subtle text-purple-emphasis rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                            <i class="fas fa-file-alt fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-bold">บันทึกข้อความภายใน</div>
                            <h4 class="mb-0 fw-bold text-dark">
                                {{ collect($outgoingDocuments ?? [])->where('doc_type', 'internal')->count() }} 
                                <span class="fs-6 text-muted fw-normal">รายการ</span>
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 🌟 Filter & Search --}}
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
            <div class="card-body p-3">
                <form method="GET" action="{{ route('documents.registry') }}" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted rounded-start-pill"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 rounded-end-pill" placeholder="ค้นหา เลขรับ, เลขส่ง, เรื่อง..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="year" class="form-select rounded-pill">
                            @php $currentYear = date('Y'); @endphp
                            @for($i = 0; $i <= 4; $i++)
                                <option value="{{ $currentYear - $i }}" {{ (request('year', $currentYear) == ($currentYear - $i)) ? 'selected' : '' }}>
                                    ปีงบประมาณ {{ ($currentYear - $i) + 543 }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-dark rounded-pill w-100 fw-bold">กรองข้อมูล</button>
                    </div>
                    @if(request('search') || request('year'))
                    <div class="col-md-2">
                        <a href="{{ route('documents.registry') }}" class="btn btn-outline-danger rounded-pill w-100">ล้างค่า</a>
                    </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- 🌟 Tabs เลือกดูสมุดทะเบียน --}}
        <ul class="nav nav-pills mb-4 bg-white p-2 rounded-pill shadow-sm d-inline-flex" id="registryTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill fw-bold px-4" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button" role="tab">
                    <i class="fas fa-keyboard mt-px me-1"></i> รอออกเลขทะเบียน @if($summary['pending'] > 0) <span class="badge bg-danger ms-1">{{ $summary['pending'] }}</span> @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold px-4 text-secondary" id="incoming-tab" data-bs-toggle="pill" data-bs-target="#incoming" type="button" role="tab">
                    <i class="fas fa-inbox mt-px me-1"></i> ทะเบียนหนังสือรับ (เข้า)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold px-4 text-secondary" id="outgoing-tab" data-bs-toggle="pill" data-bs-target="#outgoing" type="button" role="tab">
                    <i class="fas fa-paper-plane mt-px me-1"></i> ทะเบียนหนังสือส่ง (ออก)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill fw-bold px-4 text-secondary" id="internal-tab" data-bs-toggle="pill" data-bs-target="#internal" type="button" role="tab">
                    <i class="fas fa-file-alt mt-px me-1"></i> ทะเบียนบันทึกข้อความ (ภายใน)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="registryTabsContent">
            
            {{-- 🟢 แท็บ 1: เอกสารรอออกเลขทะเบียน --}}
            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">วันที่ส่งเรื่อง</th>
                                    <th>ประเภทงาน</th>
                                    <th>ส่วนราชการ (ผู้เสนอ)</th>
                                    <th>เรื่อง</th>
                                    <th class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingDocuments ?? [] as $doc)
                                    <tr>
                                        <td class="ps-4 text-muted small">{{ \Carbon\Carbon::parse($doc->updated_at)->addYears(543)->locale('th')->translatedFormat('j M y / H:i') }} น.</td>
                                        <td>
                                            <span class="badge @if($doc->doc_type === 'internal') bg-purple-subtle text-purple @else bg-info-subtle text-info @endif rounded-pill px-2.5">
                                                {{ $doc->doc_type === 'internal' ? 'บันทึกภายใน' : 'หนังสือส่งออก' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $doc->creator->department ?? '-' }}</div>
                                            <div class="small text-muted">{{ $doc->creator->name ?? '-' }}</div>
                                        </td>
                                        <td>
                                            {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ชื่อเรื่องเป็น UUID --}}
                                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="fw-bold text-primary text-decoration-none text-wrap" style="max-width: 300px; display: inline-block;">
                                                {{ $doc->title }}
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#numberingModal{{ $doc->id }}">
                                                <i class="fas fa-pen me-1"></i> ออกเลข
                                            </button>
                                        </td>
                                    </tr>

                                    {{-- Modal ออกเลข --}}
                                    <div class="modal fade" id="numberingModal{{ $doc->id }}" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content border-0 shadow" style="border-radius: 16px;">
                                                <div class="modal-header border-bottom-0 pb-0">
                                                    <h5 class="modal-title fw-bold text-dark"><i class="fas fa-keyboard text-success me-2"></i>ลงทะเบียนออกเลขหนังสือ</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ Modal ฟอร์มเป็น UUID --}}
                                                <form action="{{ route('documents.assign_number', $doc->uuid ?? $doc->id) }}" method="POST">
                                                    @csrf
                                                    <div class="modal-body py-4">
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted small fw-bold">เรื่อง</label>
                                                            <div class="p-2 bg-light rounded border fw-bold text-dark">{{ $doc->title }}</div>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label fw-bold text-dark">ระบุเลขที่หนังสือ <span class="text-danger">*</span></label>
                                                            <input type="text" name="doc_number" class="form-control form-control-lg text-primary fw-bold" 
                                                                   placeholder="เช่น {{ $doc->doc_type === 'internal' ? '๐๐๒๓.๑/' : 'ยล ๕๔๒๐๑/' }}" required autofocus>
                                                            <small class="text-muted mt-1 d-block">ระบบจะนำเลขทะเบียนนี้ไปประทับบนหัวเอกสารของแฟ้มระบบทันที</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top-0 pt-0 justify-content-center">
                                                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                                                        <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">บันทึกเลขเอกสาร</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 text-light"></i><br>ไม่มีเอกสารรอออกเลขในขณะนี้</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 🟢 แท็บ 2: ทะเบียนหนังสือรับ (เข้า) --}}
            <div class="tab-pane fade" id="incoming" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="bg-light p-3 border-bottom d-flex justify-content-end">
                        <a href="{{ route('documents.create_incoming') }}" class="btn btn-sm btn-success rounded-pill fw-bold shadow-sm">
                            <i class="fas fa-plus me-1"></i> ลงรับหนังสือใหม่
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">เลขรับ</th>
                                    <th>วันที่รับ</th>
                                    <th>เลขที่หนังสือ</th>
                                    <th>จาก (หน่วยงาน)</th>
                                    <th>เรื่อง</th>
                                    <th>มอบหมายกองงาน</th>
                                    <th class="text-center">ดู</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($incomingDocuments ?? [] as $doc)
                                    <tr>
                                        <td class="ps-4"><span class="badge bg-success-subtle text-success-emphasis border rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">{{ $doc->receive_number ?? '-' }}</span></td>
                                        <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->receive_date ?? $doc->created_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                        <td><span class="text-dark small">{{ $doc->doc_number ?? '-' }}</span></td>
                                        <td><div class="text-dark small text-wrap" style="max-width: 150px;">{{ $doc->doc_from ?? '-' }}</div></td>
                                        <td>
                                            {{-- 🌟 จุดที่ 3: เปลี่ยนลิงก์ชื่อเรื่องเป็น UUID --}}
                                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="fw-bold text-primary text-decoration-none text-wrap" style="max-width: 250px; display: inline-block;">
                                                {{ $doc->title }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($doc->assigned_to)
                                                <span class="badge bg-warning text-dark border shadow-sm text-xs">{{ $doc->assigned_to }}</span>
                                            @else
                                                <span class="badge bg-light text-secondary border">- รอมอบหมาย -</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{-- 🌟 จุดที่ 4: เปลี่ยนลิงก์ปุ่มดูเป็น UUID --}}
                                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-circle"><i class="fas fa-search"></i></a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-3x mb-3 text-light"></i><br>ยังไม่มีประวัติทะเบียนรับหนังสือเข้า</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 🟢 แท็บ 3: ทะเบียนหนังสือส่ง (ออก) --}}
            <div class="tab-pane fade" id="outgoing" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">เลขที่หนังสือออก</th>
                                    <th>วันที่ส่ง (ลงเลข)</th>
                                    <th>ส่วนราชการผู้เสนอ</th>
                                    <th>เรื่อง</th>
                                    <th>ถึง (ผู้รับ)</th>
                                    <th>สถานะ</th>
                                    <th class="text-center">ดู</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $hasOutgoing = false; @endphp
                                @foreach($outgoingDocuments ?? [] as $doc)
                                    @if($doc->doc_type === 'outgoing')
                                        @php $hasOutgoing = true; @endphp
                                        <tr>
                                            <td class="ps-4"><span class="badge bg-info-subtle text-info-emphasis border rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">{{ $doc->doc_number }}</span></td>
                                            <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->updated_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                            <td><div class="text-dark smallfw-bold">{{ $doc->creator->department ?? '-' }}</div></td>
                                            <td><div class="fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $doc->title }}</div></td>
                                            <td><div class="text-muted small text-wrap" style="max-width: 150px;">{{ $doc->doc_to ?? $doc->send_to ?? '-' }}</div></td>
                                            <td>
                                                @if($doc->status === 'CANCELED')
                                                    <span class="badge bg-dark">ยกเลิก (เลขเสีย)</span>
                                                @else
                                                    <span class="badge bg-success">ออกเลขแล้ว</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                {{-- 🌟 จุดที่ 5: เปลี่ยนลิงก์ปุ่มดูเป็น UUID --}}
                                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-circle"><i class="fas fa-search"></i></a>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                @if(!$hasOutgoing)
                                    <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-3x mb-3 text-light"></i><br>ยังไม่มีประวัติทะเบียนหนังสือส่งออกภายนอก</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 🟢 แท็บ 4: ทะเบียนบันทึกข้อความ (ภายใน) --}}
            <div class="tab-pane fade" id="internal" role="tabpanel">
                <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-nowrap">
                            <thead class="bg-light text-secondary" style="font-size: 14px;">
                                <tr>
                                    <th class="ps-4 py-3">เลขที่บันทึกภายใน</th>
                                    <th>วันที่ลงเลข</th>
                                    <th>หน่วยงานผู้เสนอ</th>
                                    <th>เรื่อง</th>
                                    <th>เรียน (ถึง)</th>
                                    <th>สถานะคุมเล่ม</th>
                                    <th class="text-center">ดู</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $hasInternal = false; @endphp
                                @foreach($outgoingDocuments ?? [] as $doc)
                                    @if($doc->doc_type === 'internal')
                                        @php $hasInternal = true; @endphp
                                        <tr>
                                            <td class="ps-4"><span class="badge bg-purple-subtle text-purple-emphasis border rounded-pill px-3 py-2 fw-bold" style="font-size: 13px;">{{ $doc->doc_number }}</span></td>
                                            <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->updated_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                            <td><div class="text-dark small fw-bold">{{ $doc->creator->department ?? '-' }}</div></td>
                                            <td><div class="fw-bold text-dark text-wrap" style="max-width: 250px;">{{ $doc->title }}</div></td>
                                            <td><div class="text-muted small text-wrap" style="max-width: 150px;">{{ $doc->doc_to ?? 'นายกองค์การบริหารส่วนตำบล' }}</div></td>
                                            <td><span class="badge bg-success shadow-sm">บันทึกข้อความแล้ว</span></td>
                                            <td class="text-center">
                                                {{-- 🌟 จุดที่ 6: เปลี่ยนลิงก์ปุ่มดูเป็น UUID --}}
                                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-circle"><i class="fas fa-search"></i></a>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                @if(!$hasInternal)
                                    <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-file-alt fa-3x mb-3 text-light"></i><br>ยังไม่มีประวัติทะเบียนบันทึกข้อความภายใน</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        @if(session('success')) 
            Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '{{ session("success") }}', timer: 2000, showConfirmButton: false }); 
        @endif
    });
</script>
@endsection