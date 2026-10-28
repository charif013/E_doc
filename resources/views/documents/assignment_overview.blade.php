{{-- View: ภาพรวมความคืบหน้างานที่ผู้บริหารหรือหัวหน้าส่วนมอบหมาย --}}
@extends('layouts.app')

@section('title', 'ภาพรวมงานที่มอบหมาย')

@section('content')
<div class="assignment-overview-page container-fluid px-3 px-lg-4 py-4">
    <div class="assignment-overview-shell mx-auto">
        <header class="assignment-overview-hero mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="assignment-overview-hero__icon"><i class="fas fa-chart-line"></i></span>
                <div>
                    <div class="assignment-overview-hero__eyebrow">ผู้บริหารและหัวหน้าส่วนราชการ</div>
                    <h2>ภาพรวมความคืบหน้างานที่มอบหมาย</h2>
                    <p>ติดตามสถานะ ผู้รับงาน และรายงานล่าสุดของงานที่อยู่ในความรับผิดชอบ</p>
                </div>
            </div>
            <a href="{{ route('home') }}" class="btn btn-light border rounded-pill px-3 fw-bold"><i class="fas fa-arrow-left me-1"></i>กลับหน้าหลัก</a>
        </header>

        <section class="assignment-overview-summary mb-4" aria-label="สรุปงานที่มอบหมาย">
            <article class="assignment-overview-stat assignment-overview-stat--blue"><span><i class="fas fa-layer-group"></i></span><div><strong>{{ $summary['total'] }}</strong><small>งานที่ติดตามได้ทั้งหมด</small></div></article>
            <article class="assignment-overview-stat assignment-overview-stat--amber"><span><i class="fas fa-hourglass-half"></i></span><div><strong>{{ $summary['pending'] }}</strong><small>รอรับงาน</small></div></article>
            <article class="assignment-overview-stat assignment-overview-stat--sky"><span><i class="fas fa-spinner"></i></span><div><strong>{{ $summary['in_progress'] }}</strong><small>กำลังดำเนินการ</small></div></article>
            <article class="assignment-overview-stat assignment-overview-stat--green"><span><i class="fas fa-check-double"></i></span><div><strong>{{ $summary['completed'] }}</strong><small>ดำเนินการเสร็จสิ้น</small></div></article>
            <article class="assignment-overview-stat assignment-overview-stat--violet"><span><i class="fas fa-percent"></i></span><div><strong>{{ $summary['average_progress'] !== null ? $summary['average_progress'].'%' : '-' }}</strong><small>ความคืบหน้าเฉลี่ย</small></div></article>
        </section>

        <section class="assignment-overview-panel">
            <div class="assignment-overview-panel__header">
                <div>
                    <h3><i class="fas fa-list-check me-2"></i>รายการงานที่มอบหมาย</h3>
                    <p>เรียงตามรายการที่มีการอัปเดตล่าสุด</p>
                </div>
                <span class="badge rounded-pill text-bg-primary px-3 py-2">{{ $assignments->groupBy(fn ($assignment) => $assignment->document_id ?: 'assignment-'.$assignment->id)->count() }} เอกสาร · {{ $assignments->count() }} งาน</span>
            </div>

            <div class="assignment-overview-list">
                @forelse($assignments->groupBy(fn ($assignment) => $assignment->document_id ?: 'assignment-'.$assignment->id) as $documentAssignments)
                    @php
                        $document = $documentAssignments->first()->document;
                        $documentNumber = $document?->formatted_receive_number ?: ($document?->formatted_doc_number ?: ($document?->receive_number ?: $document?->doc_number));
                        $latestUpdatedAt = $documentAssignments->map(function ($assignment) {
                            $latestProgress = $assignment->progressUpdates->first();
                            return $latestProgress?->created_at ?: $assignment->acknowledged_at ?: $assignment->assigned_at ?: $assignment->updated_at;
                        })->filter()->sortDesc()->first();
                    @endphp
                    <article class="assignment-overview-item assignment-overview-document">
                        <div class="assignment-overview-item__status assignment-overview-item__status--primary"><i class="fas fa-file-lines"></i></div>
                        <div class="assignment-overview-item__body">
                            <div class="assignment-overview-item__topline">
                                <span class="assignment-overview-item__count"><i class="fas fa-user-group"></i>{{ $documentAssignments->count() }} ผู้รับงาน</span>
                                <small><i class="far fa-clock me-1"></i>{{ $latestUpdatedAt?->format('d/m/Y H:i') ?: '-' }}</small>
                            </div>
                            <a href="{{ route('documents.show', $document?->uuid ?? $document?->id) }}" class="assignment-overview-item__title">{{ $document?->title ?? 'ไม่พบเอกสาร' }}</a>
                            <div class="assignment-overview-item__meta">
                                @if($documentNumber)<span><i class="fas fa-hashtag"></i>{{ $documentNumber }}</span>@endif
                            </div>
                            <div class="assignment-overview-assignees">
                                @foreach($documentAssignments as $assignment)
                                    @php
                                        $status = strtolower((string) $assignment->status->value);
                                        $latestProgress = $assignment->progressUpdates->first();
                                        $progress = $status === 'completed' ? 100 : $latestProgress?->progress_percent;
                                        [$statusClass, $statusLabel] = match ($status) {
                                            'completed' => ['success', 'เสร็จสิ้น'],
                                            'in_progress' => ['primary', 'กำลังดำเนินการ'],
                                            'acknowledged' => ['info', 'รับงานแล้ว'],
                                            default => ['warning', 'รอรับงาน'],
                                        };
                                    @endphp
                                    <section class="assignment-overview-assignee">
                                        <div class="assignment-overview-assignee__head">
                                            <div><span class="badge text-bg-{{ $statusClass }} rounded-pill">{{ $statusLabel }}</span><strong><i class="fas fa-user"></i>{{ $assignment->assignee?->name ?? $assignment->unit?->name ?? 'รอระบุผู้รับ' }}</strong></div>
                                            <span><i class="fas fa-share-nodes"></i>มอบหมายโดย: {{ $assignment->delegator?->name ?? $assignment->assigner?->name ?? '-' }}</span>
                                        </div>
                                        @if($latestProgress)
                                            <div class="assignment-overview-item__progress">
                                                <div class="d-flex justify-content-between gap-3"><span>{{ $latestProgress->note ?: 'ไม่มีรายละเอียดเพิ่มเติม' }}</span><strong>{{ $progress ?? '-' }}{{ $progress !== null ? '%' : '' }}</strong></div>
                                                @if($progress !== null)<div class="progress mt-2" role="progressbar" aria-label="ความคืบหน้า" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-{{ $statusClass }}" style="width: {{ $progress }}%"></div></div>@endif
                                                @if($latestProgress->attachment_path)<a href="{{ route('documents.assignment_progress_attachment', [$document?->uuid ?? $document?->id, $latestProgress->id]) }}" class="assignment-overview-item__attachment"><i class="fas fa-paperclip"></i>{{ $latestProgress->attachment_original_name ?: 'ดาวน์โหลดไฟล์แนบ' }}</a>@endif
                                            </div>
                                        @elseif($status === 'acknowledged')
                                            <div class="assignment-overview-item__progress assignment-overview-item__progress--muted">ผู้รับงานตอบรับแล้ว แต่ยังไม่ได้ส่งรายงานความคืบหน้า</div>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                        <a href="{{ route('documents.show', $document?->uuid ?? $document?->id) }}" class="btn btn-outline-primary btn-sm assignment-overview-item__open"><i class="fas fa-arrow-up-right-from-square me-1"></i>เปิดเอกสาร</a>
                    </article>
                @empty
                    <div class="assignment-overview-empty">
                        <span><i class="fas fa-clipboard-check"></i></span>
                        <h4>ยังไม่มีงานที่ต้องติดตาม</h4>
                        <p>งานที่มอบหมายโดยคุณ หรือส่งถึงหน่วยงานของคุณ จะปรากฏที่หน้านี้</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<style>
    .assignment-overview-page{--ao-text:#172b3a;--ao-muted:#64748b;--ao-border:#dce8ee;--ao-blue:#087fba;background:#f7fbfa;min-height:calc(100vh - 64px)}.assignment-overview-shell{max-width:1320px}.assignment-overview-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:25px 27px;color:#fff;background:linear-gradient(135deg,#075985,#0ea5e9);border-radius:20px;box-shadow:0 10px 25px rgba(2,132,199,.18)}.assignment-overview-hero__icon{width:58px;height:58px;display:grid;place-items:center;flex:0 0 58px;color:#0369a1;background:#e0f2fe;border-radius:17px;font-size:24px}.assignment-overview-hero__eyebrow{font-size:.73rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;opacity:.82}.assignment-overview-hero h2{margin:2px 0;font-size:clamp(1.35rem,2.4vw,1.9rem);font-weight:800}.assignment-overview-hero p{margin:0;font-size:.88rem;opacity:.88}.assignment-overview-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:13px}.assignment-overview-stat{display:flex;align-items:center;gap:12px;padding:16px;background:#fff;border:1px solid var(--ao-border);border-top:3px solid;border-radius:16px;box-shadow:0 4px 15px rgba(15,23,42,.04)}.assignment-overview-stat>span{width:42px;height:42px;display:grid;place-items:center;flex:0 0 42px;border-radius:12px}.assignment-overview-stat strong,.assignment-overview-stat small{display:block}.assignment-overview-stat strong{color:var(--ao-text);font-size:1.4rem;line-height:1.1}.assignment-overview-stat small{margin-top:4px;color:var(--ao-muted);font-size:.71rem;font-weight:700}.assignment-overview-stat--blue{border-color:#0284c7}.assignment-overview-stat--blue>span{color:#0369a1;background:#e0f2fe}.assignment-overview-stat--amber{border-color:#f59e0b}.assignment-overview-stat--amber>span{color:#a16207;background:#fef3c7}.assignment-overview-stat--sky{border-color:#0ea5e9}.assignment-overview-stat--sky>span{color:#0369a1;background:#e0f2fe}.assignment-overview-stat--green{border-color:#10b981}.assignment-overview-stat--green>span{color:#047857;background:#d1fae5}.assignment-overview-stat--violet{border-color:#8b5cf6}.assignment-overview-stat--violet>span{color:#6d28d9;background:#ede9fe}.assignment-overview-panel{overflow:hidden;background:#fff;border:1px solid var(--ao-border);border-radius:20px;box-shadow:0 7px 24px rgba(15,23,42,.055)}.assignment-overview-panel__header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid #e8eef2}.assignment-overview-panel__header h3{margin:0 0 3px;color:var(--ao-text);font-size:1.07rem;font-weight:800}.assignment-overview-panel__header p{margin:0;color:var(--ao-muted);font-size:.78rem}.assignment-overview-list{display:grid;gap:12px;padding:14px}.assignment-overview-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:start;gap:14px;padding:16px;border:1px solid #e3ebef;border-radius:15px}.assignment-overview-item__status{width:43px;height:43px;display:grid;place-items:center;border-radius:13px}.assignment-overview-item__status--success{color:#047857;background:#d1fae5}.assignment-overview-item__status--primary{color:#0369a1;background:#e0f2fe}.assignment-overview-item__status--info{color:#0369a1;background:#e0f2fe}.assignment-overview-item__status--warning{color:#a16207;background:#fef3c7}.assignment-overview-item__body{min-width:0}.assignment-overview-item__topline{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px}.assignment-overview-item__topline small{color:#94a3b8;font-size:.71rem}.assignment-overview-item__title{display:block;margin-bottom:9px;color:var(--ao-text);font-size:.98rem;font-weight:800;text-decoration:none;overflow-wrap:anywhere}.assignment-overview-item__title:hover{color:#0284c7}.assignment-overview-item__meta{display:flex;flex-wrap:wrap;gap:7px 15px;color:#64748b;font-size:.75rem}.assignment-overview-item__meta span{display:inline-flex;align-items:center;gap:5px}.assignment-overview-item__meta i{color:#94a3b8}.assignment-overview-item__progress{margin-top:11px;padding:10px 12px;color:#475569;background:#f8fafc;border-radius:10px;font-size:.76rem}.assignment-overview-item__progress--muted{color:#64748b}.assignment-overview-item__attachment{display:inline-flex;align-items:center;gap:5px;max-width:100%;margin-top:8px;color:#0369a1;font-weight:700;overflow:hidden;text-decoration:none;text-overflow:ellipsis;white-space:nowrap}.assignment-overview-item__attachment:hover{text-decoration:underline}.assignment-overview-item__open{white-space:nowrap}.assignment-overview-empty{padding:55px 20px;text-align:center}.assignment-overview-empty>span{width:70px;height:70px;display:grid;place-items:center;margin:0 auto 14px;color:#94a3b8;background:#f1f5f9;border-radius:22px;font-size:28px}.assignment-overview-empty h4{margin:0 0 5px;color:#334155;font-size:1rem;font-weight:800}.assignment-overview-empty p{margin:0;color:#94a3b8;font-size:.81rem}@media(max-width:1199.98px){.assignment-overview-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:767.98px){.assignment-overview-hero{align-items:flex-start;flex-direction:column;padding:20px}.assignment-overview-hero .btn{width:100%}.assignment-overview-summary{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.assignment-overview-stat{align-items:flex-start;flex-direction:column;padding:13px}.assignment-overview-stat>span{width:36px;height:36px;flex-basis:36px}.assignment-overview-panel__header{align-items:flex-start;flex-direction:column}.assignment-overview-item{grid-template-columns:1fr}.assignment-overview-item__status{display:none}.assignment-overview-item__open{width:100%}.assignment-overview-item__topline{align-items:flex-start;flex-direction:column}.assignment-overview-item__meta{flex-direction:column;gap:7px}}
    .assignment-overview-item__count{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;color:#0369a1;background:#e0f2fe;border-radius:999px;font-size:.7rem;font-weight:800}.assignment-overview-assignees{display:grid;gap:8px;margin-top:12px}.assignment-overview-assignee{padding:11px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:11px}.assignment-overview-assignee__head{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#64748b;font-size:.73rem}.assignment-overview-assignee__head>div{display:flex;align-items:center;gap:8px;min-width:0}.assignment-overview-assignee__head strong{color:#334155;font-size:.78rem;white-space:nowrap}.assignment-overview-assignee__head strong i,.assignment-overview-assignee__head>span i{margin-right:5px;color:#94a3b8}.assignment-overview-assignee__head>span{white-space:nowrap}.assignment-overview-assignee .assignment-overview-item__progress{margin-top:9px;background:#fff;border:1px solid #edf2f5}
    @media(max-width:767.98px){.assignment-overview-assignee__head{align-items:flex-start;flex-direction:column}.assignment-overview-assignee__head>div{flex-wrap:wrap}.assignment-overview-assignee__head>span{white-space:normal}}
</style>
@endsection
