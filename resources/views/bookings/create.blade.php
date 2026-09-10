@extends('layouts.app')

@section('content')
<div class="booking-create-page container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <a href="{{ route('bookings.index') }}" class="ds-back-link mb-2"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับปฏิทินการจอง</a>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-calendar-plus text-primary me-2"></i>จองห้องประชุม</h3>
            <p class="text-muted mb-0">กรอกข้อมูลตามลำดับ ระบบจะตรวจช่วงเวลาและยืนยันการจองให้ทันที</p>
        </div>
        <div class="booking-auto-status"><span class="status-dot"></span><div><strong>ยืนยันอัตโนมัติ</strong><small>เมื่อห้องว่างในช่วงเวลาที่เลือก</small></div></div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex gap-3"><i class="fas fa-exclamation-circle fs-4 mt-1"></i><div><strong>กรุณาตรวจสอบข้อมูลอีกครั้ง</strong><ul class="mb-0 mt-1 ps-3 small">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
        </div>
    @endif

    <form action="{{ route('bookings.store') }}" method="POST" id="bookingForm">
        @csrf
        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <section class="booking-section card border-0 shadow-sm mb-4">
                    <div class="card-body p-4 p-lg-5">
                        <div class="section-heading"><span class="section-number">1</span><div><h5>รายละเอียดการใช้งาน</h5><p>เลือกวัตถุประสงค์ ห้อง และระบุหัวข้อให้ชัดเจน</p></div></div>
                        <div class="mb-4">
                            <label for="title" class="form-label fw-bold">หัวข้อ / วัตถุประสงค์ <span class="text-danger">*</span></label>
                            <input id="title" type="text" name="title" value="{{ old('title') }}" class="form-control form-control-lg @error('title') is-invalid @enderror" placeholder="เช่น ประชุมติดตามงานประจำเดือน" maxlength="255" required autofocus>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <label class="form-label fw-bold">ประเภทการใช้งาน <span class="text-danger">*</span></label>
                        <div class="booking-type-grid mb-4">
                            @foreach(['meeting' => ['fa-users', 'นัดประชุม', 'เชิญผู้เข้าร่วมและส่งการแจ้งเตือน'], 'general_use' => ['fa-door-open', 'ใช้งานทั่วไป', 'ใช้ห้องทำงานหรือกิจกรรมทั่วไป'], 'maintenance' => ['fa-tools', 'ปิดปรับปรุง', 'กันช่วงเวลาสำหรับซ่อมบำรุง']] as $value => [$icon, $label, $description])
                                <label class="booking-type-option"><input type="radio" name="booking_type" value="{{ $value }}" @checked(old('booking_type', 'meeting') === $value)><span class="booking-type-card"><i class="fas {{ $icon }}"></i><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><i class="fas fa-check-circle selected-check"></i></span></label>
                            @endforeach
                        </div>
                        @error('booking_type')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="room_id" class="form-label fw-bold">ห้องประชุม <span class="text-danger">*</span></label>
                                <select id="room_id" name="room_id" class="form-select form-select-lg @error('room_id') is-invalid @enderror" required @disabled($rooms->isEmpty())>
                                    <option value="">เลือกห้องประชุม</option>
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}" data-room-name="{{ $room->name }}" data-capacity="{{ $room->capacity ?: '-' }}" @selected((string) old('room_id') === (string) $room->id)>{{ $room->name }} · รองรับ {{ $room->capacity ?: 'ไม่ระบุ' }} คน</option>
                                    @endforeach
                                </select>
                                @error('room_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if($rooms->isEmpty())<div class="text-danger small mt-2"><i class="fas fa-info-circle me-1"></i>ยังไม่มีห้องที่เปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ</div>@endif
                            </div>
                            <div class="col-md-6">
                                <label for="document_id" class="form-label fw-bold">เอกสารที่เกี่ยวข้อง <span class="text-muted fw-normal">(ถ้ามี)</span></label>
                                <select id="document_id" name="document_id" class="form-select form-select-lg @error('document_id') is-invalid @enderror">
                                    <option value="">ไม่แนบเอกสาร</option>
                                    @foreach($assignedDocuments as $assignedDocument)
                                        <option value="{{ $assignedDocument->id }}" @selected((string) old('document_id') === (string) $assignedDocument->id)>{{ $assignedDocument->title }}{{ $assignedDocument->doc_number ? ' — '.$assignedDocument->doc_number : '' }}</option>
                                    @endforeach
                                </select>
                                @error('document_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mt-4"><label for="description" class="form-label fw-bold">รายละเอียดเพิ่มเติม</label><textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3" maxlength="5000" placeholder="วาระประชุม อุปกรณ์ที่ต้องการ หรือข้อมูลเพิ่มเติม">{{ old('description') }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    </div>
                </section>

                <section class="booking-section card border-0 shadow-sm mb-4" id="timeSection">
                    <div class="card-body p-4 p-lg-5">
                        <div class="section-heading"><span class="section-number">2</span><div><h5>วันและเวลา</h5><p>ระบบไม่อนุญาตให้จองทับช่วงเวลาที่มีผู้ใช้งานแล้ว</p></div></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label for="start_time" class="form-label fw-bold">เริ่มใช้งาน <span class="text-danger">*</span></label><div class="input-icon-wrap"><i class="far fa-calendar-alt"></i><input id="start_time" type="text" name="start_time" value="{{ old('start_time') }}" class="form-control form-control-lg datetime-picker @error('start_time') is-invalid @enderror" placeholder="เลือกวันและเวลาเริ่ม" required></div>@error('start_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                            <div class="col-md-6"><label for="end_time" class="form-label fw-bold">สิ้นสุด <span class="text-danger">*</span></label><div class="input-icon-wrap"><i class="far fa-clock"></i><input id="end_time" type="text" name="end_time" value="{{ old('end_time') }}" class="form-control form-control-lg datetime-picker @error('end_time') is-invalid @enderror" placeholder="เลือกวันและเวลาสิ้นสุด" required></div>@error('end_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><span class="small text-muted me-1">กำหนดระยะเวลาเร็ว:</span>@foreach([30 => '30 นาที', 60 => '1 ชั่วโมง', 120 => '2 ชั่วโมง', 180 => '3 ชั่วโมง'] as $minutes => $label)<button type="button" class="btn btn-sm btn-light border rounded-pill quick-duration" data-minutes="{{ $minutes }}">{{ $label }}</button>@endforeach<span id="durationFeedback" class="duration-feedback ms-md-auto">ยังไม่ได้เลือกเวลา</span></div>
                        <div class="holiday-help mt-3"><span class="holiday-dot"></span><span>วันที่พื้นสีส้มคือวันหยุด เลื่อนเมาส์เหนือวันที่เพื่อดูรายละเอียด</span></div>
                    </div>
                </section>

                <section class="booking-section card border-0 shadow-sm" id="invitees_section">
                    <div class="card-body p-4 p-lg-5">
                        <div class="section-heading mb-3"><span class="section-number">3</span><div><h5>ผู้เข้าร่วมประชุม</h5><p>ผู้ที่เลือกจะได้รับการแจ้งเตือนพร้อมรายละเอียดนัดหมาย</p></div></div>
                        <div class="d-flex flex-wrap gap-2 justify-content-between mb-3"><div class="invite-search"><i class="fas fa-search"></i><input type="search" id="inviteeSearch" class="form-control" placeholder="ค้นหาชื่อหรือตำแหน่ง"></div><div class="d-flex align-items-center gap-2"><span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2"><span id="selectedInvitees">0</span> คนที่เลือก</span><button type="button" id="clearInvitees" class="btn btn-sm btn-link text-secondary">ล้างทั้งหมด</button></div></div>
                        <div class="invitee-list" id="inviteeList">
                            @forelse($users as $user)
                                <label class="invitee-item" data-search="{{ mb_strtolower($user->name.' '.($user->position ?? '')) }}"><input class="form-check-input invitee-checkbox" type="checkbox" name="invitees[]" value="{{ $user->id }}" @checked(in_array($user->id, old('invitees', [])))><span class="invitee-avatar">{{ mb_substr($user->name, 0, 1) }}</span><span class="invitee-info"><strong>{{ $user->name }}</strong><small>{{ $user->position ?: 'ไม่ระบุตำแหน่ง' }}</small></span></label>
                            @empty<div class="text-center text-muted py-4">ยังไม่มีรายชื่อบุคลากรสำหรับเชิญ</div>@endforelse
                        </div>
                        @error('invitees')<div class="text-danger small mt-2">{{ $message }}</div>@enderror @error('invitees.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <aside class="booking-summary card border-0 shadow-sm"><div class="card-body p-4"><div class="d-flex align-items-center justify-content-between mb-4"><h5 class="fw-bold mb-0">สรุปการจอง</h5><i class="fas fa-clipboard-check text-primary fs-4"></i></div><div class="summary-title" id="summaryTitle">ยังไม่ได้ระบุหัวข้อ</div><div class="summary-list">
                    <div><span class="summary-icon bg-primary-subtle text-primary"><i class="fas fa-tag"></i></span><span><small>ประเภท</small><strong id="summaryType">นัดประชุม</strong></span></div>
                    <div><span class="summary-icon bg-success-subtle text-success"><i class="fas fa-door-open"></i></span><span><small>ห้อง</small><strong id="summaryRoom">ยังไม่ได้เลือกห้อง</strong></span></div>
                    <div><span class="summary-icon bg-warning-subtle text-warning-emphasis"><i class="far fa-calendar-alt"></i></span><span><small>วันและเวลา</small><strong id="summaryDate">ยังไม่ได้เลือกเวลา</strong></span></div>
                    <div id="summaryInviteeRow"><span class="summary-icon bg-info-subtle text-info"><i class="fas fa-users"></i></span><span><small>ผู้เข้าร่วม</small><strong><span id="summaryInvitees">0</span> คน</strong></span></div>
                </div><div class="summary-confirmation"><i class="fas fa-shield-alt"></i><span><strong>ระบบตรวจห้องว่างก่อนบันทึก</strong><small>หากมีรายการจองทับ ระบบจะแจ้งให้เลือกเวลาใหม่</small></span></div><button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill fw-bold shadow-sm mt-4" id="submitBooking" @disabled($rooms->isEmpty())><i class="fas fa-check me-2"></i>ยืนยันการจอง</button><a href="{{ route('bookings.index') }}" class="btn btn-link text-secondary text-decoration-none w-100 mt-2">ยกเลิกและกลับหน้าปฏิทิน</a></div></aside>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const holidays = @json($holidays->mapWithKeys(fn ($holiday) => [$holiday->holiday_date => $holiday->name]));
    const form = document.getElementById('bookingForm'), title = document.getElementById('title'), room = document.getElementById('room_id');
    const startInput = document.getElementById('start_time'), endInput = document.getElementById('end_time'), durationFeedback = document.getElementById('durationFeedback');
    let startPicker, endPicker;
    function holidayDay(dObj, dStr, fp, dayElem) { const key = fp.formatDate(dayElem.dateObj, 'Y-m-d'); if (holidays[key]) { dayElem.classList.add('booking-holiday'); dayElem.title = 'วันหยุด: ' + holidays[key]; } }
    function selectedType() { return document.querySelector('input[name="booking_type"]:checked'); }
    function pad(value) { return String(value).padStart(2, '0'); }
    function formatDate(date) { return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear() + 543} ${pad(date.getHours())}:${pad(date.getMinutes())}`; }
    function updateDuration() {
        const start = startPicker?.selectedDates[0], end = endPicker?.selectedDates[0];
        if (!start || !end) { durationFeedback.textContent = 'ยังไม่ได้เลือกเวลาครบ'; durationFeedback.className = 'duration-feedback ms-md-auto'; document.getElementById('summaryDate').textContent = 'ยังไม่ได้เลือกเวลา'; return; }
        const minutes = Math.round((end - start) / 60000);
        if (minutes <= 0) { durationFeedback.textContent = 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม'; durationFeedback.className = 'duration-feedback duration-error ms-md-auto'; return; }
        const hours = Math.floor(minutes / 60), remainder = minutes % 60, duration = [hours ? `${hours} ชม.` : '', remainder ? `${remainder} นาที` : ''].filter(Boolean).join(' ');
        durationFeedback.textContent = `ระยะเวลา ${duration}`; durationFeedback.className = 'duration-feedback duration-ok ms-md-auto'; document.getElementById('summaryDate').textContent = `${formatDate(start)} – ${pad(end.getHours())}:${pad(end.getMinutes())} น.`;
    }
    const options = {enableTime:true,time_24hr:true,locale:'th',dateFormat:'Y-m-d H:i',altInput:true,altFormat:'d/m/Y เวลา H:i น.',minuteIncrement:15,allowInput:true,minDate:'today',onDayCreate:holidayDay};
    startPicker = flatpickr(startInput, {...options, onChange:function(dates){ if(!dates.length)return; const start=dates[0]; endPicker.set('minDate',start); if(!endPicker.selectedDates[0]||endPicker.selectedDates[0]<=start)endPicker.setDate(new Date(start.getTime()+3600000),true); updateDuration(); }});
    endPicker = flatpickr(endInput, {...options, onChange:updateDuration});
    document.querySelectorAll('.quick-duration').forEach(button => button.addEventListener('click', function(){ const start=startPicker.selectedDates[0]; if(!start){startPicker.open();return;} endPicker.setDate(new Date(start.getTime()+Number(this.dataset.minutes)*60000),true); }));
    function updateSummary() {
        document.getElementById('summaryTitle').textContent = title.value.trim() || 'ยังไม่ได้ระบุหัวข้อ';
        const type=selectedType(); document.getElementById('summaryType').textContent=type?.closest('label').querySelector('strong').textContent||'-';
        const option=room.options[room.selectedIndex]; document.getElementById('summaryRoom').textContent=option?.dataset.roomName?`${option.dataset.roomName} · ${option.dataset.capacity} คน`:'ยังไม่ได้เลือกห้อง';
        const count=document.querySelectorAll('.invitee-checkbox:checked').length; document.getElementById('selectedInvitees').textContent=count; document.getElementById('summaryInvitees').textContent=count;
        const meeting=type?.value==='meeting'; document.getElementById('invitees_section').hidden=!meeting; document.getElementById('summaryInviteeRow').hidden=!meeting;
    }
    title.addEventListener('input',updateSummary); room.addEventListener('change',updateSummary); document.querySelectorAll('input[name="booking_type"],.invitee-checkbox').forEach(input=>input.addEventListener('change',updateSummary));
    document.getElementById('clearInvitees').addEventListener('click',function(){document.querySelectorAll('.invitee-checkbox').forEach(input=>input.checked=false);updateSummary();});
    document.getElementById('inviteeSearch').addEventListener('input',function(){const keyword=this.value.trim().toLocaleLowerCase('th');document.querySelectorAll('.invitee-item').forEach(item=>item.hidden=!item.dataset.search.includes(keyword));});
    form.addEventListener('submit',function(event){const start=startPicker.selectedDates[0],end=endPicker.selectedDates[0];if(!start||!end||end<=start){event.preventDefault();durationFeedback.textContent='กรุณาเลือกเวลาเริ่มและสิ้นสุดให้ถูกต้อง';durationFeedback.className='duration-feedback duration-error ms-md-auto';document.getElementById('timeSection').scrollIntoView({behavior:'smooth',block:'center'});return;}const submit=document.getElementById('submitBooking');submit.disabled=true;submit.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>กำลังตรวจสอบห้องว่าง...';});
    document.querySelector('.content-area')?.addEventListener('scroll',function(){startPicker.close();endPicker.close();}); updateSummary(); updateDuration();
});
</script>

<style>
.booking-create-page{max-width:1440px}.booking-auto-status{display:flex;align-items:center;gap:.75rem;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:14px;padding:.7rem 1rem}.booking-auto-status .status-dot{width:11px;height:11px;border-radius:50%;background:#10b981;box-shadow:0 0 0 5px rgba(16,185,129,.12)}.booking-auto-status div{display:flex;flex-direction:column;line-height:1.25}.booking-auto-status small{opacity:.75}.booking-section,.booking-summary{border-radius:20px}.section-heading{display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.5rem}.section-heading h5{font-weight:800;margin:0 0 .25rem}.section-heading p{color:#64748b;margin:0;font-size:.9rem}.section-number{display:inline-grid;place-items:center;min-width:36px;height:36px;border-radius:11px;background:#e8f1ff;color:#0d6efd;font-weight:800}
.booking-type-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.booking-type-option input{position:absolute;opacity:0;pointer-events:none}.booking-type-card{height:100%;display:flex;align-items:center;gap:.75rem;padding:1rem;border:1.5px solid #e2e8f0;border-radius:14px;cursor:pointer;transition:.18s;background:#fff}.booking-type-card>i:first-child{font-size:1.2rem;color:#64748b}.booking-type-card span{display:flex;flex-direction:column;min-width:0}.booking-type-card small{font-size:.72rem;color:#64748b;margin-top:.15rem}.selected-check{margin-left:auto;color:#0d6efd;opacity:0}.booking-type-option input:checked+.booking-type-card{border-color:#0d6efd;background:#f4f8ff;box-shadow:0 0 0 3px rgba(13,110,253,.08)}.booking-type-option input:checked+.booking-type-card .selected-check{opacity:1}.booking-type-option input:focus-visible+.booking-type-card{outline:3px solid rgba(13,110,253,.25)}
.form-control,.form-select{border-color:#dbe3ee;border-radius:12px}.form-control-lg,.form-select-lg{font-size:1rem;min-height:50px}.input-icon-wrap{position:relative}.input-icon-wrap>i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#64748b;z-index:2}.input-icon-wrap .form-control{padding-left:2.7rem}.duration-feedback{font-size:.85rem;color:#64748b;font-weight:600}.duration-ok{color:#047857}.duration-error{color:#dc2626}.holiday-help{display:flex;align-items:center;gap:.5rem;color:#64748b;font-size:.8rem}.holiday-dot{width:10px;height:10px;border-radius:50%;background:#f59e0b;flex:none}.flatpickr-day.booking-holiday{background:#fef3c7!important;border-color:#f59e0b!important;color:#92400e!important;font-weight:700}.flatpickr-day.booking-holiday:hover{background:#fde68a!important}
.invite-search{position:relative;flex:1;max-width:360px}.invite-search i{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);color:#94a3b8}.invite-search input{padding-left:2.5rem}.invitee-list{display:grid;grid-template-columns:repeat(2,1fr);gap:.65rem;max-height:330px;overflow:auto;padding:.15rem}.invitee-item{display:flex;align-items:center;gap:.7rem;border:1px solid #e2e8f0;border-radius:13px;padding:.75rem;cursor:pointer;transition:.15s}.invitee-item:hover{border-color:#93c5fd;background:#f8fbff}.invitee-item:has(input:checked){border-color:#60a5fa;background:#eff6ff}.invitee-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:#dbeafe;color:#1d4ed8;font-weight:800}.invitee-info{display:flex;flex-direction:column;min-width:0}.invitee-info strong,.invitee-info small{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.invitee-info small{color:#64748b;font-size:.75rem}
.booking-summary{position:sticky;top:1.25rem}.summary-title{font-size:1.05rem;font-weight:800;background:#f8fafc;border-radius:13px;padding:1rem;margin-bottom:1.25rem;word-break:break-word}.summary-list{display:flex;flex-direction:column;gap:1rem}.summary-list>div{display:flex;align-items:center;gap:.8rem}.summary-list span:not(.summary-icon){display:flex;flex-direction:column;min-width:0}.summary-list small{color:#64748b}.summary-list strong{font-size:.9rem;white-space:normal}.summary-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;flex:none}.summary-confirmation{display:flex;gap:.75rem;background:#f0fdf4;color:#166534;border-radius:13px;padding:1rem;margin-top:1.5rem}.summary-confirmation>i{margin-top:.15rem}.summary-confirmation span{display:flex;flex-direction:column}.summary-confirmation small{opacity:.75;font-size:.75rem;margin-top:.15rem}
@media(max-width:991.98px){.booking-summary{position:static}.booking-type-grid{grid-template-columns:1fr}.invitee-list{grid-template-columns:1fr}}@media(max-width:575.98px){.booking-create-page{padding-left:.75rem!important;padding-right:.75rem!important}.booking-section .card-body{padding:1.25rem!important}.booking-auto-status{width:100%}.invite-search{max-width:none;width:100%}.quick-duration{flex:1}.section-heading p{font-size:.8rem}}
</style>
@endsection
