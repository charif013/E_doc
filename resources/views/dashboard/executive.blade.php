@extends('layouts.app')
@section('title', 'รายงานภาพรวมทั้งหมด')

@section('content')
@php
    $thaiMonths = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน','05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม','09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
    $periodLabel = ($thaiMonths[str_pad((string) $month, 2, '0', STR_PAD_LEFT)] ?? '-') . ' ' . ((int) $year + 543);
    $completionRate = $stats['total'] > 0 ? round(($stats['approved'] / $stats['total']) * 100) : 0;
    $widgets = [
        ['เอกสารทั้งหมด', $stats['total'], 'รายการในเดือนนี้', 'fa-file-alt', 'blue'],
        ['รอผู้บริหารพิจารณา', $stats['waiting'], 'รายการที่ยังต้องติดตาม', 'fa-clock', 'amber'],
        ['อนุมัติ/สั่งการแล้ว', $stats['approved'], 'คิดเป็น '.$completionRate.'% ของทั้งหมด', 'fa-check-circle', 'green'],
        ['งานด่วนรอดำเนินการ', $stats['urgent'], 'ควรตรวจสอบเป็นลำดับแรก', 'fa-bolt', 'red'],
    ];
@endphp

<div class="executive-report-page container-fluid px-3 px-lg-4 py-4">
    <div class="mx-auto" style="max-width: 1440px;">
        <header class="report-heading mb-4">
            <div class="report-heading__copy">
                <div class="report-heading__icon" aria-hidden="true"><i class="fas fa-chart-pie"></i></div>
                <div>
                    <span class="report-eyebrow">ข้อมูลเพื่อการบริหาร</span>
                    <h1>รายงานภาพรวมงานบริหาร</h1>
                    <p>ติดตามปริมาณเอกสาร ผลการดำเนินงาน และรายการเร่งด่วน</p>
                </div>
            </div>

            <form action="{{ route('dashboard.executive') }}" method="GET" class="report-period-filter" aria-label="เลือกช่วงเวลารายงาน">
                <div class="report-period-filter__title">
                    <i class="far fa-calendar-alt" aria-hidden="true"></i>
                    <span>ช่วงรายงาน</span>
                </div>
                <label>
                    <span class="visually-hidden">เดือน</span>
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        @foreach($thaiMonths as $k => $v)
                            <option value="{{ $k }}" {{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="visually-hidden">ปี</span>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        @for($i = date('Y'); $i >= date('Y')-3; $i--)
                            <option value="{{ $i }}" {{ (int) $year === $i ? 'selected' : '' }}>พ.ศ. {{ $i + 543 }}</option>
                        @endfor
                    </select>
                </label>
            </form>
        </header>

        <section aria-labelledby="report-summary-heading" class="mb-4">
            <div class="report-section-intro mb-3">
                <div>
                    <h2 id="report-summary-heading">สรุปประจำเดือน</h2>
                    <p>ข้อมูลของเดือน{{ $periodLabel }}</p>
                </div>
                <div class="completion-pill" title="จำนวนเอกสารที่อนุมัติหรือสั่งการแล้วเทียบกับเอกสารทั้งหมด">
                    <span>ดำเนินการสำเร็จ</span><strong>{{ $completionRate }}%</strong>
                </div>
            </div>

            <div class="row g-3">
                @foreach($widgets as $item)
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="report-stat report-stat--{{ $item[4] }} h-100">
                            <div class="report-stat__top">
                                <span class="report-stat__icon"><i class="fas {{ $item[3] }}" aria-hidden="true"></i></span>
                                @if($item[4] === 'red' && $item[1] > 0)
                                    <span class="report-stat__signal">เร่งด่วน</span>
                                @elseif($item[4] === 'amber' && $item[1] > 0)
                                    <span class="report-stat__signal">รอติดตาม</span>
                                @endif
                            </div>
                            <div class="report-stat__value">{{ number_format($item[1]) }}</div>
                            <h3>{{ $item[0] }}</h3>
                            <p>{{ $item[2] }}</p>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="row g-4 align-items-stretch">
            <section class="col-xl-7" aria-labelledby="department-chart-heading">
                <div class="report-panel h-100">
                    <div class="report-panel__header">
                        <div class="report-panel__title">
                            <span class="report-panel__icon report-panel__icon--blue"><i class="fas fa-chart-bar" aria-hidden="true"></i></span>
                            <div>
                                <h2 id="department-chart-heading">เอกสารแยกตามส่วนราชการ</h2>
                                <p>เปรียบเทียบจำนวนเอกสารรับและส่งในแต่ละส่วนราชการ</p>
                            </div>
                        </div>
                        <span class="report-period-badge">{{ $periodLabel }}</span>
                    </div>
                    <div class="report-panel__body report-chart-area">
                        @if(count($byDept) > 0)
                            <canvas id="deptChart" aria-label="กราฟจำนวนเอกสารแยกตามส่วนราชการ" role="img"></canvas>
                        @else
                            <div class="report-empty-state">
                                <span class="report-empty-state__icon"><i class="fas fa-chart-bar" aria-hidden="true"></i></span>
                                <h3>ยังไม่มีข้อมูลสำหรับสร้างกราฟ</h3>
                                <p>ไม่พบเอกสารในช่วงเดือนที่เลือก ลองเปลี่ยนเดือนหรือปีด้านบน</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="col-xl-5" aria-labelledby="urgent-documents-heading">
                <div class="report-panel h-100">
                    <div class="report-panel__header">
                        <div class="report-panel__title">
                            <span class="report-panel__icon report-panel__icon--red"><i class="fas fa-bolt" aria-hidden="true"></i></span>
                            <div>
                                <h2 id="urgent-documents-heading">รายการเร่งด่วน</h2>
                                <p>เอกสารด่วนมากและด่วนที่สุดที่ยังค้างอยู่</p>
                            </div>
                        </div>
                        <span class="urgent-count-badge">{{ count($urgentDocs) }} รายการ</span>
                    </div>

                    <div class="report-panel__body urgent-document-list">
                        @forelse($urgentDocs as $doc)
                            <article class="urgent-document-item">
                                <div class="urgent-document-item__marker" aria-hidden="true"><i class="fas fa-exclamation"></i></div>
                                <div class="urgent-document-item__content">
                                    <div class="urgent-document-item__number">{{ $doc->formatted_doc_number ?? 'ยังไม่ออกเลข' }}</div>
                                    <h3 title="{{ $doc->title }}">{{ $doc->title }}</h3>
                                    <div class="urgent-document-item__meta">
                                        <span><i class="fas fa-building" aria-hidden="true"></i>{{ $doc->assigned_to ?? $doc->creator->department ?? 'ไม่ระบุส่วนราชการ' }}</span>
                                        @if($doc->created_at)
                                            <span><i class="far fa-clock" aria-hidden="true"></i>{{ $doc->created_at->locale('th')->translatedFormat('d M Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="urgent-document-item__action">พิจารณา</a>
                            </article>
                        @empty
                            <div class="report-empty-state report-empty-state--success">
                                <span class="report-empty-state__icon"><i class="fas fa-check-double" aria-hidden="true"></i></span>
                                <h3>ไม่มีรายการเร่งด่วนค้างอยู่</h3>
                                <p>เอกสารด่วนได้รับการดำเนินการครบแล้ว</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<style>
    .executive-report-page {
        --report-blue: #087fb9;
        --report-navy: #12384d;
        --report-border: #dfe9ee;
        --report-muted: #6b7d8d;
        background: transparent;
    }
    .report-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
    }
    .report-heading__copy {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
    }
    .report-heading__icon {
        width: 58px;
        height: 58px;
        flex: 0 0 58px;
        display: grid;
        place-items: center;
        border-radius: 17px;
        color: #fff;
        background: linear-gradient(145deg, #0ea5e9, #0877ae);
        box-shadow: 0 10px 24px rgba(2, 132, 199, .22);
        font-size: 23px;
    }
    .report-eyebrow {
        display: block;
        margin-bottom: 2px;
        color: var(--report-blue);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
    }
    .report-heading h1 {
        margin: 0;
        color: var(--report-navy);
        font-size: clamp(1.35rem, 2vw, 1.8rem);
        font-weight: 800;
    }
    .report-heading p,
    .report-section-intro p,
    .report-panel__title p {
        margin: 3px 0 0;
        color: var(--report-muted);
        font-size: 13px;
    }
    .report-period-filter {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        border: 1px solid var(--report-border);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(15, 23, 42, .05);
    }
    .report-period-filter__title {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 0 8px;
        color: #526574;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .report-period-filter .form-select {
        min-width: 135px;
        border-color: #e3eaf0;
        border-radius: 10px;
        color: var(--report-navy);
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }
    .report-section-intro {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .report-section-intro h2 {
        margin: 0;
        color: var(--report-navy);
        font-size: 17px;
        font-weight: 800;
    }
    .completion-pill {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 8px 12px;
        border: 1px solid #bbefda;
        border-radius: 999px;
        color: #087750;
        background: #eefcf6;
        font-size: 12px;
    }
    .completion-pill strong { font-size: 15px; }
    .report-stat {
        position: relative;
        overflow: hidden;
        padding: 18px;
        border: 1px solid var(--report-border);
        border-top: 3px solid var(--stat-accent);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 7px 22px rgba(15, 23, 42, .05);
    }
    .report-stat::after {
        content: '';
        position: absolute;
        width: 100px;
        height: 100px;
        top: -50px;
        right: -42px;
        border-radius: 50%;
        background: var(--stat-soft);
    }
    .report-stat--blue { --stat-accent: #0ea5e9; --stat-soft: #e4f6fd; --stat-text: #0877ae; }
    .report-stat--amber { --stat-accent: #f59e0b; --stat-soft: #fff4d6; --stat-text: #b45309; }
    .report-stat--green { --stat-accent: #10b981; --stat-soft: #ddf9ed; --stat-text: #087750; }
    .report-stat--red { --stat-accent: #ef4444; --stat-soft: #fee8e8; --stat-text: #c72e2e; }
    .report-stat__top {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
    }
    .report-stat__icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        color: var(--stat-text);
        background: var(--stat-soft);
        font-size: 17px;
    }
    .report-stat__signal {
        padding: 5px 8px;
        border-radius: 999px;
        color: var(--stat-text);
        background: var(--stat-soft);
        font-size: 10px;
        font-weight: 800;
    }
    .report-stat__value {
        color: #102a3a;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }
    .report-stat h3 {
        margin: 8px 0 2px;
        color: #263b49;
        font-size: 14px;
        font-weight: 800;
    }
    .report-stat p {
        margin: 0;
        color: var(--report-muted);
        font-size: 11.5px;
    }
    .report-panel {
        overflow: hidden;
        border: 1px solid var(--report-border);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .055);
    }
    .report-panel__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px;
        border-bottom: 1px solid #e8eff3;
    }
    .report-panel__title {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }
    .report-panel__title h2 {
        margin: 0;
        color: var(--report-navy);
        font-size: 16px;
        font-weight: 800;
    }
    .report-panel__icon {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 11px;
    }
    .report-panel__icon--blue { color: #0877ae; background: #e7f7fd; }
    .report-panel__icon--red { color: #d73535; background: #feeeee; }
    .report-period-badge,
    .urgent-count-badge {
        flex: 0 0 auto;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }
    .report-period-badge { color: #0877ae; background: #eaf8fd; }
    .urgent-count-badge { color: #c72e2e; background: #feeeee; }
    .report-panel__body { padding: 20px 22px; }
    .report-chart-area {
        position: relative;
        min-height: 380px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .report-chart-area canvas { width: 100% !important; height: 340px !important; }
    .urgent-document-list {
        display: flex;
        flex-direction: column;
        gap: 11px;
    }
    .urgent-document-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px;
        border: 1px solid #e5edf1;
        border-radius: 13px;
        background: #fff;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .urgent-document-item:hover {
        border-color: #f3bcbc;
        box-shadow: 0 5px 16px rgba(127, 29, 29, .07);
    }
    .urgent-document-item__marker {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        color: #d73535;
        background: #feeeee;
        font-size: 13px;
    }
    .urgent-document-item__content { min-width: 0; flex: 1; }
    .urgent-document-item__number {
        color: #c43a3a;
        font-size: 10.5px;
        font-weight: 800;
    }
    .urgent-document-item h3 {
        overflow: hidden;
        margin: 2px 0 5px;
        color: #203644;
        font-size: 13.5px;
        font-weight: 800;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .urgent-document-item__meta {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        color: #758594;
        font-size: 10.5px;
    }
    .urgent-document-item__meta span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .urgent-document-item__meta i { margin-right: 4px; }
    .urgent-document-item__action {
        flex: 0 0 auto;
        padding: 7px 11px;
        border-radius: 9px;
        color: #0877ae;
        background: #eaf8fd;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
    }
    .urgent-document-item__action:hover { color: #fff; background: #087fb9; }
    .report-empty-state {
        width: 100%;
        padding: 45px 20px;
        color: var(--report-muted);
        text-align: center;
    }
    .report-empty-state__icon {
        width: 58px;
        height: 58px;
        display: inline-grid;
        place-items: center;
        margin-bottom: 14px;
        border-radius: 17px;
        color: #6f8796;
        background: #edf3f6;
        font-size: 22px;
    }
    .report-empty-state--success .report-empty-state__icon { color: #087750; background: #e5f8ef; }
    .report-empty-state h3 { margin: 0 0 5px; color: #29404e; font-size: 15px; font-weight: 800; }
    .report-empty-state p { margin: 0 auto; max-width: 350px; font-size: 12px; }

    @media (max-width: 767.98px) {
        .report-heading { align-items: stretch; flex-direction: column; }
        .report-heading__copy { align-items: flex-start; }
        .report-heading__icon { width: 48px; height: 48px; flex-basis: 48px; border-radius: 14px; font-size: 19px; }
        .report-period-filter { display: grid; grid-template-columns: 1fr 1fr; }
        .report-period-filter__title { grid-column: 1 / -1; }
        .report-period-filter .form-select { min-width: 0; width: 100%; }
        .report-section-intro { align-items: flex-start; }
        .completion-pill { align-items: flex-end; flex-direction: column; gap: 0; border-radius: 12px; }
        .report-panel__header { align-items: flex-start; padding: 17px; }
        .report-panel__body { padding: 16px; }
        .report-panel__title p { display: none; }
        .report-chart-area { min-height: 320px; }
        .report-chart-area canvas { height: 300px !important; }
        .urgent-document-item { align-items: flex-start; flex-wrap: wrap; }
        .urgent-document-item__action { margin-left: 46px; }
    }
</style>
@endsection

@section('scripts')
{{-- โหลด Chart.js สำหรับสร้างกราฟ --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rawData = @json($byDept);
        const chartElement = document.getElementById('deptChart');

        if (chartElement && rawData && rawData.length > 0) {
            const labels = rawData.map(item => item.assigned_to ? item.assigned_to : 'ไม่ได้ระบุ');
            const dataTotals = rawData.map(item => Number(item.total));

            new Chart(chartElement.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'จำนวนเอกสาร',
                        data: dataTotals,
                        backgroundColor: 'rgba(14, 165, 233, 0.72)',
                        hoverBackgroundColor: 'rgba(2, 132, 199, 0.9)',
                        borderColor: '#0284c7',
                        borderWidth: 1,
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 42
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: window.innerWidth < 768 ? 'y' : 'x',
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            displayColors: false,
                            callbacks: {
                                label: context => ` ${context.chart.options.indexAxis === 'y' ? context.parsed.x : context.parsed.y} รายการ`
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: { color: '#526574', font: { family: 'Sarabun', size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(148, 163, 184, .18)' },
                            border: { display: false },
                            ticks: { precision: 0, color: '#71808d', font: { family: 'Sarabun', size: 11 } }
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
