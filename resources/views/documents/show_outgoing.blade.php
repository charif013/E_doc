@extends('layouts.app')
@section('title', 'รายละเอียดหนังสือส่งออก')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    @include('documents.partials.status_timeline')
    @php
        $user = auth()->user();
        $isReviewer = (isset($canApprove) && $canApprove) || (
            ($user->hasRole('saraban') && in_array($document->status, ['WAITING_ADMIN', 'WAITING_NUMBERING'])) || 
            ($user->hasRole('head') && $document->status === 'WAITING_SUPERVISOR') || 
            ($user->hasAnyRole(['palad', 'deputy-palad']) && $document->status === 'WAITING_PALAD') || 
            ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK')
        );
        
        // 🌟 อัปเดตสีป้ายสถานะให้เป็นสีทึบ (Solid) ป้องกันปัญหาตัวหนังสือกลืนกับพื้นหลัง 🌟
        $badges = [
            'DRAFT'              => ['bg-secondary', 'text-white', 'ฉบับร่าง'],
            'REGISTERED'         => ['bg-info', 'text-dark', 'รับเรื่องแล้ว'],
            'IN_REVIEW'          => ['bg-warning', 'text-dark', 'อยู่ระหว่างพิจารณา'],
            'WAITING_REVIEWER'   => ['bg-warning', 'text-dark', 'รอผู้ตรวจสอบ'],
            'WAITING_APPROVER'   => ['bg-primary', 'text-white', 'รออนุมัติ'],
            'WAITING_ADMIN'      => ['bg-secondary', 'text-white', 'รอธุรการรับเรื่อง'],
            'WAITING_SUPERVISOR' => ['bg-warning', 'text-dark', 'รอหัวหน้าสำนักปลัด'],
            'WAITING_PALAD'      => ['bg-primary', 'text-white', 'รอปลัด อบต.'],
            'WAITING_NAYOK'      => ['bg-info', 'text-dark', 'รอนายกฯ อนุมัติ'], // ใช้สีฟ้าสว่าง ตัวหนังสือดำ
            'WAITING_NUMBERING'  => ['bg-dark', 'text-white', 'รอธุรการลงทะเบียนเลข'],
            'APPROVED'           => ['bg-success', 'text-white', 'อนุมัติแล้ว / เสร็จสิ้น'],
            'COMPLETED'          => ['bg-success', 'text-white', 'อนุมัติแล้ว / เสร็จสิ้น'],
            'ARCHIVED'           => ['bg-dark', 'text-white', 'จัดเก็บแล้ว'],
            'REJECTED'           => ['bg-danger', 'text-white', 'ถูกตีกลับ / แก้ไข'],
            'CANCELED'           => ['bg-dark', 'text-white', 'ยกเลิก / เลขเสีย']
        ];
        $b = $badges[$document->status] ?? ['bg-light', 'text-dark', $document->status];
        if ($document->isAtFinalApprovalStep()) {
            $b = ['bg-primary', 'text-white', 'รออนุมัติ'];
        }

        $sigSteps = [
            ['label'=>'ผู้เสนอเรื่อง',       'sig'=>$document->creator_signature,    'name'=>$document->creator->name ?? '-',    'pos'=>$document->creator->position ?? '-',     'at'=>$document->created_at?->format('d/m/Y H:i'), 'icon'=>'fa-user'],
            ['label'=>'หัวหน้าส่วนราชการ',  'sig'=>$document->supervisor_signature, 'name'=>$document->supervisor->name ?? '-', 'pos'=>$document->supervisor->position ?? 'หัวหน้าส่วนฯ', 'at'=>$document->supervisor_approved_at ? \Carbon\Carbon::parse($document->supervisor_approved_at)->format('d/m/Y H:i') : null, 'icon'=>'fa-user-tie'],
            ['label'=>'ปลัด อบต.',           'sig'=>$document->palad_signature,      'name'=>$document->palad->name ?? '-',      'pos'=>$document->palad->position ?? 'ปลัด อบต.',   'at'=>$document->palad_approved_at ? \Carbon\Carbon::parse($document->palad_approved_at)->format('d/m/Y H:i') : null, 'icon'=>'fa-user-shield'],
            ['label'=>'นายก อบต.',           'sig'=>$document->nayok_signature,      'name'=>$document->nayok->name ?? '-',      'pos'=>$document->nayok->position ?? 'นายก อบต.',   'at'=>$document->nayok_approved_at ? \Carbon\Carbon::parse($document->nayok_approved_at)->format('d/m/Y H:i') : null, 'icon'=>'fa-star'],
        ];

        if (!function_exists('getSigUrl')) { 
            function getSigUrl($path) { 
                if (empty($path)) return ''; 
                $path = trim($path); 
                if (str_starts_with($path, 'data:image')) return $path; 
                if (preg_match('/^https?:\/\//', $path)) return $path; 
                return asset('storage/' . preg_replace('/^(public|storage)\//', '', ltrim($path, '/'))); 
            } 
        }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 mx-auto no-print" style="max-width: 950px;">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);"><i class="fas fa-file-export text-primary me-2"></i>หนังสือส่งออก</h4>
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="badge bg-primary-subtle text-primary border rounded-pill px-3 py-2 shadow-sm">อ้างอิง: #{{ str_pad($document->id, 5, '0', STR_PAD_LEFT) }}</span>
                <span class="badge {{ $b[0] }} {{ $b[1] }} border rounded-pill px-3 py-2 shadow-sm">{{ $b[2] }}</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold"><i class="fas fa-print me-2"></i>พิมพ์</button>
            <a href="{{ route('documents.approve_list') }}" class="ds-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้ารายการ</a>
        </div>
    </div>

    <div class="mx-auto" style="max-width: 950px;">
        
        {{-- แจ้งเตือนถูกยกเลิก --}}
        @if($document->status === 'CANCELED')
            <div class="alert alert-dark border-0 shadow-sm d-flex align-items-center mb-4 no-print" style="border-left: 5px solid #1e293b !important; border-radius: 12px;">
                <i class="fas fa-ban fs-3 text-secondary me-3"></i>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">หนังสือส่งออกฉบับนี้ถูกยกเลิก</h6>
                    <span class="small text-muted"><strong>เหตุผล:</strong> {{ $document->reject_reason ?? 'ไม่อนุมัติ' }}</span>
                </div>
            </div>
        @endif

        {{-- ซ่อนกระดาษ A4 ถ้าเป็นการอัปโหลดไฟล์เข้ามาล้วนๆ --}}
        @if(!str_contains(strip_tags($document->content), 'อ้างอิงจากไฟล์แนบในระบบ'))
        <div class="card border-0 shadow-sm mb-4 doc-container" style="border-radius: 0; background: #525659; padding: 40px 0;">
            <div class="doc-paper shadow-lg mx-auto bg-white" style="width: 210mm; min-height: 297mm; padding: 20mm 15mm 20mm 25mm; position: relative;">
                @include('documents.partials.paper')
            </div>
        </div>
        @endif

        {{-- PDF Viewer (แสดงไฟล์แนบที่อัปโหลด + ใบแนบท้าย ถ้ามี) --}}
        @if($document->attachment_path)
            <div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-file-pdf me-2 text-danger"></i>ไฟล์เอกสารที่อัปโหลด
                            @if($document->signed_path) 
                                <span class="badge bg-success-subtle text-success border border-success-subtle ms-2"><i class="fas fa-check-circle me-1"></i> มีใบแนบท้ายแล้ว</span>
                            @endif
                        </h6>
                        <a href="{{ route('documents.file', [$document->uuid ?? $document->id, $document->signed_path ? 'signed' : 'main']) }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold shadow-sm">
                            <i class="fas fa-external-link-alt me-1"></i> เปิดดูฉบับเต็ม
                        </a>
                    </div>
                    
                    @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
                        <div class="bg-light p-3 rounded-3 border mb-3 no-print">
                            <form action="{{ route('documents.upload', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap align-items-end gap-3">
                                @csrf
                                <div class="flex-grow-1">
                                    <label class="form-label small fw-bold text-muted mb-2">
                                        <i class="fas fa-paperclip me-1"></i> เปลี่ยนไฟล์แนบใหม่ (PDF, JPG, PNG)
                                    </label>
                                    <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="border-radius: 8px;">
                                </div>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:var(--primary);">
                                    <i class="fas fa-upload me-1"></i> อัปโหลดทับไฟล์เดิม
                                </button>
                            </form>
                        </div>
                    @endif

                    <div class="rounded-3 overflow-hidden border shadow-sm">
                        <iframe src="{{ route('documents.file', [$document->uuid ?? $document->id, $document->signed_path ? 'signed' : 'main']) }}" width="100%" height="800px"></iframe>
                    </div>
                </div>
            </div>
        @endif

        @include('documents.partials.dynamic_route_track')

        @if($isReviewer)
            <div class="card border-0 shadow-sm overflow-hidden mb-4 no-print" style="border-radius: 16px; border-left: 5px solid var(--primary) !important;">
                <div class="card-header bg-white py-3 border-bottom-0"><h6 class="fw-bold mb-0 text-dark"><i class="fas fa-signature text-primary me-2"></i>ส่วนการพิจารณาและอนุมัติ</h6></div>
                <div class="card-body pt-0">@include('documents.partials.review_box_internal')</div>
            </div>
        @endif

        @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
        <div class="text-center mb-5 p-4 rounded-3 shadow-sm no-print" style="background:var(--primary-light);border:1px solid var(--primary-border);">
            <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width:55px;height:55px;background:var(--primary);font-size:22px;"><i class="fas fa-paper-plane"></i></div>
            <h5 class="fw-bold mb-1" style="color:var(--primary-dark);">ขั้นตอนสุดท้าย: ยืนยันการส่งเรื่อง</h5>
            <p class="mb-4" style="font-size:14px;color:var(--primary);">ยืนยันความถูกต้องและลงนามเพื่อส่งเอกสารเข้าสู่ระบบ</p>
            <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                <a href="{{ route('documents.edit', $document->uuid ?? $document->id) }}" class="btn rounded-pill fw-bold px-4 shadow-sm" style="background:#fef08a;border:2px solid #fde047;color:#854d0e;padding:10px 22px;font-size:14px;"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูล</a>
                <form action="{{ route('documents.destroy', $document->uuid ?? $document->id) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="btn rounded-pill fw-bold px-4 bg-white" style="border:2px solid #fca5a5;color:#dc2626;padding:10px 22px;font-size:14px;" onclick="return confirm('ต้องการลบเอกสารนี้?\n(ข้อมูลจะถูกลบถาวร)')"><i class="fas fa-trash-alt me-2"></i>ลบทิ้ง</button></form>
                <form id="signDocumentForm" action="{{ route('documents.sign', $document->uuid ?? $document->id) }}" method="POST" class="ms-md-2">@csrf<input type="hidden" name="pin" id="signaturePinInput"><input type="hidden" name="stamp_creator" id="stampCreatorInput" value="1"><button type="button" onclick="promptSignaturePin()" class="btn rounded-pill fw-bold text-white px-4 shadow-sm" style="background:var(--primary);border:none;padding:12px 28px;font-size:15px;white-space:nowrap;"><i class="fas fa-paper-plane me-2"></i>ส่งเรื่องทันที</button></form>
            </div>
        </div>
        @endif

    </div>
