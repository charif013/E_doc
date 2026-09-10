@extends('layouts.app')
@section('title', 'รายละเอียดเอกสาร')

@section('content')
<div class="container-fluid px-4 py-4" style="background-color: var(--bg-page); min-height: 100vh;">
    @include('documents.partials.status_timeline')

    {{-- ============================================================
         🔧 HELPER: ตัวแปรกลางและฟังก์ชันช่วยเหลือ
    ============================================================ --}}
    @php
        $user = auth()->user();

        // ─── สถานะและผู้มีสิทธิ์พิจารณา ───────────────────────────────
        $isReviewer = (
            ($user->hasRole('saraban')                           && in_array($document->status, ['WAITING_ADMIN', 'WAITING_NUMBERING'])) ||
            ($user->hasRole('head')                             && $document->status === 'WAITING_SUPERVISOR') ||
            ($user->hasAnyRole(['palad', 'deputy-palad'])       && $document->status === 'WAITING_PALAD') ||
            ($user->hasRole('executive')                        && $document->status === 'WAITING_NAYOK')
        );

        // ─── Badge แสดงสถานะ ──────────────────────────────────────────
        $badges = [
            'DRAFT'              => ['bg-secondary',  'text-white', 'ฉบับร่าง'],
            'REGISTERED'         => ['bg-info',       'text-dark',  'รับเรื่องแล้ว'],
            'IN_REVIEW'          => ['bg-warning',    'text-dark',  'อยู่ระหว่างพิจารณา'],
            'WAITING_REVIEWER'   => ['bg-warning',    'text-dark',  'รอผู้ตรวจสอบ'],
            'WAITING_APPROVER'   => ['bg-primary',    'text-white', 'รออนุมัติ'],
            'WAITING_ADMIN'      => ['bg-secondary',  'text-white', 'รอธุรการรับเรื่อง'],
            'WAITING_SUPERVISOR' => ['bg-warning',    'text-dark',  'รอหัวหน้าสำนักปลัด'],
            'WAITING_PALAD'      => ['bg-primary',    'text-white', 'รอปลัด อบต.'],
            'WAITING_NAYOK'      => ['bg-info',       'text-dark',  'รอนายกฯ อนุมัติ'],
            'WAITING_NUMBERING'  => ['bg-info',       'text-dark',  'รอธุรการลงทะเบียนเลข'],
            'APPROVED'           => ['bg-warning',    'text-dark',  'อนุมัติแล้ว / รอออกเลข'],
            'COMPLETED'          => ['bg-success',    'text-white', 'ออกเลขและดำเนินการเสร็จสิ้น'],
            'ARCHIVED'           => ['bg-dark',       'text-white', 'จัดเก็บแล้ว'],
            'REJECTED'           => ['bg-danger',     'text-white', 'ถูกตีกลับ / แก้ไข'],
            'CANCELED'           => ['bg-dark',       'text-white', 'ยกเลิก / เลขเสีย'],
        ];
        $b = $badges[$document->status] ?? ['bg-light', 'text-dark', $document->status];
        if ($document->isAtFinalApprovalStep()) {
            $b = ['bg-primary', 'text-white', 'รออนุมัติ'];
        }

        // ─── ฟังก์ชัน: แปลง path ลายเซ็นให้ปลอดภัย ──────────────────
        if (!function_exists('getSigUrl')) {
            function getSigUrl($path) {
                if (empty($path)) return '';
                $path = trim($path);
                if (str_starts_with($path, 'data:image'))   return $path;
                if (preg_match('/^https?:\/\//', $path))    return $path;
                $path = preg_replace('/^(public|storage)\//', '', ltrim($path, '/'));
                return asset('storage/' . $path);
            }
        }

        // ─── ฟังก์ชัน: แปลงวันที่ Carbon เป็น พ.ศ. ──────────────────
        if (!function_exists('toThaiDate')) {
            function toThaiDate($dateValue, $withTime = false) {
                if (empty($dateValue)) return '-';
                $dt = $dateValue instanceof \Carbon\Carbon
                    ? $dateValue
                    : \Carbon\Carbon::parse($dateValue);
                $thaiMonths = [
                    1  => 'มกราคม',   2  => 'กุมภาพันธ์', 3  => 'มีนาคม',
                    4  => 'เมษายน',   5  => 'พฤษภาคม',    6  => 'มิถุนายน',
                    7  => 'กรกฎาคม',  8  => 'สิงหาคม',    9  => 'กันยายน',
                    10 => 'ตุลาคม',   11 => 'พฤศจิกายน',  12 => 'ธันวาคม',
                ];
                $day   = $dt->format('j');
                $month = $thaiMonths[(int)$dt->format('n')];
                $year  = $dt->year + 543;
                $base  = "{$day} {$month} {$year}";
                return $withTime ? $base . ' ' . $dt->format('H:i') : $base;
            }
        }

        // ─── สร้าง sigSteps ตามประเภทเอกสาร ─────────────────────────
        $sigSteps = [];

        if ($document->doc_type === 'outgoing') {
            // หนังสือส่งออก: ผู้เสนอ → ผู้ลงนาม (2 ช่อง)
            $sigSteps[] = [
                'label' => 'ผู้เสนอเรื่อง / ธุรการ',
                'sig'   => $document->creator_signature,
                'name'  => $document->creator?->name     ?? '-',
                'pos'   => $document->creator?->position ?? '-',
                'at'    => toThaiDate($document->created_at, true),
                'icon'  => 'fa-user',
            ];

            // ระบุผู้ลงนามจากชื่อที่บันทึกไว้
            $signerRaw = explode('—', $document->signer_name ?? '');
            $sName = trim($signerRaw[0] ?? 'ผู้ลงนาม');
            $sPos  = trim($signerRaw[1] ?? 'ผู้บริหาร');

            [$sSig, $sAt] = match (true) {
                str_contains($document->signer_name ?? '', 'นายก') => [$document->nayok_signature,      $document->nayok_approved_at],
                str_contains($document->signer_name ?? '', 'ปลัด') => [$document->palad_signature,      $document->palad_approved_at],
                default                                            => [$document->supervisor_signature, $document->supervisor_approved_at],
            };

            $sigSteps[] = [
                'label' => 'ผู้ลงนามในหนังสือ',
                'sig'   => $sSig,
                'name'  => $sName,
                'pos'   => $sPos,
                'at'    => toThaiDate($sAt, true),
                'icon'  => 'fa-pen-nib',
            ];

        } else {
            // บันทึกข้อความภายใน: 4 ช่องปกติ
            $sigSteps = [
                [
                    'label' => 'ผู้เสนอเรื่อง',
                    'sig'   => $document->creator_signature,
                    'name'  => $document->creator?->name     ?? '-',
                    'pos'   => $document->creator?->position ?? '-',
                    'at'    => toThaiDate($document->created_at, true),
                    'icon'  => 'fa-user',
                ],
                [
                    'label' => 'หัวหน้าส่วนราชการ',
                    'sig'   => $document->supervisor_signature,
                    'name'  => $document->supervisor?->name     ?? '-',
                    'pos'   => $document->supervisor?->position ?? 'หัวหน้าส่วนฯ',
                    'at'    => toThaiDate($document->supervisor_approved_at, true),
                    'icon'  => 'fa-user-tie',
                ],
                [
                    'label' => 'ปลัด อบต.',
                    'sig'   => $document->palad_signature,
                    'name'  => $document->palad?->name     ?? '-',
                    'pos'   => $document->palad?->position ?? 'ปลัด อบต.',
                    'at'    => toThaiDate($document->palad_approved_at, true),
                    'icon'  => 'fa-user-shield',
                ],
                [
                    'label' => 'นายก อบต.',
                    'sig'   => $document->nayok_signature,
                    'name'  => $document->nayok?->name     ?? '-',
                    'pos'   => $document->nayok?->position ?? 'นายก อบต.',
                    'at'    => toThaiDate($document->nayok_approved_at, true),
                    'icon'  => 'fa-star',
                ],
            ];
        }
    @endphp

    {{-- ============================================================
         1. HEADER: ชื่อหน้า + ปุ่มดำเนินการ (ไม่พิมพ์)
    ============================================================ --}}
    <div class="d-flex justify-content-between align-items-center mb-4 mx-auto no-print" style="max-width: 950px;">
        <div>
            <h4 class="fw-bold mb-1" style="color: var(--primary-dark);">
                <i class="fas fa-file-alt text-primary me-2"></i>รายละเอียดบันทึกข้อความ
            </h4>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary-subtle text-primary border rounded-pill px-3 shadow-sm">
                    เลขที่อ้างอิง: #{{ str_pad($document->id, 5, '0', STR_PAD_LEFT) }}
                </span>
                <span class="badge {{ $b[0] }} {{ $b[1] }} rounded-pill px-3 shadow-sm">{{ $b[2] }}</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 shadow-sm fw-bold">
                <i class="fas fa-print me-2"></i>พิมพ์เอกสาร
            </button>
            <a href="{{ $user->hasAnyRole(['super-admin','executive','palad','deputy-palad','head','saraban','officer']) ? route('documents.approve_list') : route('documents.assigned') }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้ารายการ
            </a>
        </div>
    </div>

    <div class="mx-auto" style="max-width: 950px;">

        {{-- ============================================================
             2. แจ้งเตือน: เอกสารถูกยกเลิก (CANCELED)
        ============================================================ --}}
        @if($document->status === 'CANCELED')
            <div class="alert alert-dark border-0 shadow-sm d-flex align-items-center mb-4 no-print"
                 style="border-left: 5px solid #1e293b !important; border-radius: 12px;">
                <i class="fas fa-ban fs-3 text-secondary me-3"></i>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">เอกสารฉบับนี้ถูกยกเลิก (บันทึกเป็นเลขเสีย)</h6>
                    <p class="small text-muted mb-1">
                        <strong>เหตุผล:</strong> {{ $document->reject_reason ?? 'ไม่อนุมัติ / ยกเลิกการจองเลข' }}
                    </p>
                    <p class="small text-danger fw-bold mb-0">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        ห้ามนำเลขที่ {{ $document->formatted_doc_number }} ไปเวียนใช้กับเอกสารอื่นเด็ดขาด
                    </p>
                </div>
            </div>
        @endif

        {{-- ============================================================
             4. จองเลขที่หนังสือล่วงหน้า (เฉพาะ saraban + ยังไม่มีเลข)
        ============================================================ --}}
        @if($user->hasRole('saraban') && empty($document->doc_number) && in_array($document->status, ['WAITING_ADMIN', 'WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK']))
            <div class="card border-0 shadow-sm mb-4 no-print"
                 style="border-radius: 12px; background: #fffbeb; border: 1px dashed #fcd34d;">
                <div class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div>
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fas fa-lock text-warning me-2"></i>จองเลขที่หนังสือล่วงหน้า
                        </h6>
                        <small class="text-muted">สำหรับงานด่วน เพื่อนำเลขไปพิมพ์ลงในเอกสารก่อนผู้บริหารลงนาม</small>
                    </div>
                    <form action="{{ route('documents.reserve_number', $document->id) }}" method="POST"
                          class="d-flex gap-2 w-100" style="max-width: 450px;">
                        @csrf
                        <input type="hidden" name="running_number" id="reserve_running_number">
                        <div class="input-group input-group-sm shadow-sm"
                             style="border-radius: 8px; overflow: hidden; border: 1px solid #fcd34d;">
                            <input type="text" name="doc_number" id="reserve_doc_number"
                                   class="form-control border-0 bg-white fw-bold text-dark px-3"
                                   placeholder="คลิกปุ่มเวทมนตร์เพื่อดึงเลข..." required>
                            <button type="button" onclick="autoReserveNo()" class="btn btn-primary fw-bold px-3" title="ดึงเลขอัตโนมัติ">
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm fw-bold text-nowrap shadow-sm text-dark px-3 rounded-3">
                            จองเลขทันที
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- ============================================================
             5. สรุปข้อมูลหนังสือส่งออก (แทนกระดาษ A4) + ประทับลายเซ็น
        ============================================================ --}}
        @if($document->doc_type === 'outgoing')
            <div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 16px; border: 1px solid #e4eaf0;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold" style="color: var(--primary-dark);">
                        <i class="fas fa-info-circle text-primary me-2"></i>ข้อมูลสรุปหนังสือส่งออก
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">

                        {{-- เลขที่ + วันที่ --}}
                        <div class="col-md-6">
                            <p class="text-label">เลขที่หนังสือ</p>
                            <p class="text-value text-primary">{{ $document->formatted_doc_number ?? 'รอออกเลขและจัดคุมสมุด' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-label">วันที่ลงเอกสาร</p>
                            <p class="text-value">{{ toThaiDate($document->doc_date) }}</p>
                        </div>

                        {{-- เรื่อง + เรียน --}}
                        <div class="col-12">
                            <p class="text-label">เรื่อง</p>
                            <p class="text-value" style="color: var(--primary); font-size: 16px;">{{ $document->title }}</p>
                        </div>
                        <div class="col-12">
                            <p class="text-label">เรียน (ผู้รับ)</p>
                            <p class="text-value">{{ $document->doc_to ?? '-' }}</p>
                        </div>

                        <div class="col-12"><hr class="my-1" style="border-color: #cbd5e1;"></div>

                        {{-- ประเภท + ชั้นความเร็ว + ชั้นความลับ --}}
                        <div class="col-md-4">
                            <p class="text-label">ประเภทหนังสือ</p>
                            <p class="text-value">{{ $document->doc_type_category ?? '-' }}</p>
                        </div>
                        <div class="col-md-4">
                            <p class="text-label">ชั้นความเร็ว</p>
                            <p class="text-value">
                                @if(!empty($document->doc_speed) && $document->doc_speed !== 'ปกติ')
                                    <span class="text-danger fw-bold"><i class="fas fa-bolt me-1"></i>{{ $document->doc_speed }}</span>
                                @else
                                    {{ $document->doc_speed ?? 'ปกติ' }}
                                @endif
                            </p>
                        </div>
                        <div class="col-md-4">
                            <p class="text-label">ชั้นความลับ</p>
                            <p class="text-value">
                                @if(!empty($document->doc_secret) && $document->doc_secret !== 'ปกติ')
                                    <span class="text-danger fw-bold"><i class="fas fa-lock me-1"></i>{{ $document->doc_secret }}</span>
                                @else
                                    {{ $document->doc_secret ?? 'ปกติ' }}
                                @endif
                            </p>
                        </div>

                        {{-- อ้างถึง / สิ่งที่ส่งมาด้วย / หมายเหตุ (แสดงเมื่อมีข้อมูล) --}}
                        @if($document->reference_doc || ($document->content && $document->content !== 'อ้างอิงจากไฟล์แนบในระบบ') || $document->remark)
                            <div class="col-12"><hr class="my-1" style="border-color: #cbd5e1;"></div>
                        @endif

                        @if($document->reference_doc)
                            <div class="col-md-6">
                                <p class="text-label">อ้างถึง</p>
                                <p class="text-value">{{ $document->reference_doc }}</p>
                            </div>
                        @endif

                        @if($document->content && $document->content !== 'อ้างอิงจากไฟล์แนบในระบบ')
                            <div class="col-md-6">
                                <p class="text-label">สิ่งที่ส่งมาด้วย</p>
                                <p class="text-value">{{ str_replace('สิ่งที่ส่งมาด้วย: ', '', $document->content) }}</p>
                            </div>
                        @endif

                        @if($document->remark)
                            <div class="col-12">
                                <p class="text-label">หมายเหตุการค้นหา</p>
                                <p class="text-value">{{ $document->remark }}</p>
                            </div>
                        @endif

                    </div>

                    {{-- 🌟 โซนจัดการหนังสือออก (ประทับลายเซ็น) 🌟 --}}
                    <div class="mt-4 p-3 bg-white rounded-3 border border-primary-subtle text-end shadow-sm">
                        <h6 class="fw-bold text-primary mb-3 text-start"><i class="fas fa-file-export me-1"></i> จัดการฉบับจริง (ประทับลายเซ็น)</h6>
                        
                        @if(empty($document->signed_path))
                            <form id="stampSignatureForm" action="{{ route('documents.stamp_signature', $document->id) }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="pin" id="signaturePinInput">
                                
                                <button type="button" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4" onclick="promptSignaturePin()">
                                    <i class="fas fa-stamp me-1"></i> ประทับลายเซ็นลงเอกสารต้นฉบับ
                                </button>
                            </form>
                        @else
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold me-2">
                                <i class="fas fa-check-circle me-1"></i> ประทับลายเซ็นแล้ว
                            </span>
                            <a href="{{ route('documents.file', [$document->uuid ?? $document->id, 'signed']) }}" target="_blank" class="btn btn-success fw-bold shadow-sm rounded-pill px-4">
                                <i class="fas fa-file-signature me-1"></i> ดูฉบับสมบูรณ์ (มีลายเซ็น)
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================
             7. กระดาษ A4 (เฉพาะบันทึกข้อความภายใน)
        ============================================================ --}}
        @if($document->doc_type === 'internal')
            <div class="card border-0 shadow-sm mb-5 doc-container"
                 style="border-radius: 0; background: #525659; padding: 40px 0;">
                <div class="doc-paper shadow-lg mx-auto bg-white"
                     style="width: 210mm; min-height: 297mm; padding: 20mm 15mm 20mm 25mm; position: relative;">
                    @include('documents.partials.paper')
                </div>
            </div>
        @endif

        {{-- ============================================================
             9. ไฟล์แนบ PDF ฉบับเต็ม
        ============================================================ --}}
        @if($document->attachment_path)
            <div class="card border-0 shadow-sm mb-5 no-print" style="border-radius: 16px;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="fas fa-file-pdf me-2 text-primary"></i>ไฟล์เอกสารแนบในระบบ (ฉบับเต็ม)
                    </h6>
                    <div class="rounded-3 overflow-hidden border shadow-sm">
                        <iframe src="{{ route('documents.file', [$document->uuid ?? $document->id, 'main']) }}" width="100%" height="600px"></iframe>
                    </div>
                </div>
            </div>
        @endif

        @include('documents.partials.dynamic_route_track')

        @if(isset($canApprove) && $canApprove)
            <div class="card border-0 shadow-sm overflow-hidden mb-4 no-print" style="border-radius:16px;border-left:5px solid var(--success) !important;">
                <div class="card-header py-3 border-bottom-0" style="background-color:#f0fdf4;">
                    <h6 class="fw-bold mb-0 text-success"><i class="fas fa-edit me-2"></i>ถึงคิวของคุณ: ส่วนการพิจารณาและอนุมัติ</h6>
                </div>
                <div class="card-body pt-3">
                    @if($document->doc_type === 'outgoing')
                        @include('documents.partials.review_box_outgoing')
                    @else
                        @include('documents.partials.review_box_internal')
                    @endif
                </div>
            </div>
        @elseif(isset($currentRoute) && $currentRoute && !in_array($document->status, ['APPROVED', 'CANCELED', 'REJECTED']))
            <div class="alert alert-warning shadow-sm border-0 mb-4 no-print d-flex align-items-center" style="background-color:#fef3c7;color:#92400e;border-radius:12px;border-left:5px solid #f59e0b !important;">
                <i class="fas fa-hourglass-half fs-4 me-3 text-warning"></i>
                <div><strong>กำลังรอการพิจารณาจาก {{ $currentRoute?->user?->name ?? 'ผู้พิจารณาท่านต่อไป' }}</strong></div>
            </div>
        @endif

         {{-- ============================================================
             8. ดำเนินการสำหรับเจ้าของเรื่อง (DRAFT / REJECTED)
        ============================================================ --}}
        @if(in_array($document->status, ['DRAFT', 'REJECTED']) && $document->created_by === auth()->id())
            <div class="card border-0 shadow-sm mb-4 no-print"
                 style="border-radius: 16px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd;">
                <div class="card-body p-4 text-center">
                    <div class="icon-box mb-3 mx-auto rounded-circle d-flex align-items-center justify-content-center bg-primary text-white shadow-sm"
                         style="width: 60px; height: 60px;">
                        <i class="fas fa-paper-plane fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">ขั้นตอนสุดท้าย: ลงนามส่งเรื่อง</h5>
                    <p class="text-muted small mb-4">ตรวจสอบความถูกต้องก่อนบันทึกลายเซ็นเพื่อส่งให้ฝ่ายบริหารพิจารณา</p>

                    <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                        {{-- ปุ่มแก้ไข --}}
                        <a href="{{ route('documents.edit', $document->id) }}"
                           class="btn btn-warning rounded-pill fw-bold px-4 shadow-sm">
                            <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
                        </a>

                        {{-- ฟอร์มลงนาม --}}
                        <form action="{{ route('documents.sign', $document->id) }}" method="POST"
                              class="d-flex align-items-center gap-2">
                            @csrf
                            <input type="password" name="pin" maxlength="6"
                                   placeholder="รหัส PIN 6 หลัก" required
                                   class="form-control text-center shadow-sm"
                                   style="width: 150px; border-radius: 20px; letter-spacing: 5px; height: 45px;">
                            <button type="submit" class="btn btn-primary rounded-pill fw-bold px-4 shadow-sm" style="height: 45px;">
                                <i class="fas fa-check-circle me-2"></i>ลงนามและส่งเรื่อง
                            </button>
                        </form>

                        {{-- ปุ่มลบ --}}
                        <form action="{{ route('documents.destroy', $document->id) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="btn btn-outline-danger rounded-pill fw-bold px-4 bg-white"
                                    onclick="return confirm('ต้องการลบเอกสารฉบับร่างนี้?')">
                                <i class="fas fa-trash-alt me-1"></i> ลบทิ้ง
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

