@extends('layouts.app')

@section('title', 'งานที่ได้รับมอบหมาย')

@section('content')
@php
    $leaveDelegations = $leaveDelegations ?? collect();
    $documents = $documents ?? collect();
    $currentUser = auth()->user();
    $pendingDocuments = $documents->filter(fn ($doc) => !in_array(strtolower((string) $doc->assignment_status), ['accepted', 'acknowledged', 'completed'], true))->count();
    $acceptedDocuments = $documents->count() - $pendingDocuments;
@endphp

<div class="assignment-page container-fluid px-3 px-lg-4 py-4">
    <div class="assignment-shell mx-auto">
        <header class="assignment-hero mb-4">
            <div class="assignment-hero__content">
                <div class="assignment-hero__icon"><i class="fas fa-clipboard-check"></i></div>
                <div>
                    <div class="assignment-hero__eyebrow">ศูนย์รวมงานของฉัน</div>
                    <h2 class="assignment-hero__title">งานที่ได้รับมอบหมาย</h2>
                    <p class="assignment-hero__subtitle">ตรวจสอบ รับดำเนินการ หรือส่งต่องานให้บุคลากรในฝ่ายเดียวกัน</p>
                </div>
            </div>
            <a href="{{ route('home') }}" class="ds-back-link assignment-back"><i class="fas fa-arrow-left"></i>กลับหน้าหลัก</a>
        </header>

        @if(session('success'))
            <div class="assignment-alert assignment-alert--success"><i class="fas fa-circle-check"></i>{{ session('success') }}</div>
        @endif
        @if(session('error') || (isset($errors) && $errors->any()))
            <div class="assignment-alert assignment-alert--danger"><i class="fas fa-circle-exclamation"></i>{{ session('error') ?: $errors->first() }}</div>
        @endif

        <section class="assignment-summary mb-4" aria-label="สรุปงาน">
            <article class="summary-card summary-card--blue"><span><i class="fas fa-layer-group"></i></span><div><strong>{{ $documents->count() }}</strong><small>งานเอกสารทั้งหมด</small></div></article>
            <article class="summary-card summary-card--amber"><span><i class="fas fa-hourglass-half"></i></span><div><strong>{{ $pendingDocuments }}</strong><small>งานที่ต้องดำเนินการ</small></div></article>
            <article class="summary-card summary-card--green"><span><i class="fas fa-check"></i></span><div><strong>{{ $acceptedDocuments }}</strong><small>รับดำเนินการแล้ว</small></div></article>
            <article class="summary-card summary-card--violet"><span><i class="fas fa-people-arrows"></i></span><div><strong>{{ $leaveDelegations->count() }}</strong><small>งานแทนรอตอบรับ</small></div></article>
        </section>

        @if($leaveDelegations->isNotEmpty())
            <section class="work-panel work-panel--leave mb-4" aria-labelledby="leave-heading">
                <div class="work-panel__header">
                    <div class="panel-heading">
                        <span class="panel-heading__icon panel-heading__icon--amber"><i class="fas fa-user-clock"></i></span>
                        <div><h3 id="leave-heading">งานแทนระหว่างการลา</h3><p>กรุณาตอบรับก่อน ระบบจึงจะส่งใบลาไปยังหัวหน้าส่วนราชการ</p></div>
                    </div>
                    <span class="panel-count panel-count--amber">{{ $leaveDelegations->count() }} งานรอตอบรับ</span>
                </div>
                <div class="task-list">
                    @foreach($leaveDelegations as $leave)
                        <article class="leave-card">
                            <div class="leave-card__person">
                                <div class="person-avatar">{{ mb_substr($leave->user?->name ?? 'ผ', 0, 1) }}</div>
                                <div class="min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <h4>{{ $leave->user?->name ?? 'ไม่ทราบชื่อ' }}</h4>
                                        <span class="soft-badge soft-badge--slate">{{ $leave->leave_type }}</span>
                                    </div>
                                    <div class="task-meta">
                                        <span><i class="far fa-calendar"></i>{{ \Carbon\Carbon::parse($leave->start_date)->addYears(543)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($leave->end_date)->addYears(543)->format('d/m/Y') }}</span>
                                        <span><i class="far fa-clock"></i>{{ $leave->total_days }} วัน</span>
                                        @if($leave->user?->department)<span><i class="fas fa-building"></i>{{ $leave->user->department }}</span>@endif
                                    </div>
                                    @if($leave->reason)<p class="task-reason"><strong>เหตุผล:</strong> {{ $leave->reason }}</p>@endif
                                </div>
                            </div>
                            <div class="task-actions">
                                <a href="{{ route('leaves.show', $leave->id) }}" class="task-btn task-btn--neutral"><i class="fas fa-eye"></i>ดูรายละเอียด</a>
                                <form action="{{ route('leaves.delegateAction', $leave->id) }}" method="POST">@csrf<button name="action" value="accept" class="task-btn task-btn--success"><i class="fas fa-check"></i>รับงานแทน</button></form>
                                <details class="action-menu action-menu--danger">
                                    <summary><i class="fas fa-xmark"></i>ปฏิเสธ</summary>
                                    <form action="{{ route('leaves.delegateAction', $leave->id) }}" method="POST" class="action-menu__panel">
                                        @csrf
                                        <label for="decline_{{ $leave->id }}">เหตุผลที่ไม่สามารถรับงานได้</label>
                                        <textarea id="decline_{{ $leave->id }}" name="decline_reason" class="form-control" rows="3" maxlength="1000" required placeholder="ระบุเหตุผล..."></textarea>
                                        <button name="action" value="decline" class="task-btn task-btn--danger w-100">ยืนยันปฏิเสธ</button>
                                    </form>
                                </details>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="work-panel" aria-labelledby="document-heading">
            <div class="work-panel__header">
                <div class="panel-heading">
                    <span class="panel-heading__icon panel-heading__icon--blue"><i class="fas fa-file-circle-check"></i></span>
                    <div>
                        <h3 id="document-heading">งานเอกสารที่ได้รับมอบหมาย</h3>
                        <p>รายการที่ส่งถึงคุณแล้ว @if($currentUser->department)<em>{{ $currentUser->department }}</em>@endif</p>
                    </div>
                </div>
                <span class="panel-count panel-count--blue">{{ $documents->count() }} รายการ</span>
            </div>

            <div class="task-list">
                @forelse($documents as $doc)
                    @php
                        $assignmentStatus = strtolower((string) $doc->assignment_status);
                        $canAct = (!config('edoc.v2.document_reads') || config('edoc.v2.write_enabled'))
                            && ($doc->assigned_user_id === auth()->id()
                            || ($currentUser->hasRole('head') && !$doc->assigned_user_id && $doc->assigned_to === $currentUser->department));
                        $statusUi = match ($assignmentStatus) {
                            'accepted', 'acknowledged' => ['success', 'รับดำเนินการแล้ว', 'fa-circle-check'],
                            'in_progress' => ['blue', 'กำลังดำเนินการ', 'fa-spinner'],
                            'completed' => ['success', 'ดำเนินการเสร็จแล้ว', 'fa-check-double'],
                            'delegated' => ['blue', 'ส่งต่อแล้ว', 'fa-share-nodes'],
                            default => ['warning', 'รอรับเรื่อง', 'fa-clock'],
                        };
                        $documentNumber = $doc->formatted_receive_number ?: ($doc->formatted_doc_number ?: ($doc->receive_number ?: $doc->doc_number));
                    @endphp
                    <article class="document-card document-card--{{ $statusUi[0] }}">
                        <div class="document-card__icon"><i class="fas fa-file-lines"></i></div>
                        <div class="document-card__body">
                            <div class="document-card__topline">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="soft-badge soft-badge--{{ $statusUi[0] }}"><i class="fas {{ $statusUi[2] }}"></i>{{ $statusUi[1] }}</span>
                                    @if($documentNumber)<span class="document-number">{{ $documentNumber }}</span>@endif
                                </div>
                                <time><i class="far fa-clock"></i>{{ optional($doc->assigned_at ?? $doc->updated_at)->format('d/m/Y H:i') ?: '-' }}</time>
                            </div>
                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="document-title">{{ $doc->title }}</a>
                            <div class="document-meta">
                                <span><i class="fas fa-building"></i><span><small>ส่วนราชการ</small>{{ $doc->assigned_to ?: '-' }}</span></span>
                                <span><i class="fas fa-user"></i><span><small>ผู้รับปัจจุบัน</small>{{ $doc->assignee?->name ?? 'หัวหน้าส่วนราชการ' }}</span></span>
                                @if($doc->delegator)<span><i class="fas fa-share"></i><span><small>ผู้ส่งต่อ</small>{{ $doc->delegator->name }}</span></span>@endif
                            </div>
                        </div>
                        <div class="document-card__actions">
                            <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="task-btn task-btn--neutral"><i class="fas fa-eye"></i>ดูเอกสาร</a>
                            @if($canAct && !in_array($assignmentStatus, ['accepted', 'acknowledged', 'completed'], true))
                                <form action="{{ route('documents.assignment_action', $doc->uuid ?? $doc->id) }}" method="POST">@csrf<input type="hidden" name="action" value="accept"><button class="task-btn task-btn--success"><i class="fas fa-check"></i>รับดำเนินการเอง</button></form>
                                @if($subordinates->isNotEmpty())
                                    <details class="action-menu action-menu--blue">
                                        <summary><i class="fas fa-share-nodes"></i>ส่งต่อบุคลากร</summary>
                                        <form action="{{ route('documents.assignment_action', $doc->uuid ?? $doc->id) }}" method="POST" class="action-menu__panel">
                                            @csrf<input type="hidden" name="action" value="delegate">
                                            <label for="delegate_{{ $doc->id }}">เลือกบุคลากรในฝ่าย</label>
                                            <select id="delegate_{{ $doc->id }}" name="delegate_user_id" class="form-select" required>
                                                <option value="">กรุณาเลือกผู้รับงาน...</option>
                                                @foreach($subordinates as $subordinate)<option value="{{ $subordinate->id }}">{{ $subordinate->name }}{{ $subordinate->position ? ' — '.$subordinate->position : '' }}</option>@endforeach
                                            </select>
                                            <button class="task-btn task-btn--primary w-100"><i class="fas fa-paper-plane"></i>ยืนยันส่งต่อ</button>
                                        </form>
                                    </details>
                                @endif
                            @elseif(in_array($assignmentStatus, ['accepted', 'acknowledged'], true))
                                <div class="action-finished"><i class="fas fa-check-double"></i>{{ $doc->assignee?->name ?? 'คุณ' }} รับเรื่องแล้ว</div>
                            @elseif($doc->assignee)
                                <div class="action-finished action-finished--blue"><i class="fas fa-share"></i>ส่งต่อให้ {{ $doc->assignee->name }}</div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="assignment-empty">
                        <span><i class="fas fa-inbox"></i></span><h4>ยังไม่มีงานเอกสารที่ได้รับมอบหมาย</h4>
                        <p>เมื่อมีเอกสารส่งถึงคุณ รายการจะแสดงในส่วนนี้โดยอัตโนมัติ</p>
                        <a href="{{ route('home') }}" class="task-btn task-btn--primary"><i class="fas fa-house"></i>กลับหน้าแดชบอร์ด</a>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<style>
    .assignment-page{--ap-blue:#087fba;--ap-dark:#075985;--ap-border:#dce8ee;--ap-text:#172b3a;--ap-muted:#64748b;background:#f7fbfa;min-height:calc(100vh - 64px)}
    .assignment-shell{max-width:1240px}.assignment-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:24px 26px;background:linear-gradient(135deg,#fff,#f0f9fc);border:1px solid var(--ap-border);border-radius:20px;box-shadow:0 8px 26px rgba(15,48,68,.06)}
    .assignment-hero__content{display:flex;align-items:center;gap:16px;min-width:0}.assignment-hero__icon{width:58px;height:58px;flex:0 0 58px;display:grid;place-items:center;color:#fff;background:linear-gradient(145deg,#0ea5e9,#0876ad);border-radius:17px;box-shadow:0 9px 22px rgba(2,132,199,.23);font-size:24px}.assignment-hero__eyebrow{color:var(--ap-blue);font-size:12px;font-weight:800}.assignment-hero__title{margin:2px 0;color:var(--ap-dark);font-size:clamp(1.4rem,2.4vw,2rem);font-weight:800}.assignment-hero__subtitle{margin:0;color:var(--ap-muted);font-size:.9rem}.assignment-back{flex:0 0 auto;background:#fff}
    .assignment-alert{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding:13px 16px;border-left:4px solid;border-radius:12px;box-shadow:0 4px 14px rgba(15,23,42,.05)}.assignment-alert--success{color:#065f46;background:#ecfdf5;border-color:#10b981}.assignment-alert--danger{color:#991b1b;background:#fef2f2;border-color:#ef4444}
    .assignment-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.summary-card{display:flex;align-items:center;gap:13px;padding:18px;background:#fff;border:1px solid var(--ap-border);border-top:3px solid;border-radius:16px;box-shadow:0 5px 17px rgba(15,23,42,.045)}.summary-card>span{width:43px;height:43px;flex:0 0 43px;display:grid;place-items:center;border-radius:12px}.summary-card strong,.summary-card small{display:block}.summary-card strong{color:var(--ap-text);font-size:1.5rem;line-height:1.1}.summary-card small{margin-top:4px;color:var(--ap-muted);font-size:.76rem;font-weight:700}.summary-card--blue{border-top-color:#0ea5e9}.summary-card--blue>span{color:#0369a1;background:#e0f2fe}.summary-card--amber{border-top-color:#f59e0b}.summary-card--amber>span{color:#b45309;background:#fef3c7}.summary-card--green{border-top-color:#10b981}.summary-card--green>span{color:#047857;background:#d1fae5}.summary-card--violet{border-top-color:#8b5cf6}.summary-card--violet>span{color:#6d28d9;background:#ede9fe}
    .work-panel{overflow:visible;background:#fff;border:1px solid var(--ap-border);border-radius:20px;box-shadow:0 7px 24px rgba(15,23,42,.055)}.work-panel--leave{border-color:#f3d991}.work-panel__header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:19px 21px;border-bottom:1px solid #e8eef2}.panel-heading{display:flex;align-items:center;gap:12px;min-width:0}.panel-heading__icon{width:42px;height:42px;flex:0 0 42px;display:grid;place-items:center;border-radius:12px}.panel-heading__icon--blue{color:#0369a1;background:#e0f2fe}.panel-heading__icon--amber{color:#b45309;background:#fef3c7}.panel-heading h3{margin:0 0 3px;color:var(--ap-text);font-size:1.05rem;font-weight:800}.panel-heading p{margin:0;color:var(--ap-muted);font-size:.79rem}.panel-heading em{display:inline-flex;margin-left:5px;padding:2px 7px;color:#0369a1;background:#e0f2fe;border-radius:999px;font-style:normal;font-weight:700}.panel-count{flex:0 0 auto;padding:6px 11px;border-radius:999px;font-size:.75rem;font-weight:800}.panel-count--blue{color:#0369a1;background:#e0f2fe}.panel-count--amber{color:#92400e;background:#fef3c7}.task-list{display:grid;gap:12px;padding:14px}
    .leave-card{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:18px;padding:16px;background:#fffdf7;border:1px solid #f4e2ad;border-radius:15px}.leave-card__person{display:flex;align-items:flex-start;gap:12px;min-width:0}.person-avatar{width:45px;height:45px;flex:0 0 45px;display:grid;place-items:center;color:#92400e;background:#fef3c7;border-radius:13px;font-weight:800}.leave-card h4{margin:0;color:var(--ap-text);font-size:.98rem;font-weight:800}.task-meta{display:flex;flex-wrap:wrap;gap:6px 14px;color:var(--ap-muted);font-size:.77rem}.task-meta span{display:inline-flex;align-items:center;gap:5px}.task-meta i{color:#94a3b8}.task-reason{margin:7px 0 0;color:#475569;font-size:.81rem}.task-actions{position:relative;display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:7px}
    .task-btn,.action-menu summary{min-height:38px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:8px 13px;border:1px solid transparent;border-radius:10px;font-size:.79rem;font-weight:800;text-decoration:none;cursor:pointer}.task-btn--neutral{color:#334155;background:#fff;border-color:#cbd5e1}.task-btn--success{color:#fff;background:#059669}.task-btn--primary{color:#fff;background:#0284c7}.task-btn--danger{color:#fff;background:#dc2626}.task-btn:hover{filter:brightness(.95);color:inherit}.task-btn--success:hover,.task-btn--primary:hover,.task-btn--danger:hover{color:#fff}
    .action-menu{position:relative}.action-menu summary{list-style:none}.action-menu summary::-webkit-details-marker{display:none}.action-menu--danger summary{color:#b91c1c;background:#fff;border-color:#fecaca}.action-menu--blue summary{color:#0369a1;background:#f0f9ff;border-color:#bae6fd}.action-menu__panel{position:absolute;z-index:20;right:0;top:calc(100% + 7px);width:min(340px,86vw);display:grid;gap:9px;padding:14px;color:#334155;background:#fff;border:1px solid var(--ap-border);border-radius:13px;box-shadow:0 14px 35px rgba(15,23,42,.16)}.action-menu__panel label{font-size:.78rem;font-weight:800}
    .document-card{display:grid;grid-template-columns:auto minmax(0,1fr) minmax(190px,auto);align-items:center;gap:15px;padding:17px;background:#fff;border:1px solid #e3ebef;border-left:4px solid #f59e0b;border-radius:15px;transition:.18s}.document-card:hover,.leave-card:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(15,23,42,.07)}.document-card--success{border-left-color:#10b981}.document-card--blue{border-left-color:#0ea5e9}.document-card__icon{width:46px;height:46px;display:grid;place-items:center;color:#087fba;background:#e8f7fc;border-radius:13px;font-size:18px}.document-card__body{min-width:0}.document-card__topline{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:7px}.document-card__topline time{color:#94a3b8;font-size:.71rem;white-space:nowrap}.document-card__topline time i{margin-right:5px}.soft-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;font-size:.69rem;font-weight:800}.soft-badge--warning{color:#a16207;background:#fef3c7}.soft-badge--success{color:#047857;background:#d1fae5}.soft-badge--blue{color:#0369a1;background:#e0f2fe}.soft-badge--slate{color:#475569;background:#f1f5f9}.document-number{color:#64748b;font-size:.73rem;font-weight:700}.document-title{display:block;margin-bottom:10px;color:var(--ap-text);font-size:1rem;font-weight:800;line-height:1.45;text-decoration:none;overflow-wrap:anywhere}.document-title:hover{color:var(--ap-blue)}.document-meta{display:flex;flex-wrap:wrap;gap:9px 20px}.document-meta>span{display:flex;align-items:center;gap:8px;color:#334155;font-size:.78rem}.document-meta>span>i{color:#94a3b8}.document-meta small{display:block;color:#94a3b8;font-size:.65rem}.document-card__actions{position:relative;display:flex;align-items:stretch;justify-content:flex-end;flex-direction:column;gap:7px;min-width:190px}.document-card__actions form,.document-card__actions button,.document-card__actions>a,.document-card__actions summary{width:100%}.action-finished{display:flex;align-items:center;justify-content:center;gap:7px;padding:9px;color:#047857;background:#ecfdf5;border-radius:10px;font-size:.76rem;font-weight:800}.action-finished--blue{color:#0369a1;background:#f0f9ff}
    .assignment-empty{display:flex;align-items:center;flex-direction:column;padding:52px 20px;text-align:center}.assignment-empty>span{width:70px;height:70px;display:grid;place-items:center;margin-bottom:14px;color:#94a3b8;background:#f1f5f9;border-radius:22px;font-size:28px}.assignment-empty h4{margin:0 0 5px;color:#334155;font-size:1rem;font-weight:800}.assignment-empty p{margin:0 0 18px;color:#94a3b8;font-size:.81rem}
    @media(max-width:991.98px){.assignment-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.leave-card,.document-card{grid-template-columns:1fr}.document-card__icon{display:none}.task-actions{justify-content:flex-start}.document-card__actions{min-width:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.action-finished{grid-column:1/-1}.action-menu__panel{right:auto;left:0}}
    @media(max-width:575.98px){.assignment-hero{align-items:flex-start;flex-direction:column;padding:19px}.assignment-hero__icon{width:48px;height:48px;flex-basis:48px}.assignment-back{width:100%;justify-content:center}.assignment-summary{gap:9px}.summary-card{align-items:flex-start;flex-direction:column;padding:13px}.summary-card>span{width:36px;height:36px;flex-basis:36px}.work-panel__header{align-items:flex-start;flex-direction:column;padding:16px}.panel-count{align-self:flex-start}.panel-heading__icon{display:none}.task-list{padding:9px}.leave-card,.document-card{padding:14px}.task-actions,.document-card__actions{display:flex;align-items:stretch;flex-direction:column;width:100%}.task-actions>*,.task-actions form,.task-actions button,.task-actions a,.task-actions summary{width:100%}.document-card__topline{align-items:flex-start;flex-direction:column}.document-meta{flex-direction:column;gap:8px}.action-menu__panel{position:fixed;left:12px;right:12px;top:auto;bottom:12px;width:auto}}
</style>
@endsection
