@extends('layouts.app')

@section('title', 'ประวัติการใช้งานระบบ')

@section('content')
@php
    $activeFilterCount = collect($filters)->filter(fn ($value) => filled($value))->count();
    $eventMeta = function (?string $event): array {
        $event = (string) $event;
        return match (true) {
            str_contains($event, 'deleted'), str_contains($event, 'failed') => ['danger', 'fa-triangle-exclamation'],
            str_contains($event, 'created'), str_contains($event, 'restored') => ['success', 'fa-circle-check'],
            str_contains($event, 'updated'), str_contains($event, 'changed') => ['warning', 'fa-pen'],
            str_contains($event, 'viewed'), str_contains($event, 'opened'), str_contains($event, 'downloaded') => ['info', 'fa-eye'],
            default => ['neutral', 'fa-shield-halved'],
        };
    };
    $targetName = fn ($type) => match (class_basename((string) $type)) {
        'Document' => 'เอกสาร',
        'User' => 'ผู้ใช้งาน',
        'LeaveRequest' => 'ใบลา',
        'RoomBooking' => 'การจองห้อง',
        'Room' => 'ห้องประชุม',
        default => class_basename((string) $type) ?: 'ระบบ',
    };
@endphp

<style>
    .audit-page { max-width: 1600px; margin-inline: auto; }
    .audit-page .min-w-0 { min-width: 0; }
    .audit-hero { padding: 1.25rem 1.4rem; border: 1px solid #dbeafe; border-radius: 18px; background: linear-gradient(135deg, #f0f9ff 0%, #fff 64%, #f0fdf4 100%); }
    .audit-hero__icon { width: 48px; height: 48px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 14px; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); box-shadow: 0 8px 20px rgba(2,132,199,.2); }
    .audit-title { font-size: 1.45rem; font-weight: 700; color: var(--text-primary); }
    .audit-readonly { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .65rem; border: 1px solid var(--success-border); border-radius: var(--radius-pill); color: var(--success-text); background: var(--success-bg); font-size: .78rem; font-weight: 700; white-space: nowrap; }
    .audit-filter-card { overflow: hidden; }
    .audit-filter-heading { display: flex; align-items: center; gap: .55rem; font-weight: 700; }
    .audit-filter-count { min-width: 22px; height: 22px; display: inline-grid; place-items: center; padding-inline: .35rem; border-radius: 999px; color: #fff; background: var(--primary); font-size: .72rem; }
    .audit-label { margin-bottom: .35rem; color: var(--text-secondary); font-size: .84rem; font-weight: 700; }
    .audit-summary { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; border-bottom: 1px solid var(--border-color); background: #f8fafc; }
    .audit-total { font-size: .9rem; color: var(--text-secondary); }
    .audit-total strong { color: var(--primary-dark); font-size: 1.05rem; }
    .audit-table { min-width: 1080px; }
    .audit-table thead th { padding: .8rem 1rem; border-bottom: 1px solid var(--border-color); color: #64748b; background: #fff; font-size: .76rem; letter-spacing: .02em; white-space: nowrap; }
    .audit-table tbody td { padding: .95rem 1rem; border-color: #eef2f7; vertical-align: top; }
    .audit-time { font-weight: 700; color: var(--text-primary); white-space: nowrap; }
    .audit-date { color: var(--text-muted); font-size: .78rem; }
    .audit-avatar { width: 34px; height: 34px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 10px; color: var(--primary-dark); background: var(--primary-light); font-weight: 700; }
    .audit-actor-name { max-width: 180px; overflow: hidden; color: var(--text-primary); font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .audit-actor-email { max-width: 180px; overflow: hidden; color: var(--text-muted); font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }
    .audit-event { display: inline-flex; align-items: center; gap: .4rem; max-width: 260px; padding: .35rem .6rem; border: 1px solid; border-radius: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .75rem; font-weight: 700; overflow-wrap: anywhere; }
    .audit-event--success { color: var(--success-text); background: var(--success-bg); border-color: var(--success-border); }
    .audit-event--warning { color: var(--warning-text); background: var(--warning-bg); border-color: var(--warning-border); }
    .audit-event--danger { color: var(--danger-text); background: var(--danger-bg); border-color: var(--danger-border); }
    .audit-event--info { color: var(--info-text); background: var(--info-bg); border-color: var(--info-border); }
    .audit-event--neutral { color: #475569; background: #f8fafc; border-color: #e2e8f0; }
    .audit-target { color: var(--text-primary); font-weight: 700; }
    .audit-target-id { color: var(--text-muted); font-size: .78rem; }
    .audit-detail summary { display: inline-flex; align-items: center; gap: .4rem; min-height: 34px; padding: .35rem .65rem; border: 1px solid var(--border-color); border-radius: 8px; color: var(--primary-dark); background: #fff; font-size: .8rem; font-weight: 700; cursor: pointer; list-style: none; }
    .audit-detail summary::-webkit-details-marker { display: none; }
    .audit-detail[open] summary { border-color: var(--primary); background: var(--primary-light); }
    .audit-detail__panel { width: min(560px, 70vw); margin-top: .65rem; padding: .75rem; border: 1px solid var(--border-color); border-radius: 10px; background: #f8fafc; }
    .audit-values + .audit-values { margin-top: .65rem; }
    .audit-values__label { margin-bottom: .25rem; color: #64748b; font-size: .72rem; font-weight: 700; text-transform: uppercase; }
    .audit-values pre { max-height: 220px; margin: 0; padding: .65rem; overflow: auto; border-radius: 8px; color: #334155; background: #fff; font-size: .75rem; white-space: pre-wrap; word-break: break-word; }
    .audit-empty { padding: 4rem 1rem; text-align: center; }
    .audit-empty__icon { width: 60px; height: 60px; display: grid; place-items: center; margin: 0 auto .85rem; border-radius: 18px; color: var(--primary); background: var(--primary-light); font-size: 1.4rem; }
    .audit-mobile-card { padding: 1rem; border-bottom: 1px solid var(--border-color); }
    .audit-mobile-card:last-child { border-bottom: 0; }
    .audit-mobile-meta { display: grid; grid-template-columns: 92px minmax(0, 1fr); gap: .5rem; margin-top: .9rem; font-size: .85rem; }
    .audit-mobile-meta dt { color: var(--text-muted); font-weight: 600; }
    .audit-mobile-meta dd { min-width: 0; margin: 0; color: var(--text-primary); overflow-wrap: anywhere; }
    .audit-pagination .pagination { margin-bottom: 0; flex-wrap: wrap; }
    @media (max-width: 767.98px) {
        .audit-hero { padding: 1rem; }
        .audit-hero__icon { width: 42px; height: 42px; border-radius: 12px; }
        .audit-title { font-size: 1.2rem; }
        .audit-readonly { width: 100%; justify-content: center; margin-top: .85rem; }
        .audit-summary { align-items: flex-start; flex-direction: column; }
        .audit-detail__panel { width: 100%; }
    }
</style>

<div class="container-fluid py-2 py-md-3 audit-page">
    <header class="audit-hero mb-3">
        <div class="d-md-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="audit-hero__icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></div>
                <div>
                    <h1 class="audit-title mb-1">ประวัติการใช้งานระบบ</h1>
                    <p class="mb-0 text-secondary small">ตรวจสอบกิจกรรม ผู้ดำเนินการ และการเปลี่ยนแปลงข้อมูลย้อนหลัง</p>
                </div>
            </div>
            <div class="audit-readonly" title="ข้อมูลหน้านี้แก้ไขหรือลบไม่ได้">
                <i class="fa-solid fa-lock" aria-hidden="true"></i> บันทึกเพื่อการตรวจสอบเท่านั้น
            </div>
        </div>
    </header>

    <section class="card ds-card audit-filter-card mb-3" aria-labelledby="audit-filter-title">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between gap-2">
            <div id="audit-filter-title" class="audit-filter-heading">
                <i class="fa-solid fa-sliders text-primary" aria-hidden="true"></i> ค้นหาและกรองข้อมูล
                @if($activeFilterCount)
                    <span class="audit-filter-count" aria-label="เปิดใช้ {{ $activeFilterCount }} ตัวกรอง">{{ $activeFilterCount }}</span>
                @endif
            </div>
            @if($activeFilterCount)
                <a href="{{ route('admin.audit_logs.index') }}" class="small fw-semibold text-decoration-none">ล้างตัวกรอง</a>
            @endif
        </div>
        <form method="GET" class="card-body" aria-label="ตัวกรองประวัติการใช้งาน">
            <div class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label for="event" class="audit-label">เหตุการณ์</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                        <input id="event" name="event" value="{{ $filters['event'] ?? '' }}" class="form-control border-start-0 ps-0" placeholder="เช่น document หรือ updated">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label for="user_id" class="audit-label">ผู้ดำเนินการ</label>
                    <select id="user_id" name="user_id" class="form-select">
                        <option value="">ผู้ใช้งานทั้งหมด</option>
                        @foreach($actors as $actor)
                            <option value="{{ $actor->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $actor->id)>
                                {{ $actor->name }}{{ $actor->trashed() ? ' (ปิดใช้งาน)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="from" class="audit-label">ตั้งแต่วันที่</label>
                    <input id="from" type="text" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control audit-date-picker" placeholder="เลือกวันที่เริ่มต้น" autocomplete="off">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label for="to" class="audit-label">ถึงวันที่</label>
                    <input id="to" type="text" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control audit-date-picker" placeholder="เลือกวันที่สิ้นสุด" autocomplete="off">
                </div>
                <div class="col-lg-2 d-grid">
                    <button class="ds-btn ds-btn-primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> แสดงผล</button>
                </div>
            </div>
        </form>
    </section>

    <section class="card ds-card overflow-hidden" aria-labelledby="audit-results-title">
        <div class="audit-summary">
            <div id="audit-results-title" class="audit-total">
                พบทั้งหมด <strong>{{ number_format($logs->total()) }}</strong> รายการ
                @if($logs->total())
                    <span class="d-none d-sm-inline"> · กำลังแสดง {{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }}</span>
                @endif
            </div>
            <div class="small text-muted"><i class="fa-solid fa-arrow-down-wide-short me-1" aria-hidden="true"></i> ใหม่ล่าสุดก่อน</div>
        </div>

        @if($logs->isNotEmpty())
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover audit-table mb-0">
                <thead>
                    <tr><th>วันและเวลา</th><th>ผู้ดำเนินการ</th><th>เหตุการณ์</th><th>รายการที่เกี่ยวข้อง</th><th>รายละเอียด</th><th>IP Address</th></tr>
                </thead>
                <tbody>
                @foreach($logs as $log)
                    @php([$tone, $icon] = $eventMeta($log->event))
                    <tr>
                        <td>
                            <div class="audit-time">{{ $log->created_at?->format('H:i:s') ?? '-' }}</div>
                            <div class="audit-date">{{ $log->created_at?->format('d/m/Y') ?? '-' }}</div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="audit-avatar" aria-hidden="true">{{ mb_substr($log->user?->name ?? 'ร', 0, 1) }}</span>
                                <div class="min-w-0">
                                    <div class="audit-actor-name">{{ $log->user?->name ?? 'ระบบอัตโนมัติ' }}</div>
                                    @if($log->user?->email)<div class="audit-actor-email">{{ $log->user->email }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td><span class="audit-event audit-event--{{ $tone }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $log->event }}</span></td>
                        <td>
                            <div class="audit-target">{{ $targetName($log->auditable_type) }}</div>
                            <div class="audit-target-id">{{ $log->auditable_id ? 'รหัส #'.$log->auditable_id : 'ไม่มีรหัสรายการ' }}</div>
                        </td>
                        <td>
                            @if($log->old_values || $log->new_values)
                                <details class="audit-detail">
                                    <summary><i class="fa-solid fa-code-compare" aria-hidden="true"></i> ดูการเปลี่ยนแปลง</summary>
                                    <div class="audit-detail__panel">
                                        @if($log->old_values)
                                            <div class="audit-values"><div class="audit-values__label">ข้อมูลก่อนหน้า</div><pre>{{ json_encode($log->old_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>
                                        @endif
                                        @if($log->new_values)
                                            <div class="audit-values"><div class="audit-values__label">ข้อมูลหลังดำเนินการ</div><pre>{{ json_encode($log->new_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>
                                        @endif
                                    </div>
                                </details>
                            @else
                                <span class="text-muted small">ไม่มีข้อมูลเปลี่ยนแปลง</span>
                            @endif
                        </td>
                        <td><span class="small font-monospace text-secondary">{{ $log->ip_address ?? '-' }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-lg-none">
            @foreach($logs as $log)
                @php([$tone, $icon] = $eventMeta($log->event))
                <article class="audit-mobile-card">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="audit-event audit-event--{{ $tone }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $log->event }}</span>
                        <time class="text-end text-nowrap"><span class="audit-time d-block">{{ $log->created_at?->format('H:i') ?? '-' }}</span><span class="audit-date">{{ $log->created_at?->format('d/m/Y') ?? '-' }}</span></time>
                    </div>
                    <dl class="audit-mobile-meta">
                        <dt>ผู้ดำเนินการ</dt><dd>{{ $log->user?->name ?? 'ระบบอัตโนมัติ' }}</dd>
                        <dt>รายการ</dt><dd>{{ $targetName($log->auditable_type) }} {{ $log->auditable_id ? '#'.$log->auditable_id : '' }}</dd>
                        <dt>IP Address</dt><dd class="font-monospace">{{ $log->ip_address ?? '-' }}</dd>
                    </dl>
                    @if($log->old_values || $log->new_values)
                        <details class="audit-detail mt-3">
                            <summary><i class="fa-solid fa-code-compare" aria-hidden="true"></i> ดูการเปลี่ยนแปลง</summary>
                            <div class="audit-detail__panel">
                                @if($log->old_values)<div class="audit-values"><div class="audit-values__label">ข้อมูลก่อนหน้า</div><pre>{{ json_encode($log->old_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>@endif
                                @if($log->new_values)<div class="audit-values"><div class="audit-values__label">ข้อมูลหลังดำเนินการ</div><pre>{{ json_encode($log->new_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div>@endif
                            </div>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>
        @else
            <div class="audit-empty">
                <div class="audit-empty__icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></div>
                <h2 class="h5 fw-bold mb-2">ไม่พบประวัติที่ตรงกับเงื่อนไข</h2>
                <p class="text-muted mb-3">ลองเปลี่ยนคำค้น ผู้ดำเนินการ หรือช่วงวันที่</p>
                @if($activeFilterCount)<a href="{{ route('admin.audit_logs.index') }}" class="ds-btn ds-btn-secondary">ล้างตัวกรองทั้งหมด</a>@endif
            </div>
        @endif

        @if($logs->hasPages())
            <nav class="card-footer bg-white border-top audit-pagination py-3" aria-label="หน้าประวัติการใช้งาน">{{ $logs->onEachSide(1)->links() }}</nav>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof flatpickr === 'undefined') return;

        const thaiCalendar = {
            locale: 'th',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j F Y',
            allowInput: false,
            disableMobile: true,
            monthSelectorType: 'dropdown',
        };

        let endDatePicker;
        const startDatePicker = flatpickr('#from', {
            ...thaiCalendar,
            maxDate: document.querySelector('#to')?.value || null,
            onChange: function (dates) {
                endDatePicker?.set('minDate', dates[0] || null);
            },
        });

        endDatePicker = flatpickr('#to', {
            ...thaiCalendar,
            minDate: document.querySelector('#from')?.value || null,
            onChange: function (dates) {
                startDatePicker.set('maxDate', dates[0] || null);
            },
        });
    });
</script>
@endpush