{{-- ============================================================
     STYLES
============================================================ --}}
<style>
    /* ─── ฟอนต์ไทยสำหรับกระดาษ ─────────────────────────────── */
    @font-face {
        font-family: 'MySarabun';
        src: url('{{ asset("fonts/THSarabunIT๙.ttf") }}') format('truetype');
        font-weight: normal;
    }
    @font-face {
        font-family: 'MySarabun';
        src: url('{{ asset("fonts/THSarabunIT๙_Bold.ttf") }}') format('truetype');
        font-weight: bold;
    }

    /* ─── Helper classes สำหรับ card สรุปหนังสือส่งออก ────────── */
    .text-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 4px;
    }
    .text-value {
        font-size: 15px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0;
    }

    /* ─── กระดาษ A4 ───────────────────────────────────────────── */
    .doc-paper, .doc-paper * {
        font-family: 'MySarabun', sans-serif !important;
        line-height: 1.15 !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    .doc-paper {
        color: #000;
        background-color: white;
        background-image: linear-gradient(to bottom, transparent 296mm, #94a3b8 296mm, #94a3b8 297mm);
        background-size: 100% 297mm;
        box-sizing: border-box;
    }

    /* ─── Print ────────────────────────────────────────────────── */
    @media print {
        @page {
            size: A4;
            margin: 20mm 15mm 20mm 25mm !important;
        }

        html, body, div, main, section, article, header, footer {
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: visible !important;
        }

        body, #app, .container-fluid, .doc-container {
            display: block !important;
            position: static !important;
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
        }

        .no-print, nav, aside, .sidebar, .navbar, .topbar, button {
            display: none !important;
        }

        .card, .doc-container, .doc-paper, .shadow-sm, .shadow-lg {
            box-shadow: none !important;
            border: none !important;
            outline: none !important;
            margin: 0 !important;
            width: 100% !important;
            padding: 0 !important;
            background-image: none !important;
            background-color: white !important;
        }

        .document-content {
            white-space: pre-wrap !important;
            word-break: break-word !important;
            overflow-wrap: break-word !important;
        }

        .signature-block { page-break-inside: avoid !important; }

        img {
            print-color-adjust: exact !important;
            -webkit-print-color-adjust: exact !important;
        }
    }
</style>
@endsection

{{-- ============================================================
     SCRIPTS
============================================================ --}}
@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ─── Flash messages ──────────────────────────────────────────
    @if(session('error_signature'))
        Swal.fire({
            icon: 'warning',
            title: 'ลายเซ็นไม่พร้อม',
            text: '{{ session("error_signature") }}',
            confirmButtonText: 'ไปหน้าตั้งค่า',
            confirmButtonColor: '#0284c7'
        }).then(r => { if (r.isConfirmed) window.location.href = "{{ route('profile.index') }}"; });
    @endif

    @if(session('error'))
        Swal.fire({ icon: 'error', title: 'ไม่สามารถดำเนินการได้', text: '{!! session("error") !!}', confirmButtonColor: '#dc2626' });
    @endif

    @if($errors->any())
        Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ถูกต้อง', text: '{{ $errors->first() }}', confirmButtonColor: '#dc2626' });
    @endif

    @if(session('success'))
        Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '{{ session("success") }}', timer: 2000, showConfirmButton: false });
    @endif
});

