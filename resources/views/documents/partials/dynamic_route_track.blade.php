@php
    // แสดงเส้นทางครบตั้งแต่ผู้นำเสนอ (ลำดับที่ 1) ไปจนถึงผู้พิจารณาคนสุดท้าย
    $displayRoutes = $document->routes
        ->sortBy('step_order')
        ->values();
    $routeCount = $displayRoutes->count();
    $routeColumn = match (true) {
        $routeCount === 1 => 'col-12',
        $routeCount === 2 => 'col-12 col-md-6',
        $routeCount === 3 => 'col-12 col-md-4',
        default => 'col-12 col-md-6 col-lg-3',
    };
@endphp

@if($routeCount > 0)
<div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 16px;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3 pb-2 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <span style="width:4px;height:18px;background:var(--accent-green);border-radius:4px;display:inline-block;"></span>
                <span class="fw-bold" style="color:var(--primary-dark);">เส้นทางนำส่งและพิจารณา</span>
            </div>
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3">{{ $routeCount }} ขั้นตอน</span>
        </div>

        <div class="row g-3 justify-content-center">
            @foreach($displayRoutes as $index => $route)
                @php
                    $isPresenter = (int) $route->step_order === 1;
                    $isApproved = $route->status === 'approved';
                    $isRejected = $route->status === 'rejected';
                    $isCurrent = $route->status === 'pending' && $route->step_order === $document->current_step;
                    $routeColor = $isApproved ? '#16a34a' : ($isRejected ? '#dc2626' : ($isCurrent ? '#0284c7' : '#94a3b8'));
                    $routeBackground = $isApproved ? '#f0fdf4' : ($isRejected ? '#fef2f2' : ($isCurrent ? '#eff6ff' : '#f8fafc'));
                @endphp
                <div class="{{ $routeColumn }}">
                    <div class="h-100 rounded-3 border p-3 text-center shadow-sm" style="border-top:3px solid {{ $routeColor }} !important;background:{{ $routeBackground }};min-height:175px;">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 fw-bold text-white" style="width:30px;height:30px;background:{{ $routeColor }};">
                            {{ $route->step_order }}
                        </div>
                        <div class="small text-muted fw-bold mb-1">
                            {{ $isPresenter ? 'ผู้นำเสนอเอกสาร' : 'ผู้พิจารณาลำดับที่ '.($route->step_order - 1) }}
                        </div>
                        <div class="fw-bold text-dark">{{ $route->user->name ?? 'ไม่พบข้อมูลผู้ใช้' }}</div>
                        <div class="small text-muted mb-2">{{ $route->user->position ?? '-' }}</div>

                        @if($isApproved)
                            @if(!empty($route->user?->signature))
                                <img src="{{ getSigUrl($route->user->signature) }}" alt="ลายเซ็น" style="max-height:48px;max-width:150px;mix-blend-mode:multiply;">
                            @endif
                            <div class="badge bg-success rounded-pill mt-2">{{ $isPresenter ? 'ลงนามนำส่งแล้ว' : 'พิจารณาแล้ว' }}</div>
                            @if($route->actioned_at)<div class="small text-muted mt-1">{{ $route->actioned_at->format('d/m/Y H:i') }}</div>@endif
                        @elseif($isRejected)
                            <div class="badge bg-danger rounded-pill mt-3">ตีกลับเอกสาร</div>
                        @elseif($isCurrent)
                            <div class="badge bg-primary rounded-pill mt-3">{{ $isPresenter ? 'รอลงนามนำส่ง' : 'กำลังรอพิจารณา' }}</div>
                        @else
                            <div class="badge bg-secondary-subtle text-secondary rounded-pill mt-3"><i class="fas fa-lock me-1"></i>รอตามลำดับ</div>
                        @endif

                        @if($route->comment)<div class="small text-muted mt-2">“{{ $route->comment }}”</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
