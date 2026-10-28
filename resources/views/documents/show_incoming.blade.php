{{-- View: หน้าแสดงรายละเอียดหนังสือรับเข้า --}}
@extends('layouts.app')
@section('title', 'รายละเอียดหนังสือรับเข้า')

@section('content')
<div class="container-fluid px-3 px-lg-4 py-3 incoming-detail-page">
    <div class="mx-auto" style="max-width: 1200px;">

        {{-- 🌟 Header --}}
        <div class="incoming-page-heading d-flex justify-content-between align-items-center gap-3 mb-3 no-print">
            <div>
                <div class="incoming-page-heading__eyebrow mb-1">งานสารบรรณอิเล็กทรอนิกส์</div>
                <h3 class="fw-bold mb-1" style="color: var(--primary-dark);">
                    <i class="fas fa-file-import text-primary me-2"></i>รายละเอียดหนังสือรับเข้า
                </h3>
                <small class="text-muted">ข้อมูล เอกสารแนบ และสถานะการดำเนินงานอยู่ในหน้าเดียว</small>
            </div>
            <div class="d-flex align-items-center justify-content-end flex-wrap gap-2">
                <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
                
                {{-- 🌟 ปุ่มพิมพ์ใบเกษียน (โชว์เฉพาะตอนอนุมัติแล้ว) - อัปเดตให้ลิงก์ไปหน้า Routing Slip --}}
                @if($document->status === 'APPROVED' && $document->assigned_to)
                <a href="{{ route('documents.routing_slip', $document->uuid ?? $document->id) }}" target="_blank" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm fw-bold">
                    <i class="fas fa-print me-1"></i> พิมพ์ใบเกษียน
                </a>
                @endif

                <a href="{{ route('documents.approve_list') }}" class="ds-back-link">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้ารายการ
                </a>
            </div>
        </div>

        <div class="incoming-timeline-shell mb-3">
            @include('documents.partials.status_timeline')
        </div>

        @php
            $user = auth()->user();
            
            $isReviewer = (isset($canApprove) && $canApprove) || (
                ($user->hasRole('saraban') && in_array($document->status, ['WAITING_ADMIN', 'WAITING_NUMBERING'])) ||
                ($user->hasRole('head')      && $document->status === 'WAITING_SUPERVISOR') ||
                ($user->hasRole('palad') && $document->status === 'WAITING_PALAD') ||
                ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK')
            );
            
            // 🌟 แก้ไขปัญหาสีป้ายสถานะกลืนกับพื้นหลัง โดยใช้สีทึบ (Solid) และบังคับสีข้อความให้ตัดกันชัดเจน
            $badges = [
                'DRAFT'              => ['bg-secondary', 'text-white', 'ฉบับร่าง'],
                'REGISTERED'         => ['bg-info', 'text-dark', 'รับเรื่องแล้ว'],
                'IN_REVIEW'          => ['bg-warning', 'text-dark', 'อยู่ระหว่างพิจารณา'],
                'WAITING_REVIEWER'   => ['bg-warning', 'text-dark', 'รอผู้ตรวจสอบ'],
                'WAITING_APPROVER'   => ['bg-primary', 'text-white', 'รออนุมัติ'],
                'WAITING_ADMIN'      => ['bg-secondary', 'text-white', 'รอธุรการรับเรื่อง'],
                'WAITING_SUPERVISOR' => ['bg-warning', 'text-dark', 'รอหัวหน้าสำนักปลัด'],
                'WAITING_PALAD'      => ['bg-primary', 'text-white', 'รอปลัด อบต.'],
                'WAITING_NAYOK'      => ['bg-info', 'text-dark', 'รอนายกฯ อนุมัติ'], 
                'WAITING_NUMBERING'  => ['bg-success', 'text-white', 'ดำเนินการเสร็จสิ้น'],
                'APPROVED'           => ['bg-success', 'text-white', 'อนุมัติแล้ว / เสร็จสิ้น'],
                'COMPLETED'          => ['bg-success', 'text-white', 'ดำเนินการเสร็จสิ้น'],
                'ARCHIVED'           => ['bg-dark', 'text-white', 'จัดเก็บแล้ว'],
                'REJECTED'           => ['bg-danger', 'text-white', 'ถูกตีกลับ / แก้ไข'],
                'CANCELED'           => ['bg-dark', 'text-white', 'ยกเลิกเอกสาร'], 
            ];
            $b = $badges[$document->status] ?? ['bg-light', 'text-dark', $document->status];
            if ($document->isAtFinalApprovalStep()) {
                $b = ['bg-primary', 'text-white', 'รออนุมัติ'];
            }
            
            // ข้อมูลเส้นทางเดินเอกสาร
            $sigSteps = [
                ['label'=>'ผู้เสนอเรื่อง',       'sig'=>$document->creator_signature,    'name'=>$document->creator->name ?? '-',    'pos'=>$document->creator->position ?? 'เจ้าหน้าที่ธุรการ',       'at'=>$document->created_at?->format('d/m/Y H:i'),                                       'icon'=>'fa-user'],
                ['label'=>'หัวหน้าสำนักปลัด',    'sig'=>$document->supervisor_signature, 'name'=>$document->supervisor->name ?? '-', 'pos'=>$document->supervisor->position ?? 'หัวหน้าสำนักปลัด อบต.', 'at'=>$document->supervisor_approved_at ? \Carbon\Carbon::parse($document->supervisor_approved_at)->format('d/m/Y H:i') : null, 'icon'=>'fa-user-tie'],
                ['label'=>'ปลัด อบต.',           'sig'=>$document->palad_signature,      'name'=>$document->palad->name ?? '-',      'pos'=>$document->palad->position ?? 'ปลัด อบต.',               'at'=>$document->palad_approved_at      ? \Carbon\Carbon::parse($document->palad_approved_at)->format('d/m/Y H:i')      : null, 'icon'=>'fa-user-shield'],
                ['label'=>'นายก อบต.',           'sig'=>$document->nayok_signature,      'name'=>$document->nayok->name ?? '-',      'pos'=>$document->nayok->position ?? 'นายก อบต.',               'at'=>$document->nayok_approved_at      ? \Carbon\Carbon::parse($document->nayok_approved_at)->format('d/m/Y H:i')      : null, 'icon'=>'fa-star'],
            ];

            // 🌟 ตัวช่วยจัดการ URL ลายเซ็นแบบครอบจักรวาล ป้องกัน Error (Malformed HTTP request)
            // หน้าที่: ประมวลผล callback ของส่วนนี้
            if (!function_exists('getSigUrl')) {
                // หน้าที่: สร้าง URL สำหรับแสดงรูปภาพลายเซ็น
                function getSigUrl($path) {
                    if (empty($path)) return '';
                    $path = trim($path); // ตัดช่องว่างซ่อนเร้น
                    
                    if (str_starts_with($path, 'data:image')) return $path;
                    if (preg_match('/^https?:\/\//', $path)) return $path;
                    
                    $path = preg_replace('/^(public|storage)\//', '', ltrim($path, '/'));
                    
                    return asset('storage/' . $path);
                }
            }

            // 🌟 ฟังก์ชันแปลงเลขไทย
            // หน้าที่: ประมวลผล callback ของส่วนนี้
            if (!function_exists('toThaiNum')) {
                // หน้าที่: แปลงตัวเลขอารบิกเป็นเลขไทยสำหรับแสดงในเอกสาร
                function toThaiNum($string) {
                    if (!$string) return '';
                    $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                    $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
                    return str_replace($arabic, $thai, $string);
                }
            }
        @endphp

        <nav class="incoming-section-nav no-print mb-4" aria-label="เมนูภายในหน้ารายละเอียดเอกสาร">
            <a href="#document-overview"><i class="fas fa-info-circle"></i>ข้อมูลเอกสาร</a>
            <a href="#document-attachment"><i class="fas fa-paperclip"></i>เนื้อหาและไฟล์แนบ</a>
            <a href="#document-routing"><i class="fas fa-route"></i>เส้นทางเอกสาร</a>
            @if($isReviewer)
                <a href="#document-action"><i class="fas fa-edit"></i>ดำเนินการ</a>
            @endif
        </nav>

        {{-- 🌟 กล่องแสดงข้อมูล: เลขเสีย (CANCELED) --}}
        @if($document->status === 'CANCELED')
            <div class="mb-4 no-print">
                <div class="alert alert-dark border-0 shadow-sm d-flex align-items-center" style="border-left: 5px solid #1e293b !important; border-radius: 12px;">
                    <i class="fas fa-ban fs-3 text-secondary me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">หนังสือรับเข้าฉบับนี้ถูกยกเลิก</h6>
                        <span class="small text-muted"><strong>เหตุผล:</strong> {{ $document->reject_reason ?? 'ผู้บริหารไม่อนุมัติ / ยกเลิกคำสั่งการ' }}</span>
                    </div>
                </div>
            </div>
        @endif

        @php
            $activeAssignmentStatuses = ['pending', 'accepted', 'delegated', 'in_progress', 'completed'];
            $isV2Assignment = config('edoc.v2.document_reads');
            $v2ActiveAssignments = $isV2Assignment
                ? $document->assignments->filter(fn ($assignment) => in_array(strtoupper((string) $assignment->status->value), ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED'], true))
                : collect();
            $v2UserAssignment = $v2ActiveAssignments->where('assigned_user_id', $user->id)->sortByDesc('id')->first();
            $v2UnitAssignment = $v2ActiveAssignments->first(fn ($assignment) => !$assignment->assigned_user_id
                && $assignment->unit
                && $assignment->unit->name === $user->department);
            $assignmentIsFinalized = in_array($document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true)
                && ($isV2Assignment ? $v2ActiveAssignments->isNotEmpty() : in_array($document->assignment_status, $activeAssignmentStatuses, true));
            $canHandleAssignment = (!config('edoc.v2.document_reads') || config('edoc.v2.write_enabled'))
                && $assignmentIsFinalized
                && ($isV2Assignment
                    ? ($v2UserAssignment || ($user->hasRole('head') && $v2UnitAssignment))
                    : ($document->assigned_user_id === $user->id
                        || ($user->hasRole('head') && !$document->assigned_user_id && $document->assigned_to === $user->department)));
            $assignmentAccepted = $isV2Assignment
                ? in_array(strtoupper((string) ($v2UserAssignment?->status->value ?? '')), ['ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED'], true)
                : $document->assignment_status === 'accepted';
            $canDelegateAssignment = $canHandleAssignment && $user->hasRole('head');
            $departmentUsers = $canDelegateAssignment
                ? \App\Models\User::where('department', $user->department)->where('id', '!=', $user->id)->orderBy('name')->get()
                : collect();
            $deptUsersJson = $departmentUsers->map(function($u) {
                return [
                    'id' => (string) $u->id,
                    'n' => (string) $u->name,
                    'r' => (string) ($u->position ?? ''),
                ];
            })->values();
            $isAssignmentIssuer = $isV2Assignment && $v2ActiveAssignments->contains(fn ($assignment) =>
                (int) $assignment->assigned_by === (int) $user->id
                || (int) $assignment->delegated_by === (int) $user->id
            );
            $trackableAssignments = $isV2Assignment
                ? ($isAssignmentIssuer
                    ? $v2ActiveAssignments->filter(fn ($assignment) => (int) $assignment->assigned_by === (int) $user->id || (int) $assignment->delegated_by === (int) $user->id)
                    : $v2ActiveAssignments->where('assigned_user_id', $user->id))
                : collect();
        @endphp
        @if($document->status === 'APPROVED' && $canHandleAssignment && !$assignmentAccepted)
            <section class="delegate-assignment-card no-print mb-4">
                <h2>
                    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor">
                        <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5H2Zm4.5.25a.75.75 0 0 0 0 1.5h8a.75.75 0 0 0 0-1.5h-8Zm0 4.5a.75.75 0 0 0 0 1.5h8a.75.75 0 0 0 0-1.5h-8Zm0 4.5a.75.75 0 0 0 0 1.5h8a.75.75 0 0 0 0-1.5h-8ZM1.5 7.5A.5.5 0 0 1 2 7h1a.5.5 0 0 1 .5.5v1A.5.5 0 0 1 3 9H2a.5.5 0 0 1-.5-.5v-1Zm.5 4a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H2Z"/>
                    </svg>
                    งานที่นายกมอบหมายให้ {{ $document->assigned_to }}
                </h2>
                <p class="sub">เลือกรับดำเนินการด้วยตนเอง หรือเลือกส่งต่อให้บุคลากรในฝ่ายเดียวกันได้หลายคน</p>

                <div class="row">
                    <form action="{{ route('documents.assignment_action', $document->uuid ?? $document->id) }}" method="POST" class="d-inline-flex m-0">
                        @csrf
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="btn g" id="take">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M13.485 3.929a1 1 0 0 1 0 1.414l-6.01 6.01a1 1 0 0 1-1.414 0L2.515 7.808a1 1 0 1 1 1.414-1.414l2.84 2.84 5.303-5.304a1 1 0 0 1 1.414 0Z"/></svg>
                            รับงาน
                        </button>
                    </form>

                    @if($departmentUsers->isNotEmpty())
                    <form action="{{ route('documents.assignment_action', $document->uuid ?? $document->id) }}" method="POST" class="delegate-form-row m-0" id="delegateForm">
                        @csrf
                        <input type="hidden" name="action" value="delegate">
                        <div id="delegateHiddenInputs"></div>

                        <div class="dd" id="dd">
                            <button type="button" class="trigger" id="tg" aria-haspopup="listbox" aria-expanded="false"></button>
                            <div class="panel" role="listbox" aria-multiselectable="true">
                                <div class="search"><input id="q" type="search" placeholder="ค้นหาชื่อหรือตำแหน่ง" autocomplete="off"></div>
                                <label class="opt all"><input type="checkbox" id="all">เลือกทั้งหมด</label>
                                <div class="list" id="ls"></div>
                                <div class="empty" id="em">ไม่พบบุคลากรที่ค้นหา</div>
                            </div>
                        </div>

                        <button type="submit" class="btn b" id="fw" disabled>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M9.5 2.5a.5.5 0 0 1 .82-.38l5 4.25a.5.5 0 0 1 0 .76l-5 4.25a.5.5 0 0 1-.82-.38V8.5C5.6 8.4 3.3 9.6 2 12.5c0-4 1.8-7.4 7.5-7.9V2.5Z"/></svg>
                            <span id="fl">ส่งต่อให้ผู้ที่เลือก</span>
                        </button>
                    </form>
                    @endif
                </div>
            </section>
        @endif

        @if($isV2Assignment && $trackableAssignments->isNotEmpty())
            <section class="assignment-progress-ui no-print mb-4">
                <div class="assignment-progress-ui__heading">
                    <h5><i class="fas fa-chart-line"></i>{{ $isAssignmentIssuer ? 'ติดตามความคืบหน้างานที่มอบหมาย' : 'ความคืบหน้างานที่ได้รับมอบหมาย' }}</h5>
                    <p>ผู้รับงานบันทึกสถานะและรายละเอียดล่าสุดไว้ที่นี่</p>
                </div>
                <div class="assignment-progress-ui__recipients">
                        @foreach($trackableAssignments as $trackedAssignment)
                            @php
                                $trackedStatus = strtolower((string) $trackedAssignment->status->value);
                                $latestProgress = $trackedAssignment->progressUpdates->first();
                                $progressPercent = $latestProgress?->progress_percent;
                                $receivedAt = $trackedAssignment->acknowledged_at ?: $trackedAssignment->assigned_at;
                                $statusLabel = match ($trackedStatus) {
                                    'completed' => 'ดำเนินการเสร็จสิ้น',
                                    'in_progress' => 'กำลังดำเนินการ',
                                    'acknowledged' => 'รับดำเนินการแล้ว',
                                    default => 'รอรับดำเนินการ',
                                };
                            @endphp
                            <article class="assignment-progress-ui__who">
                                <div>
                                    <strong>{{ $trackedAssignment->assignee?->name ?? $trackedAssignment->unit?->name ?? 'รอระบุผู้รับ' }}</strong>
                                    <small>{{ $receivedAt ? 'รับงานเมื่อ '.$receivedAt->format('d/m/Y H:i') : 'ผู้รับงานยังไม่ได้รับงาน' }}</small>
                                    @if($latestProgress)
                                        <span class="assignment-progress-ui__latest">{{ $latestProgress->note ?: 'ไม่มีรายละเอียดเพิ่มเติม' }}</span>
                                        @if($latestProgress->attachment_path)
                                            <a href="{{ route('documents.assignment_progress_attachment', [$document->uuid ?? $document->id, $latestProgress->id]) }}"><i class="fas fa-paperclip"></i>{{ $latestProgress->attachment_original_name ?: 'ดาวน์โหลดไฟล์แนบ' }}</a>
                                        @endif
                                    @endif
                                </div>
                                <span class="assignment-progress-ui__badge assignment-progress-ui__badge--{{ $trackedStatus }}">{{ $statusLabel }}{{ $progressPercent !== null ? ' · '.$progressPercent.'%' : '' }}</span>
                            </article>
                        @endforeach
                </div>

                    @if(config('edoc.v2.write_enabled') && $v2UserAssignment && in_array(strtolower((string) $v2UserAssignment->status->value), ['acknowledged', 'in_progress'], true))
                        @php
                            $currentProgress = $v2UserAssignment->progressUpdates->first()?->progress_percent ?? 0;
                            $currentStatus = strtolower((string) $v2UserAssignment->status->value);
                        @endphp
                        <form action="{{ route('documents.assignment_progress', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data" class="assignment-progress-ui__form" data-assignment-progress-form>
                            @csrf
                            <input type="hidden" name="assignment_id" value="{{ $v2UserAssignment->id }}">
                            <div class="assignment-progress-ui__fields">
                                <div>
                                    <label for="assignment_progress_status">สถานะ</label>
                                    <select id="assignment_progress_status" name="status" class="assignment-progress-ui__control" data-progress-status required>
                                        <option value="in_progress" {{ $currentStatus === 'in_progress' ? 'selected' : '' }}>กำลังดำเนินการ</option>
                                        <option value="completed">ดำเนินการเสร็จสิ้น</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="assignment_progress_percent">ความคืบหน้า (%)</label>
                                    <div class="assignment-progress-ui__percent"><input id="assignment_progress_percent" name="progress_percent" type="number" class="assignment-progress-ui__control" min="0" max="100" value="{{ $currentProgress }}" required data-progress-number><em>%</em></div>
                                    <input type="range" min="0" max="100" step="5" value="{{ $currentProgress }}" aria-label="ความคืบหน้า" data-progress-range>
                                </div>
                                <div>
                                    <label for="assignment_progress_note">รายละเอียด</label>
                                    <input id="assignment_progress_note" name="progress_note" type="text" class="assignment-progress-ui__control" maxlength="2000" placeholder="ระบุงานที่ดำเนินการ อุปสรรค หรือผลลัพธ์">
                                </div>
                            </div>
                            <div class="assignment-progress-ui__upload-row">
                                <div>
                                    <label>ไฟล์แนบ (ถ้ามี)</label>
                                    <label class="assignment-progress-ui__file" for="assignment_progress_attachment">
                                        <input id="assignment_progress_attachment" name="progress_attachment" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" data-progress-file>
                                        <span>เลือกไฟล์</span><i><b data-progress-file-name>ยังไม่ได้เลือกไฟล์</b><button type="button" aria-label="ลบไฟล์" data-progress-file-clear>&times;</button></i>
                                    </label>
                                    <small>PDF, Word, Excel หรือรูปภาพ ไม่เกิน 10 MB</small>
                                </div>
                                <button class="assignment-progress-ui__save" type="submit"><i class="fas fa-floppy-disk"></i>บันทึก</button>
                            </div>
                        </form>
                    @endif
            </section>
        @endif

        {{-- 🌟 Main Card (ข้อมูลเอกสาร) --}}
        <div class="card border-0 mb-4 shadow-sm print-container incoming-document-card" id="document-overview">

            {{-- Hero Section --}}
            <div class="incoming-document-hero d-flex justify-content-between align-items-start flex-wrap gap-3 px-3 px-md-4 py-4">
                <div class="d-flex align-items-start gap-3 incoming-document-hero__content">
                    <div class="incoming-document-hero__icon" aria-hidden="true"><i class="fas fa-envelope-open-text"></i></div>
                    <div>
                        <div class="incoming-document-hero__meta">หนังสือรับเข้า · เลขที่เอกสาร <strong>{{ $document->doc_number ?? '-' }}</strong></div>
                        <h4 class="mb-1 fw-bold incoming-document-hero__title">{{ $document->title }}</h4>
                        <div class="small text-muted">รับเข้าระบบ {{ $document->created_at ? $document->created_at->locale('th')->translatedFormat('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
                <span class="badge {{ $b[0] }} {{ $b[1] }} border rounded-pill px-3 py-2 fw-bold shadow-sm" style="font-size:14px;">{{ $b[2] }}</span>
            </div>

            <div class="p-4 bg-white">

                {{-- กล่องแสดงเหตุผลการตีกลับ --}}
                @if($document->status === 'REJECTED')
                <div class="mb-4 p-3 rounded-3 shadow-sm no-print" style="background: var(--danger-bg); border: 1px solid var(--danger-border); border-left: 4px solid var(--danger-text);">
                    <div class="d-flex gap-3 align-items-start">
                        <div style="font-size: 24px; color: var(--danger-text);"><i class="fas fa-times-circle"></i></div>
                        <div>
                            <div style="font-size: 14px; font-weight: 700; color: var(--danger-text); margin-bottom: 4px;">เอกสารฉบับนี้ถูกตีกลับ / ให้แก้ไข</div>
                            <div style="font-size: 14.5px; color: #7f1d1d;"><strong>เหตุผล:</strong> {{ $document->reject_reason ?? 'ไม่มีการระบุเหตุผลเพิ่มเติม' }}</div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ข้อมูลทั่วไป (หนังสือรับเข้า) --}}
                @php
                    $infoItems = [
                        ['เลขที่รับ', $document->formatted_receive_number ?? '-', 'fa-hashtag'],
                        ['วันที่รับ', $document->receive_date ? \Carbon\Carbon::parse($document->receive_date)->addYears(543)->format('d/m/Y') : '-', 'fa-calendar-check'],
                        ['ลงวันที่บนเอกสาร', $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->addYears(543)->format('d/m/Y') : '-', 'fa-calendar-day'],
                        ['ประเภทหนังสือ', $document->doc_type_category ?? '-', 'fa-file-alt'],
                        ['จาก (หน่วยงาน/บุคคล)', $document->doc_from ?? '-', 'fa-building'],
                        ['ชั้นความเร็ว', $document->doc_speed ?? 'ปกติ', 'fa-tachometer-alt'],
                        ['ชั้นความลับ', $document->doc_secret ?? 'ไม่มีชั้นความลับ', 'fa-shield-alt'],
                    ];
                @endphp
                <div class="mb-4 no-print">
                    <div class="incoming-section-heading mb-3">
                        <div class="incoming-section-heading__icon"><i class="fas fa-info-circle"></i></div>
                        <div>
                            <h5 class="mb-0 fw-bold">ข้อมูลเอกสาร</h5>
                            <div class="small text-muted">รายละเอียดการรับและข้อมูลจากหนังสือต้นทาง</div>
                        </div>
                    </div>
                    <div class="incoming-info-grid">
                        @foreach($infoItems as $item)
                        <div class="incoming-info-item">
                            <div class="incoming-info-item__icon"><i class="fas {{ $item[2] }}"></i></div>
                            <div class="min-w-0">
                                <div class="incoming-info-item__label">{{ $item[0] }}</div>
                                <div class="incoming-info-item__value">{{ $item[1] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    {{-- 🌟 ส่วนแสดงการมอบหมายงาน --}}
                    @if($document->assigned_to)
                    <div class="incoming-assignment mt-3">
                        <div class="incoming-assignment__icon"><i class="fas fa-share-square"></i></div>
                        <div>
                            <div class="small text-muted fw-semibold">{{ $assignmentIsFinalized ? 'มอบหมายให้ส่วนราชการ' : 'ข้อเสนอการมอบหมาย (รออนุมัติขั้นสุดท้าย)' }}</div>
                            <div class="fw-bold">{{ $document->assigned_to }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- โซนเนื้อหา และ ไฟล์แนบ --}}
                <div class="mb-4 no-print incoming-scroll-target" id="document-attachment">
                    <div class="incoming-section-heading mb-3">
                        <div class="incoming-section-heading__icon"><i class="fas fa-paperclip"></i></div>
                        <div>
                            <h5 class="mb-0 fw-bold">เนื้อหาและไฟล์แนบ</h5>
                            <div class="small text-muted">ตรวจสอบข้อความและเอกสารต้นฉบับที่แนบมากับเรื่อง</div>
                        </div>
                    </div>
                    
                    <div class="rounded-3 p-4" style="background:var(--bg-page);border:1px solid #edf0f4;">
                        @if($document->content && $document->content !== 'อ้างอิงจากไฟล์แนบในระบบ')
                            <div style="font-size:15px;line-height:1.85;color:var(--text-primary);">
                                {!! nl2br(e($document->content)) !!}
                            </div>
                        @endif

                        @if($document->attachment_path)
                            @if($document->content && $document->content !== 'อ้างอิงจากไฟล์แนบในระบบ')
                                <hr class="my-4 no-print" style="border-color: #cbd5e1;">
                            @endif
                            @php
                                $attachmentUrl = route('documents.file', [$document->uuid ?? $document->id, 'main']);
                                $attachmentExtension = strtolower(pathinfo($document->attachment_path, PATHINFO_EXTENSION));
                                $attachmentIsImage = in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
                            @endphp
                            <div class="document-attachment-preview no-print">
                                @if($attachmentIsImage)
                                    <img src="{{ $attachmentUrl }}"
                                         alt="ตัวอย่างไฟล์แนบ {{ $document->title }}"
                                         class="document-attachment-preview__image">
                                @else
                                    <iframe src="{{ $attachmentUrl }}#toolbar=1&navpanes=0&view=FitH"
                                            title="ตัวอย่างไฟล์แนบ {{ $document->title }}"
                                            class="document-attachment-preview__frame"
                                            loading="lazy"></iframe>
                                @endif
                            </div>
                            <div class="text-center mt-3 no-print">
                                <a href="{{ $attachmentUrl }}" target="_blank"
                                   class="btn rounded-pill px-4 fw-bold shadow-sm bg-white" style="color:var(--primary-dark);border:1px solid #cbd5e1;font-size:14px;">
                                    <i class="fas fa-external-link-alt me-2"></i>เปิดดูไฟล์ฉบับเต็ม
                                </a>
                            </div>
                        @endif

                        @if($document->external_url)
                            <hr class="my-4 no-print" style="border-color:#cbd5e1;">
                            <div class="bg-white border rounded-3 p-3 p-md-4 no-print">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                                    <div>
                                        <div class="fw-bold" style="color:var(--primary-dark);">
                                            <i class="fas fa-qrcode me-2 text-success"></i>เอกสารสิ่งที่ส่งมาด้วยจาก QR Code
                                        </div>
                                        <div class="small text-muted mt-1">เก็บลิงก์ต้นฉบับไว้สำหรับตรวจสอบย้อนหลัง</div>
                                    </div>
                                    <a href="{{ $document->external_url }}" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-sm btn-outline-success rounded-pill fw-bold px-3">
                                        <i class="fas fa-up-right-from-square me-1"></i>เปิดลิงก์ต้นฉบับ
                                    </a>
                                </div>

                                @if($document->external_attachment_path)
                                    @php
                                        $qrArchiveUrl = route('documents.file', [$document->uuid ?? $document->id, 'external']);
                                        $qrIsImage = str_starts_with((string) $document->external_mime_type, 'image/');
                                        $qrIsPdf = $document->external_mime_type === 'application/pdf';
                                    @endphp

                                    @if($qrIsImage)
                                        <div class="text-center rounded-3 border bg-light p-2">
                                            <img src="{{ $qrArchiveUrl }}" alt="เอกสารจาก QR Code" style="max-width:100%;max-height:800px;object-fit:contain;">
                                        </div>
                                    @elseif($qrIsPdf)
                                        <div class="ratio ratio-16x9 rounded-3 overflow-hidden shadow-sm border">
                                            <iframe src="{{ $qrArchiveUrl }}" title="เอกสารจาก QR Code"></iframe>
                                        </div>
                                    @else
                                        <div class="alert alert-info mb-3">
                                            ระบบเก็บสำเนาไฟล์แล้ว แต่ไฟล์ชนิดนี้ไม่รองรับการแสดงตัวอย่างในเบราว์เซอร์
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-3">
                                        <div class="small text-muted">
                                            <div><strong>ไฟล์:</strong> {{ $document->external_original_name ?: basename($document->external_attachment_path) }}</div>
                                            <div><strong>เก็บเมื่อ:</strong> {{ optional($document->external_downloaded_at)->locale('th')->translatedFormat('d M Y H:i') }}</div>
                                            <div><strong>ขนาด:</strong> {{ number_format(($document->external_file_size ?? 0) / 1048576, 2) }} MB</div>
                                            <div class="text-break"><strong>SHA-256:</strong> <code>{{ $document->external_sha256 }}</code></div>
                                        </div>
                                        <a href="{{ $qrArchiveUrl }}" target="_blank" class="btn btn-primary rounded-pill fw-bold px-4">
                                            <i class="fas fa-file-arrow-down me-1"></i>เปิดสำเนาที่เก็บในระบบ
                                        </a>
                                    </div>
                                @else
                                    @if($document->external_download_error)
                                        <div class="alert alert-warning mb-0">
                                            <div class="fw-bold"><i class="fas fa-triangle-exclamation me-1"></i>ยังเก็บสำเนาจากลิงก์ไม่ได้</div>
                                            <div class="small mt-1">{{ $document->external_download_error }}</div>
                                        </div>
                                    @else
                                        <div class="alert alert-info mb-0">
                                            <div class="fw-bold"><i class="fas fa-circle-notch fa-spin me-1"></i>กำลังเก็บสำเนาเอกสาร</div>
                                            <div class="small mt-1">ระบบรับลิงก์แล้วและกำลังดาวน์โหลดสำเนา กรุณารีเฟรชหน้านี้อีกครั้ง</div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif

                        @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
                            <hr class="my-4 no-print" style="border-color: #cbd5e1;">
                            <div class="bg-white p-3 rounded-3 border shadow-sm no-print">
                                <form id="manual_qr_attachment_form" action="{{ route('documents.upload', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap align-items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="attachment_context" value="external_qr">
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-bold text-muted mb-2">
                                            <i class="fas fa-paperclip me-1"></i> แนบสำเนาเอกสารที่ดาวน์โหลดจากลิงก์ QR (PDF, JPG, PNG)
                                        </label>
                                        <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="border-radius: 8px;">
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:var(--primary);">
                                        <i class="fas fa-upload me-1"></i> อัปโหลดไฟล์
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

                <div id="document-routing" class="incoming-scroll-target">
                {{-- 🌟 โซนพรีวิวใบเกษียนหนังสือรับเข้า (ดึงมาจากหน้า Routing Slip โดยตรง) 🌟 --}}
                @if($document->assigned_to)
                <div class="card border-0 shadow-sm mb-5 no-print" style="border-radius: 16px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-file-signature text-primary me-2"></i>พรีวิวใบเกษียนหนังสือ</h6>
                        <a href="{{ route('documents.routing_slip', $document->uuid ?? $document->id) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3">
                            <i class="fas fa-external-link-alt me-1"></i> เปิดดูเต็มจอ
                        </a>
                    </div>
                    <div class="card-body p-0 bg-secondary">
                        <iframe src="{{ route('documents.routing_slip', $document->uuid ?? $document->id) }}?preview=1" width="100%" height="850px" style="border: none;"></iframe>
                    </div>
                </div>
                @endif

                {{-- เมื่อมอบหมายงานและอนุมัติครบแล้ว ให้ผู้รับงานเห็นเฉพาะใบเกษียน
                     ไม่แสดงเส้นทางนำส่ง/พิจารณาภายในอีกต่อไป --}}
                @if(! $assignmentIsFinalized)
                    @include('documents.partials.dynamic_route_track')
                @endif
                </div>

                @if($isReviewer)
                    <div class="no-print mt-4 mb-4 incoming-scroll-target" id="document-action">
                        @include('documents.partials.review_box_incoming')
                    </div>
                @endif

            </div>
        </div>

        {{-- Draft Sign & Edit (สำหรับคนสร้าง) --}}
        @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
        @php
            $requiresQrManualAttachment = $document->doc_type === 'incoming'
                && $document->external_url
                && !$document->attachment_path
                && !$document->external_attachment_path;
        @endphp
        <div class="text-center mt-5 p-4 rounded-3 shadow-sm no-print" style="background:var(--primary-light);border:1px solid var(--primary-border);">
            <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width:55px;height:55px;background:var(--primary);font-size:22px;">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h5 class="fw-bold mb-1" style="color:var(--primary-dark);">ขั้นตอนสุดท้าย: ยืนยันการส่งเรื่อง</h5>
            @if($requiresQrManualAttachment)
                <div class="alert alert-warning text-start mx-auto mb-4" style="max-width:760px;">
                    <div class="fw-bold"><i class="fas fa-download me-1"></i>ต้องแนบไฟล์ก่อนส่งเรื่อง</div>
                    <div class="small mt-1">ระบบดาวน์โหลดเอกสารจากลิงก์นี้อัตโนมัติไม่ได้ กรุณาเปิดลิงก์ต้นฉบับ ดาวน์โหลดไฟล์ แล้วนำไฟล์มาแนบก่อน ระบบจึงจะส่งต่อตามลำดับได้</div>
                    <div class="d-flex gap-2 flex-wrap mt-3">
                        <a href="{{ $document->external_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-dark rounded-pill fw-bold">
                            <i class="fas fa-up-right-from-square me-1"></i>เปิดลิงก์เพื่อดาวน์โหลด
                        </a>
                        <button type="button" class="btn btn-sm btn-warning rounded-pill fw-bold" onclick="document.getElementById('manual_qr_attachment_form')?.scrollIntoView({behavior:'smooth', block:'center'})">
                            <i class="fas fa-paperclip me-1"></i>ไปยังช่องแนบไฟล์
                        </button>
                    </div>
                </div>
            @else
                <p class="mb-4" style="font-size:14px;color:var(--primary);">กรอกรหัส PIN 6 หลักเพื่อส่งเอกสารรับเข้าสู่ระบบ</p>
            @endif
            <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                <a href="{{ route('documents.edit', $document->uuid ?? $document->id) }}" class="btn rounded-pill fw-bold px-4 shadow-sm" style="background:#fef08a; border:2px solid #fde047; color:#854d0e; padding:10px 22px; font-size:14px;">
                    <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
                </a>
                <form action="{{ route('documents.destroy', $document->uuid ?? $document->id) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn rounded-pill fw-bold px-4 bg-white" style="border:2px solid #fca5a5;color:#dc2626;padding:10px 22px;font-size:14px;"
                            onclick="return confirm('ต้องการลบเอกสารนี้?\n(ข้อมูลและไฟล์แนบจะถูกลบถาวร)')">
                        <i class="fas fa-trash-alt me-2"></i>ลบทิ้ง
                    </button>
                </form>
                @if(!$requiresQrManualAttachment)
                <form action="{{ route('documents.sign', $document->uuid ?? $document->id) }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap ms-md-3">
                    @csrf
                    <input type="password" name="pin" maxlength="6" placeholder="• • • • • •" required autocomplete="off"
                           style="border:2px solid var(--primary-border);border-radius:10px;padding:10px 16px;font-size:18px;letter-spacing:10px;text-align:center;width:160px;outline:none;background:#fff;"
                           onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--primary-border)'">
                    <button type="submit" class="btn rounded-pill fw-bold text-white px-4 shadow-sm" style="background:var(--primary);border:none;padding:12px 28px;font-size:15px;white-space:nowrap;">
                        <i class="fas fa-paper-plane me-2"></i>ส่งเรื่องทันที
                    </button>
                </form>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>

<style>
    .incoming-detail-page {
        --incoming-blue: #087fb9;
        --incoming-blue-dark: #075985;
        --incoming-blue-soft: #ecf8fd;
        --incoming-border: #dce8ee;
    }
    .incoming-page-heading {
        padding: 8px 2px;
    }
    .incoming-page-heading__eyebrow {
        color: var(--incoming-blue);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
    }
    .incoming-timeline-shell > * {
        margin-bottom: 0 !important;
    }
    .incoming-section-nav {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow-x: auto;
        padding: 8px;
        background: #fff;
        border: 1px solid var(--incoming-border);
        border-radius: 14px;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .05);
        scrollbar-width: thin;
    }
    .incoming-section-nav a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        flex: 0 0 auto;
        padding: 9px 14px;
        border-radius: 10px;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: background-color .18s ease, color .18s ease, transform .18s ease;
    }
    .incoming-section-nav a:hover,
    .incoming-section-nav a:focus-visible {
        color: var(--incoming-blue-dark);
        background: var(--incoming-blue-soft);
        transform: translateY(-1px);
        outline: none;
    }
    .incoming-document-card {
        overflow: hidden;
        border: 1px solid var(--incoming-border) !important;
        border-radius: 18px !important;
    }
    .incoming-document-hero {
        background:
            radial-gradient(circle at 95% 10%, rgba(14, 165, 233, .13), transparent 34%),
            linear-gradient(135deg, #f8fcfe 0%, #eef8fc 100%);
        border-bottom: 1px solid var(--incoming-border);
    }
    .incoming-document-hero__content {
        min-width: 0;
        flex: 1 1 620px;
    }
    .incoming-document-hero__icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        color: #fff;
        background: linear-gradient(145deg, #0ea5e9, #0876ad);
        box-shadow: 0 8px 20px rgba(2, 132, 199, .22);
        font-size: 20px;
    }
    .incoming-document-hero__meta {
        margin-bottom: 3px;
        color: #64748b;
        font-size: 12.5px;
        font-weight: 600;
    }
    .incoming-document-hero__title {
        color: #0f3044;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    .incoming-section-heading {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e8eef2;
        color: #12384d;
    }
    .incoming-section-heading__icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: grid;
        place-items: center;
        border-radius: 11px;
        color: var(--incoming-blue);
        background: var(--incoming-blue-soft);
    }
    .incoming-info-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .incoming-info-item {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        min-width: 0;
        min-height: 92px;
        padding: 16px;
        border: 1px solid #e4edf2;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 3px 12px rgba(15, 23, 42, .035);
    }
    .incoming-info-item:nth-child(5) {
        grid-column: span 2;
    }
    .incoming-info-item__icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: grid;
        place-items: center;
        border-radius: 9px;
        color: var(--incoming-blue);
        background: #edf8fc;
        font-size: 13px;
    }
    .incoming-info-item__label {
        margin-bottom: 5px;
        color: #738292;
        font-size: 12px;
        font-weight: 700;
    }
    .incoming-info-item__value {
        color: #172b3a;
        font-size: 15px;
        font-weight: 700;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }
    .incoming-assignment {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 17px;
        border: 1px solid #fde3ad;
        border-radius: 13px;
        color: #92400e;
        background: #fffaf0;
    }
    .incoming-assignment__icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        color: #b45309;
        background: #ffefc7;
    }
    .incoming-scroll-target {
        scroll-margin-top: 90px;
    }
    .document-attachment-preview {
        width: 100%;
        height: clamp(520px, 72vh, 900px);
        display: flex;
        align-items: flex-start;
        justify-content: center;
        overflow: auto;
        padding: 16px;
        background: #eef2f5;
        border: 1px solid #dbe4ea;
        border-radius: 12px;
        box-shadow: inset 0 1px 3px rgba(15, 23, 42, .06);
    }
    .document-attachment-preview__image {
        display: block;
        width: auto;
        max-width: 100%;
        height: auto;
        max-height: none;
        margin: 0 auto;
        object-fit: contain;
        background: #fff;
        box-shadow: 0 3px 12px rgba(15, 23, 42, .12);
    }
    .document-attachment-preview__frame {
        display: block;
        width: 100%;
        height: 100%;
        background: #fff;
        border: 0;
        border-radius: 7px;
    }
    .delegate-assignment-card{--dac-card:#fff;--dac-ink:#212529;--dac-mute:#6c757d;--dac-line:#dee2e6;--dac-soft:#f8f9fa;--dac-blue:#0d6efd;--dac-edge:#0d9bf0;--dac-green:#198754;background:var(--dac-card);border-radius:12px;border-left:4px solid var(--dac-edge) !important;box-shadow:0 2px 12px rgba(20,40,80,.08);padding:24px 28px}
    .delegate-assignment-card h2{margin:0;font-size:1.35rem;font-weight:700;display:flex;align-items:center;gap:8px;color:var(--dac-ink)}
    .delegate-assignment-card h2 svg{color:var(--dac-blue)}
    .delegate-assignment-card .sub{margin:2px 0 18px;color:var(--dac-mute);font-size:.95rem}
    .delegate-assignment-card .row{display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin:0}
    .delegate-assignment-card .delegate-form-row{display:flex;gap:12px;align-items:flex-start;flex:1 1 360px;flex-wrap:wrap}
    .delegate-assignment-card .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:44px;padding:0 24px;border:0;border-radius:6px;color:#fff;font:inherit;font-weight:600;cursor:pointer;line-height:normal;text-decoration:none}
    .delegate-assignment-card .btn:focus-visible{outline:0;box-shadow:0 0 0 .22rem rgba(13,110,253,.35)}
    .delegate-assignment-card .btn.g{background:var(--dac-green);border-radius:99px;padding:0 28px}
    .delegate-assignment-card .btn.g:hover{background:#157347;color:#fff}
    .delegate-assignment-card .btn.b{background:var(--dac-blue);white-space:nowrap}
    .delegate-assignment-card .btn.b:hover{background:#0b5ed7;color:#fff}
    .delegate-assignment-card .btn:disabled{opacity:.5;cursor:not-allowed;background:var(--dac-blue)}
    .delegate-assignment-card .dd{position:relative;flex:1;min-width:260px}
    .delegate-assignment-card .trigger{width:100%;min-height:44px;display:flex;align-items:center;gap:8px;border:1px solid var(--dac-line);border-radius:6px;background:#fff;padding:5px 40px 5px 12px;font:inherit;text-align:left;cursor:pointer;position:relative;flex-wrap:wrap}
    .delegate-assignment-card .trigger:focus-visible,.delegate-assignment-card .dd.open .trigger{outline:0;border-color:#86b7fe;box-shadow:0 0 0 .22rem rgba(13,110,253,.2)}
    .delegate-assignment-card .trigger .ph{color:#8a949e;font-size:.95rem}
    .delegate-assignment-card .trigger:after{content:"";position:absolute;right:14px;top:50%;width:8px;height:8px;border:solid #343a40;border-width:0 2px 2px 0;transform:translateY(-70%) rotate(45deg);transition:transform .15s;pointer-events:none}
    .delegate-assignment-card .dd.open .trigger:after{transform:translateY(-20%) rotate(225deg)}
    .delegate-assignment-card .chip{display:inline-flex;align-items:center;gap:6px;background:#e7f1ff;color:#0a58ca;border-radius:99px;padding:3px 6px 3px 12px;font-size:.88rem;font-weight:500}
    .delegate-assignment-card .chip b{width:18px;height:18px;border-radius:50%;display:grid;place-items:center;font-size:.7rem;font-weight:400;cursor:pointer;user-select:none}
    .delegate-assignment-card .chip b:hover{background:#cfe2ff}
    .delegate-assignment-card .panel{display:none;position:absolute;z-index:1050;left:0;right:0;top:calc(100% + 6px);background:#fff;border:1px solid var(--dac-line);border-radius:8px;box-shadow:0 8px 24px rgba(20,40,80,.14);overflow:hidden}
    .delegate-assignment-card .dd.open .panel{display:block}
    .delegate-assignment-card .search{padding:10px;border-bottom:1px solid var(--dac-line)}
    .delegate-assignment-card .search input{width:100%;height:38px;border:1px solid var(--dac-line);border-radius:6px;padding:0 12px;font:inherit;font-size:.95rem}
    .delegate-assignment-card .search input:focus{outline:0;border-color:#86b7fe;box-shadow:0 0 0 .2rem rgba(13,110,253,.2)}
    .delegate-assignment-card .opt{display:flex;align-items:center;gap:12px;padding:10px 14px;cursor:pointer;font-size:.98rem;margin-bottom:0;user-select:none}
    .delegate-assignment-card .opt:hover{background:var(--dac-soft)}
    .delegate-assignment-card .opt input{width:18px;height:18px;accent-color:var(--dac-blue);margin:0;cursor:pointer}
    .delegate-assignment-card .opt small{color:var(--dac-mute)}
    .delegate-assignment-card .all{font-weight:700;border-bottom:1px solid var(--dac-line)}
    .delegate-assignment-card .list{max-height:220px;overflow:auto}
    .delegate-assignment-card .empty{padding:14px;color:var(--dac-mute);text-align:center;display:none}
    @media (max-width: 600px){.delegate-assignment-card{padding:18px}.delegate-assignment-card .row{flex-direction:column}.delegate-assignment-card .row>form,.delegate-assignment-card .row>form .btn,.delegate-assignment-card .delegate-form-row,.delegate-assignment-card .delegate-form-row .btn,.delegate-assignment-card .dd{width:100%;min-width:100%}}
    .assignment-progress-ui{--apu-blue:#0d6efd;--apu-edge:#0d9bf0;--apu-ink:#212529;--apu-muted:#6c757d;--apu-line:#dee2e6;--apu-soft:#f8f9fa;padding:24px 28px;background:#fff;border-left:4px solid var(--apu-edge);border-radius:12px;box-shadow:0 2px 12px rgba(20,40,80,.08)}
    .assignment-progress-ui__heading h5{display:flex;align-items:center;gap:8px;margin:0;color:var(--apu-ink);font-size:1.35rem;font-weight:700}.assignment-progress-ui__heading h5 i{color:var(--apu-edge)}.assignment-progress-ui__heading p{margin:2px 0 16px;color:var(--apu-muted);font-size:.92rem}.assignment-progress-ui__recipients{display:grid;gap:10px}.assignment-progress-ui__who{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:14px 18px;background:var(--apu-soft);border:1px solid var(--apu-line);border-radius:8px}.assignment-progress-ui__who strong,.assignment-progress-ui__who small,.assignment-progress-ui__who a,.assignment-progress-ui__latest{display:block}.assignment-progress-ui__who strong{margin-bottom:4px;color:var(--apu-ink);font-size:1.05rem}.assignment-progress-ui__who small{color:var(--apu-muted);font-size:.88rem}.assignment-progress-ui__latest{margin-top:8px;color:#475569;font-size:.88rem}.assignment-progress-ui__who a{max-width:430px;margin-top:6px;color:#0b5ed7;font-size:.85rem;font-weight:700;overflow:hidden;text-decoration:none;text-overflow:ellipsis;white-space:nowrap}.assignment-progress-ui__who a i{margin-right:5px}.assignment-progress-ui__badge{flex:0 0 auto;padding:4px 10px;color:#212529;background:#ffc107;border-radius:6px;font-size:.78rem;font-weight:600}.assignment-progress-ui__badge--in_progress{color:#fff;background:#0d6efd}.assignment-progress-ui__badge--completed{color:#fff;background:#198754}.assignment-progress-ui__badge--acknowledged{color:#055160;background:#cff4fc}.assignment-progress-ui__form{margin-top:20px;padding-top:20px;border-top:1px solid var(--apu-line)}.assignment-progress-ui__fields{display:grid;grid-template-columns:230px 190px minmax(0,1fr);gap:18px;margin-bottom:18px}.assignment-progress-ui label{display:block;margin-bottom:8px;color:var(--apu-ink);font-weight:600}.assignment-progress-ui__control{width:100%;height:42px;padding:0 12px;color:var(--apu-ink);background:#fff;border:1px solid var(--apu-line);border-radius:6px;font:inherit;font-size:1rem}.assignment-progress-ui__control:focus{outline:0;border-color:#86b7fe;box-shadow:0 0 0 .22rem rgba(13,110,253,.2)}.assignment-progress-ui select.assignment-progress-ui__control{appearance:auto}.assignment-progress-ui__percent{position:relative}.assignment-progress-ui__percent .assignment-progress-ui__control{padding-right:34px}.assignment-progress-ui__percent em{position:absolute;top:10px;right:12px;color:var(--apu-muted);font-style:normal}.assignment-progress-ui input[type=range]{width:100%;height:6px;margin:12px 0 0;appearance:none;outline:0;border-radius:99px;background:linear-gradient(to right,var(--apu-blue) var(--progress-value,0%),var(--apu-line) var(--progress-value,0%))}.assignment-progress-ui input[type=range]::-webkit-slider-thumb{width:18px;height:18px;appearance:none;cursor:pointer;background:var(--apu-blue);border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 1px var(--apu-blue)}.assignment-progress-ui input[type=range]::-moz-range-thumb{width:12px;height:12px;cursor:pointer;background:var(--apu-blue);border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 1px var(--apu-blue)}.assignment-progress-ui__upload-row{display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap}.assignment-progress-ui__upload-row>div{flex:1 1 340px;min-width:min(340px,100%)}.assignment-progress-ui__file{display:flex !important;width:340px;max-width:100%;height:42px;margin:0 !important;overflow:hidden;cursor:pointer;background:#fff;border:1px solid var(--apu-line);border-radius:6px}.assignment-progress-ui__file input{display:none}.assignment-progress-ui__file>span{display:flex;align-items:center;padding:0 14px;background:var(--apu-soft);border-right:1px solid var(--apu-line)}.assignment-progress-ui__file i{display:flex;align-items:center;justify-content:space-between;flex:1;min-width:0;padding:0 12px;font-style:normal;color:var(--apu-ink)}.assignment-progress-ui__file b{overflow:hidden;font-weight:400;text-overflow:ellipsis;white-space:nowrap}.assignment-progress-ui__file button{display:none;padding:0;color:var(--apu-muted);background:none;border:0;font-size:1rem;cursor:pointer}.assignment-progress-ui__upload-row small{display:block;margin-top:8px;color:var(--apu-muted);font-size:.85rem}.assignment-progress-ui__save{display:inline-flex;align-items:center;gap:8px;height:42px;margin-top:33px;margin-left:auto;padding:0 26px;color:#fff;background:var(--apu-blue);border:0;border-radius:6px;font:inherit;font-weight:600;cursor:pointer}.assignment-progress-ui__save:hover{background:#0b5ed7}.assignment-progress-ui__save:focus-visible{outline:0;box-shadow:0 0 0 .22rem rgba(13,110,253,.35)}
    @media (max-width: 820px){.assignment-progress-ui__fields{grid-template-columns:1fr 1fr}.assignment-progress-ui__fields>div:last-child{grid-column:1/-1}}
    @media (max-width: 520px){.assignment-progress-ui{padding:18px}.assignment-progress-ui__fields{grid-template-columns:1fr}.assignment-progress-ui__fields>div:last-child{grid-column:auto}.assignment-progress-ui__who{padding:13px}.assignment-progress-ui__who a{max-width:240px}.assignment-progress-ui__upload-row>div,.assignment-progress-ui__file{width:100%;min-width:0}.assignment-progress-ui__save{width:100%;justify-content:center;margin-top:0;margin-left:0}}
    @media (max-width: 767.98px) {
        .incoming-page-heading {
            align-items: flex-start !important;
            flex-direction: column;
        }
        .incoming-page-heading > div:last-child {
            width: 100%;
            justify-content: space-between !important;
        }
        .incoming-page-heading h3 {
            font-size: 1.35rem;
        }
        .incoming-section-nav {
            margin-left: -4px;
            margin-right: -4px;
        }
        .incoming-info-grid {
            grid-template-columns: 1fr;
        }
        .incoming-info-item:nth-child(5) {
            grid-column: auto;
        }
        .incoming-document-hero__icon {
            width: 42px;
            height: 42px;
            flex-basis: 42px;
        }
        .document-attachment-preview {
            height: 58vh;
            min-height: 420px;
            padding: 8px;
        }
    }
    @media (min-width: 768px) and (max-width: 1099.98px) {
        .incoming-info-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @font-face {
        font-family: 'MySarabun';
        src: url('{{ asset("fonts/THSarabunIT๙.ttf") }}') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    @font-face {
        font-family: 'MySarabun';
        src: url('{{ asset("fonts/THSarabunIT๙_Bold.ttf") }}') format('truetype');
        font-weight: bold;
        font-style: normal;
    }

    @media print {
        @page {
            size: A4;
            margin: 15mm !important;
        }
        body { background: white !important; -webkit-print-color-adjust: exact; }
        .no-print, nav, aside, .navbar, footer { display: none !important; }
        .print-container { display: none !important; }
    }
</style>
@endsection

@section('scripts')
<script>
    // หน้าที่: ตรวจสอบว่าระบุเหตุผลก่อนส่งผลการพิจารณา
    function checkReason() {
        const reasonInput = document.getElementById('comment');
        if (reasonInput && reasonInput.value.trim() === '') {
            reasonInput.style.borderColor = '#ef4444';
            reasonInput.style.backgroundColor = '#fef2f2';
            const reasonAst = document.getElementById('reason_asterisk');
            if(reasonAst) reasonAst.style.display = 'block';
            
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาระบุเหตุผล',
                text: 'จำเป็นต้องระบุเหตุผลในช่องความเห็น เมื่อต้องการตีกลับเอกสาร',
                confirmButtonColor: '#dc2626'
            }).then(() => { reasonInput.focus(); });
            return false;
        }
        return true;
    }

    // หน้าที่: จัดการเหตุการณ์ขององค์ประกอบในหน้านี้
    document.getElementById('comment')?.addEventListener('input', function() {
        this.style.borderColor = '#dde3ea';
        this.style.backgroundColor = '#ffffff';
        const reasonAst = document.getElementById('reason_asterisk');
        if(reasonAst) reasonAst.style.display = 'none';
    });

    // หน้าที่: เริ่มตั้งค่าหน้าและผูกเหตุการณ์เมื่อ DOM พร้อมใช้งาน
    document.addEventListener('DOMContentLoaded', function() {
        const delegateDd = document.getElementById('dd');
        if (delegateDd) {
            const P = @json($deptUsersJson ?? []);
            const S = {};
            const tg = document.getElementById('tg');
            const ls = document.getElementById('ls');
            const q = document.getElementById('q');
            const all = document.getElementById('all');
            const fw = document.getElementById('fw');
            const fl = document.getElementById('fl');
            const em = document.getElementById('em');
            const hiddenInputs = document.getElementById('delegateHiddenInputs');

            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str || '';
                return div.innerHTML;
            }

            function sel() {
                return P.filter(function(p) { return S[p.id]; });
            }

            function renderList() {
                const k = q.value.trim().toLowerCase();
                let n = 0;
                ls.innerHTML = '';
                P.forEach(function(p) {
                    if (k && (p.n + ' ' + p.r).toLowerCase().indexOf(k) < 0) return;
                    n++;
                    const l = document.createElement('label');
                    l.className = 'opt';
                    l.innerHTML = '<input type="checkbox"' + (S[p.id] ? ' checked' : '') + '><span>' + escapeHtml(p.n) + (p.r ? ' <small>— ' + escapeHtml(p.r) + '</small>' : '') + '</span>';
                    l.querySelector('input').addEventListener('change', function(e) {
                        S[p.id] = e.target.checked;
                        sync();
                    });
                    ls.appendChild(l);
                });
                em.style.display = n ? 'none' : 'block';
            }

            function sync() {
                const s = sel();
                if (s.length > 0) {
                    tg.innerHTML = s.map(function(p) {
                        return '<span class="chip">' + escapeHtml(p.n) + '<b data-id="' + p.id + '" title="เอาออก">✕</b></span>';
                    }).join('');
                } else {
                    tg.innerHTML = '<span class="ph">เลือกบุคลากรที่จะส่งต่อ</span>';
                }

                all.checked = P.length > 0 && s.length === P.length;
                all.indeterminate = s.length > 0 && s.length < P.length;

                fw.disabled = s.length === 0;
                fl.textContent = 'ส่งต่อให้ผู้ที่เลือก' + (s.length ? ' (' + s.length + ')' : '');

                if (hiddenInputs) {
                    hiddenInputs.innerHTML = s.map(function(p) {
                        return '<input type="hidden" name="delegate_user_ids[]" value="' + escapeHtml(p.id) + '">';
                    }).join('');
                }

                renderList();
            }

            function open_(o) {
                delegateDd.classList.toggle('open', o);
                tg.setAttribute('aria-expanded', o ? 'true' : 'false');
                if (o) {
                    q.focus();
                }
            }

            tg.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('[data-id]');
                if (removeBtn) {
                    e.stopPropagation();
                    const uid = removeBtn.getAttribute('data-id');
                    S[uid] = false;
                    sync();
                    return;
                }
                open_(!delegateDd.classList.contains('open'));
            });

            all.addEventListener('change', function() {
                P.forEach(function(p) {
                    S[p.id] = all.checked;
                });
                sync();
            });

            q.addEventListener('input', renderList);

            document.addEventListener('click', function(e) {
                if (!delegateDd.contains(e.target)) {
                    open_(false);
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    open_(false);
                    tg.focus();
                }
            });

            sync();
        }
        document.querySelectorAll('[data-assignment-progress-form]').forEach(function(form) {
            const status = form.querySelector('[data-progress-status]');
            const number = form.querySelector('[data-progress-number]');
            const range = form.querySelector('[data-progress-range]');
            const file = form.querySelector('[data-progress-file]');
            const fileName = form.querySelector('[data-progress-file-name]');
            const clearFile = form.querySelector('[data-progress-file-clear]');
            const syncProgress = function(value, updateStatus) {
                const progress = Math.max(0, Math.min(100, Number(value) || 0));
                number.value = progress;
                range.value = progress;
                range.style.setProperty('--progress-value', progress + '%');
                if (updateStatus) status.value = progress === 100 ? 'completed' : 'in_progress';
            };
            ['input', 'change'].forEach(function(eventName) {
                number.addEventListener(eventName, function() { syncProgress(number.value, true); });
                range.addEventListener(eventName, function() { syncProgress(range.value, true); });
            });
            status.addEventListener('change', function() {
                if (status.value === 'completed') syncProgress(100, false);
            });
            file.addEventListener('change', function() {
                const selectedFile = file.files[0];
                fileName.textContent = selectedFile ? selectedFile.name : 'ยังไม่ได้เลือกไฟล์';
                clearFile.style.display = selectedFile ? 'block' : 'none';
            });
            clearFile.addEventListener('click', function(event) {
                event.preventDefault();
                file.value = '';
                file.dispatchEvent(new Event('change'));
            });
            form.addEventListener('submit', function() {
                syncProgress(status.value === 'completed' ? 100 : number.value, true);
            });
            syncProgress(number.value, false);
        });
        @if(session('error_signature'))
            Swal.fire({ icon: 'warning', title: 'ยังไม่พร้อมใช้งาน', text: '{{ session("error_signature") }}', confirmButtonText: 'ไปหน้าตั้งค่าโปรไฟล์', confirmButtonColor: '#0284c7' }).then((result) => { if (result.isConfirmed) window.location.href = "{{ route('profile.index') }}"; });
        @endif
        @if(session('error_pin')) Swal.fire({ icon: 'error', title: 'รหัสไม่ถูกต้อง', text: '{{ session("error_pin") }}', confirmButtonText: 'ลองอีกครั้ง', confirmButtonColor: '#dc2626' }); @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: '{{ session("error") }}', confirmButtonText: 'ตกลง', confirmButtonColor: '#dc2626' }); @endif
        @if(session('success')) Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '{{ session("success") }}', confirmButtonText: 'ตกลง', confirmButtonColor: '#10b981', timer: 2500 }); @endif
    });

    // หน้าที่: ขอคำยืนยันก่อนบันทึกการรับทราบเอกสาร
    function confirmAcknowledge(btn) {
        Swal.fire({
            icon: 'question',
            title: 'ยืนยันการรับทราบคำสั่ง?',
            html: '<span style="font-size:14px;color:#475569;">ระบบจะบันทึก<strong>เวลาและชื่อ</strong>ของคุณไว้เป็นหลักฐาน</span>',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
            focusConfirm: false,
        }).then((result) => {
            if (result.isConfirmed) {
                btn.closest('form').submit();
            }
        });
    }
</script>
@endsection
