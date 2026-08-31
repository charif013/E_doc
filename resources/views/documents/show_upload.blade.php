@extends('layouts.app')
@section('title', 'รายละเอียดเอกสาร')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-file-pdf text-primary me-2"></i>รายละเอียดบันทึกข้อความ (อัปโหลด)
            </h4>
        </div>
        <div>
            <a href="{{ route('documents.approve_list') }}" class="btn btn-outline-dark btn-sm rounded-pill px-4 shadow-sm fw-bold bg-white">
                <i class="fas fa-arrow-left me-1"></i> กลับหน้ารายการ
            </a>
        </div>
    </div>

    @php
        $user = auth()->user();
        $isReviewer = (
            ($user->hasRole('saraban') && in_array($document->status, ['WAITING_ADMIN', 'WAITING_NUMBERING'])) ||
            ($user->hasRole('head')      && $document->status === 'WAITING_SUPERVISOR') ||
            ($user->hasAnyRole(['palad', 'deputy-palad']) && $document->status === 'WAITING_PALAD') ||
            ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK')
        );
    @endphp

    <div class="row g-4">
        {{-- 📄 คอลัมน์ซ้าย: แสดงไฟล์ PDF --}}
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-2"></i>{{ $document->title }}</h6>
                </div>
                <div class="card-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small fw-bold mb-1">วันที่บนเอกสาร</div>
                            {{-- 🌟 แสดงวันที่ไทย (ชื่อเดือนเต็ม) --}}
                            <div class="text-dark fw-bold">{{ $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->addYears(543)->locale('th')->translatedFormat('d F Y') : '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small fw-bold mb-1">เลขที่เอกสาร</div>
                            <div class="text-dark fw-bold">{{ $document->formatted_doc_number ?? 'รอธุรการออกเลข' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fas fa-file-pdf me-2 text-danger"></i>เอกสารแนบต้นฉบับ</h6>
                <div>
                    {{-- ปุ่มดูต้นฉบับ --}}
                    <a href="{{ asset('storage/'.$document->attachment_path) }}" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-bold me-2 bg-white">
                        <i class="fas fa-file-alt me-1"></i>ดูต้นฉบับ
                    </a>
                    
                    {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ดาวน์โหลดเป็น UUID --}}
                    @if($document->creator_signature)
                    <a href="{{ route('documents.download_signed', $document->uuid ?? $document->id) }}" target="_blank" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="fas fa-download me-1"></i>โหลดฉบับลงลายมือชื่อ (PDF)
                    </a>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm" style="border-radius: 16px; min-height: 800px; overflow: hidden;">
                <div class="card-body p-0">
                    <iframe src="{{ asset('storage/'.$document->attachment_path) }}#toolbar=0" width="100%" height="850px" style="border:none;"></iframe>
                </div>
            </div>
        </div>

        {{-- ✍️ คอลัมน์ขวา: การพิจารณาและเกษียนหนังสือ --}}
        <div class="col-xl-5">
            @include('documents.partials.review_box')

            <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history me-2 text-warning"></i>เส้นทางการพิจารณาและการลงนาม</h6>
                </div>
                <div class="card-body p-4">
                    
                    {{-- 1. ผู้เสนอ --}}
                    <div class="d-flex mb-4">
                        <div class="me-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;"><i class="fas fa-user"></i></div>
                        </div>
                        <div class="flex-grow-1 pb-3 border-bottom">
                            <div class="fw-bold text-dark">ผู้เสนอเรื่อง / ผู้รายงาน</div>
                            @if($document->creator_signature)
                                <div class="mt-2"><img src="{{ $document->creator_signature }}" style="max-height: 45px; mix-blend-mode: multiply;"></div>
                            @endif
                            <div class="mt-2 text-primary fw-bold">{{ $document->creator->name ?? '-' }}</div>
                            {{-- 🌟 แสดงวันที่ไทย (ชื่อเดือนย่อ + เวลา) --}}
                            <div class="small text-muted">{{ $document->created_at ? \Carbon\Carbon::parse($document->created_at)->addYears(543)->locale('th')->translatedFormat('d พ.ค. Y H:i น.') : '-' }}</div>
                        </div>
                    </div>

                    {{-- 2. หัวหน้า --}}
                    @if($document->supervisor_signature || in_array($document->status, ['WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED', 'REJECTED']))
                    <div class="d-flex mb-4">
                        <div class="me-3">
                            <div class="bg-{{ $document->supervisor_signature ? 'success' : 'secondary' }} text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;"><i class="fas fa-user-tie"></i></div>
                        </div>
                        <div class="flex-grow-1 pb-3 border-bottom">
                            <div class="fw-bold text-dark">หัวหน้าสำนักปลัด/ผอ.กอง</div>
                            @if($document->supervisor_signature)
                                <div class="mt-2 p-2 bg-light rounded text-dark fw-bold" style="font-size: 14px; white-space: pre-wrap; border-left: 3px solid #1e3a8a;">{{ $document->supervisor_comment ?? 'ทราบ / เพื่อโปรดพิจารณา' }}</div>
                                <div class="mt-2"><img src="{{ $document->supervisor_signature }}" style="max-height: 45px; mix-blend-mode: multiply;"></div>
                                <div class="mt-1 small fw-bold text-primary">{{ $document->supervisor->name ?? '-' }}</div>
                                {{-- 🌟 แสดงวันที่ไทย --}}
                                <div class="small text-muted">{{ $document->supervisor_approved_at ? \Carbon\Carbon::parse($document->supervisor_approved_at)->addYears(543)->locale('th')->translatedFormat('d พ.ค. Y H:i น.') : '-' }}</div>
                            @else
                                <div class="small text-warning mt-1"><i class="fas fa-hourglass-half me-1"></i>กำลังรอการพิจารณา...</div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- 3. ปลัด --}}
                    @if($document->palad_signature || in_array($document->status, ['WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED']))
                    <div class="d-flex mb-4">
                        <div class="me-3">
                            <div class="bg-{{ $document->palad_signature ? 'success' : 'secondary' }} text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;"><i class="fas fa-user-shield"></i></div>
                        </div>
                        <div class="flex-grow-1 pb-3 border-bottom">
                            <div class="fw-bold text-dark">ปลัด อบต.</div>
                            @if($document->palad_signature)
                                <div class="mt-2 p-2 bg-light rounded text-dark fw-bold" style="font-size: 14px; white-space: pre-wrap; border-left: 3px solid #1e3a8a;">{{ $document->palad_comment ?? 'ทราบ / เพื่อโปรดพิจารณา' }}</div>
                                <div class="mt-2"><img src="{{ $document->palad_signature }}" style="max-height: 45px; mix-blend-mode: multiply;"></div>
                                <div class="mt-1 small fw-bold text-primary">{{ $document->palad->name ?? '-' }}</div>
                                {{-- 🌟 แสดงวันที่ไทย --}}
                                <div class="small text-muted">{{ $document->palad_approved_at ? \Carbon\Carbon::parse($document->palad_approved_at)->addYears(543)->locale('th')->translatedFormat('d พ.ค. Y H:i น.') : '-' }}</div>
                            @else
                                <div class="small text-warning mt-1"><i class="fas fa-hourglass-half me-1"></i>กำลังรอการพิจารณา...</div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- 4. นายก --}}
                    @if($document->nayok_signature || in_array($document->status, ['WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED']))
                    <div class="d-flex mb-2">
                        <div class="me-3">
                            <div class="bg-{{ $document->nayok_signature ? 'success' : 'secondary' }} text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px;"><i class="fas fa-star"></i></div>
                        </div>
                        <div class="flex-grow-1 pb-2">
                            <div class="fw-bold text-dark">นายก อบต.</div>
                            @if($document->nayok_signature)
                                <div class="mt-2 p-2 bg-light rounded text-dark fw-bold" style="font-size: 14px; white-space: pre-wrap; border-left: 3px solid #1e3a8a;">{{ $document->nayok_comment ?? 'อนุมัติ / สั่งการ' }}</div>
                                <div class="mt-2"><img src="{{ $document->nayok_signature }}" style="max-height: 45px; mix-blend-mode: multiply;"></div>
                                <div class="mt-1 small fw-bold text-primary">{{ $document->nayok->name ?? '-' }}</div>
                                {{-- 🌟 แสดงวันที่ไทย --}}
                                <div class="small text-muted">{{ $document->nayok_approved_at ? \Carbon\Carbon::parse($document->nayok_approved_at)->addYears(543)->locale('th')->translatedFormat('d พ.ค. Y H:i น.') : '-' }}</div>
                            @else
                                <div class="small text-warning mt-1"><i class="fas fa-hourglass-half me-1"></i>กำลังรอการพิจารณา...</div>
                            @endif
                        </div>
                    </div>
                    @endif

                </div>
            </div>

            {{-- ✏️ Draft Edit (กล่องเซ็นส่งเรื่อง) --}}
            @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
            <div class="text-center mt-4 p-4 rounded-3 shadow-sm" style="background:var(--primary-light);border:1px solid var(--primary-border);">
                <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width:55px;height:55px;background:var(--primary);font-size:22px;">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h5 class="fw-bold mb-1" style="color:var(--primary-dark);">ลงนามส่งเรื่อง</h5>
                <p class="mb-4" style="font-size:14px;color:var(--primary);">กรอกรหัส PIN 6 หลักเพื่อประทับลายเซ็นและส่งให้ผู้บริหาร</p>
                
                <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                    {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ปุ่มแก้ไขเป็น UUID --}}
                    <a href="{{ route('documents.edit', $document->uuid ?? $document->id) }}" class="btn rounded-pill fw-bold px-4 shadow-sm" style="background:#fef08a; border:2px solid #fde047; color:#854d0e; padding:10px 22px; font-size:14px;">
                        <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
                    </a>
                    
                    {{-- 🌟 จุดที่ 3: เปลี่ยนลิงก์ Action ของฟอร์มลบเป็น UUID --}}
                    <form action="{{ route('documents.destroy', $document->uuid ?? $document->id) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn rounded-pill fw-bold px-4 bg-white" style="border:2px solid #fca5a5;color:#dc2626;padding:10px 22px;font-size:14px;"
                                onclick="return confirm('ต้องการลบเอกสารนี้?\n(ข้อมูลและไฟล์จะถูกลบถาวร)')">
                            <i class="fas fa-trash-alt me-2"></i>ลบทิ้ง
                        </button>
                    </form>
                    
                    {{-- 🌟 จุดที่ 4: เปลี่ยนลิงก์ Action ของฟอร์มลงนามเป็น UUID --}}
                    <form action="{{ route('documents.sign', $document->uuid ?? $document->id) }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap ms-md-3">
                        @csrf
                        <input type="password" name="pin" maxlength="6" placeholder="• • • • • •" required autocomplete="off"
                               style="border:2px solid var(--primary-border);border-radius:10px;padding:10px 16px;font-size:18px;letter-spacing:10px;text-align:center;width:160px;outline:none;background:#fff;">
                        <button type="submit" class="btn rounded-pill fw-bold text-white px-4 shadow-sm" style="background:var(--primary);border:none;padding:12px 28px;font-size:15px;">
                            ส่งเรื่องทันที <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>

