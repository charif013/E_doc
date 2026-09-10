@extends('layouts.app')

@section('title', 'พิจารณาอนุมัติใบลา')

@section('content')

@php
    // 🌟 คำนวณปีและเลขที่เอกสาร
    $thaiYear = \Carbon\Carbon::parse($leave->created_at)->addYears(543)->format('Y');
    $suggestedNumber = 'LV-' . $thaiYear . '-' . str_pad($leave->id, 3, '0', STR_PAD_LEFT);
    $docId = $leave->leave_number ?: $suggestedNumber;
    $submitDate = \Carbon\Carbon::parse($leave->created_at)->addYears(543)->locale('th')->translatedFormat('d M Y | H:i');
    // 🌟 ตรรกะคัดกรองว่าใครกำลังเปิดหน้านี้
    $user = auth()->user();
    $canApprove = false;
    $actionTitle = '';
    $approveText = '';
    $rejectText = 'ไม่อนุมัติ / ตีกลับ';

    if ($leave->workflow_status === 'pending_head' && $user->hasRole('head')) {
        $canApprove = true; $actionTitle = 'ส่วนของหัวหน้าสำนักปลัด/ผอ.กอง'; $approveText = 'เห็นควรอนุญาต';
    } elseif ($leave->workflow_status === 'pending_inspector' && $user->hasRole('hr')) {
        $canApprove = true; $actionTitle = 'งานบุคคล (ตรวจสอบสิทธิ์และวันลาคงเหลือ)'; $approveText = 'ตรวจสอบสิทธิ์แล้ว ส่งต่อธุรการ';
    } elseif ($leave->workflow_status === 'pending_palad' && $user->hasAnyRole(['palad', 'deputy-palad'])) {
        $canApprove = true; $actionTitle = 'ส่วนของปลัด อบต.'; $approveText = 'เห็นควรอนุญาต';
    } elseif ($leave->workflow_status === 'pending_nayok' && $user->hasRole('executive')) {
        $canApprove = true; $actionTitle = 'คำสั่งนายก อบต.'; $approveText = 'อนุมัติการลา'; $rejectText = 'ไม่อนุมัติการลา';
    } elseif ($leave->workflow_status === 'pending_numbering' && $user->hasRole('saraban')) {
        $canApprove = true; $actionTitle = 'ส่วนของธุรการ (ลงเลขใบลา)'; $approveText = 'ลงเลขและปิดเรื่อง';
    }

    $isDelegate = ($leave->workflow_status === 'pending_delegate' && $user->id == $leave->delegate_id);
    $isLeaveOwner = $leave->user_id === $user->id;
    $isAssignedDelegate = $leave->delegate_id === $user->id;
    $backUrl = $isAssignedDelegate
        ? route('documents.assigned')
        : ($isLeaveOwner ? route('leaves.index') : route('leaves.approve_list'));
    $backLabel = $isAssignedDelegate ? 'กลับไปงานที่ได้รับ' : ($isLeaveOwner ? 'กลับไปประวัติการลา' : 'กลับหน้ารายการ');
@endphp

