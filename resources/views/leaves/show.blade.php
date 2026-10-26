{{-- View: หน้าแสดงรายละเอียดและพิจารณาใบลา --}}
@extends('layouts.app')
@section('title', 'พิจารณาใบลา')
@section('content')
@php
    $user = auth()->user();
    $thaiCreated = $leave->created_at->copy()->locale('th');
    $leaveTypes = ['ลากิจส่วนตัว', 'ลาป่วย', 'ลาคลอดบุตร'];
    if (!in_array($leave->leave_type, $leaveTypes, true)) $leaveTypes[] = $leave->leave_type;
    $usedLeaves = collect(['ลากิจส่วนตัว' => $personalUsed, 'ลาป่วย' => $sickUsed, 'ลาคลอดบุตร' => 0, 'ลาพักผ่อน' => $vacationUsed]);
    $canApprove = false; $actionTitle = ''; $approveText = ''; $rejectText = 'ไม่อนุญาต / ตีกลับ';
    if ($leave->workflow_status === 'pending_head' && $user->hasRole('head')) {
        $canApprove = true; $actionTitle = 'ความเห็นหัวหน้าส่วนราชการ'; $approveText = 'เห็นควรอนุญาตและส่งต่อ';
    } elseif ($leave->workflow_status === 'pending_inspector' && $user->hasRole('hr')) {
        $canApprove = true; $actionTitle = 'งานบุคคลตรวจสอบสิทธิ์และวันลา'; $approveText = 'ตรวจสอบแล้วและส่งต่อ';
    } elseif ($leave->workflow_status === 'pending_palad' && $user->hasRole('palad')) {
        $canApprove = true; $actionTitle = 'ความเห็นปลัด อบต.'; $approveText = 'เห็นควรอนุญาตและส่งต่อ';
    } elseif ($leave->workflow_status === 'pending_nayok' && $user->hasRole('executive')) {
        $canApprove = true; $actionTitle = 'คำสั่งนายก อบต.'; $approveText = 'อนุญาตการลา';
    }
    $isDelegate = $leave->workflow_status === 'pending_delegate' && $user->id === $leave->delegate_id;
    $isOwner = $leave->user_id === $user->id;
    $backUrl = $isOwner ? route('leaves.index') : ($isDelegate ? route('documents.assigned') : route('leaves.approve_list'));
    $backLabel = $isOwner ? 'กลับประวัติการลา' : ($isDelegate ? 'กลับไปงานที่ได้รับ' : 'กลับรายการรอพิจารณา');
    $thaiDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->addYears(543)->format('d/m/Y') : null;
@endphp

