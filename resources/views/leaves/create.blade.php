@extends('layouts.app')

@section('title', 'ยื่นแบบฟอร์มใบลา')

@section('content')
<div class="container-fluid px-4 py-3 leave-page-wrapper">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 page-title">ระบบการลาออนไลน์</h4>
            <small class="text-muted">ปีงบประมาณ {{ now()->year + 543 }}</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted fw-bold">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
            <a href="{{ route('home') }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก
            </a>
        </div>
    </div>

    {{-- Validation Errors --}}
    @if ($errors->any())
        <div class="alert alert-danger border-0 rounded-3 mb-4 shadow-sm">
            <h6 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>พบข้อผิดพลาด:</h6>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">

        {{-- ===== ฝั่งซ้าย: ฟอร์ม ===== --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm form-card">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 px-md-5">
                    <h5 class="mb-0 fw-bold card-main-title">
                        <i class="fas fa-file-alt me-2 opacity-50"></i>แบบฟอร์มใบลา
                    </h5>
                </div>

                <div class="card-body p-4 p-md-5 pt-4">
                    <form action="{{ route('leaves.store') }}" method="POST" id="leaveForm" novalidate>
                        @csrf

                        {{-- ── Section 1: ข้อมูลผู้ยื่น ── --}}
                        <div class="section-title mb-3">
                            <div class="section-indicator"></div>
                            <h6 class="fw-bold mb-0 text-secondary">ข้อมูลผู้ยื่นใบลา</h6>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label class="form-label label-style">ชื่อ-นามสกุล</label>
                                <input type="text" class="form-control custom-input bg-light" value="{{ Auth::user()->name }}" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label label-style">ตำแหน่ง</label>
                                <input type="text" class="form-control custom-input bg-light" value="{{ Auth::user()->position }}" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label label-style">สังกัด (กอง/สำนัก)</label>
                                <input type="text" class="form-control custom-input bg-light" value="{{ Auth::user()->department }}" readonly>
                            </div>
                        </div>

                        {{-- ── Section 2: ประเภทการลา ── --}}
                        <div class="section-title mb-3">
                            <div class="section-indicator"></div>
                            <h6 class="fw-bold mb-0 text-secondary">ประเภทการลา <span class="text-danger">*</span></h6>
                        </div>
                        <div class="row mb-2">
                            @foreach($balances as $type => $data)
                            <div class="col-md-4 col-sm-6 mb-3">
                                <input type="radio" name="leave_type" id="type_{{ $loop->index }}"
                                       value="{{ $type }}" class="d-none leave-type-radio" required>
                                <label for="type_{{ $loop->index }}" class="leave-type-card w-100 m-0 shadow-sm">
                                    <i class="far fa-circle radio-icon fs-5"></i>
                                    <span class="fw-bold">{{ $type }}</span>
                                </label>
                            </div>
                            @endforeach
                        </div>
                        {{-- แสดงสิทธิ์คงเหลือแบบ inline badge --}}
                        <div id="leave_balance_show" class="mb-4" style="display:none;"></div>

                        {{-- ── Section 3: วันที่ขอลา ── --}}
                        <div class="section-title mb-3">
                            <div class="section-indicator"></div>
                            <h6 class="fw-bold mb-0 text-secondary">วันที่ขอลา</h6>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <label class="form-label label-style">วันที่เริ่มลา <span class="text-danger">*</span></label>
                                <input type="text" name="start_date" id="start_date"
                                       class="form-control custom-input bg-light"
                                       placeholder="ระบุประเภทการลาก่อน"
                                       autocomplete="off" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label label-style">วันที่สิ้นสุด <span class="text-danger">*</span></label>
                                <input type="text" name="end_date" id="end_date"
                                       class="form-control custom-input bg-light"
                                       placeholder="ระบุประเภทการลาก่อน"
                                       autocomplete="off" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex justify-content-between align-items-end mb-1">
                                    <label class="form-label label-style mb-0">จำนวนวันลา (วันทำการ) <span class="text-danger">*</span></label>
                                    <button type="button" id="btn_edit_halfday"
                                            class="btn btn-sm text-primary p-0 halfday-btn"
                                            style="display:none;">
                                        <i class="fas fa-edit"></i> ลาครึ่งวัน
                                    </button>
                                </div>
                                {{-- ค่าเต็มคำนวณ JS เก็บใน hidden, แสดงแยก --}}
                                <input type="hidden" name="total_days" id="total_days_hidden">
                                <select id="total_days_select" name="_total_days_select"
                                        class="form-select custom-input bg-light fw-bold text-teal"
                                        style="display:none;" aria-label="จำนวนวันลา">
                                </select>
                                <input type="text" id="total_days_display"
                                       class="form-control custom-input bg-light fw-bold text-teal"
                                       placeholder="คำนวณอัตโนมัติ" readonly>
                            </div>
                            <div class="col-12">
                                <div id="quota_warning" class="alert alert-danger py-2 px-3 small fw-bold d-none" role="alert"></div>
                            </div>
                        </div>

                        {{-- ── Section 4: รายละเอียดเพิ่มเติม ── --}}
                        <div class="section-title mb-3">
                            <div class="section-indicator"></div>
                            <h6 class="fw-bold mb-0 text-secondary">รายละเอียดเพิ่มเติม</h6>
                        </div>
                        <div class="row mb-4">
                            <div class="col-12 mb-3">
                                <label class="form-label label-style">เหตุผลการลา / อาการป่วย <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control custom-input"
                                          rows="3" maxlength="500"
                                          placeholder="ระบุเหตุผลหรืออาการ..." required>{{ old('reason') }}</textarea>
                                <div class="text-end text-muted small mt-1">
                                    <span id="reason_count">0</span>/500
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label label-style">ที่อยู่ หรือ เบอร์โทรที่ติดต่อได้ระหว่างลา <span class="text-danger">*</span></label>
                                <input type="text" name="contact_info" class="form-control custom-input"
                                       placeholder="เบอร์โทรศัพท์ หรือ ที่อยู่..."
                                       maxlength="200"
                                       value="{{ old('contact_info') }}" required>
                            </div>
                        </div>

                        {{-- ── Section 5: มอบหมายงานแทน ── --}}
                        <div class="section-title mb-3">
                            <div class="section-indicator"></div>
                            <h6 class="fw-bold mb-0 text-secondary">มอบหมายงานแทน</h6>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label label-style">ผู้รับมอบหมายงาน <span class="text-muted fw-normal">(ถ้ามี)</span></label>
                                <select name="delegate_id" class="form-select custom-input">
                                    <option value="">-- ไม่ระบุผู้รับมอบงาน --</option>
                                    @isset($users)
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ old('delegate_id') == $u->id ? 'selected' : '' }}>
                                                {{ $u->name }} ({{ $u->position }})
                                            </option>
                                        @endforeach
                                    @endisset
                                </select>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="text-end pt-3 mt-2 border-top">
                            <a href="{{ route('leaves.index') }}" class="btn btn-outline-secondary rounded-pill px-4 me-2 fw-bold">
                                ยกเลิก
                            </a>
                            <button type="submit" id="submitBtn" class="btn btn-teal btn-lg px-5 rounded-pill shadow-sm fw-bold text-white">
                                <span id="submitBtnText"><i class="fas fa-paper-plane me-2"></i>ส่งใบลา</span>
                                <span id="submitBtnLoading" class="d-none">
                                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>กำลังส่ง...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===== ฝั่งขวา: Sidebar ===== --}}
        <div class="col-lg-4">

            {{-- สถิติการลา --}}
            <div class="card border-0 shadow-sm mb-4 stats-card">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h6 class="mb-0 fw-bold card-main-title">
                        <i class="fas fa-chart-pie me-2 opacity-50"></i>สถิติการลาในปีงบประมาณนี้
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach($balances as $type => $data)
                        <div class="col-6">
                            <div class="stat-item p-3 rounded-3 text-center border">
                                <div class="small text-muted mb-1 stat-type-label">{{ $type }}</div>
                                <h4 class="fw-bold mb-0 {{ $data['remaining'] <= 0 ? 'text-danger' : 'text-teal' }}">
                                    {{ $data['remaining'] }}<span class="fs-6 fw-normal"> วัน</span>
                                </h4>
                                <div class="progress mt-2 mx-auto" style="height:5px; width:80%;">
                                    <div class="progress-bar {{ $data['percent'] >= 100 ? 'bg-danger' : 'bg-teal' }}"
                                         role="progressbar"
                                         style="width:{{ min($data['percent'], 100) }}%;"
                                         aria-valuenow="{{ $data['percent'] }}"
                                         aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="text-muted mt-1" style="font-size:0.7rem;">
                                    ใช้ไป {{ $data['used'] }}/{{ $data['limit'] }}
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- กฎการนับวัน --}}
            <div class="card border-0 shadow-sm holiday-info-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-warning-emphasis mb-3">
                        <i class="fas fa-calendar-alt me-2"></i>กฎการนับวันหยุดราชการ
                    </h6>
                    <div class="small text-muted">
                        <p class="mb-2">
                            <span class="badge bg-danger-subtle text-danger-emphasis me-1">นับรวม</span>
                            <strong>เสาร์-อาทิตย์:</strong> ลาคลอด, ลาอุปสมบท, ลาศึกษา/อบรม, ลาเกณฑ์ทหาร
                        </p>
                        <p class="mb-0">
                            <span class="badge bg-success-subtle text-success-emphasis me-1">ไม่นับ</span>
                            <strong>วันทำการเท่านั้น:</strong> ลาป่วย, ลากิจ, ลาพักผ่อน, ลาช่วยภริยาคลอด
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ── Flatpickr ── --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ─── ข้อมูลจาก PHP ───────────────────────────────────────────────────────
    const leaveBalances    = @json($balances);
    const publicHolidays   = @json($publicHolidays ?? []);
    
   // 🌟 1. กันเหนียว (Fallback) ถ้าระบบหา Config ไม่เจอ จะได้ไม่พัง
    @php
        $defaultCountWeekends = ['ลาคลอดบุตร', 'ลาอุปสมบท/ประกอบพิธีฮัจย์', 'ลาเกณฑ์ทหาร/เตรียมพล'];
        $countWeekendsConfig = config('leave.count_weekends', $defaultCountWeekends);
    @endphp
    const countWeekendsTypes = @json($countWeekendsConfig);
    
    // 🌟 3. รับค่าเดิมกรณี Validation Error
    const oldLeaveType = @json(old('leave_type'));
    const oldStartDate = @json(old('start_date'));
    const oldEndDate   = @json(old('end_date'));
    const oldTotalDays = @json(old('total_days'));

    // ─── Element References ──────────────────────────────────────────────────
    const leaveTypeRadios  = document.querySelectorAll('input[name="leave_type"]');
    const totalHidden      = document.getElementById('total_days_hidden');
    const totalDisplay     = document.getElementById('total_days_display');
    const totalSelect      = document.getElementById('total_days_select');
    const warningBox       = document.getElementById('quota_warning');
    const balanceShow      = document.getElementById('leave_balance_show');
    const submitBtn        = document.getElementById('submitBtn');
    const submitBtnText    = document.getElementById('submitBtnText');
    const submitBtnLoading = document.getElementById('submitBtnLoading');
    const btnEditHalfday   = document.getElementById('btn_edit_halfday');
    const reasonTextarea   = document.querySelector('textarea[name="reason"]');
    const reasonCount      = document.getElementById('reason_count');

    let calculatedDays   = 0;   // วันทำการที่คำนวณได้จริง
    let selectedLeaveType = '';
    let isHalfdayMode    = false;

    // ─── Textarea character counter ─────────────────────────────────────────
    if (reasonTextarea.value.length > 0) {
        reasonCount.textContent = reasonTextarea.value.length; // อัปเดตตอนโหลดหน้า (ถ้ามีค่า old)
    }
    reasonTextarea.addEventListener('input', function () {
        reasonCount.textContent = this.value.length;
    });

    // ─── Flatpickr helper: ระบายสีวันหยุดบนปฏิทิน ──────────────────────────
    function markHolidays(dObj, dStr, fp, dayElem) {
        const date      = dayElem.dateObj;
        const dayOfWeek = date.getDay();
        
        // 🌟 2. ใช้ Flatpickr จัดการเรื่อง Timezone ให้เลย โค้ดคลีนขึ้น
        const dateStr   = flatpickr.formatDate(date, "Y-m-d");

        if (dayOfWeek === 0 || dayOfWeek === 6 || publicHolidays.includes(dateStr)) {
            dayElem.classList.add('is-holiday');
        }
    }

    // ─── Init Flatpickr ──────────────────────────────────────────────────────
    const fpConfig = {
        locale      : 'th',
        altInput    : true,
        altFormat   : 'd/m/Y',
        dateFormat  : 'Y-m-d',
        disableMobile: true,          // บังคับใช้ flatpickr บน mobile ด้วย
        onChange    : calculateDays,
        onDayCreate : markHolidays,
    };

    const fpStart = flatpickr('#start_date', fpConfig);
    const fpEnd   = flatpickr('#end_date',   {
        ...fpConfig,
        onChange: [
            calculateDays,
            (dates) => {
                // ล็อค end_date ไม่ให้เลือกก่อน start_date
                if (fpStart.selectedDates[0]) {
                    fpEnd.set('minDate', fpStart.selectedDates[0]);
                }
            }
        ]
    });

    // ล็อคปฏิทินจนกว่าจะเลือกประเภทการลา
    setCalendarLocked(true);

    function setCalendarLocked(locked) {
        [fpStart, fpEnd].forEach(fp => {
            fp.set('clickOpens', !locked);
            fp.altInput.disabled = locked;
            fp.altInput.classList.toggle('bg-light', locked);
        });
    }

    // ─── แสดง badge สิทธิ์คงเหลือ ───────────────────────────────────────────
    function showCurrentBalance(type) {
        const data = leaveBalances[type];
        if (!data) { balanceShow.style.display = 'none'; return; }

        const isOk  = data.remaining > 0;
        const icon  = isOk ? 'fa-check-circle' : 'fa-times-circle';
        const cls   = isOk ? 'bg-success-subtle text-success-emphasis' : 'bg-danger-subtle text-danger-emphasis';

        balanceShow.innerHTML = `
            <span class="badge ${cls} px-3 py-2 rounded-pill fs-6 shadow-sm">
                <i class="fas ${icon} me-1"></i>
                สิทธิ ${type} คงเหลือ: ${data.remaining} วัน
            </span>`;
        balanceShow.style.display = 'block';
    }

    // ─── เลือกประเภทการลา ────────────────────────────────────────────────────
    leaveTypeRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            selectedLeaveType = this.value;

            // อัปเดตไอคอน radio
            document.querySelectorAll('.radio-icon').forEach(icon => {
                icon.className = 'far fa-circle radio-icon fs-5';
            });
            this.nextElementSibling.querySelector('.radio-icon').className = 'fas fa-dot-circle radio-icon fs-5';

            showCurrentBalance(selectedLeaveType);
            setCalendarLocked(false);
            fpStart.altInput.placeholder = 'คลิกเลือกวันที่เริ่มต้น';
            fpEnd.altInput.placeholder   = 'คลิกเลือกวันที่สิ้นสุด';

            // Reset half-day mode เมื่อเปลี่ยนประเภท
            resetHalfdayMode();
            calculateDays();
        });
    });

    // ─── คำนวณวันทำการ ───────────────────────────────────────────────────────
    function calculateDays() {
        const startVal = document.getElementById('start_date').value;
        const endVal   = document.getElementById('end_date').value;

        if (!selectedLeaveType || !startVal || !endVal) return;

        const start = new Date(startVal);
        const end   = new Date(endVal);

        if (end < start) {
            showWarning('วันสิ้นสุดต้องไม่อยู่ก่อนวันเริ่มต้น');
            clearDayResult();
            return;
        }

        // อัปเดต minDate ของ end ให้ตรงกับ start เสมอ
        fpEnd.set('minDate', start);

        const shouldCountWeekends = countWeekendsTypes.includes(selectedLeaveType);
        let days    = 0;
        let current = new Date(start);

        while (current <= end) {
            const dow  = current.getDay();
            
            // 🌟 2. ใช้ Flatpickr จัดการ Date String
            const dStr = flatpickr.formatDate(current, "Y-m-d");

            if (shouldCountWeekends) {
                days++;
            } else {
                if (dow !== 0 && dow !== 6 && !publicHolidays.includes(dStr)) {
                    days++;
                }
            }
            current.setDate(current.getDate() + 1);
        }

        calculatedDays = days;
        resetHalfdayMode();      // reset แล้ว set ค่าใหม่
        setDayValue(days);

        if (days > 0) {
            btnEditHalfday.style.display = 'inline-block';
        } else {
            btnEditHalfday.style.display = 'none';
        }

        checkQuota(days);
    }

    // ─── Helper: set ค่าวันลา ────────────────────────────────────────────────
    function setDayValue(val) {
        totalHidden.value  = val;
        totalDisplay.value = val > 0 ? val + ' วัน' : '';
    }

    function clearDayResult() {
        totalHidden.value  = '';
        totalDisplay.value = '';
        btnEditHalfday.style.display = 'none';
        submitBtn.disabled = true;
    }

    // ─── ปุ่มลาครึ่งวัน → เปลี่ยนเป็น dropdown ──────────────────────────────
    btnEditHalfday.addEventListener('click', function () {
        if (calculatedDays < 1) return;

        isHalfdayMode = true;

        // สร้าง option สำหรับ select
        totalSelect.innerHTML = '';
        [{v: calculatedDays, label: `${calculatedDays} วัน (เต็มวัน)`},
         {v: calculatedDays - 0.5, label: `${calculatedDays - 0.5} วัน (ครึ่งวัน)`}
        ].forEach(opt => {
            const el = document.createElement('option');
            el.value       = opt.v;
            el.textContent = opt.label;
            totalSelect.appendChild(el);
        });

        totalDisplay.style.display = 'none';
        totalSelect.style.display  = '';
        this.style.display         = 'none';

        totalHidden.value = calculatedDays;
        totalSelect.addEventListener('change', function () {
            totalHidden.value = this.value;
            checkQuota(parseFloat(this.value));
        });

        checkQuota(calculatedDays);
    });

    function resetHalfdayMode() {
        isHalfdayMode             = false;
        totalDisplay.style.display = '';
        totalSelect.style.display  = 'none';
    }

    // ─── ตรวจสิทธิ์การลา ─────────────────────────────────────────────────────
    function checkQuota(inputDays) {
        if (!selectedLeaveType || !leaveBalances[selectedLeaveType]) {
            hideWarning();
            submitBtn.disabled = false;
            return;
        }

        const remain = leaveBalances[selectedLeaveType].remaining;

        if (inputDays > remain) {
            showWarning(`ลาเกินสิทธิ! ต้องการ ${inputDays} วัน แต่เหลือสิทธิ์แค่ ${remain} วัน`);
            submitBtn.disabled = true;
        } else if (inputDays <= 0) {
            showWarning('จำนวนวันลาต้องมากกว่า 0');
            submitBtn.disabled = true;
        } else {
            hideWarning();
            submitBtn.disabled = false;
        }
    }

    function showWarning(msg) {
        warningBox.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>${msg}`;
        warningBox.classList.remove('d-none');
    }

    function hideWarning() {
        warningBox.classList.add('d-none');
        warningBox.innerHTML = '';
    }

    // ─── Loading state ตอน submit ────────────────────────────────────────────
    document.getElementById('leaveForm').addEventListener('submit', function (e) {
        // ตรวจ total_days ก่อนส่ง (server-side จะตรวจซ้ำอีกรอบ)
        const days = parseFloat(totalHidden.value);
        if (!days || days <= 0) {
            e.preventDefault();
            showWarning('กรุณาเลือกวันที่ขอลาให้ครบถ้วน');
            return;
        }

        submitBtnText.classList.add('d-none');
        submitBtnLoading.classList.remove('d-none');
        submitBtn.disabled = true;
    });

    // 🌟 3. ฟื้นฟูค่าเดิมเมื่อ Validation ไม่ผ่าน (Old Input Recovery) ───────────
    if (oldLeaveType) {
        // 1. เลือกการ์ดประเภทการลาที่เคยคลิก
        const radioToSelect = document.querySelector(`input[name="leave_type"][value="${oldLeaveType}"]`);
        if (radioToSelect) {
            radioToSelect.checked = true;
            radioToSelect.dispatchEvent(new Event('change'));
        }

        // 2. เติมวันที่ในปฏิทิน
        if (oldStartDate) fpStart.setDate(oldStartDate, true);
        if (oldEndDate) fpEnd.setDate(oldEndDate, true);

        // 3. จัดการกรณีที่เคยเลือก "ลาครึ่งวัน" เอาไว้
        if (oldTotalDays && oldTotalDays !== "") {
            setTimeout(() => {
                const calcDays = parseFloat(totalHidden.value);
                const oldDays = parseFloat(oldTotalDays);
                // ถ้าค่าเดิมน้อยกว่าค่าที่คำนวณได้ 0.5 (แปลว่าเคยถูกหักครึ่งวันไป)
                if (oldDays < calcDays && oldDays === calcDays - 0.5) {
                    btnEditHalfday.click();
                    totalSelect.value = oldDays;
                    totalSelect.dispatchEvent(new Event('change'));
                }
            }, 100);
        }
    }

});
</script>

<style>
/* ─── Color Tokens ────────────────────────────────────────────────────────── */
:root {
    --teal-dark  : #164f51;
    --teal-hover : #0f3638;
    --orange-acc : #d18b49;
    --border-def : #cbd5e1;
}

/* ─── Page ───────────────────────────────────────────────────────────────── */
.leave-page-wrapper { background-color: #f4f7f9; min-height: 90vh; }
.page-title         { color: var(--teal-dark); }

/* ─── Cards ──────────────────────────────────────────────────────────────── */
.form-card,
.stats-card         { border-radius: 15px; }
.card-main-title    { color: var(--teal-dark); }
.holiday-info-card  { border-radius: 15px; background-color: #fcf8e3; }
.stat-item          { background-color: #f8fafc; transition: box-shadow .2s; }
.stat-item:hover    { box-shadow: 0 2px 8px rgba(22,79,81,.12); }
.stat-type-label    { font-size: .78rem; }

/* ─── Form Controls ──────────────────────────────────────────────────────── */
.label-style { font-size: .8rem; font-weight: 700; color: #64748b; margin-bottom: .35rem; }

.custom-input {
    border-radius : 8px;
    border        : 1px solid var(--border-def);
    padding       : .6rem 1rem;
    transition    : border-color .2s, box-shadow .2s;
}
.custom-input:focus {
    border-color : var(--teal-dark);
    box-shadow   : 0 0 0 .2rem rgba(22,79,81,.12);
    outline      : none;
}
input:disabled,
input[readonly] { cursor: not-allowed; opacity: .7; }

/* ─── Section Indicator ──────────────────────────────────────────────────── */
.section-title      { display: flex; align-items: center; }
.section-indicator  {
    width           : 4px;
    height          : 20px;
    background-color: var(--orange-acc);
    border-radius   : 4px;
    margin-right    : 10px;
    flex-shrink     : 0;
}

/* ─── Leave Type Radio Cards ─────────────────────────────────────────────── */
.leave-type-card {
    border          : 1px solid #e2e8f0;
    border-radius   : 8px;
    padding         : 12px 15px;
    cursor          : pointer;
    transition      : all .2s;
    display         : flex;
    align-items     : center;
    gap             : 10px;
    background-color: #fff;
    color           : #475569;
    user-select     : none;
}
.leave-type-card:hover {
    border-color    : var(--teal-dark);
    background-color: #f0fdfa;
    color           : var(--teal-dark);
}
.leave-type-radio:checked + .leave-type-card {
    border-color    : var(--teal-dark);
    background-color: var(--teal-dark);
    color           : white;
    box-shadow      : 0 2px 8px rgba(22,79,81,.25);
}

/* ─── Buttons ────────────────────────────────────────────────────────────── */
.btn-teal        { background-color: var(--teal-dark); border: none; }
.btn-teal:hover  { background-color: var(--teal-hover); }
.text-teal       { color: var(--teal-dark) !important; }
.bg-teal         { background-color: var(--teal-dark) !important; }
.halfday-btn     { font-size: .75rem; line-height: 1.2; }

/* ─── Progress ───────────────────────────────────────────────────────────── */
.progress-bar.bg-teal { background-color: var(--teal-dark) !important; }

/* ─── Soft Badges ────────────────────────────────────────────────────────── */
.bg-success-subtle     { background-color: #d1e7dd !important; }
.text-success-emphasis { color: #0f5132 !important; }
.bg-danger-subtle      { background-color: #f8d7da !important; }
.text-danger-emphasis  { color: #842029 !important; }

/* ─── Flatpickr: วันหยุดราชการ ───────────────────────────────────────────── */
.flatpickr-day.is-holiday              { color: #dc3545 !important; font-weight: bold; }
.flatpickr-day.is-holiday:hover        { background-color: #f8d7da !important; }
.flatpickr-day.is-holiday.selected     { background-color: #dc3545 !important; color: white !important; border-color: #dc3545 !important; }
.flatpickr-day.is-holiday.inRange      { background-color: #f8d7da !important; color: #dc3545 !important; }
</style>
@endsection