// ─── ดึงเลขที่หนังสือล่าสุดอัตโนมัติ ────────────────────────────
async function autoReserveNo() {
    try {
        const docType = '{{ $document->doc_type }}';
        const res  = await fetch(`{{ route('documents.api_next_number') }}?type=${docType}`);
        const data = await res.json();

        document.getElementById('reserve_doc_number').value  = data.formatted;
        document.getElementById('reserve_running_number').value = data.next_number;

        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ดึงเลขล่าสุดสำเร็จ', showConfirmButton: false, timer: 1500 });

    } catch (e) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบรันเลขได้', 'error');
    }
}

// ─── ฟังก์ชันเรียก Pop-up กรอก PIN เพื่อประทับลายเซ็น ─────────────────
function promptSignaturePin() {
    Swal.fire({
        title: 'ยืนยันการลงนามหนังสือออก',
        text: 'กรุณากรอกรหัส PIN 6 หลักของคุณเพื่อประทับลายเซ็นลงบนเอกสารนี้',
        input: 'password',
        inputAttributes: {
            maxlength: 6,
            autocapitalize: 'off',
            autocorrect: 'off',
            pattern: '[0-9]*',
            inputmode: 'numeric',
            placeholder: 'รหัสตัวเลข 6 หลัก'
        },
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9', // สีฟ้า
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-stamp"></i> ยืนยันประทับลายเซ็น',
        cancelButtonText: 'ยกเลิก',
        preConfirm: (pin) => {
            if (!pin || pin.length !== 6 || !/^\d+$/.test(pin)) {
                Swal.showValidationMessage('กรุณากรอกรหัส PIN ให้ครบ 6 หลัก (ตัวเลขเท่านั้น)');
                return false;
            }
            return pin;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // เอารหัส PIN ที่กรอกไปใส่ใน Input ซ่อนของฟอร์ม
            document.getElementById('signaturePinInput').value = result.value;
            
            // แสดงหน้าต่างโหลด
            Swal.fire({
                title: 'กำลังประทับลายเซ็น...',
                html: 'กรุณารอสักครู่ ระบบกำลังสร้างไฟล์ PDF ฉบับสมบูรณ์',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // ส่งฟอร์มไปให้ Controller ทำงาน
            document.getElementById('stampSignatureForm').submit();
        }
    });
}
</script>
@endsection
