@extends('layouts.app')
@section('title', 'รายงานภาพรวมทั้งหมด')

@section('content')
<div class="container-fluid px-4 py-4">
    
    {{-- 🌟 Header & ตัวกรองเดือน/ปี (พ.ศ.) --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h4 class="fw-bold text-dark mb-0">
            <i class="fas fa-chart-pie text-primary me-2"></i>รายงานภาพรวมงานบริหาร
        </h4>

        <form action="{{ route('dashboard.executive') }}" method="GET" class="d-flex gap-2 bg-white p-2 rounded-pill shadow-sm border">
            <select name="month" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary" onchange="this.form.submit()" style="cursor: pointer;">
                @php
                    $thaiMonths = ['01'=>'มกราคม','02'=>'กุมภาพันธ์','03'=>'มีนาคม','04'=>'เมษายน','05'=>'พฤษภาคม','06'=>'มิถุนายน','07'=>'กรกฎาคม','08'=>'สิงหาคม','09'=>'กันยายน','10'=>'ตุลาคม','11'=>'พฤศจิกายน','12'=>'ธันวาคม'];
                @endphp
                @foreach($thaiMonths as $k => $v)
                    <option value="{{ $k }}" {{ $month == $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
            </select>
            <div class="vr bg-secondary opacity-25"></div>
            <select name="year" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary" onchange="this.form.submit()" style="cursor: pointer;">
                @for($i = date('Y'); $i >= date('Y')-3; $i--)
                    <option value="{{ $i }}" {{ $year == $i ? 'selected' : '' }}>พ.ศ. {{ $i + 543 }}</option>
                @endfor
            </select>
        </form>
    </div>

    {{-- 🌟 Widget สรุปผลตัวเลข --}}
    <div class="row g-4 mb-4">
        @php
            $widgets = [
                ['เอกสารทั้งหมด', $stats['total'], 'bg-primary', 'fa-file-alt'],
                ['รอผู้บริหารพิจารณา', $stats['waiting'], 'bg-warning', 'fa-clock'],
                ['อนุมัติ/สั่งการแล้ว', $stats['approved'], 'bg-success', 'fa-check-circle'],
                ['งานด่วน (รอดำเนินการ)', $stats['urgent'], 'bg-danger', 'fa-bolt']
            ];
        @endphp
        @foreach($widgets as $item)
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm p-3 d-flex flex-row align-items-center" style="border-radius:16px;">
                <div class="{{ $item[2] }} text-white rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width:54px; height:54px;">
                    <i class="fas {{ $item[3] }} fs-4"></i>
                </div>
                <div class="ms-3">
                    <div class="text-muted small fw-bold mb-1">{{ $item[0] }}</div>
                    <div class="h3 fw-bold mb-0 text-dark">{{ number_format($item[1]) }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        {{-- 🌟 กราฟแสดงสถิติตามส่วนราชการ --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
                <div class="card-header bg-white py-3 fw-bold border-bottom-0">
                    <i class="fas fa-chart-bar text-primary me-2"></i>สถิติเอกสารรับ/ส่ง แยกตามส่วนราชการ
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    @if(count($byDept) > 0)
                        <canvas id="deptChart" style="max-height: 300px;"></canvas>
                    @else
                        <div class="text-center text-muted w-100 py-5">
                            <i class="fas fa-folder-open fs-1 mb-3 opacity-50"></i>
                            <p class="mb-0">ไม่มีข้อมูลเอกสารในเดือนที่เลือก</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- 🌟 ตารางงานด่วนที่สุด (Urgent Action) --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius:16px;">
                <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="fas fa-exclamation-triangle text-danger me-2"></i>งานด่วน/ด่วนที่สุด (รอการพิจารณา)</span>
                    <span class="badge bg-danger rounded-pill">{{ count($urgentDocs) }} รายการ</span>
                </div>
                <div class="card-body p-0">
                    @if(count($urgentDocs) > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 14px;">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 text-muted">เรื่อง / เลขที่</th>
                                        <th class="text-muted">หน่วยงาน</th>
                                        <th class="text-muted text-center">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($urgentDocs as $doc)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark text-truncate" style="max-width: 200px;" title="{{ $doc->title }}">{{ $doc->title }}</div>
                                            <div class="small text-muted">{{ $doc->formatted_doc_number ?? 'ยังไม่ออกเลข' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $doc->assigned_to ?? $doc->creator->department ?? '-' }}</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                                พิจารณา <i class="fas fa-arrow-right ms-1"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted py-5 px-3">
                            <div class="bg-success-subtle text-success d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width:60px; height:60px;">
                                <i class="fas fa-check-double fs-3"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">ยอดเยี่ยมมาก!</h6>
                            <p class="mb-0 small">ไม่มีเอกสารด่วนค้างพิจารณาในระบบ ณ ขณะนี้</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
{{-- โหลด Chart.js สำหรับสร้างกราฟ --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // เตรียมข้อมูลจาก Controller ไปใส่ใน กราฟ
        const rawData = @json($byDept);
        
        if(rawData && rawData.length > 0) {
            const labels = rawData.map(item => item.assigned_to ? item.assigned_to : 'ไม่ได้ระบุ');
            const dataTotals = rawData.map(item => item.total);

            const ctx = document.getElementById('deptChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar', // เปลี่ยนเป็น 'doughnut' ได้ถ้าอยากได้กราฟโดนัท
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'จำนวนเอกสาร',
                        data: dataTotals,
                        backgroundColor: 'rgba(2, 132, 199, 0.7)',
                        borderColor: 'rgba(2, 132, 199, 1)',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false } // ซ่อน Legend ถ้าใช้ Bar Chart
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 } // บังคับให้สเกล y เป็นเลขจำนวนเต็ม
                        }
                    }
                }
            });
        }
    });
</script>
@endsection
