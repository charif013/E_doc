@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1"><i class="fa-solid fa-shield-halved text-primary me-2"></i>ประวัติการใช้งานระบบ</h3>
            <div class="text-muted">บันทึกแบบ append-only สำหรับตรวจสอบย้อนหลัง</div>
        </div>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">เหตุการณ์</label>
                <input name="event" value="{{ $filters['event'] ?? '' }}" class="form-control" placeholder="เช่น document.secret_viewed">
            </div>
            <div class="col-md-3">
                <label class="form-label">ตั้งแต่วันที่</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">ถึงวันที่</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary">ค้นหา</button></div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>เวลา</th><th>ผู้ใช้</th><th>เหตุการณ์</th><th>รายการ</th><th>รายละเอียด</th><th>IP</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? 'ระบบ' }}</td>
                        <td><code>{{ $log->event }}</code></td>
                        <td>{{ class_basename($log->auditable_type ?? '-') }} #{{ $log->auditable_id ?? '-' }}</td>
                        <td style="min-width:280px">
                            @if($log->old_values)<details><summary>ค่าก่อนหน้า</summary><pre class="small mb-1">{{ json_encode($log->old_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
                            @if($log->new_values)<details><summary>ค่าหลังดำเนินการ</summary><pre class="small mb-0">{{ json_encode($log->new_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@endif
                        </td>
                        <td>{{ $log->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">ไม่พบข้อมูล</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