<div class="leave-review-page container-fluid px-4 py-3">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0"><i class="fas fa-file-medical-alt text-primary me-2"></i>รายละเอียดและพิจารณาใบลา</h4>
        <a href="{{ $backUrl }}" class="ds-back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>{{ $backLabel }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-4 border-success rounded-3">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-4 border-danger rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-5" style="border-radius: 12px; overflow: hidden;">
        
        {{-- 🌟 Header เอกสาร (แถบสีน้ำเงิน/เขียวเข้ม) --}}
        <div class="bg-teal text-white px-4 py-3 d-flex justify-content-between align-items-center flex-wrap">
            <h5 class="mb-0 fw-bold">อนุมัติใบลา — {{ $docId }}</h5>
            <div class="small opacity-75 mt-2 mt-md-0 fw-bold">
                ยื่นเมื่อ {{ $submitDate }} น. | {{ $leave->user->department ?? 'ไม่ระบุสังกัด' }}
            </div>
        </div>

        <div class="card-body p-4 p-md-5 bg-white">
            
            {{-- 🌟 1. รายละเอียดผู้ขอลา --}}
            <div class="section-title mb-3"><div class="section-indicator"></div><h6 class="fw-bold mb-0 text-secondary">รายละเอียดผู้ขอลา</h6></div>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="text-muted small mb-1 fw-bold">ชื่อ-นามสกุล</label>
                    <div class="info-box fw-bold text-dark fs-6">{{ $leave->user->name ?? 'ไม่ทราบชื่อ' }}</div>
                </div>
                <div class="col-md-6">
                    <label class="text-muted small mb-1 fw-bold">ตำแหน่ง</label>
                    <div class="info-box fw-bold text-dark fs-6">{{ $leave->user->position ?? 'พนักงาน' }}</div>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small mb-1 fw-bold">ประเภทการลา</label>
                    <div class="info-box fw-bold text-teal fs-6">{{ $leave->leave_type }}</div>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small mb-1 fw-bold">วันที่ลา</label>
                    <div class="info-box fw-bold text-dark fs-6">
                        {{ \Carbon\Carbon::parse($leave->start_date)->addYears(543)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($leave->end_date)->addYears(543)->format('d/m/Y') }}
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="text-muted small mb-1 fw-bold">จำนวนวัน</label>
                    <div class="info-box fw-bold text-dark fs-6">{{ $leave->total_days }} วันทำการ</div>
                </div>
                
                <div class="col-md-6 mt-3">
                    <label class="text-muted small mb-1 fw-bold">เหตุผลการลา</label>
                    <div class="info-box text-dark" style="min-height: 60px;">{{ $leave->reason }}</div>
                </div>
                <div class="col-md-6 mt-3">
                    <label class="text-muted small mb-1 fw-bold">ข้อมูลติดต่อระหว่างลา</label>
                    <div class="info-box text-dark" style="min-height: 60px;"><i class="fas fa-phone-alt text-muted me-2"></i>{{ $leave->contact_info }}</div>
                </div>

                @if($leave->delegate_id)
                <div class="col-12 mt-3">
                    <label class="text-muted small mb-1 fw-bold">ผู้ปฏิบัติหน้าที่แทน</label>
                    <div class="info-box text-dark bg-light" style="border-left: 4px solid #f59e0b;">
                        <i class="fas fa-user-friends text-warning me-2"></i> 
                        <span class="fw-bold">{{ $leave->delegate->name }}</span> ({{ $leave->delegate->position }})
                        @if($leave->delegate_status == 'accepted')
                            <span class="badge bg-success ms-2">รับทราบแล้ว</span>
                        @elseif($leave->delegate_status == 'declined')
                            <span class="badge bg-danger ms-2">ปฏิเสธแล้ว</span>
                        @elseif($leave->workflow_status == 'delegate_escalated')
                            <span class="badge bg-warning text-dark ms-2">เกินเวลาตอบรับ</span>
                        @else
                            <span class="badge bg-secondary ms-2">รอการยืนยัน</span>
                        @endif
                        @if($leave->delegate_decline_reason)
                            <div class="mt-2 text-danger"><strong>เหตุผล:</strong> {{ $leave->delegate_decline_reason }}</div>
                        @endif
                    </div>
                </div>
                @endif
                @if($leave->user_id === $user->id && in_array($leave->workflow_status, ['pending_delegate', 'delegate_declined', 'delegate_escalated']))
                <div class="col-12">
                    <form action="{{ route('leaves.reassign_delegate', $leave->id) }}" method="POST" class="card card-body border-warning-subtle bg-warning-subtle">
                        @csrf
                        <label class="form-label fw-bold">เปลี่ยนผู้รับมอบงาน</label>
                        <div class="input-group">
                            <select name="delegate_id" class="form-select" required>
                                <option value="">-- เลือกผู้รับมอบคนใหม่ --</option>
                                @foreach($delegateCandidates as $candidate)
                                    <option value="{{ $candidate->id }}" @selected($candidate->id === $leave->delegate_id)>
                                        {{ $candidate->name }} — {{ $candidate->position ?? 'ไม่ระบุตำแหน่ง' }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-warning fw-bold" type="submit">ส่งคำขอใหม่</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>

            {{-- 🌟 2. ประวัติการลาในปีงบประมาณ --}}
            <div class="section-title mb-3 mt-5"><div class="section-indicator"></div><h6 class="fw-bold mb-0 text-secondary">ประวัติการลาในปีงบประมาณ</h6></div>
            <div class="row g-3 mb-4 leave-stats">
                <div class="col-md-4">
                    <div class="stat-card stat-card--vacation">
                        <div class="stat-card__icon"><i class="fas fa-umbrella-beach" aria-hidden="true"></i></div>
                        <div>
                        <div class="text-muted small mb-1 fw-bold">ลาพักผ่อน (ใช้ไปแล้ว)</div>
                        <h3 class="fw-bold text-primary mb-0">{{ $vacationUsed }} <span class="fs-6 fw-normal text-muted">วันทำการ</span></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card stat-card--sick">
                        <div class="stat-card__icon"><i class="fas fa-kit-medical" aria-hidden="true"></i></div>
                        <div>
                        <div class="text-muted small mb-1 fw-bold">ลาป่วย (ใช้ไปแล้ว)</div>
                        <h3 class="fw-bold text-danger mb-0">{{ $sickUsed }} <span class="fs-6 fw-normal text-muted">วันทำการ</span></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card stat-card--personal">
                        <div class="stat-card__icon"><i class="fas fa-user-clock" aria-hidden="true"></i></div>
                        <div>
                        <div class="text-muted small mb-1 fw-bold">ลากิจส่วนตัว (ใช้ไปแล้ว)</div>
                        <h3 class="fw-bold text-warning mb-0">{{ $personalUsed }} <span class="fs-6 fw-normal text-muted">วันทำการ</span></h3>
                        </div>
                    </div>
                </div>
            </div>

           {{-- 🌟 3. สถานะการอนุมัติ (แนวนอน 4 กล่อง) --}}
            <div class="section-title mb-3 mt-5"><div class="section-indicator"></div><h6 class="fw-bold mb-0 text-secondary">สถานะการอนุมัติ</h6></div>
            <div class="row g-3 mb-4 approval-flow">
                
                {{-- กล่อง 1: หัวหน้า/ผอ.กองของผู้ยื่น --}}
                <div class="col-lg col-md-4 col-6">
                    <div class="approval-card {{ $leave->head_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_head' ? 'active' : '') }}">
                        <div class="small text-muted mb-1 fw-bold">หน.สำนักปลัด/ผอ.กองต้นสังกัด</div>
                        <div class="fw-bold text-dark mb-2 text-truncate" style="min-height: 20px;">
                            {{ $leave->head_id ? $leave->head->name : '-' }}
                        </div>
                        @if($leave->head_status == 'approved')
                            <span class="badge bg-success-subtle text-success-emphasis w-100 py-2 rounded-pill shadow-sm">เห็นควรอนุญาต</span>
                        @elseif($leave->workflow_status == 'pending_head')
                            <span class="badge bg-warning-subtle text-warning-emphasis w-100 py-2 rounded-pill shadow-sm">รอความเห็น ⏳</span>
                        @else
                            <span class="badge bg-light text-muted border w-100 py-2 rounded-pill">รอดำเนินการ</span>
                        @endif
                    </div>
                </div>

                {{-- กล่อง 2: ธุรการ/งานบุคคล --}}
                <div class="col-lg col-md-4 col-6">
                    <div class="approval-card {{ $leave->inspector_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_inspector' ? 'active' : '') }}">
                        <div class="small text-muted mb-1 fw-bold">งานบุคคลตรวจสอบสิทธิ์</div>
                        <div class="fw-bold text-dark mb-2 text-truncate" style="min-height: 20px;">
                            {{ $leave->inspector_id ? $leave->inspector->name : '-' }}
                        </div>
                        @if($leave->inspector_status == 'approved')
                            <span class="badge bg-success-subtle text-success-emphasis w-100 py-2 rounded-pill shadow-sm">ตรวจสอบแล้ว</span>
                        @elseif($leave->workflow_status == 'pending_inspector')
                            <span class="badge bg-warning-subtle text-warning-emphasis w-100 py-2 rounded-pill shadow-sm">รอตรวจสอบสิทธิ์ ⏳</span>
                        @else
                            <span class="badge bg-light text-muted border w-100 py-2 rounded-pill">รอดำเนินการ</span>
                        @endif
                    </div>
                </div>

                {{-- กล่อง 3: ธุรการลงเลขรับ --}}
                <div class="col-lg col-md-4 col-6">
                    <div class="approval-card {{ $leave->numbered_at ? 'completed' : ($leave->workflow_status == 'pending_numbering' ? 'active' : '') }}">
                        <div class="small text-muted mb-1 fw-bold">ธุรการลงเลขรับ</div>
                        <div class="fw-bold text-dark mb-2 text-truncate" style="min-height: 20px;">
                            {{ $leave->leave_number ?: '-' }}
                        </div>
                        @if($leave->numbered_at)
                            <span class="badge bg-success-subtle text-success-emphasis w-100 py-2 rounded-pill shadow-sm">ลงเลขแล้ว</span>
                        @elseif($leave->workflow_status == 'pending_numbering')
                            <span class="badge bg-warning-subtle text-warning-emphasis w-100 py-2 rounded-pill shadow-sm">รอลงเลขรับ ⏳</span>
                        @else
                            <span class="badge bg-light text-muted border w-100 py-2 rounded-pill">รอดำเนินการ</span>
                        @endif
                    </div>
                </div>

                {{-- กล่อง 4: ปลัด --}}
                <div class="col-lg col-md-4 col-6">
                    <div class="approval-card {{ $leave->palad_status == 'approved' ? 'completed' : ($leave->workflow_status == 'pending_palad' ? 'active' : '') }}">
                        <div class="small text-muted mb-1 fw-bold">ปลัด อบต.</div>
                        <div class="fw-bold text-dark mb-2 text-truncate" style="min-height: 20px;">
                            {{ $leave->palad_id ? $leave->palad->name : '-' }}
                        </div>
                        @if($leave->palad_status == 'approved')
                            <span class="badge bg-success-subtle text-success-emphasis w-100 py-2 rounded-pill shadow-sm">เห็นควรอนุญาต</span>
                        @elseif($leave->workflow_status == 'pending_palad')
                            <span class="badge bg-warning-subtle text-warning-emphasis w-100 py-2 rounded-pill shadow-sm">รอความเห็น ⏳</span>
                        @else
                            <span class="badge bg-light text-muted border w-100 py-2 rounded-pill">รอดำเนินการ</span>
                        @endif
                    </div>
                </div>

                {{-- กล่อง 5: นายก --}}
                <div class="col-lg col-md-4 col-6">
                    <div class="approval-card {{ $leave->nayok_status == 'approved' ? 'completed' : ($leave->status == 'REJECTED' ? 'rejected' : ($leave->workflow_status == 'pending_nayok' ? 'active' : '')) }}">
                        <div class="small text-muted mb-1 fw-bold">นายก อบต.</div>
                        <div class="fw-bold text-dark mb-2 text-truncate" style="min-height: 20px;">
                            {{ $leave->nayok_id ? $leave->nayok->name : '-' }}
                        </div>
                        @if($leave->nayok_status == 'approved')
                            <span class="badge bg-success-subtle text-success-emphasis w-100 py-2 rounded-pill shadow-sm">อนุมัติเรียบร้อย</span>
                        @elseif($leave->status == 'REJECTED')
                            <span class="badge bg-danger-subtle text-danger-emphasis w-100 py-2 rounded-pill shadow-sm">ไม่อนุมัติ ❌</span>
                        @elseif($leave->workflow_status == 'pending_nayok')
                            <span class="badge bg-warning-subtle text-warning-emphasis w-100 py-2 rounded-pill shadow-sm">รอพิจารณา ⏳</span>
                        @else
                            <span class="badge bg-light text-muted border w-100 py-2 rounded-pill">รอดำเนินการ</span>
                        @endif
                    </div>
                </div>

            </div>

            {{-- 🌟 แจ้งเตือนกรณีถูกตีกลับ --}}
            @if($leave->status === 'REJECTED')
                <div class="alert alert-danger border-0 shadow-sm rounded-3 p-4 mt-4 text-center">
                    <h5 class="fw-bold text-danger mb-2"><i class="fas fa-times-circle me-2"></i> ใบลาฉบับนี้ไม่อนุมัติ / ถูกตีกลับ</h5>
                    <p class="mb-0 text-dark">เหตุผล: <span class="fw-bold">{{ $leave->reject_reason ?? 'ไม่มีการระบุเหตุผล' }}</span></p>
                </div>
            @endif

            {{-- 🌟 4. ฟอร์มสำหรับลงนามอนุมัติ (จะโชว์เฉพาะด่านตัวเอง) --}}
            @if($isDelegate)
                {{-- ฟอร์มผู้รับมอบงาน --}}
                <div class="card mt-5 border-0 bg-light shadow-sm" style="border-radius: 15px;">
                    <div class="card-body p-4 text-center">
                        <h5 class="fw-bold text-warning-emphasis mb-2"><i class="fas fa-handshake me-2"></i> การรับมอบหมายงาน</h5>
                        <p class="text-muted mb-4">คุณถูกระบุให้เป็นผู้ปฏิบัติหน้าที่แทนในระหว่างที่ <b class="text-dark">{{ $leave->user->name }}</b> ลา</p>
                        <form action="{{ route('leaves.delegateAction', $leave->id) }}" method="POST">
                            @csrf
                            <textarea name="decline_reason" class="form-control mb-3" rows="2" maxlength="1000" placeholder="ระบุเหตุผลหากไม่สามารถรับมอบงานได้">{{ old('decline_reason') }}</textarea>
                            <div class="d-flex justify-content-center gap-3">
                                <button type="submit" name="action" value="decline" class="btn btn-outline-danger fw-bold rounded-pill px-5 py-2">ปฏิเสธงานนี้</button>
                                <button type="submit" name="action" value="accept" class="btn btn-warning fw-bold rounded-pill px-5 py-2 shadow-sm text-dark">
                                    <i class="fas fa-check me-1"></i> ยินดีรับมอบงาน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif($canApprove)
                {{-- ฟอร์มผู้พิจารณา (ธุรการ, หัวหน้า, ปลัด, นายก) --}}
                <form action="{{ route('leaves.reviewAction', $leave->id) }}" method="POST" class="mt-5 pt-4 border-top" id="approveForm">
                    @csrf
                    
                    <h6 class="fw-bold text-teal mb-3"><i class="fas fa-pen-nib me-2"></i> {{ $actionTitle }}</h6>

                    @if($leave->workflow_status === 'pending_numbering')
                        <div class="bg-warning-subtle p-3 rounded-3 border border-warning mb-3">
                            <label class="form-label fw-bold text-dark">เลขที่ใบลา <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                 <input type="hidden" name="running_number" id="leave_running_number" value="{{ old('running_number') }}">
                                 <input type="text" name="leave_number" id="leave_number" value="{{ old('leave_number') }}"
                                       class="form-control bg-white fw-bold text-primary" placeholder="คลิกปุ่มรันเลข..." readonly>
                                <button type="button" onclick="autoLeaveNo()" class="btn btn-primary fw-bold px-4">
                                    <i class="fas fa-magic me-1"></i> รันเลข
                                </button>
                            </div>
                            @error('leave_number')<div class="text-danger small mt-2 fw-bold">{{ $message }}</div>@enderror
                            @error('running_number')<div class="text-danger small mt-2 fw-bold">{{ $message }}</div>@enderror
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-book-open me-1"></i> ดึงเลขถัดไปจากสมุดคุมเลขสารบรรณ หมวดทะเบียนใบลา
                            </small>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="text-muted small fw-bold mb-2" for="reject_reason">ความเห็น / หมายเหตุ <span class="text-danger" id="reason_asterisk" style="display:none;">*</span></label>
                            <textarea name="reject_reason" id="reject_reason" class="form-control" style="min-height: 80px; border-radius: 10px; border: 1px solid #cbd5e1; background-color: #f8fafc;" placeholder="ระบุความเห็น... (บังคับกรอกหากเลือกไม่อนุมัติ)" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#cbd5e1'"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small fw-bold mb-2">รหัส PIN ลายเซ็น <span class="text-danger">*</span></label>
                            <input type="password" name="pin" class="form-control text-center tracking-widest fw-bold" style="height: 50px; border-radius: 10px; border: 2px solid #164f51; font-size: 1.2rem; color: #164f51;" placeholder="••••••" maxlength="6" required autocomplete="off">
                        </div>
                    </div>

                    <div class="text-end mt-4 d-flex justify-content-end flex-wrap gap-3">
                        @if($leave->workflow_status !== 'pending_numbering')
                            <button type="submit" name="is_approved" value="0" class="btn btn-outline-danger btn-lg rounded-pill px-5 fw-bold" onclick="return checkReason()">
                                <i class="fas fa-times me-1"></i> {{ $rejectText }}
                            </button>
                        @endif
                        <button type="submit" name="is_approved" value="1" class="btn btn-dark btn-lg rounded-pill px-5 fw-bold shadow-sm" style="background-color: #164f51; border-color: #164f51;">
                            <i class="fas fa-check me-1"></i> {{ $approveText }}
                        </button>
                    </div>
                </form>
            @endif

        </div>
    </div>
</div>

<style>
    /* สีหลัก */
    .leave-review-page { min-height: 100vh; background: #fff; }
    .bg-teal { background-color: #164f51 !important; }
    .text-teal { color: #164f51 !important; }
    .btn-teal { background-color: #164f51; border: none; }
    .btn-teal:hover { background-color: #0f3638; }
    .tracking-widest { letter-spacing: 0.4em; }

    /* เส้นขีดหน้าหัวข้อ */
    .section-title { display: flex; align-items: center; }
    .section-title h6 { color: #334155 !important; font-size: 1rem; }
    .section-indicator { width: 4px; height: 18px; background-color: #3b82f6; border-radius: 4px; margin-right: 10px; }

    /* กล่องข้อมูล (Info Box) */
    .info-box {
        border: 1px solid #dbe4ee;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        background-color: #f8fafc;
        min-height: 45px;
    }

    /* การ์ดสถิติ */
    .stat-card {
        --stat-accent: #2563eb;
        --stat-soft: #eff6ff;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.15rem;
        color: #0f172a;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-top: 4px solid var(--stat-accent);
        border-radius: 14px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, .09); }
    .stat-card--vacation { --stat-accent: #2563eb; --stat-soft: #dbeafe; }
    .stat-card--sick { --stat-accent: #e11d48; --stat-soft: #ffe4e6; }
    .stat-card--personal { --stat-accent: #d97706; --stat-soft: #fef3c7; }
    .stat-card__icon { width: 48px; height: 48px; display: grid; place-items: center; flex: 0 0 auto; color: var(--stat-accent); background: var(--stat-soft); border-radius: 13px; font-size: 1.1rem; }
    .stat-card .text-muted { color: #64748b !important; }
    .stat-card h3 { font-size: 1.7rem; }

    /* ลำดับสถานะการอนุมัติ */
    .approval-flow { counter-reset: approval-step; position: relative; }
    .approval-card {
        counter-increment: approval-step;
        position: relative;
        height: 100%;
        min-height: 164px;
        padding: 3.65rem 1rem 1rem;
        color: #0f172a;
        background: #fff;
        border: 1px solid #dbe4ee;
        border-radius: 14px;
        box-shadow: 0 3px 12px rgba(15, 23, 42, .05);
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }
    .approval-card::before {
        content: counter(approval-step);
        position: absolute;
        top: 1rem;
        left: 1rem;
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        color: #64748b;
        background: #f1f5f9;
        border: 2px solid #cbd5e1;
        border-radius: 50%;
        font-size: .82rem;
        font-weight: 800;
    }
    .approval-card .text-muted { color: #475569 !important; }
    .approval-card .text-dark { color: #0f172a !important; }

    .approval-card.active {
        background: #eff6ff;
        border: 2px solid #3b82f6;
        box-shadow: 0 7px 18px rgba(59, 130, 246, .16);
        transform: translateY(-2px);
    }
    .approval-card.active::before { color: #fff; background: #2563eb; border-color: #2563eb; box-shadow: 0 0 0 5px #dbeafe; }
    .approval-card.completed {
        background: #f0fdf4;
        border-color: #86efac;
    }
    .approval-card.completed::before { content: '\f00c'; color: #fff; background: #16a34a; border-color: #16a34a; font-family: 'Font Awesome 6 Free'; font-weight: 900; }
    .approval-card.rejected {
        background: #fef2f2;
        border-color: #fca5a5;
    }
    .approval-card.rejected::before { content: '\f00d'; color: #fff; background: #dc2626; border-color: #dc2626; font-family: 'Font Awesome 6 Free'; font-weight: 900; }

    .approval-card .badge { box-shadow: none !important; border: 1px solid rgba(148, 163, 184, .35); }

    /* ป้ายสถานะ */
    .bg-success-subtle { background-color: #ecfdf5 !important; }
    .text-success-emphasis { color: #059669 !important; }
    .bg-warning-subtle { background-color: #fffbeb !important; }
    .text-warning-emphasis { color: #d97706 !important; }
    .bg-danger-subtle { background-color: #fef2f2 !important; }
    .text-danger-emphasis { color: #dc2626 !important; }

    @media (max-width: 767.98px) {
        .leave-review-page { padding-left: .75rem !important; padding-right: .75rem !important; }
        .leave-review-page > .d-flex:first-child { align-items: flex-start !important; flex-direction: column; gap: .75rem; }
        .stat-card { padding: 1rem; }
        .approval-card { min-height: 150px; }
    }
</style>

<script>
    // สคริปต์ตรวจสอบการกรอกเหตุผล กรณีที่กด "ไม่อนุมัติ"
    function checkReason() {
        const reasonInput = document.getElementById('reject_reason');
        if (reasonInput.value.trim() === '') {
            // แจ้งเตือน และทำกรอบสีแดงให้ช่องกรอก
            reasonInput.style.borderColor = '#ef4444';
            reasonInput.style.backgroundColor = '#fef2f2';
            document.getElementById('reason_asterisk').style.display = 'inline';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาระบุเหตุผล',
                    text: 'จำเป็นต้องระบุความเห็นหรือเหตุผล ในกรณีที่กด "ไม่อนุมัติ" หรือ "ตีกลับ"',
                    confirmButtonColor: '#164f51'
                }).then(() => {
                    reasonInput.focus();
                });
            } else {
                alert('กรุณาระบุความเห็นหรือเหตุผลการไม่อนุมัติ');
                reasonInput.focus();
            }
            return false; // หยุดการส่งฟอร์ม
        }
        return true; // ยอมให้ส่งฟอร์ม
    }

    // รีเซ็ตสีกรอบเมื่อเริ่มพิมพ์เหตุผล
    document.getElementById('reject_reason')?.addEventListener('input', function() {
        this.style.borderColor = '#3b82f6';
        this.style.backgroundColor = '#ffffff';
        document.getElementById('reason_asterisk').style.display = 'none';
    });
</script>

@if($leave->workflow_status === 'pending_numbering' && auth()->user()->hasRole('saraban'))
<script>
async function autoLeaveNo() {
    const button = document.querySelector('[onclick="autoLeaveNo()"]');

    try {
        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> กำลังรันเลข...';
        }

        const response = await fetch({{ Illuminate\Support\Js::from(route('documents.api_next_number', ['type' => 'leave', 'leave_id' => $leave->id])) }}, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || `ไม่สามารถรันเลขใบลาได้ (HTTP ${response.status})`);
        }

        const data = await response.json();
        document.getElementById('leave_number').value = data.formatted;
        document.getElementById('leave_running_number').value = data.next_number;
    } catch (error) {
        const message = error?.message || 'ไม่สามารถรันเลขใบลาได้ กรุณาลองใหม่อีกครั้ง';
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'รันเลขใบลาไม่สำเร็จ',
                text: message,
                confirmButtonText: 'ตกลง'
            });
        } else {
            alert(message);
        }
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-magic me-1"></i> รันเลข';
        }
    }
}
</script>
@endif

@endsection