<div class="leave-review-document-page">
    <div class="review-toolbar">
        <div><h4>ใบลาและการพิจารณา</h4><p>ลายเซ็นของผู้พิจารณาจะแสดงบนใบลาตามลำดับขั้น</p></div>
        <a href="{{ $backUrl }}" class="ds-back-link"><i class="fas fa-arrow-left"></i>{{ $backLabel }}</a>
    </div>
    @if(session('success')) <div class="alert alert-success review-alert">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger review-alert">{{ session('error') }}</div> @endif

    <article class="leave-paper review-paper" aria-label="แบบฟอร์มใบลา">
        <h1>ใบลาป่วย&nbsp;&nbsp;ลากิจส่วนตัว&nbsp;&nbsp;ลาคลอดบุตร</h1>
        <div class="paper-meta"><p>เขียนที่ <span>องค์การบริหารส่วนตำบลพร่อน</span></p><p>วันที่ <span>{{ $thaiCreated->translatedFormat('j F') }} {{ $thaiCreated->year + 543 }}</span></p></div>
        <p class="paper-line"><b>เรื่อง</b><span>ขอลา {{ $leave->leave_type }}</span></p>
        <p class="paper-line"><b>เรียน</b><span>นายกองค์การบริหารส่วนตำบลพร่อน</span></p>
        <div class="identity-line"><span>ข้าพเจ้า</span><strong>{{ $leave->user->name ?? '-' }}</strong><span>ตำแหน่ง</span><strong>{{ $leave->user->position ?? '-' }}</strong></div>
        <div class="identity-line department-line"><span>สังกัดส่วนราชการ</span><strong>{{ $leave->user->department ?? '-' }}</strong></div>
        <div class="leave-choice-row"><span>ขอลา</span><div class="choice-list">@foreach($leaveTypes as $type)<span class="choice {{ $leave->leave_type === $type ? 'selected' : '' }}"><i>{{ $leave->leave_type === $type ? '✓' : '' }}</i>{{ $type }}</span>@endforeach</div></div>
        <p class="reason-row"><span>เนื่องจาก</span><strong>{{ $leave->reason }}</strong></p>
        <div class="date-row"><span>ตั้งแต่วันที่</span><strong>{{ $leave->start_date->copy()->addYears(543)->format('d/m/Y') }}</strong><span>ถึงวันที่</span><strong>{{ $leave->end_date->copy()->addYears(543)->format('d/m/Y') }}</strong><span>มีกำหนด</span><strong>{{ rtrim(rtrim(number_format((float) $leave->total_days, 2), '0'), '.') }}</strong><span>วัน</span></div>
        <p class="contact-row"><span>ในระหว่างลาครั้งนี้จะติดต่อข้าพเจ้าได้ที่</span><strong>{{ $leave->contact_info ?: '-' }}</strong></p>

        <div class="paper-lower-grid">
            <section>
                <h2>สถิติการลาในปีงบประมาณนี้</h2>
                <table><thead><tr><th>ประเภทลา</th><th>ลามาแล้ว<br><small>(วันทำการ)</small></th><th>ลาครั้งนี้<br><small>(วันทำการ)</small></th><th>รวมเป็น<br><small>(วันทำการ)</small></th></tr></thead><tbody>
                @foreach($leaveTypes as $type)
                    @php $used = (float) ($usedLeaves->get($type) ?? 0); $current = $leave->leave_type === $type ? (float) $leave->total_days : 0; @endphp
                    <tr><th>{{ $type }}</th><td>{{ $used }}</td><td>{{ $current ?: '-' }}</td><td>{{ $used + $current }}</td></tr>
                @endforeach
                </tbody></table>
                <div class="signature approval-signature"><h2>ผู้ตรวจสอบสิทธิ์การลา</h2>
                    @if($leave->inspector_signature)
                        @if($leave->inspector_comment)<p class="approval-comment">“{{ $leave->inspector_comment }}”</p>@endif
                        <img src="{{ $leave->inspector_signature }}" alt="ลายเซ็นผู้ตรวจสอบ"><p>({{ $leave->inspector?->name ?? '-' }})</p><p>ตำแหน่ง {{ $leave->inspector?->position ?: 'นักทรัพยากรบุคคล' }}</p><p>วันที่ {{ $thaiDate($leave->inspector_at) }}</p>
                    @else
                        <p class="signature-space">(ลงชื่อ)........................................ผู้ตรวจสอบ</p><p>ตำแหน่ง นักทรัพยากรบุคคล</p><p>วันที่........../................../..........</p>
                    @endif
                </div>
            </section>
            <section class="approval-side">
                <div class="signature applicant-signature"><p>ขอแสดงความนับถือ</p>
                    @if($leave->applicant_signature)
                        <img src="{{ $leave->applicant_signature }}" alt="ลายเซ็นผู้ยื่นใบลา"><p>({{ $leave->user->name ?? '-' }})</p><p>วันที่ {{ $thaiDate($leave->submitted_at) }}</p>
                    @else
                        <p class="signature-space">(ลงชื่อ)........................................................</p><p>({{ $leave->user->name ?? '................................' }})</p>
                    @endif
                </div>
                @foreach([['ความเห็นหัวหน้าส่วนราชการ', $leave->head_signature, $leave->head_comment, $leave->head, $leave->head_at, 'หัวหน้าส่วนราชการ'], ['ความเห็นผู้บังคับบัญชา (ปลัด อบต.)', $leave->palad_signature, $leave->palad_comment, $leave->palad, $leave->palad_at, 'ปลัดองค์การบริหารส่วนตำบลพร่อน']] as [$title, $signature, $comment, $actor, $actedAt, $defaultPosition])
                    <div class="approval-block"><h2>{{ $title }}</h2>
                        @if($signature)@if($comment)<p class="approval-comment">“{{ $comment }}”</p>@endif<img src="{{ $signature }}" alt="ลายเซ็น {{ $title }}"><p>({{ $actor?->name ?? '-' }})</p><p>ตำแหน่ง {{ $actor?->position ?: $defaultPosition }}</p><p>วันที่ {{ $thaiDate($actedAt) }}</p>
                        @else<p class="dotted"></p><p>(ลงชื่อ)........................................................</p><p>ตำแหน่ง {{ $defaultPosition }}</p><p>วันที่........../................../..........</p>@endif
                    </div>
                @endforeach
                <div class="approval-block"><h2>คำสั่ง</h2><p class="command"><span>{{ $leave->nayok_status === 'approved' ? '☑' : '□' }} อนุญาต</span><span>□ ไม่อนุญาต</span></p>
                    @if($leave->nayok_signature)@if($leave->nayok_comment)<p class="approval-comment">“{{ $leave->nayok_comment }}”</p>@endif<img src="{{ $leave->nayok_signature }}" alt="ลายเซ็นนายก อบต."><p>({{ $leave->nayok?->name ?? '-' }})</p><p>ตำแหน่ง {{ $leave->nayok?->position ?: 'นายกองค์การบริหารส่วนตำบลพร่อน' }}</p><p>วันที่ {{ $thaiDate($leave->nayok_at) }}</p>
                    @else<p>(ลงชื่อ)........................................................</p><p>ตำแหน่ง นายกองค์การบริหารส่วนตำบลพร่อน</p><p>วันที่........../................../..........</p>@endif
                </div>
            </section>
        </div>
        @if($leave->delegate)<section class="paper-supporting"><b>ผู้รับมอบหมายงาน:</b> {{ $leave->delegate->name }} ({{ $leave->delegate->position ?: 'ไม่ระบุตำแหน่ง' }})</section>@endif
    </article>

    @if($leave->status === 'REJECTED')<div class="alert alert-danger review-alert mt-3"><b>ใบลานี้ไม่อนุญาต:</b> {{ $leave->reject_reason ?: 'ไม่ระบุเหตุผล' }}</div>@endif

    @if($leave->attachments->isNotEmpty())
        <section class="attachment-panel"><h5><i class="fas fa-paperclip me-2"></i>เอกสารแนบ</h5><p>เอกสารประกอบใบลา {{ $leave->attachments->count() }} ไฟล์</p><div class="attachment-list">
            @foreach($leave->attachments as $attachment)
                @php $url = route('leaves.attachments.download', [$leave->id, $attachment->id]); $isImage = str_starts_with((string) $attachment->mime_type, 'image/'); $isPdf = $attachment->mime_type === 'application/pdf' || str_ends_with(strtolower($attachment->original_name), '.pdf'); @endphp
                <article class="attachment-card"><div class="attachment-card-title"><strong>{{ $attachment->original_name }}</strong><a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">เปิดเอกสาร</a></div>
                    @if($isImage)<a href="{{ $url }}" target="_blank" class="attachment-preview-image"><img src="{{ $url }}" alt="{{ $attachment->original_name }}"></a>
                    @elseif($isPdf)<iframe src="{{ $url }}#toolbar=1&navpanes=0" title="{{ $attachment->original_name }}"></iframe>
                    @else<a href="{{ $url }}" target="_blank" class="attachment-fallback"><i class="fas fa-file-word"></i><span>เปิดหรือดาวน์โหลดเอกสาร</span></a>@endif
                </article>
            @endforeach
        </div></section>
    @endif

    @if($isDelegate)
        <section class="review-action-panel"><h5>การรับมอบหมายงาน</h5><p>คุณถูกระบุให้ปฏิบัติหน้าที่แทน {{ $leave->user->name }} ระหว่างลา</p><form action="{{ route('leaves.delegateAction', $leave->id) }}" method="POST">@csrf<textarea name="decline_reason" class="form-control mb-3" rows="2" placeholder="ระบุเหตุผลหากไม่สามารถรับมอบงานได้"></textarea><div class="d-flex justify-content-end gap-2"><button name="action" value="decline" class="btn btn-outline-danger">ปฏิเสธ</button><button name="action" value="accept" class="btn btn-warning fw-bold">รับมอบงาน</button></div></form></section>
    @elseif($canApprove)
        <section class="review-action-panel"><h5><i class="fas fa-pen-nib me-2"></i>{{ $actionTitle }}</h5><form action="{{ route('leaves.reviewAction', $leave->id) }}" method="POST">@csrf<div class="row g-3"><div class="col-md-8"><label class="form-label">ความเห็น / เหตุผลกรณีไม่อนุญาต</label><textarea name="reject_reason" id="reject_reason" class="form-control" rows="3"></textarea></div><div class="col-md-4"><label class="form-label">รหัส PIN ลายเซ็น</label><input type="password" name="pin" class="form-control text-center" maxlength="6" required autocomplete="off"></div></div><div class="d-flex justify-content-end gap-2 mt-3"><button type="submit" name="is_approved" value="0" class="btn btn-outline-danger" onclick="return requireReason()">{{ $rejectText }}</button><button type="submit" name="is_approved" value="1" class="btn btn-teal fw-bold">{{ $approveText }}</button></div></form></section>
    @endif
