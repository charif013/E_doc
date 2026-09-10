{{-- 🌟 สำหรับ: หนังสือรับเข้า (Incoming) - เกษียนสั่งการ / เสนอเรื่อง --}}
@php
    $user = auth()->user();
    $isNayok = $user->hasRole('executive');
    $noAssignmentValue = '__NO_ASSIGNMENT__';
    $selectedAssignment = old('assignment_choice');
    if ($selectedAssignment === null) {
        $selectedAssignment = $document->assigned_to ?: ($isNayok ? $noAssignmentValue : '');
    }
@endphp

<form action="{{ route('documents.review', $document->uuid ?? $document->id) }}" method="POST" id="reviewFormIncoming">    @csrf
    <input type="hidden" name="is_approved" id="is_approved_incoming" value="1">

    <div class="p-3">
        <div class="row g-3">
            
            {{-- 🌟 ซ่อน/แสดง ช่องมอบหมาย: เฉพาะ ปลัด, รองปลัด และ นายกฯ ถึงจะเห็น --}}
            @hasanyrole('palad|deputy-palad|executive')
            <div class="col-md-12 mb-2">
                <label class="form-label fw-bold text-dark" for="assignment_choice">
                    <i class="fas fa-share-nodes me-1 text-primary"></i> การมอบหมายงาน <span class="text-danger">*</span>
                </label>
                
                {{-- 💡 ถ้านายกฯ เปิดดู และปลัดเคยเลือกไว้แล้ว ให้โชว์แจ้งเตือนให้รู้ --}}
                @if($isNayok)
                    <div class="alert alert-info py-2 px-3 mb-2 small border-0" style="background-color: #e0f2fe; color: #0369a1;">
                        <i class="fas fa-info-circle me-1"></i> ปลัด อบต. เสนอ: <strong>{{ $document->assigned_to ?: 'ไม่มอบหมาย' }}</strong><br>
                        <span class="text-muted" style="font-size: 12px;">หากไม่เปลี่ยน ระบบจะยืนยันตามค่าที่ปลัดเสนอไว้</span>
                    </div>
                @endif

                <select name="assignment_choice" id="assignment_choice" class="form-select form-select-lg border-primary-subtle rounded-3" required>
                    <option value="" disabled {{ $selectedAssignment === '' ? 'selected' : '' }}>-- กรุณาเลือกการมอบหมาย --</option>
                    <option value="{{ $noAssignmentValue }}" {{ $selectedAssignment === $noAssignmentValue ? 'selected' : '' }}>ไม่มอบหมายให้ส่วนราชการใด</option>
                    <option value="สำนักงานปลัด"                      {{ $selectedAssignment === 'สำนักงานปลัด'                      ? 'selected' : '' }}>สำนักงานปลัด</option>
                    <option value="กองคลัง"                            {{ $selectedAssignment === 'กองคลัง'                            ? 'selected' : '' }}>กองคลัง</option>
                    <option value="กองช่าง"                            {{ $selectedAssignment === 'กองช่าง'                            ? 'selected' : '' }}>กองช่าง</option>
                    <option value="กองการศึกษา ศาสนา และวัฒนธรรม"     {{ $selectedAssignment === 'กองการศึกษา ศาสนา และวัฒนธรรม'     ? 'selected' : '' }}>กองการศึกษา ศาสนา และวัฒนธรรม</option>
                    <option value="กองสาธารณสุขและสิ่งแวดล้อม"        {{ $selectedAssignment === 'กองสาธารณสุขและสิ่งแวดล้อม'        ? 'selected' : '' }}>กองสาธารณสุขและสิ่งแวดล้อม</option>
                    <option value="กองสวัสดิการสังคม"                 {{ $selectedAssignment === 'กองสวัสดิการสังคม'                 ? 'selected' : '' }}>กองสวัสดิการสังคม</option>
                </select>
                <div class="form-text"><i class="fas fa-lock me-1"></i>งานจะถูกส่งให้ส่วนราชการหลังผู้ลงนามคนสุดท้ายอนุมัติแล้วเท่านั้น</div>
                @error('assignment_choice')<div class="text-danger small fw-bold mt-1">{{ $message }}</div>@enderror
            </div>
            @endhasanyrole

            <div class="col-md-12 mb-3">
                <label class="form-label fw-bold text-dark"><i class="fas fa-comment-dots me-1 text-primary"></i> บันทึกความเห็น / สั่งการ</label>
                <textarea name="comment" class="form-control" rows="3" placeholder="ระบุความเห็น หรือคำสั่งการ (ถ้ามี)..."></textarea>
            </div>
        </div>

        <div class="row align-items-end mt-2">
            <div class="col-md-5 mb-2">
                <label class="form-label fw-bold text-dark">รหัส PIN 6 หลัก *</label>
                <input type="password" name="pin" maxlength="6" class="form-control text-center fw-bold" 
                       style="letter-spacing: 10px; font-size: 20px; border-radius: 12px;" placeholder="******" required autocomplete="off">
            </div>
            <div class="col-md-7 mb-2">
                <div class="d-flex gap-2">
                    <button type="submit" onclick="prepareIncomingReview(true)" class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-signature me-1"></i> 
                        @if($isNayok)
                            ลงนามสั่งการ
                        @else
                            เสนอเพื่อโปรดพิจารณา
                        @endif
                    </button>
                    {{-- 🌟 เพิ่มปุ่มตีกลับเผื่อใช้ --}}
                    <button type="submit" onclick="prepareIncomingReview(false)" class="btn btn-outline-danger py-2 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-times-circle me-1"></i> ตีกลับ
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    function prepareIncomingReview(isApproved) {
        document.getElementById('is_approved_incoming').value = isApproved ? '1' : '0';
        const assignment = document.getElementById('assignment_choice');
        if (assignment) assignment.required = isApproved;
    }
</script>
