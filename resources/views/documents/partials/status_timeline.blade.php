@php
    $timelineStatus = strtoupper((string) ($document->status ?? 'DRAFT'));
    if ($document->isAtFinalApprovalStep()) {
        $timelineStatus = 'WAITING_APPROVER';
    }
    $isOutgoingTimeline = strtolower((string) ($document->doc_type ?? '')) === 'outgoing';
    $isIncomingTimeline = strtolower((string) ($document->doc_type ?? '')) === 'incoming';

    if ($isOutgoingTimeline) {
        // หนังสือส่งออกได้รับเลขตั้งแต่หน้ากรอกรายละเอียด จึงไม่มีขั้นรอออกเลข
        $selectedSigner = trim((string) ($document->signer_name ?? ''));
        $signerParts = preg_split('/\s*[—–]\s*/u', $selectedSigner, 2);
        $signerName = trim((string) ($signerParts[0] ?? ''));
        $signerPosition = trim((string) ($signerParts[1] ?? ''));
        $signerStepLabel = $signerName !== '' ? "รอ{$signerName}ลงนาม" : 'รอผู้ลงนาม';
        $signerStepDescription = $signerPosition !== '' ? $signerPosition : 'ผู้ลงนามพิจารณาอนุมัติ';
        $timelineOrder = [
            'DRAFT' => 0,
            'REJECTED' => 0,
            'WAITING_ADMIN' => 1,
            'REGISTERED' => 1,
            'IN_REVIEW' => 1,
            'WAITING_REVIEWER' => 1,
            'WAITING_SUPERVISOR' => 1,
            'WAITING_PALAD' => 2,
            'WAITING_NAYOK' => 2,
            'WAITING_APPROVER' => 2,
            'WAITING_NUMBERING' => 2,
            'APPROVED' => 3,
            'COMPLETED' => 3,
            'ARCHIVED' => 3,
        ];
        $timelineSteps = [
            ['ร่าง', 'จัดทำหนังสือส่งออก'],
            ['รอตรวจ', 'ธุรการตรวจสอบ'],
            [$signerStepLabel, $signerStepDescription],
            ['เสร็จสิ้น', 'พร้อมใช้งาน'],
        ];
    } elseif ($isIncomingTimeline) {
        // หนังสือรับเข้าระบุเลขรับตั้งแต่ตอนลงทะเบียน จึงจบงานหลังอนุมัติ
        $timelineOrder = [
            'DRAFT' => 0,
            'REJECTED' => 0,
            'WAITING_ADMIN' => 1,
            'REGISTERED' => 1,
            'IN_REVIEW' => 1,
            'WAITING_REVIEWER' => 1,
            'WAITING_SUPERVISOR' => 1,
            'WAITING_PALAD' => 2,
            'WAITING_NAYOK' => 2,
            'WAITING_APPROVER' => 2,
            'PROCESSING' => 2,
            'WAITING_NUMBERING' => 3,
            'APPROVED' => 3,
            'COMPLETED' => 3,
            'ARCHIVED' => 3,
        ];
        $timelineSteps = [
            ['ร่าง', 'จัดทำหนังสือรับเข้า'],
            ['รอตรวจ', 'เจ้าหน้าที่ตรวจสอบ'],
            ['รออนุมัติ', 'ผู้มีอำนาจพิจารณา'],
            ['เสร็จสิ้น', 'พร้อมใช้งาน'],
        ];
    } else {
        $timelineOrder = [
            'DRAFT' => 0,
            'REJECTED' => 0,
            'WAITING_ADMIN' => 1,
            'REGISTERED' => 1,
            'IN_REVIEW' => 1,
            'WAITING_REVIEWER' => 1,
            'WAITING_SUPERVISOR' => 1,
            'WAITING_PALAD' => 2,
            'WAITING_NAYOK' => 2,
            'WAITING_APPROVER' => 2,
            'WAITING_NUMBERING' => 3,
            // V2: APPROVED = ผ่านการอนุมัติแล้ว แต่ยังรอสารบรรณออกเลข
            'APPROVED' => 3,
            'COMPLETED' => 4,
            'ARCHIVED' => 4,
        ];
        $timelineSteps = [
            ['ร่าง', 'จัดทำเอกสาร'],
            ['รอตรวจ', 'เจ้าหน้าที่ตรวจสอบ'],
            ['รออนุมัติ', 'ผู้มีอำนาจพิจารณา'],
            ['ออกเลข', 'ลงทะเบียนสารบรรณ'],
            ['เสร็จสิ้น', 'พร้อมใช้งาน'],
        ];
    }
    $timelineCurrent = $timelineOrder[$timelineStatus] ?? 0;
    $timelineIsFinished = in_array($timelineStatus, ['COMPLETED', 'ARCHIVED'], true)
        || (($isOutgoingTimeline || $isIncomingTimeline) && $timelineStatus === 'APPROVED');
