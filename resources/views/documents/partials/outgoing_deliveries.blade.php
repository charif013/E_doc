{{-- Partial: แสดงและจัดการข้อมูลการจัดส่งหนังสือส่งออก --}}
@php
    $deliveryStatuses = [
        'PENDING' => ['รอส่ง', 'bg-secondary-subtle text-secondary'],
        'SENT' => ['ส่งแล้ว', 'bg-primary-subtle text-primary'],
        'RECEIVED' => ['ผู้รับได้รับแล้ว', 'bg-success-subtle text-success'],
        'FAILED' => ['ส่งไม่สำเร็จ', 'bg-danger-subtle text-danger'],
    ];
    $deliveryChannels = [
        'EMAIL' => 'อีเมล',
        'POST' => 'ไปรษณีย์',
        'COURIER' => 'บริษัทขนส่ง',
        'HAND' => 'นำส่งด้วยตนเอง',
        'E_DOC' => 'ระบบสารบรรณอิเล็กทรอนิกส์',
        'LINE' => 'LINE',
        'OTHER' => 'ช่องทางอื่น',
    ];
    $deliveryRecords = $document->deliveries()->with('sender')->get();
    $canDispatchOutgoing = auth()->user()->can('dispatch', $document);
@endphp

@if(in_array((string) $document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true))
<div class="card border-0 shadow-sm mb-4 no-print" style="border-radius:16px;">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-3 border-bottom">
        <div>
            <h6 class="fw-bold text-dark mb-1"><i class="fas fa-truck-fast text-primary me-2"></i>การส่งและติดตามหนังสือออก</h6>
            <div class="small text-muted">บันทึกผู้รับ ช่องทางส่ง เลขติดตาม และหลักฐานการส่งมอบ</div>
        </div>
        <span class="badge bg-primary-subtle text-primary border rounded-pill px-3 py-2">{{ $deliveryRecords->count() }} รายการ</span>
    </div>

    @if($canDispatchOutgoing)
        <div class="card-body border-bottom bg-light-subtle">
            <details {{ $deliveryRecords->isEmpty() ? 'open' : '' }}>
                <summary class="fw-bold text-primary" style="cursor:pointer;"><i class="fas fa-plus-circle me-1"></i> บันทึกการส่งหนังสือ</summary>
                <form action="{{ route('documents.deliveries.store', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data" class="row g-3 mt-1">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">ชื่อผู้รับ/หน่วยงาน <span class="text-danger">*</span></label>
                        <input type="text" name="recipient_name" class="form-control" required maxlength="500" value="{{ old('recipient_name', $document->doc_to) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">ช่องทางส่ง <span class="text-danger">*</span></label>
                        <select name="channel" class="form-select" required>
                            @foreach($deliveryChannels as $value => $label)
                                <option value="{{ $value }}" {{ old('channel') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">สถานะ <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            @foreach($deliveryStatuses as $value => $meta)
                                <option value="{{ $value }}" {{ old('status', 'SENT') === $value ? 'selected' : '' }}>{{ $meta[0] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">ที่อยู่/ช่องทางติดต่อผู้รับ</label>
                        <textarea name="recipient_address" class="form-control" rows="2" maxlength="2000">{{ old('recipient_address') }}</textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">วันที่และเวลาส่ง</label>
                        <input type="text" name="sent_at" class="form-control thai-delivery-datetime" value="{{ old('sent_at', now()->format('Y-m-d H:i')) }}" placeholder="เลือกวันและเวลาที่ส่ง" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">เลขติดตาม/เลขรับ</label>
                        <input type="text" name="tracking_number" class="form-control" maxlength="255" value="{{ old('tracking_number') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">หลักฐานการส่ง <span class="text-muted fw-normal">(PDF/JPG/PNG ไม่เกิน 10MB)</span></label>
                        <div class="delivery-file-picker">
                            <label class="btn btn-outline-secondary mb-0">
                                <i class="fas fa-paperclip me-1"></i>เลือกไฟล์หลักฐาน
                                <input type="file" name="evidence" class="visually-hidden delivery-evidence-input" accept=".pdf,.jpg,.jpeg,.png">
                            </label>
                            <span class="delivery-file-name text-muted small">ยังไม่ได้เลือกไฟล์</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">หมายเหตุ</label>
                        <input type="text" name="note" class="form-control" maxlength="2000" value="{{ old('note') }}">
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-save me-1"></i>บันทึกการส่ง</button>
                    </div>
                </form>
            </details>
        </div>
    @endif

    <div class="card-body p-0">
        @forelse($deliveryRecords as $delivery)
            @php $statusMeta = $deliveryStatuses[$delivery->status] ?? [$delivery->status, 'bg-light text-dark']; @endphp
            <div class="p-4 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                            <span class="badge {{ $statusMeta[1] }} border rounded-pill">{{ $statusMeta[0] }}</span>
                            <strong class="text-dark">{{ $delivery->recipient_name }}</strong>
                        </div>
                        <div class="small text-muted">
                            {{ $deliveryChannels[$delivery->channel] ?? $delivery->channel }}
                            @if($delivery->sent_at) · ส่งเมื่อ {{ $delivery->sent_at->format('d/m/Y H:i') }} @endif
                            @if($delivery->tracking_number) · เลขติดตาม {{ $delivery->tracking_number }} @endif
                        </div>
                        @if($delivery->recipient_address)<div class="small mt-2"><i class="fas fa-location-dot text-muted me-1"></i>{{ $delivery->recipient_address }}</div>@endif
                        @if($delivery->note)<div class="small mt-1 text-muted">หมายเหตุ: {{ $delivery->note }}</div>@endif
                        <div class="small text-muted mt-1">บันทึกโดย {{ $delivery->sender->name ?? 'ผู้ใช้งานระบบ' }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if($delivery->evidence_path)
                            <a href="{{ route('documents.deliveries.evidence', [$document->uuid ?? $document->id, $delivery->id]) }}" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fas fa-paperclip me-1"></i>หลักฐาน</a>
                        @endif
                    </div>
                </div>

                @if($canDispatchOutgoing)
                    <details class="mt-3">
                        <summary class="small fw-bold text-primary" style="cursor:pointer;">อัปเดตสถานะ</summary>
                        <form action="{{ route('documents.deliveries.update', [$document->uuid ?? $document->id, $delivery->id]) }}" method="POST" enctype="multipart/form-data" class="row g-2 mt-1">
                            @csrf @method('PATCH')
                            <div class="col-md-3">
                                <select name="status" class="form-select form-select-sm" required>
                                    @foreach($deliveryStatuses as $value => $meta)
                                        <option value="{{ $value }}" {{ $delivery->status === $value ? 'selected' : '' }}>{{ $meta[0] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><input type="text" name="received_at" class="form-control form-control-sm thai-delivery-datetime" value="{{ $delivery->received_at?->format('Y-m-d H:i') }}" placeholder="วันและเวลาที่ได้รับ" aria-label="วันที่ผู้รับได้รับหนังสือ" autocomplete="off"></div>
                            <div class="col-md-3"><input type="text" name="tracking_number" class="form-control form-control-sm" value="{{ $delivery->tracking_number }}" placeholder="เลขติดตาม/เลขรับ"></div>
                            <div class="col-md-3">
                                <div class="delivery-file-picker delivery-file-picker-sm">
                                    <label class="btn btn-outline-secondary btn-sm mb-0">
                                        <i class="fas fa-paperclip me-1"></i>เลือกหลักฐาน
                                        <input type="file" name="evidence" class="visually-hidden delivery-evidence-input" accept=".pdf,.jpg,.jpeg,.png" aria-label="อัปเดตหลักฐาน">
                                    </label>
                                    <span class="delivery-file-name text-muted small">ยังไม่ได้เลือกไฟล์</span>
                                </div>
                            </div>
                            <div class="col-md-9"><input type="text" name="note" class="form-control form-control-sm" value="{{ $delivery->note }}" placeholder="หมายเหตุ"></div>
                            <div class="col-md-3 text-end"><button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">บันทึกสถานะ</button></div>
                        </form>
                    </details>
                @endif
            </div>
        @empty
            <div class="text-center text-muted py-5"><i class="fas fa-inbox fs-3 d-block mb-2 opacity-50"></i>ยังไม่มีประวัติการส่งหนังสือ</div>
        @endforelse
    </div>
</div>
@endif

@once
    <style>
        .delivery-file-picker { display:flex; align-items:center; gap:.65rem; min-width:0; }
        .delivery-file-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .delivery-file-picker-sm { gap:.4rem; }
        .delivery-file-picker-sm .delivery-file-name { max-width:120px; }
        .flatpickr-calendar { font-family:inherit; }
        @media (max-width: 575.98px) {
            .delivery-file-picker { align-items:stretch; flex-direction:column; }
            .delivery-file-picker-sm .delivery-file-name { max-width:100%; }
        }
    </style>
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.delivery-evidence-input').forEach(input => {
                    input.addEventListener('change', () => {
                        const label = input.closest('.delivery-file-picker')?.querySelector('.delivery-file-name');
                        if (label) label.textContent = input.files?.[0]?.name || 'ยังไม่ได้เลือกไฟล์';
                    });
                });

                if (typeof flatpickr !== 'undefined') {
                    flatpickr('.thai-delivery-datetime', {
                        enableTime: true,
                        time_24hr: true,
                        locale: 'th',
                        dateFormat: 'Y-m-d H:i',
                        altInput: true,
                        altFormat: 'd/m/Y เวลา H:i น.',
                        allowInput: true,
                        minuteIncrement: 1,
                    });
                }
            });
        </script>
    @endpush
@endonce
