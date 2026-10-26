{{-- View: หน้าแสดงประวัติการใช้งานและบันทึกตรวจสอบระบบ (Audit Log) --}}
@extends('layouts.app')

@section('title', 'ประวัติการใช้งานระบบ')

@section('content')
@php
    $activeFilterCount = collect($filters)->filter(fn ($value) => filled($value))->count();

    // หน้าที่: แปลงรหัสเหตุการณ์เป็นสีและไอคอนสำหรับแสดงผล
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

    // ===== พจนานุกรมแปลเป็นภาษาคน (เพิ่ม/แก้ได้ตามต้องการ) =====

    // ชื่อเหตุการณ์แบบเต็ม (ใช้ก่อนเสมอ ถ้าไม่ตรงจะประกอบจาก "การกระทำ + ประเภท" ด้านล่าง)
    $eventFull = [
        'auth.login'                                => 'เข้าสู่ระบบ',
        'auth.logout'                               => 'ออกจากระบบ',
        'auth.login_failed'                         => 'เข้าสู่ระบบไม่สำเร็จ',
        'login'                                     => 'เข้าสู่ระบบ',
        'logout'                                    => 'ออกจากระบบ',
        'user.roles_changed'                        => 'เปลี่ยนสิทธิ์ผู้ใช้งาน',
        'document.secret_viewed'                    => 'เปิดดูเอกสารลับ',
        'document.secret_unlock_failed'             => 'ปลดล็อกเอกสารลับไม่สำเร็จ',
        'document.secret_opened'                    => 'ปลดล็อกเอกสารลับ',
        'document.uploaded_pdf_signature_confirmed' => 'ยืนยันลายเซ็นในไฟล์ PDF',
        'document.revision_file_viewed'             => 'เปิดดูไฟล์ฉบับแก้ไข',
        'document.outgoing_delivery_created'        => 'สร้างการนำส่งเอกสาร',
        'document.outgoing_delivery_updated'        => 'แก้ไขการนำส่งเอกสาร',
        'document.outgoing_delivery_evidence_viewed'=> 'เปิดดูหลักฐานการนำส่ง',
        'document.external_archived'                => 'จัดเก็บเอกสารภายนอก',
    ];

    // ส่วนหน้าของรหัสเหตุการณ์ → ชื่อประเภท (เช่น document.xxx)
    $eventObjects = [
        'document'      => 'เอกสาร',
        'user'          => 'ผู้ใช้งาน',
        'leave_request' => 'ใบลา',
        'leave'         => 'ใบลา',
        'room_booking'  => 'การจองห้อง',
        'booking'       => 'การจองห้อง',
        'room'          => 'ห้องประชุม',
        'holiday'       => 'วันหยุด',
    ];

    // ส่วนท้ายของรหัสเหตุการณ์ → การกระทำ (เช่น xxx.created)
    $eventActions = [
        'created'         => 'สร้าง',
        'updated'         => 'แก้ไข',
        'deleted'         => 'ลบ',
        'restored'        => 'กู้คืน',
        'viewed'          => 'เปิดดู',
        'opened'          => 'เปิดดู',
        'file_viewed'     => 'เปิดดูไฟล์',
        'downloaded'      => 'ดาวน์โหลด',
        'file_downloaded' => 'ดาวน์โหลดไฟล์',
        'approved'        => 'อนุมัติ',
        'rejected'        => 'ไม่อนุมัติ',
        'cancelled'       => 'ยกเลิก',
        'canceled'        => 'ยกเลิก',
        'submitted'       => 'ส่ง',
        'changed'         => 'เปลี่ยนแปลง',
        'login'           => 'เข้าสู่ระบบ',
        'logout'          => 'ออกจากระบบ',
        'failed'          => 'ทำรายการไม่สำเร็จ',
    ];

    // ชื่อหัวข้อข้อมูล (key ใน JSON) → ภาษาไทย
    $fieldLabels = [
        'kind'          => 'ประเภทไฟล์',
        'name'          => 'ชื่อ',
        'title'         => 'ชื่อเรื่อง',
        'email'         => 'อีเมล',
        'status'        => 'สถานะ',
        'role'          => 'บทบาท',
        'roles'         => 'สิทธิ์การใช้งาน',
        'reason'        => 'เหตุผล',
        'note'          => 'หมายเหตุ',
        'description'   => 'รายละเอียด',
        'start_date'    => 'วันที่เริ่มต้น',
        'end_date'      => 'วันที่สิ้นสุด',
        'start_time'    => 'เวลาเริ่มต้น',
        'end_time'      => 'เวลาสิ้นสุด',
        'holiday_date'  => 'วันที่',
        'file_name'     => 'ชื่อไฟล์',
        'original_name' => 'ชื่อไฟล์เดิม',
        'room_id'       => 'ห้องประชุม',
        'user_id'       => 'ผู้ใช้งาน',
        'document_id'   => 'เอกสาร',
        'is_active'     => 'เปิดใช้งาน',
    ];

    // ค่าของข้อมูลบางหัวข้อ → ภาษาไทย  [key][value]
    $valueLabels = [
        'kind' => [
            'main'       => 'ไฟล์หลักของเอกสาร',
            'attachment' => 'ไฟล์แนบ',
        ],
        'status' => [
            'pending'   => 'รออนุมัติ',
            'approved'  => 'อนุมัติแล้ว',
            'rejected'  => 'ไม่อนุมัติ',
            'cancelled' => 'ยกเลิกแล้ว',
            'canceled'  => 'ยกเลิกแล้ว',
            'draft'     => 'แบบร่าง',
            'active'    => 'ใช้งานอยู่',
            'inactive'  => 'ปิดใช้งาน',
        ],
        'roles' => [
            'super-admin' => 'ผู้ดูแลระบบสูงสุด',
            'executive'   => 'ผู้บริหาร',
            'palad'       => 'ปลัด อบต.',
            'saraban'     => 'สารบรรณกลาง',
            'head'        => 'หัวหน้าหน่วยงาน',
            'hr'          => 'บุคลากร',
            'officer'     => 'เจ้าหน้าที่ทั่วไป',
            'worker'      => 'พนักงานจ้าง',
        ],
    ];

    // หน้าที่: แปลงรหัสเหตุการณ์เป็นข้อความภาษาไทยที่คนทั่วไปอ่านเข้าใจ
    $eventLabel = function (?string $event) use ($eventFull, $eventObjects, $eventActions): string {
        $event = (string) $event;
        if ($event === '') return '-';
        if (isset($eventFull[$event])) return $eventFull[$event];

        $parts  = explode('.', $event, 2);
        $object = $parts[0];
        $action = $parts[1] ?? null;

        if ($action !== null && isset($eventObjects[$object]) && isset($eventActions[$action])) {
            return $eventActions[$action] . $eventObjects[$object];
        }
        if ($action === null && isset($eventActions[$object])) {
            return $eventActions[$object];
        }
        return 'ดำเนินการกับข้อมูล';
    };

    // หน้าที่: แปลงค่าข้อมูลหนึ่งค่าเป็นข้อความ HTML ที่อ่านง่าย
    $formatValue = function (string $key, $value) use ($valueLabels): string {
        if ($value === null || $value === '') return '<span class="text-muted">ไม่มีข้อมูล</span>';
        if (is_bool($value)) return $value ? 'ใช่' : 'ไม่ใช่';
        if (is_array($value)) {
            $value = array_map(fn ($item) => is_scalar($item)
                ? ($valueLabels[$key][(string) $item] ?? $item)
                : $item, $value);
            return '<code>' . e(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</code>';
        }
        $text = (string) $value;
        return e($valueLabels[$key][$text] ?? $text);
    };

    // หน้าที่: สร้างตารางแสดงรายละเอียด/การเปลี่ยนแปลงแบบภาษาคน (คืนค่าเป็น HTML ที่ escape แล้ว)
    $renderChanges = function ($old, $new) use ($fieldLabels, $formatValue): string {
        $old = (array) ($old ?? []);
        $new = (array) ($new ?? []);
        $hasOld = !empty($old);
        $hasNew = !empty($new);

        $label = fn (string $key) => isset($fieldLabels[$key])
            ? e($fieldLabels[$key])
            : '<span class="font-monospace">' . e($key) . '</span>';

        $html = '<div class="audit-changes"><table class="audit-changes__table">';

        if ($hasOld && $hasNew) {
            $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
            $changed = array_values(array_filter($keys, fn ($k) => ($old[$k] ?? null) !== ($new[$k] ?? null)));
            if ($changed) $keys = $changed; // แสดงเฉพาะหัวข้อที่เปลี่ยนจริง

            $html .= '<thead><tr><th>หัวข้อ</th><th>ก่อนหน้า</th><th>หลังแก้ไข</th></tr></thead><tbody>';
            foreach ($keys as $k) {
                $k = (string) $k;
                $html .= '<tr><td class="audit-changes__key">' . $label($k) . '</td>'
                    . '<td class="audit-changes__old">' . $formatValue($k, $old[$k] ?? null) . '</td>'
                    . '<td class="audit-changes__new">' . $formatValue($k, $new[$k] ?? null) . '</td></tr>';
            }
        } else {
            $data = $hasOld ? $old : $new;
            $head = $hasOld ? 'ข้อมูลก่อนหน้า' : 'ข้อมูล';
            $html .= '<thead><tr><th>หัวข้อ</th><th>' . $head . '</th></tr></thead><tbody>';
            foreach ($data as $k => $v) {
                $k = (string) $k;
                $html .= '<tr><td class="audit-changes__key">' . $label($k) . '</td><td>' . $formatValue($k, $v) . '</td></tr>';
            }
        }
        $html .= '</tbody></table>';

        // ข้อมูลดิบ (JSON) สำหรับผู้ดูแลระบบที่ต้องการตรวจสอบละเอียด
        $raw = array_filter(['ก่อนหน้า' => $old, 'หลังดำเนินการ' => $new]);
        $html .= '<details class="audit-raw"><summary>ดูข้อมูลดิบ (JSON)</summary><pre>'
            . e(json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            . '</pre></details></div>';

        return $html;
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
    /* คอลัมน์ "เหตุการณ์" (คอลัมน์ที่ 3) ต้องมีความกว้างขั้นต่ำ ไม่ให้ถูกบีบจน badge ยาวลงเป็นแนวตั้ง */
    .audit-table th:nth-child(3), .audit-table td:nth-child(3) { min-width: 240px; }
    .audit-time { font-weight: 700; color: var(--text-primary); white-space: nowrap; }
    .audit-date { color: var(--text-muted); font-size: .78rem; }
    .audit-avatar { width: 34px; height: 34px; display: grid; place-items: center; flex: 0 0 auto; border-radius: 10px; color: var(--primary-dark); background: var(--primary-light); font-weight: 700; }
    .audit-actor-name { max-width: 180px; overflow: hidden; color: var(--text-primary); font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .audit-actor-email { max-width: 180px; overflow: hidden; color: var(--text-muted); font-size: .75rem; text-overflow: ellipsis; white-space: nowrap; }
    /* badge เหตุการณ์ (ข้อความภาษาไทย) ห้ามตัดทีละตัวอักษร */
    .audit-event { display: inline-flex; align-items: center; gap: .4rem; max-width: none; padding: .35rem .6rem; border: 1px solid; border-radius: 8px; font-size: .82rem; font-weight: 700; white-space: nowrap; overflow-wrap: normal; word-break: normal; }
    .audit-event i { flex: 0 0 auto; }
    .audit-event-code { margin-top: .3rem; color: var(--text-muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .7rem; white-space: nowrap; }
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

    /* ตารางรายละเอียดแบบอ่านง่าย */
    .audit-changes__table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; font-size: .82rem; }
    .audit-changes__table th { padding: .45rem .65rem; color: #64748b; background: #f1f5f9; font-size: .72rem; font-weight: 700; text-align: left; }
    .audit-changes__table td { padding: .5rem .65rem; border-top: 1px solid #eef2f7; color: #334155; vertical-align: top; overflow-wrap: anywhere; }
    .audit-changes__key { width: 32%; color: #475569; font-weight: 700; }
    .audit-changes__old { color: var(--danger-text); background: var(--danger-bg); }
    .audit-changes__new { color: var(--success-text); background: var(--success-bg); }
    .audit-changes code { font-size: .75rem; color: #334155; word-break: break-word; }
    .audit-raw { margin-top: .6rem; }
    .audit-raw summary { color: #64748b; font-size: .75rem; cursor: pointer; }
    .audit-raw pre { max-height: 220px; margin: .4rem 0 0; padding: .65rem; overflow: auto; border-radius: 8px; color: #334155; background: #fff; font-size: .75rem; white-space: pre-wrap; word-break: break-word; }

    .audit-empty { padding: 4rem 1rem; text-align: center; }
    .audit-empty__icon { width: 60px; height: 60px; display: grid; place-items: center; margin: 0 auto .85rem; border-radius: 18px; color: var(--primary); background: var(--primary-light); font-size: 1.4rem; }
    .audit-mobile-card { padding: 1rem; border-bottom: 1px solid var(--border-color); }
    .audit-mobile-card:last-child { border-bottom: 0; }
    /* มือถือ: card กว้างพอ จึงให้ตัดบรรทัดได้ตามปกติ */
    .audit-mobile-card .audit-event { min-width: 0; white-space: normal; overflow-wrap: anywhere; }
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
                        <input id="event" name="event" value="{{ $filters['event'] ?? '' }}" class="form-control border-start-0 ps-0" placeholder="ค้นด้วยรหัส เช่น document หรือ updated">
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
                        <td>
                            <span class="audit-event audit-event--{{ $tone }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $eventLabel($log->event) }}</span>
                        </td>
                        <td>
                            <div class="audit-target">{{ $targetName($log->auditable_type) }}</div>
                            <div class="audit-target-id">{{ $log->auditable_id ? 'รหัส #'.$log->auditable_id : 'ไม่มีรหัสรายการ' }}</div>
                        </td>
                        <td>
                            @if($log->old_values || $log->new_values)
                                <details class="audit-detail">
                                    <summary><i class="fa-solid fa-code-compare" aria-hidden="true"></i> {{ ($log->old_values && $log->new_values) ? 'ดูการเปลี่ยนแปลง' : 'ดูรายละเอียด' }}</summary>
                                    <div class="audit-detail__panel">
                                        {!! $renderChanges($log->old_values, $log->new_values) !!}
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
                        <div class="min-w-0">
                            <span class="audit-event audit-event--{{ $tone }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i>{{ $eventLabel($log->event) }}</span>
                        </div>
                        <time class="text-end text-nowrap"><span class="audit-time d-block">{{ $log->created_at?->format('H:i') ?? '-' }}</span><span class="audit-date">{{ $log->created_at?->format('d/m/Y') ?? '-' }}</span></time>
                    </div>
                    <dl class="audit-mobile-meta">
                        <dt>ผู้ดำเนินการ</dt><dd>{{ $log->user?->name ?? 'ระบบอัตโนมัติ' }}</dd>
                        <dt>รายการ</dt><dd>{{ $targetName($log->auditable_type) }} {{ $log->auditable_id ? '#'.$log->auditable_id : '' }}</dd>
                        <dt>IP Address</dt><dd class="font-monospace">{{ $log->ip_address ?? '-' }}</dd>
                    </dl>
                    @if($log->old_values || $log->new_values)
                        <details class="audit-detail mt-3">
                            <summary><i class="fa-solid fa-code-compare" aria-hidden="true"></i> {{ ($log->old_values && $log->new_values) ? 'ดูการเปลี่ยนแปลง' : 'ดูรายละเอียด' }}</summary>
                            <div class="audit-detail__panel">
                                {!! $renderChanges($log->old_values, $log->new_values) !!}
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
    // หน้าที่: เริ่มตั้งค่าหน้าและผูกเหตุการณ์เมื่อ DOM พร้อมใช้งาน
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
            // หน้าที่: ซิงก์ข้อมูลเมื่อค่าของตัวเลือกวันที่เปลี่ยน
            onChange: function (dates) {
                endDatePicker?.set('minDate', dates[0] || null);
            },
        });

        endDatePicker = flatpickr('#to', {
            ...thaiCalendar,
            minDate: document.querySelector('#from')?.value || null,
            // หน้าที่: ซิงก์ข้อมูลเมื่อค่าของตัวเลือกวันที่เปลี่ยน
            onChange: function (dates) {
                startDatePicker.set('maxDate', dates[0] || null);
            },
        });
    });
</script>
@endpush