</div>

<style>
    @font-face { font-family: 'MySarabun'; src: url('{{ asset("fonts/THSarabunIT๙.ttf") }}') format('truetype'); font-weight: normal; font-style: normal; }
    @font-face { font-family: 'MySarabun'; src: url('{{ asset("fonts/THSarabunIT๙_Bold.ttf") }}') format('truetype'); font-weight: bold; font-style: normal; }
    
    .doc-paper, .doc-paper * { font-family: 'MySarabun', sans-serif !important; line-height: 1.15 !important; }
    
    .doc-paper { 
        color: #000; 
        background-color: white; 
        background-image: linear-gradient(to bottom, transparent 296mm, #94a3b8 296mm, #94a3b8 297mm); 
        background-size: 100% 297mm; 
        box-sizing: border-box; 
    }
    
    /* 🌟🌟🌟 ทะลวงโครงสร้าง Layout ของระบบ สำหรับการปริ้นโดยเฉพาะ 🌟🌟🌟 */
    @media print {
        @page { 
            size: A4; 
            margin: 0; 
        }

        html, body, #app, main, .wrapper, .content-wrapper, .container-fluid, .doc-container {
            height: auto !important;
            min-height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            position: static !important;
            background: white !important;
        }

        body * { visibility: hidden; }
        .doc-paper, .doc-paper * { visibility: visible; }

        .no-print, .doc-paper .no-print, .doc-paper .no-print * {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            position: absolute !important;
        }

        .doc-paper {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            margin: 0 !important;
            padding: 20mm 15mm 20mm 25mm !important; 
            width: 210mm !important;
            height: auto !important;
            min-height: auto !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            overflow: visible !important;
        }

        /* 🌟🌟 1. ปิดการใช้ Flexbox ทั่วทั้งเอกสาร (ตัวการทำให้ Chrome หั่นหน้ากระดาษ) 🌟🌟 */
        .doc-paper .row,
        .doc-paper .d-flex {
            display: block !important;
            width: 100% !important;
        }

        /* 🌟🌟 2. ดันกล่องลายเซ็นไปฝั่งขวา (ใช้ inline-block เพื่อให้ห้ามตัดหน้าทำงาน) 🌟🌟 */
        .doc-paper .col-md-6.text-center,
        .doc-paper .offset-md-6,
        .doc-paper .justify-content-end > div {
            display: inline-block !important;
            width: 50% !important;
            margin-left: 50% !important;
            text-align: center !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            -webkit-column-break-inside: avoid !important;
        }

        /* 🌟🌟 3. จัดให้คำว่า (ลงชื่อ) และเส้นประอยู่บรรทัดเดียวกัน 🌟🌟 */
        .doc-paper .align-items-end {
            display: block !important;
            text-align: center !important;
            margin-bottom: 5px !important;
        }

        .doc-paper .align-items-end > span,
        .doc-paper .align-items-end > div {
            display: inline-block !important;
            vertical-align: bottom !important;
        }

        /* 🌟🌟 4. ป้องกันรูปภาพลายเซ็น และกล่องความเห็นโดนผ่าครึ่ง 🌟🌟 */
        .doc-paper img,
        .doc-paper .mt-4,
        .doc-paper .mt-5 {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            -webkit-column-break-inside: avoid !important;
        }

        .doc-paper img {
            display: block !important;
            margin: 0 auto !important;
        }
    }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function promptSignaturePin() {
        Swal.fire({
            title: 'ยืนยันการส่งเรื่อง',
            html: `
                <p class="mb-3" style="font-size: 14px; color: #475569;">กรุณากรอกรหัส PIN 6 หลักของคุณ</p>
                <input type="password" id="swal-pin" class="swal2-input" maxlength="6" pattern="[0-9]*" inputmode="numeric" placeholder="••••••" style="text-align: center; letter-spacing: 10px; font-size: 24px; width: 200px; margin: 0 auto 20px auto;">
                <div class="form-check text-start mx-auto mt-3" style="max-width: 280px; padding-left: 2rem;">
                    <input class="form-check-input" type="checkbox" id="swal-stamp-creator" value="1" checked style="cursor: pointer; transform: scale(1.2);">
                    <label class="form-check-label" for="swal-stamp-creator" style="font-size: 14px; color: #334155; cursor: pointer; padding-left: 5px;">
                        ประทับลายเซ็นลงบนไฟล์ PDF<br>
                        <span style="font-size: 12px; color: #ef4444;">(เอาออกหากในไฟล์มีลายเซ็นสดอยู่แล้ว)</span>
                    </label>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#0ea5e9',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> ยืนยันส่งเรื่อง',
            cancelButtonText: 'ยกเลิก',
            preConfirm: () => {
                const pin = document.getElementById('swal-pin').value;
                const stamp = document.getElementById('swal-stamp-creator').checked ? '1' : '0';
                if (!pin || pin.length !== 6 || !/^\d+$/.test(pin)) {
                    Swal.showValidationMessage('กรุณากรอกรหัส PIN ให้ครบ 6 หลัก (ตัวเลขเท่านั้น)');
                    return false;
                }
                return { pin: pin, stamp: stamp };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('signaturePinInput').value = result.value.pin;
                document.getElementById('stampCreatorInput').value = result.value.stamp;
                Swal.fire({ title: 'กำลังดำเนินการ...', text: 'ระบบกำลังประทับลายเซ็นและต่อใบแนบท้าย', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                document.getElementById('signDocumentForm').submit();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        @if(session('error_signature'))
            Swal.fire({ icon: 'warning', title: 'ลายเซ็นไม่พร้อม', text: '{{ session("error_signature") }}', confirmButtonText: 'ไปหน้าตั้งค่า', confirmButtonColor: '#0284c7' }).then(r => { if (r.isConfirmed) window.location.href = "{{ route('profile.index') }}"; });
        @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'ไม่สามารถดำเนินการได้', text: '{!! session("error") !!}', confirmButtonColor: '#dc2626' }); @endif
        @if(session('error_pin')) Swal.fire({ icon: 'error', title: 'รหัสไม่ถูกต้อง', text: '{{ session("error_pin") }}', confirmButtonText: 'ลองอีกครั้ง', confirmButtonColor: '#dc2626' }); @endif
        @if($errors->any()) Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ถูกต้อง', text: '{{ $errors->first() }}', confirmButtonColor: '#dc2626' }); @endif
        @if(session('success')) Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '{{ session("success") }}', timer: 2000, showConfirmButton: false }); @endif
    });
</script>
@endsection