</div>

<style>
@font-face{font-family:'TH Sarabun New';src:url('{{ asset('fonts/THSarabunNew.ttf') }}')}@font-face{font-family:'TH Sarabun New';src:url('{{ asset('fonts/THSarabunNew_Bold.ttf') }}');font-weight:700}
.leave-review-document-page{min-height:90vh;padding:1.5rem clamp(.75rem,3vw,2rem) 3rem;background:#e9edf0}.review-toolbar,.review-alert,.attachment-panel,.review-action-panel{width:min(100%,210mm);margin-left:auto;margin-right:auto}.review-toolbar{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem}.review-toolbar h4{margin:0;color:#164f51;font-weight:800}.review-toolbar p{margin:.15rem 0 0;color:#667085}.leave-paper{width:min(100%,210mm);min-height:297mm;margin:0 auto;padding:18mm 16mm 15mm;border:1px solid #d0d5dd;background:#fff;box-shadow:0 12px 34px rgba(16,24,40,.14);color:#111;font-family:'TH Sarabun New',Tahoma,sans-serif;font-size:17pt;line-height:1.2}.leave-paper h1{margin:0 0 1.1rem;font-size:21pt;font-weight:400;text-align:center;text-decoration:underline}.paper-meta{width:52%;margin-left:auto}.paper-meta p{display:flex;gap:.3rem;margin:.1rem 0}.paper-meta span{flex:1;border-bottom:1px dotted #222;text-align:center}.paper-line{display:grid;grid-template-columns:3.7rem 1fr;margin:.4rem 0}.paper-line b{font-weight:400}.identity-line{display:grid;grid-template-columns:auto 1fr auto 1fr;gap:.35rem;margin:.7rem 0 .25rem 2.5rem;align-items:baseline}.identity-line strong,.reason-row strong,.contact-row strong,.date-row strong{min-width:0;padding:0 .25rem;border-bottom:1px dotted #222;font-weight:400;text-align:center}.department-line{grid-template-columns:auto 1fr;margin-top:.25rem}.leave-choice-row{display:grid;grid-template-columns:4.5rem 1fr;margin:.8rem 0 .35rem}.choice-list{display:flex;flex-wrap:wrap;gap:.2rem 1rem}.choice{display:inline-flex;align-items:center;gap:.35rem;min-width:8.7rem}.choice i{width:1.1rem;height:1.1rem;border:1px solid #111;font:700 .85rem/1rem Tahoma,sans-serif;text-align:center}.choice.selected{font-weight:700}.reason-row,.contact-row{display:grid;grid-template-columns:auto 1fr;gap:.35rem;margin:.55rem 0}.reason-row strong,.contact-row strong{text-align:left}.date-row{display:grid;grid-template-columns:auto 1fr auto 1fr auto .55fr auto;gap:.3rem;align-items:baseline;margin:.55rem 0}.paper-lower-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:1.6rem;margin-top:1rem}.leave-paper h2{margin:0 0 .35rem;font-size:16.5pt;font-weight:700;text-align:center;text-decoration:underline}.leave-paper table{width:100%;border-collapse:collapse;font-size:14pt;line-height:1.05}.leave-paper th,.leave-paper td{padding:.28rem .2rem;border:1px solid #111;text-align:center}.signature,.approval-block{text-align:center}.signature p,.approval-block p{margin:.16rem 0}.approval-signature{margin-top:1.5rem}.applicant-signature p:first-child{margin-bottom:.8rem}.approval-block{margin-top:.85rem}.approval-block img,.approval-signature img{display:block;width:auto;max-width:145px;height:48px;object-fit:contain;margin:.1rem auto}.signature-space{margin-top:1.1rem!important}.dotted{height:1rem;border-bottom:1px dotted #222}.command{display:flex;justify-content:center;gap:1.5rem;font-weight:700}.paper-supporting{margin-top:1rem;padding-top:.6rem;border-top:1px solid #999;font-size:14pt}.attachment-panel,.review-action-panel{margin-top:1.25rem;padding:1.25rem;border:1px solid #d0d5dd;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.08)}.attachment-panel h5,.review-action-panel h5{margin:0;color:#164f51;font-weight:800}.attachment-panel>p{margin:.2rem 0;color:#667085}.attachment-list{display:grid;gap:1rem;margin-top:1rem}.attachment-card{overflow:hidden;border:1px solid #d8dee5;border-radius:12px;background:#f8fafc}.attachment-card-title{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1rem;background:#fff;border-bottom:1px solid #e4e7ec}.attachment-preview-image{display:block;padding:1rem;text-align:center}.attachment-preview-image img{max-width:100%;max-height:720px}.attachment-card iframe{display:block;width:100%;height:min(72vh,760px);border:0}.attachment-fallback{display:flex;min-height:160px;align-items:center;justify-content:center;gap:1rem;text-decoration:none}.attachment-fallback i{font-size:3rem}.btn-teal{color:#fff;background:#164f51;border-color:#164f51}.btn-teal:hover{color:#fff;background:#0f3638}
.approval-comment{margin:.3rem 0 .45rem!important;padding:.28rem .45rem;border-bottom:1px dotted #777;font-weight:700;overflow-wrap:anywhere}
@media(max-width:760px){.review-toolbar{align-items:flex-start;flex-direction:column}.leave-paper{min-height:0;padding:2rem 1rem;font-size:15pt}.paper-meta{width:100%}.identity-line{grid-template-columns:auto 1fr;margin-left:0}.date-row{grid-template-columns:auto 1fr}.paper-lower-grid{grid-template-columns:1fr}.attachment-card-title{align-items:flex-start;flex-direction:column}}
@media print{.leave-review-document-page{padding:0;background:#fff}.review-toolbar,.review-alert,.attachment-panel,.review-action-panel{display:none!important}.leave-paper{width:210mm;min-height:297mm;border:0;box-shadow:none}}
</style>
<script>
// หน้าที่: บังคับให้ระบุเหตุผลเมื่อไม่อนุญาตคำขอลา
function requireReason(){const input=document.getElementById('reject_reason');if(!input||input.value.trim())return true;if(window.Swal){Swal.fire({icon:'warning',title:'กรุณาระบุเหตุผล',text:'ต้องระบุเหตุผลเมื่อเลือกไม่อนุญาต',confirmButtonColor:'#164f51'});}else{alert('กรุณาระบุเหตุผล');}input.focus();return false;}</script>
@endsection