@endphp

<section class="document-status-timeline no-print" aria-labelledby="document-status-title">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 id="document-status-title" class="h6 fw-bold mb-0">สถานะเอกสาร</h2>
        @if($timelineStatus === 'REJECTED')
            <span class="badge bg-danger">ส่งกลับเพื่อแก้ไข</span>
        @elseif($timelineStatus === 'CANCELED')
            <span class="badge bg-dark">ยกเลิกแล้ว</span>
        @endif
    </div>
    <ol class="status-timeline-list" style="--timeline-step-count: {{ count($timelineSteps) }};">
        @foreach($timelineSteps as $index => [$label, $description])
            @php
                $isDone = $timelineStatus !== 'REJECTED'
                    && ($index < $timelineCurrent || ($timelineIsFinished && $index === $timelineCurrent));
                $isCurrent = !$timelineIsFinished
                    && $index === $timelineCurrent
                    && !in_array($timelineStatus, ['CANCELED']);
            @endphp
            <li class="status-timeline-step {{ $isDone ? 'is-done' : '' }} {{ $isCurrent ? 'is-current' : '' }}"
                @if($isCurrent) aria-current="step" @endif>
                <span class="status-timeline-marker" aria-hidden="true">{{ $isDone ? '✓' : $index + 1 }}</span>
                <span class="status-timeline-copy">
                    <strong>{{ $label }}</strong>
                    <small>{{ $description }}</small>
                </span>
            </li>
        @endforeach
    </ol>
</section>

@once
<style>
    .document-status-timeline { max-width: 1200px; margin: 0 auto 1.5rem; padding: 1.25rem; background: #fff; border: 1px solid var(--border-color); border-radius: 14px; box-shadow: var(--shadow-sm); }
    .status-timeline-list { display: grid; grid-template-columns: repeat(var(--timeline-step-count, 5), 1fr); list-style: none; margin: 0; padding: 0; }
    .status-timeline-step { position: relative; display: flex; flex-direction: column; align-items: center; gap: .45rem; color: #64748b; min-width: 0; text-align: center; }
    .status-timeline-step:not(:last-child)::after { content: ''; position: absolute; height: 3px; background: #e2e8f0; left: calc(50% + 23px); right: calc(-50% + 23px); top: 17px; z-index: 0; }
    .status-timeline-step.is-done:not(:last-child)::after { background: #10b981; }
    .status-timeline-marker { width: 36px; height: 36px; border-radius: 50%; border: 2px solid #cbd5e1; background: #fff; display: grid; place-items: center; font-weight: 700; flex: 0 0 auto; z-index: 1; }
    .is-done .status-timeline-marker { color: #fff; background: #10b981; border-color: #10b981; }
    .is-current .status-timeline-marker { color: #fff; background: var(--primary); border-color: var(--primary); box-shadow: 0 0 0 5px var(--primary-light); }
    .status-timeline-copy { display: flex; flex-direction: column; align-items: center; min-width: 0; }
    .status-timeline-copy strong { color: #334155; font-size: .9rem; }
    .status-timeline-copy small { font-size: .78rem; }
    @media (max-width: 767.98px) {
        .status-timeline-list { grid-template-columns: 1fr; gap: .8rem; }
        .status-timeline-step { display: grid; grid-template-columns: 36px minmax(0, 1fr); gap: .75rem; text-align: left; }
        .status-timeline-step:not(:last-child)::after { width: 3px; height: calc(100% + .8rem); left: 17px; right: auto; top: 34px; }
        .status-timeline-copy { align-items: flex-start; }
        .status-timeline-copy strong { font-size: 1rem; }
        .status-timeline-copy small { font-size: .875rem; }
    }
</style>
@endonce