{{-- 🌟 นำเข้า Flatpickr (ปฏิทินภาษาไทย) 🌟 --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<style>
    /* ซ่อนไอคอนปฏิทินเดิมของเบราว์เซอร์ */
    input[type="date"]::-webkit-calendar-picker-indicator {
        display: none;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ตั้งค่าปฏิทินให้เป็นภาษาไทย และใช้ พ.ศ. สำหรับ input (ถ้ามี)
    if(document.getElementById('doc_date')) {
        flatpickr("#doc_date", {
            locale: "th",
            altInput: true,
            altFormat: "d F Y",
            dateFormat: "Y-m-d",
            defaultDate: "today",
            formatDate: function(date, format, locale) {
                let d = date.getDate();
                let m = date.toLocaleString('th-TH', { month: 'long' });
                let y = date.getFullYear() + 543;
                if (format === "d F Y") { return `${d} ${m} ${y}`; }
                
                let dd = ("0" + date.getDate()).slice(-2);
                let mm = ("0" + (date.getMonth() + 1)).slice(-2);
                let yyyy = date.getFullYear();
                if (format === "Y-m-d") { return `${yyyy}-${mm}-${dd}`; }
                
                return date.toLocaleDateString('th-TH');
            }
        });
    }
});
</script>
@endsection
