{{-- View: หน้าตรวจสอบและส่งฉบับร่างใบลา --}}
@extends('layouts.app')

@section('title', 'ตรวจสอบฉบับร่างใบลา')

@section('content')
@php
    $thaiCreated = $leave->created_at->copy()->locale('th');
    $leaveTypes = ['ลากิจส่วนตัว', 'ลาป่วย', 'ลาคลอดบุตร'];
    if (!in_array($leave->leave_type, $leaveTypes, true)) {
        $leaveTypes[] = $leave->leave_type;
    }
@endphp

<div class="leave-draft-page">
    <div class="draft-toolbar">
        <div>
            <h4>ตรวจสอบฉบับร่างใบลา</h4>
            <p>ตรวจความถูกต้องก่อนยืนยันส่งเข้าสู่ขั้นตอนพิจารณา</p>
        </div>
        <a href="{{ route('leaves.index') }}" class="ds-back-link">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับประวัติการลา
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mx-auto draft-alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mx-auto draft-alert">{{ session('error') }}</div>
    @endif
    @error('pin')
        <div class="alert alert-danger mx-auto draft-alert">{{ $message }}</div>
    @enderror

    <article class="leave-paper" aria-label="ฉบับร่างแบบฟอร์มใบลา">
        <h1>ใบลาป่วย&nbsp;&nbsp;ลากิจส่วนตัว&nbsp;&nbsp;ลาคลอดบุตร</h1>

        <div class="paper-meta">
            <p>เขียนที่ <span>องค์การบริหารส่วนตำบลพร่อน</span></p>
            <p>วันที่ <span>{{ $thaiCreated->translatedFormat('j F') }} {{ $thaiCreated->year + 543 }}</span></p>
        </div>

        <p class="paper-line"><b>เรื่อง</b><span>ขอลา {{ $leave->leave_type }}</span></p>
        <p class="paper-line"><b>เรียน</b><span>นายกองค์การบริหารส่วนตำบลพร่อน</span></p>

        <div class="identity-line">
            <span>ข้าพเจ้า</span><strong>{{ $leave->user->name ?? '-' }}</strong>
            <span>ตำแหน่ง</span><strong>{{ $leave->user->position ?? '-' }}</strong>
        </div>
        <div class="identity-line department-line">
            <span>สังกัดส่วนราชการ</span><strong>{{ $leave->user->department ?? '-' }}</strong>
        </div>

        <div class="leave-choice-row">
            <span class="choice-label">ขอลา</span>
            <div class="choice-list">
                @foreach($leaveTypes as $type)
                    <span class="choice {{ $leave->leave_type === $type ? 'selected' : '' }}">
                        <i>{{ $leave->leave_type === $type ? '✓' : '' }}</i>{{ $type }}
                    </span>
                @endforeach
            </div>
        </div>

        <p class="reason-row"><span>เนื่องจาก</span><strong>{{ $leave->reason }}</strong></p>

        <div class="date-row">
            <span>ตั้งแต่วันที่</span>
            <strong>{{ $leave->start_date->copy()->addYears(543)->format('d/m/Y') }}</strong>
            <span>ถึงวันที่</span>
            <strong>{{ $leave->end_date->copy()->addYears(543)->format('d/m/Y') }}</strong>
            <span>มีกำหนด</span>
            <strong>{{ rtrim(rtrim(number_format((float) $leave->total_days, 2), '0'), '.') }}</strong>
            <span>วัน</span>
        </div>

        <p class="contact-row">
            <span>ในระหว่างลาครั้งนี้จะติดต่อข้าพเจ้าได้ที่</span>
            <strong>{{ $leave->contact_info ?: '-' }}</strong>
        </p>

        <div class="paper-lower-grid">
            <section>
                <h2>สถิติการลาในปีงบประมาณนี้</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ประเภทลา</th>
                            <th>ลามาแล้ว<br><small>(วันทำการ)</small></th>
                            <th>ลาครั้งนี้<br><small>(วันทำการ)</small></th>
                            <th>รวมเป็น<br><small>(วันทำการ)</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leaveTypes as $type)
                            @php
                                $used = (float) ($usedLeaves->get($type) ?? 0);
                                $current = $leave->leave_type === $type ? (float) $leave->total_days : 0;
                            @endphp
                            <tr>
                                <th>{{ $type }}</th>
                                <td>{{ $used }}</td>
                                <td>{{ $current ?: '-' }}</td>
                                <td>{{ $used + $current }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="signature compact-signature">
                    <p>(ลงชื่อ)........................................ผู้ตรวจสอบ</p>
                    <p>ตำแหน่ง นักทรัพยากรบุคคล</p>
                    <p>วันที่........../................../..........</p>
                </div>
            </section>

            <section class="approval-side">
                <div class="signature applicant-signature">
                    <p>ขอแสดงความนับถือ</p>
                    <p>(ลงชื่อ)........................................................</p>
                    <p>({{ $leave->user->name ?? '................................' }})</p>
                </div>

                @unless($leave->user?->hasRole('palad'))
                <div class="approval-block">
                    <h2>ความเห็นผู้บังคับบัญชา (ปลัด อบต.)</h2>
                    <p class="dotted"></p>
                    <p>(ลงชื่อ)........................................................</p>
                    <p>ตำแหน่ง ปลัดองค์การบริหารส่วนตำบลพร่อน</p>
                    <p>วันที่........../................../..........</p>
                </div>
                @endunless

                <div class="approval-block">
                    <h2>คำสั่ง</h2>
                    <p class="command"><span>□ อนุญาต</span><span>□ ไม่อนุญาต</span></p>
                    <p>(ลงชื่อ)........................................................</p>
                    <p>ตำแหน่ง นายกองค์การบริหารส่วนตำบลพร่อน</p>
                    <p>วันที่........../................../..........</p>
                </div>
            </section>
        </div>

        @if($leave->delegate)
            <section class="draft-supporting">
                <p><b>ผู้รับมอบหมายงาน:</b> {{ $leave->delegate->name }} ({{ $leave->delegate->position ?: 'ไม่ระบุตำแหน่ง' }})</p>
            </section>
        @endif
    </article>

    @if($leave->attachments->isNotEmpty())
        <section class="attachment-panel" aria-labelledby="attachment-heading">
            <div class="attachment-panel-heading">
                <div>
                    <h5 id="attachment-heading"><i class="fas fa-paperclip me-2" aria-hidden="true"></i>เอกสารแนบ</h5>
                    <p>เอกสารประกอบใบลา {{ $leave->attachments->count() }} ไฟล์</p>
                </div>
            </div>

            <div class="attachment-list">
                @foreach($leave->attachments as $attachment)
                    @php
                        $attachmentUrl = route('leaves.attachments.download', [$leave->id, $attachment->id]);
                        $isImage = str_starts_with((string) $attachment->mime_type, 'image/');
                        $isPdf = $attachment->mime_type === 'application/pdf'
                            || str_ends_with(strtolower($attachment->original_name), '.pdf');
                        $fileSize = $attachment->size_bytes >= 1048576
                            ? number_format($attachment->size_bytes / 1048576, 1).' MB'
                            : number_format(max(1, $attachment->size_bytes / 1024), 0).' KB';
                    @endphp
                    <article class="attachment-card">
                        <div class="attachment-card-title">
                            <div>
                                <strong>{{ $attachment->original_name }}</strong>
                                <small>{{ $fileSize }}</small>
                            </div>
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i>เปิดเอกสาร
                            </a>
                        </div>

                        @if($isImage)
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="attachment-preview attachment-preview-image">
                                <img src="{{ $attachmentUrl }}" alt="ตัวอย่างเอกสาร {{ $attachment->original_name }}">
                            </a>
                        @elseif($isPdf)
                            <iframe class="attachment-preview attachment-preview-pdf"
                                    src="{{ $attachmentUrl }}#toolbar=1&navpanes=0"
                                    title="ตัวอย่างเอกสาร {{ $attachment->original_name }}"></iframe>
                        @else
                            <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="attachment-file-fallback">
                                <i class="fas fa-file-word" aria-hidden="true"></i>
                                <span>ไฟล์นี้ไม่รองรับการแสดงตัวอย่าง<br><small>คลิกเพื่อเปิดหรือดาวน์โหลดเอกสาร</small></span>
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="draft-actions" aria-labelledby="submit-leave-heading">
        <div class="draft-actions-heading">
            <span class="draft-actions-icon"><i class="fas fa-file-signature" aria-hidden="true"></i></span>
            <div>
                <h5 id="submit-leave-heading">ยืนยันการส่งใบลา</h5>
                <p>กรอกรหัส PIN เพื่อประทับลายเซ็นและส่งเข้าสู่ขั้นตอนพิจารณา</p>
            </div>
        </div>

        <div class="draft-action-controls">
            <form action="{{ route('leaves.cancel', $leave->id) }}" method="POST" class="cancel-draft-form">
                @csrf
                @method('PUT')
                <button type="submit" class="btn btn-outline-danger draft-cancel-btn">
                    <i class="fas fa-xmark me-2" aria-hidden="true"></i>ยกเลิกฉบับร่าง
                </button>
            </form>
            <form action="{{ route('leaves.draft.submit', $leave->id) }}" method="POST" class="submit-draft-form">
                @csrf
                <div class="draft-submit-box">
                    <input type="password" name="pin" id="submit_pin" class="form-control text-center" inputmode="numeric" maxlength="6" required autocomplete="off" placeholder="PIN ••••••" aria-label="รหัส PIN" aria-describedby="submit-pin-help">
                    <button type="submit" class="btn btn-teal fw-bold">
                        <i class="fas fa-signature me-2" aria-hidden="true"></i>ยืนยันและส่งใบลา
                    </button>
                </div>
                <small id="submit-pin-help" class="pin-security-note"><i class="fas fa-shield-halved me-1" aria-hidden="true"></i>รหัส PIN จะใช้ยืนยันตัวตนเท่านั้น</small>
            </form>
        </div>
    </section>
</div>

<style>
@font-face { font-family: 'TH Sarabun New'; src: url('{{ asset('fonts/THSarabunNew.ttf') }}') format('truetype'); font-weight: 400; }
@font-face { font-family: 'TH Sarabun New'; src: url('{{ asset('fonts/THSarabunNew_Bold.ttf') }}') format('truetype'); font-weight: 700; }
.leave-draft-page { min-height: 90vh; padding: 1.5rem clamp(.75rem, 3vw, 2rem) 3rem; background: #e9edf0; }
.draft-toolbar, .draft-actions, .draft-alert, .attachment-panel { width: min(100%, 210mm); margin-left: auto; margin-right: auto; }
.draft-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.draft-toolbar h4 { margin: 0; color: #164f51; font-weight: 800; }
.draft-toolbar p { margin: .15rem 0 0; color: #667085; }
.leave-paper { position: relative; width: min(100%, 210mm); min-height: 297mm; margin: 0 auto; padding: 18mm 16mm 15mm; overflow: hidden; border: 1px solid #d0d5dd; background: #fff; box-shadow: 0 12px 34px rgba(16,24,40,.14); color: #111; font-family: 'TH Sarabun New', Tahoma, sans-serif; font-size: 17pt; line-height: 1.2; }
.leave-paper > * { position: relative; z-index: 1; }
.leave-paper h1 { margin: 0 0 1.1rem; font-size: 21pt; font-weight: 400; text-align: center; text-decoration: underline; text-underline-offset: .16em; }
.paper-meta { width: 52%; margin-left: auto; }
.paper-meta p { display: flex; gap: .3rem; margin: .1rem 0; }
.paper-meta span { flex: 1; border-bottom: 1px dotted #222; text-align: center; }
.paper-line { display: grid; grid-template-columns: 3.7rem 1fr; margin: .4rem 0; }
.paper-line b { font-weight: 400; }
.identity-line { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: .35rem; margin: .7rem 0 .25rem 2.5rem; align-items: baseline; }
.identity-line strong, .reason-row strong, .contact-row strong, .date-row strong { min-width: 0; padding: 0 .25rem; border-bottom: 1px dotted #222; font-weight: 400; text-align: center; }
.department-line { grid-template-columns: auto 1fr; margin-top: .25rem; }
.leave-choice-row { display: grid; grid-template-columns: 4.5rem 1fr; margin: .8rem 0 .35rem; }
.choice-list { display: flex; flex-wrap: wrap; gap: .2rem 1rem; }
.choice { display: inline-flex; align-items: center; gap: .35rem; min-width: 8.7rem; }
.choice i { width: 1.1rem; height: 1.1rem; border: 1px solid #111; font: 700 .85rem/1rem Tahoma, sans-serif; text-align: center; }
.choice.selected { font-weight: 700; }
.reason-row, .contact-row { display: grid; grid-template-columns: auto 1fr; gap: .35rem; margin: .55rem 0; }
.reason-row strong, .contact-row strong { text-align: left; }
.date-row { display: grid; grid-template-columns: auto 1fr auto 1fr auto .55fr auto; gap: .3rem; align-items: baseline; margin: .55rem 0; }
.paper-lower-grid { display: grid; grid-template-columns: 1.08fr .92fr; gap: 1.8rem; margin-top: 1rem; }
.leave-paper h2 { margin: 0 0 .35rem; font-size: 17pt; font-weight: 700; text-align: center; text-decoration: underline; text-underline-offset: .12em; }
.leave-paper table { width: 100%; border-collapse: collapse; font-size: 14pt; line-height: 1.05; }
.leave-paper th, .leave-paper td { padding: .28rem .2rem; border: 1px solid #111; text-align: center; }
.signature, .approval-block { text-align: center; }
.signature p, .approval-block p { margin: .2rem 0; }
.compact-signature { margin-top: 2.2rem; }
.applicant-signature p:first-child { margin-bottom: 1.1rem; }
.approval-block { margin-top: 1.1rem; }
.approval-block h2 { font-size: 16.5pt; }
.dotted { height: 1.2rem; border-bottom: 1px dotted #222; }
.command { display: flex; justify-content: center; gap: 2rem; font-weight: 700; }
.draft-supporting { margin-top: 1.1rem; padding-top: .65rem; border-top: 1px solid #999; font-size: 14pt; }
.draft-supporting p { margin: .25rem 0; }
.attachment-panel { margin-top: 1.25rem; padding: 1.25rem; border: 1px solid #d0d5dd; border-radius: 14px; background: #fff; box-shadow: 0 8px 24px rgba(16,24,40,.08); }
.attachment-panel-heading h5 { margin: 0; color: #164f51; font-weight: 800; }
.attachment-panel-heading p { margin: .2rem 0 0; color: #667085; }
.attachment-list { display: grid; gap: 1rem; margin-top: 1rem; }
.attachment-card { overflow: hidden; border: 1px solid #d8dee5; border-radius: 12px; background: #f8fafc; }
.attachment-card-title { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; background: #fff; border-bottom: 1px solid #e4e7ec; }
.attachment-card-title strong, .attachment-card-title small { display: block; overflow-wrap: anywhere; }
.attachment-card-title small { margin-top: .15rem; color: #667085; }
.attachment-preview { display: block; width: 100%; border: 0; background: #eef2f6; }
.attachment-preview-image { padding: 1rem; text-align: center; }
.attachment-preview-image img { display: block; width: auto; max-width: 100%; max-height: 720px; margin: 0 auto; border-radius: 6px; box-shadow: 0 2px 8px rgba(16,24,40,.12); }
.attachment-preview-pdf { height: min(72vh, 760px); }
.attachment-file-fallback { display: flex; min-height: 180px; align-items: center; justify-content: center; gap: 1rem; color: #344054; text-align: left; text-decoration: none; }
.attachment-file-fallback > i { color: #2563eb; font-size: 3rem; }
.attachment-file-fallback small { color: #667085; }
.draft-actions { margin-top: 1.25rem; padding: 1.15rem 1.25rem; border: 1px solid #cfd8dc; border-radius: 16px; background: #fff; box-shadow: 0 8px 24px rgba(16,24,40,.09); }
.draft-actions-heading { display: flex; align-items: center; gap: .8rem; padding-bottom: .9rem; border-bottom: 1px solid #e4e7ec; }
.draft-actions-icon { width: 42px; height: 42px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 12px; color: #fff; background: #164f51; font-size: 1.1rem; }
.draft-actions-heading h5 { margin: 0; color: #164f51; font-weight: 800; }
.draft-actions-heading p { margin: .15rem 0 0; color: #667085; font-size: .9rem; }
.draft-action-controls { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: start; gap: 1rem; padding-top: 1rem; }
.cancel-draft-form { align-self: start; }
.draft-cancel-btn { min-height: 48px; padding: .65rem 1.1rem; border-radius: 10px; font-weight: 700; white-space: nowrap; }
.submit-draft-form { min-width: 0; }
.draft-submit-box { display: grid; grid-template-columns: 140px minmax(180px, auto); justify-content: end; align-items: center; gap: .7rem; }
.draft-submit-box .form-control { min-height: 48px; border: 2px solid #8aa7a8; border-radius: 10px; font-size: 1.1rem; font-weight: 700; letter-spacing: .25em; }
.draft-submit-box .form-control:focus { border-color: #164f51; box-shadow: 0 0 0 3px rgba(22,79,81,.12); }
.draft-submit-box .btn { min-height: 48px; padding: .65rem 1.15rem; border-radius: 10px; white-space: nowrap; }
.pin-security-note { display: block; margin: .45rem 0 0; color: #667085; text-align: right; }
.btn-teal { border-color: #164f51; background: #164f51; color: #fff; }
.btn-teal:hover { border-color: #0f3638; background: #0f3638; color: #fff; }
@media (max-width: 900px) {
    .draft-action-controls { grid-template-columns: 1fr; }
    .cancel-draft-form { justify-self: start; }
}
@media (max-width: 760px) {
    .leave-paper { min-height: 0; padding: 2rem 1rem; font-size: 15pt; }
    .paper-meta { width: 100%; }
    .identity-line { grid-template-columns: auto 1fr; margin-left: 0; }
    .date-row { grid-template-columns: auto 1fr; }
    .paper-lower-grid { grid-template-columns: 1fr; }
    .approval-side { padding-top: 1rem; border-top: 1px solid #999; }
    .attachment-panel { padding: .75rem; }
    .attachment-card-title { align-items: flex-start; flex-direction: column; }
    .attachment-preview-pdf { height: 520px; }
    .draft-actions { position: sticky; bottom: .5rem; z-index: 5; padding: 1rem; }
    .draft-action-controls { grid-template-columns: 1fr; }
    .cancel-draft-form, .submit-draft-form, .draft-cancel-btn { width: 100%; }
    .draft-submit-box { grid-template-columns: 1fr; }
    .pin-security-note { text-align: left; }
}
@media print {
    .leave-draft-page { padding: 0; background: #fff; }
    .draft-toolbar, .draft-actions, .draft-alert, .attachment-panel { display: none !important; }
    .leave-paper { width: 210mm; min-height: 297mm; border: 0; box-shadow: none; }
}
</style>
@endsection
