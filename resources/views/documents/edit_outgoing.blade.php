@extends('layouts.app')
@section('title', 'แก้ไขหนังสือส่งออก')

@section('content')
{{-- 🌟 ส่วนประกาศฟังก์ชันภายในเพื่อป้องกัน Error: Call to undefined function --}}
@php
    if (!function_exists('toThaiNum')) {
        function toThaiNum($string) {
            if (!$string) return '';
            $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
            return str_replace($arabic, $thai, $string);
        }
    }
@endphp

<style>
    .floating-popup {
        position: absolute; z-index: 1000; width: 100%; max-height: 250px; overflow-y: auto;
        border: 1px solid #cbd5e1; border-radius: 12px; background: white; 
        padding: 10px; margin-top: 5px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); display: none;
    }
    .popup-item {
        padding: 10px 12px; border-radius: 8px; border: 1px solid transparent;
        cursor: pointer; transition: background 0.2s; margin-bottom: 5px;
    }
    .popup-item:hover { background: #f1f5f9; border-color: #cbd5e1; }
    
    .step-circle {
        width: 32px; height: 32px; border-radius: 50%; font-size: 14px; font-weight: bold;
        display: flex; align-items: center; justify-content: center; transition: 0.3s;
    }
    .step-active { background: var(--warning); color: #000; border: 2px solid var(--warning); box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
    .step-inactive { background: #f8fafc; color: #94a3b8; border: 2px solid #e2e8f0; }
    .step-completed { background: #10b981; color: white; border: 2px solid #10b981; }
    
    .form-control, .form-select { border-radius: 8px; font-size: 14px; padding: 10px 14px; border-color: #cbd5e1; }
    .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.25rem rgba(2, 132, 199, 0.15); }
</style>

<div class="container-fluid px-4 py-3">
    <div class="mx-auto" style="max-width: 900px;">

        {{-- 🌟 Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                    <i class="fas fa-edit text-warning me-2"></i>แก้ไขหนังสือส่งออก
                </h4>
                <small class="text-muted">แก้ไขข้อมูลที่ถูกตีกลับ หรือแก้ไขฉบับร่าง</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-secondary rounded-pill px-3 py-2 shadow-sm" id="displaySpeed">ปกติ</span>
                <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm d-none" id="displaySecret"></span>
                {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ปุ่มยกเลิกเป็น UUID --}}
                <a href="{{ route('documents.show', $document->uuid ?? $document->id) }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 ms-2 shadow-sm bg-white fw-bold">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger shadow-sm" style="border-radius: 12px; font-size: 14px; border-left: 5px solid #dc2626;">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Step bar --}}
        <div class="d-flex align-items-center justify-content-center mb-4 bg-white p-3 rounded-pill shadow-sm border">
            <div class="d-flex align-items-center">
                <div id="step1-icon" class="step-circle step-active">1</div>
                <span id="step1-text" class="ms-2 fw-bold text-dark" style="font-size:14px;">แก้ไขข้อมูล</span>
            </div>
            <div style="width:60px; height:2px; background:#e2e8f0; margin:0 15px;"></div>
            <div class="d-flex align-items-center">
                <div id="step2-icon" class="step-circle step-inactive">2</div>
                <span id="step2-text" class="ms-2 fw-bold text-muted" style="font-size:14px;">ตรวจสอบเอกสาร (Preview)</span>
            </div>
        </div>

        {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ Action ของ Form เป็น UUID --}}
        <form action="{{ route('documents.update', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data" id="outgoingForm">
            @csrf
            @method('PUT')

            <div id="step1">
                
                {{-- Section 1: เลขที่และวันที่ --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px; border:1px solid #e4eaf0;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-list-ol text-primary me-2"></i>1. ข้อมูลพื้นฐานหนังสือ</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-1">เลขที่หนังสือออก <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <input type="hidden" name="running_number" id="running_number" value="{{ old('running_number', $document->running_number) }}">
                                    <input type="text" name="doc_number" id="doc_number" class="form-control border-0 bg-light" required value="{{ old('doc_number', $document->formatted_doc_number) }}">
                                    <button type="button" onclick="autoDocNo()" class="btn btn-primary fw-bold px-3">รันเลข</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-1">วันที่ออกเอกสาร <span class="text-danger">*</span></label>
                                <input type="date" name="doc_date" id="doc_date" class="form-control shadow-sm" required value="{{ old('doc_date', $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->format('Y-m-d') : date('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted mb-1">ส่วนราชการเจ้าของเรื่อง</label>
                                <input type="text" class="form-control shadow-sm bg-light text-muted fw-bold" value="{{ auth()->user()->department }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 2: ประเภทและชั้นความสำคัญ --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px; border:1px solid #e4eaf0;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-tags text-primary me-2"></i>2. ประเภทและชั้นความสำคัญ</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted mb-1">ประเภทหนังสือส่งออก <span class="text-danger">*</span></label>
                                <select name="doc_type_category" id="doc_type_category" class="form-select shadow-sm fw-bold text-dark">
                                    @php $cats = ['หนังสือภายนอก (กระดาษตราครุฑ)', 'หนังสือภายใน / บันทึกข้อความ', 'หนังสือประทับตรา', 'หนังสือสั่งการ (คำสั่ง / ระเบียบ / ข้อบังคับ)', 'หนังสือประชาสัมพันธ์ (ประกาศ / แถลงการณ์)']; @endphp
                                    @foreach($cats as $cat)
                                        <option value="{{ $cat }}" {{ old('doc_type_category', $document->doc_type_category) == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">ชั้นความเร็ว</label>
                                <select name="doc_speed" id="doc_speed" class="form-select shadow-sm" onchange="updateBadges()">
                                    @foreach(['ปกติ', 'ด่วน', 'ด่วนมาก', 'ด่วนที่สุด'] as $s)
                                        <option value="{{ $s }}" {{ old('doc_speed', $document->doc_speed) == $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1">ชั้นความลับ</label>
                                <select name="doc_secret" id="doc_secret" class="form-select shadow-sm" onchange="updateBadges()">
                                    @foreach(['ปกติ', 'ลับ', 'ลับเฉพาะ', 'ลับที่สุด'] as $s)
                                        <option value="{{ $s }}" {{ old('doc_secret', $document->doc_secret) == $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 3: เนื้อหา --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px; border:1px solid #e4eaf0;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-pen-nib text-primary me-2"></i>3. เนื้อหาและการลงนาม</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">เรื่อง <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control shadow-sm" required value="{{ old('title', $document->title) }}">
                            </div>
                            <div class="col-12 position-relative">
                                <label class="form-label small fw-bold text-muted mb-1">เรียน (ผู้รับ) <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <input type="text" name="doc_to" id="doc_to" class="form-control border-0 bg-light" required value="{{ old('doc_to', $document->doc_to) }}">
                                    <button type="button" onclick="togglePopup('addrBookPopup')" class="btn btn-outline-primary fw-bold bg-white px-3"><i class="fas fa-address-book me-1"></i> สมุดที่อยู่</button>
                                </div>
                                <div id="addrBookPopup" class="floating-popup">
                                    <div style="font-size:13px;font-weight:700;color:var(--primary-dark);margin-bottom:10px;"><i class="fas fa-address-book me-1"></i> สมุดที่อยู่หน่วยงาน</div>
                                    <input type="text" id="addrSearch" class="form-control form-control-sm mb-2 shadow-none" placeholder="ค้นหาชื่อผู้รับ..." onkeyup="filterAddr()">
                                    <div id="addrList"></div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">ผู้ลงนาม <span class="text-danger">*</span></label>
                                <select name="signer_name" id="signer" class="form-select" required>
                                    <option value="" disabled>-- เลือกผู้ลงนาม --</option>
                                    @foreach($signers as $person)
                                        @php
                                            $position = $person->position;
                                            if (empty($position)) {
                                                if ($person->hasRole('executive')) $position = 'นายกองค์การบริหารส่วนตำบลพร่อน';
                                                elseif ($person->hasRole('palad') || $person->hasRole('deputy-palad')) $position = 'ปลัดองค์การบริหารส่วนตำบลพร่อน ปฏิบัติราชการแทน';
                                                else $position = 'หัวหน้าสำนักปลัด รักษาราชการแทน';
                                            }
                                            $fullNameWithPosition = $person->name . ' — ' . $position;
                                        @endphp
                                        <option value="{{ $fullNameWithPosition }}" {{ old('signer_name', $document->signer_name) == $fullNameWithPosition ? 'selected' : '' }}>
                                            {{ $fullNameWithPosition }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 4: แนบไฟล์ + จัดเก็บ --}}
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px; border:1px solid #e4eaf0;">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold" style="color:var(--primary-dark);"><i class="fas fa-paperclip text-primary me-2"></i>4. แนบไฟล์และการอ้างอิง</h6>
                    </div>
                    <div class="card-body p-4 pt-0">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">ไฟล์หนังสือฉบับเต็ม (อัปโหลดใหม่เมื่อต้องการเปลี่ยนไฟล์)</label>
                                <div id="dropzone" onclick="document.getElementById('file').click()" class="rounded-3 p-4 text-center shadow-sm" style="border: 2px dashed #cbd5e1; background: #f8fafc; cursor: pointer; transition: 0.2s;">
                                    <div id="file-display">
                                        <i class="fas fa-file-pdf text-primary mb-3" style="font-size: 36px; opacity: 0.8;"></i>
                                        <h6 class="fw-bold text-dark mb-1">คลิกหรือลากไฟล์ PDF มาวาง (หากต้องการเปลี่ยนไฟล์)</h6>
                                        <small class="text-muted">หากไม่ต้องการเปลี่ยนไฟล์ ไม่ต้องอัปโหลดใหม่</small>
                                    </div>
                                </div>
                                <input type="file" name="file" id="file" accept=".pdf" style="display:none;" onchange="handleFileChange(this)">
                            </div>
                            
                            <div class="col-md-6 position-relative">
                                <label class="form-label small fw-bold text-muted mb-1">อ้างอิงหนังสือรับเข้า (กรณีตอบกลับ)</label>
                                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">
                                    <input type="text" name="reference_doc" id="reference_doc" class="form-control border-0 bg-light" value="{{ old('reference_doc', $document->reference_doc) }}">
                                    <button type="button" onclick="togglePopup('refPopup')" class="btn btn-outline-primary fw-bold bg-white px-3"><i class="fas fa-link"></i> เลือก</button>
                                </div>
                                
                                <div id="refPopup" class="floating-popup">
                                    <div style="font-size:13px;font-weight:700;color:var(--primary-dark);margin-bottom:10px;"><i class="fas fa-inbox me-1"></i> หนังสือรับเข้าล่าสุด</div>
                                    @forelse($incomingDocs as $inDoc)
                                        @php
                                            $refNumber = $inDoc->receive_number ?? $inDoc->doc_number ?? 'ไม่ระบุเลข';
                                            $safeTitle = addslashes($inDoc->title);
                                            $safeNumber = addslashes($refNumber);
                                        @endphp
                                        <div class="popup-item" onclick="selectRef('{{ $safeNumber }} — {{ $safeTitle }}')">
                                            <div style="font-size:13px;font-weight:700;color:var(--primary);">{{ $refNumber }}</div>
                                            <div style="font-size:13px;color:var(--color-text-primary);">{{ $inDoc->title }}</div>
                                        </div>
                                    @empty
                                        <div class="text-center text-muted py-3 small">ไม่มีประวัติหนังสือรับเข้าในระบบ</div>
                                    @endforelse
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                @php
                                    $currentAtt = '';
                                    if($document->content && str_starts_with($document->content, 'สิ่งที่ส่งมาด้วย: ')) {
                                        $currentAtt = str_replace('สิ่งที่ส่งมาด้วย: ', '', $document->content);
                                    }
                                @endphp
                                <label class="form-label small fw-bold text-muted mb-1">สิ่งที่ส่งมาด้วย</label>
                                <input type="text" name="attachment" id="attachment" class="form-control shadow-sm" value="{{ old('attachment', $currentAtt) }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted mb-1">หมายเหตุ (เพื่อช่วยค้นหา)</label>
                                <input type="text" name="remark" id="remark" class="form-control shadow-sm" value="{{ old('remark', $document->remark) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-3">
                    <button type="button" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm" style="font-size:15px;" onclick="goToPreview()">
                        ตรวจสอบเอกสารก่อนส่งออก <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>

            <div id="step2" style="display:none;">
                <div class="card border-0 mb-4 shadow-sm" style="border-radius:16px; border:1px solid #cbd5e1; background: #fff;">
                    <div class="card-body p-5 text-dark" style="font-size: 15px;">
                        
                        <div class="text-center border-bottom pb-4 mb-4">
                            <div class="text-muted small mb-2"><i class="fas fa-file-alt me-1"></i> ใช้รูปแบบกระดาษตราครุฑ</div>
                            <h4 id="prev_docType" class="fw-bold" style="color: #1e293b;">หนังสือภายนอก</h4>
                        </div>
                        
                        <div class="row mb-4" style="line-height: 2;">
                            <div class="col-2 text-muted fw-bold text-end pe-3">ที่</div>
                            <div class="col-10 fw-bold text-primary" id="prev_docNo">—</div>
                            <div class="col-2 text-muted fw-bold text-end pe-3">วันที่</div>
                            <div class="col-10" id="prev_date">—</div>
                            <div class="col-2 text-muted fw-bold text-end pe-3 mt-2">เรื่อง</div>
                            <div class="col-10 fw-bold mt-2" id="prev_subject">—</div>
                            <div class="col-2 text-muted fw-bold text-end pe-3">เรียน</div>
                            <div class="col-10" id="prev_to">—</div>
                            <div class="col-2 text-muted fw-bold text-end pe-3" id="lbl_prev_ref">อ้างถึง</div>
                            <div class="col-10 text-secondary" id="prev_ref"></div>
                            <div class="col-2 text-muted fw-bold text-end pe-3" id="lbl_prev_att">ส่งมาด้วย</div>
                            <div class="col-10" id="prev_att"></div>
                        </div>

                        <div class="text-center text-muted fst-italic p-4 mb-4 rounded-3" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                            [ เนื้อความหนังสือ — อ้างอิงตามไฟล์ PDF ที่แนบในระบบ ]
                            <div id="prev_filename" class="mt-3 px-3 py-2 bg-white rounded-pill shadow-sm fw-bold text-primary d-inline-block" style="display:none; font-size:13px; border:1px solid #bae6fd;"></div>
                        </div>

                        <div class="text-center mt-5">
                            <div class="d-inline-block text-center">
                                <div id="prev_signer_name1" class="fw-bold text-primary" style="font-size:16px;">—</div>
                                <div style="border-top: 1px dotted #94a3b8; width: 250px; margin: 15px auto 5px;"></div>
                                <div style="font-size:14px;">( <span id="prev_signer_name2">—</span> )</div>
                                <div id="prev_signer_pos" class="text-muted mt-1" style="font-size:13px;">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between p-4 bg-white rounded-3 shadow-sm border">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" onclick="backToEdit()">
                        <i class="fas fa-arrow-left me-1"></i> กลับไปแก้ไข
                    </button>
                    <button type="submit" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-sm" style="font-size:15px; background: #10b981; border:none;">
                        <i class="fas fa-save me-save"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
    const ADDRESS_BOOK = [
        { name:"ผู้ว่าราชการจังหวัดยะลา", org:"จังหวัดยะลา" },
        { name:"นายอำเภอเมืองยะลา", org:"อำเภอเมืองยะลา" },
        { name:"ท้องถิ่นจังหวัดยะลา (สถจ.)", org:"สำนักงานส่งเสริมการปกครองท้องถิ่นจังหวัดยะลา" },
        { name:"ผู้อำนวยการสำนักงานคลังจังหวัดยะลา", org:"คลังจังหวัดยะลา" },
        { name:"ประธานสภา อบต.พร่อน", org:"สภา อบต.พร่อน" },
        { name:"ผู้อำนวยการโรงพยาบาลส่งเสริมสุขภาพตำบลพร่อน", org:"รพ.สต.พร่อน" },
        { name:"หัวหน้าส่วนราชการทุกส่วน", org:"ส่วนราชการภายใน" }
    ];

    function renderAddrList(filter = "") {
        const list = document.getElementById('addrList');
        list.innerHTML = "";
        const filtered = ADDRESS_BOOK.filter(a => a.name.includes(filter) || a.org.includes(filter));
        
        if(filtered.length === 0) {
            list.innerHTML = `<div class="text-center text-muted py-3 small">ไม่พบรายชื่อที่ค้นหา</div>`;
            return;
        }

        filtered.forEach(a => {
            let div = document.createElement('div');
            div.className = 'popup-item';
            div.innerHTML = `<div style="font-size:13px;font-weight:700;color:var(--text-primary);">${a.name}</div>
                             <div style="font-size:12px;color:var(--text-secondary);"><i class="fas fa-building text-muted me-1"></i>${a.org}</div>`;
            div.onclick = function() {
                document.getElementById('doc_to').value = a.name;
                document.getElementById('addrBookPopup').style.display = 'none';
            };
            list.appendChild(div);
        });
    }
    renderAddrList();

    function filterAddr() { renderAddrList(document.getElementById('addrSearch').value); }

    function togglePopup(id) {
        const el = document.getElementById(id);
        el.style.display = el.style.display === 'block' ? 'none' : 'block';
    }
    
    function selectRef(text) {
        document.getElementById('reference_doc').value = text;
        document.getElementById('refPopup').style.display = 'none';
    }

    async function autoDocNo() {
        try {
            const res = await fetch("{{ route('documents.api_next_number') }}?type=outgoing");
            const data = await res.json();
            document.getElementById('doc_number').value = data.formatted;
            document.getElementById('running_number').value = data.next_number;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'ดึงเลขรันนิ่งล่าสุดสำเร็จ', showConfirmButton: false, timer: 1500 });
        } catch (e) {
            Swal.fire('Error', 'ไม่สามารถเชื่อมต่อระบบรันเลขได้', 'error');
        }
    }

    function handleFileChange(input) {
        const display = document.getElementById('file-display');
        const dropzone = document.getElementById('dropzone');
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            dropzone.style.borderColor = '#10b981';
            dropzone.style.background = '#f0fdf4';
            display.innerHTML = `<i class="fas fa-check-circle text-success mb-2" style="font-size: 32px;"></i>
                                 <div style="font-size:14px;color:#065f46;font-weight:bold;">${file.name}</div>
                                 <div class="small text-success mt-1">อัปโหลดไฟล์ใหม่แทนที่ไฟล์เดิม</div>`;
        }
    }

    function updateBadges() {
        const speed = document.getElementById('doc_speed').value;
        const secret = document.getElementById('doc_secret').value;
        const bSpeed = document.getElementById('displaySpeed');
        bSpeed.innerText = speed;
        bSpeed.className = speed === 'ปกติ' ? 'badge bg-secondary rounded-pill px-3 py-2 shadow-sm' : 'badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm';

        const bSecret = document.getElementById('displaySecret');
        if(secret === 'ปกติ') bSecret.classList.add('d-none');
        else { bSecret.classList.remove('d-none'); bSecret.innerText = '🔒 ' + secret; }
    }

    function goToPreview() {
        const docNo = document.getElementById('doc_number').value;
        const title = document.getElementById('title').value;
        const to = document.getElementById('doc_to').value;
        const signerSelect = document.getElementById('signer');

        if(!docNo || !title || !to || !signerSelect.value) {
            Swal.fire({ icon: 'warning', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณากรอกข้อมูลที่มีเครื่องหมาย * ให้ครบถ้วน', confirmButtonColor: '#0284c7' });
            return;
        }

        const docType = document.getElementById('doc_type_category').value;
        let typeName = "หนังสือ";
        if(docType.includes('บันทึก')) typeName = "บันทึกข้อความ";
        else if(docType.includes('ประทับตรา')) typeName = "หนังสือประทับตรา";
        else if(docType.includes('สั่งการ')) typeName = "คำสั่ง / ระเบียบ";
        else if(docType.includes('ประกาศ')) typeName = "ประกาศ";

        document.getElementById('prev_docType').innerText = typeName;
        document.getElementById('prev_docNo').innerText = docNo;
        document.getElementById('prev_subject').innerText = title;
        document.getElementById('prev_to').innerText = to;
        
        const dateRaw = document.getElementById('doc_date').value;
        if(dateRaw) {
            const d = new Date(dateRaw);
            const m = ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"];
            document.getElementById('prev_date').innerText = `${d.getDate()} ${m[d.getMonth()]} ${d.getFullYear()+543}`;
        }

        const ref = document.getElementById('reference_doc').value;
        const att = document.getElementById('attachment').value;
        document.getElementById('prev_ref').innerText = ref;
        document.getElementById('lbl_prev_ref').style.display = ref ? 'block' : 'none';
        document.getElementById('prev_att').innerText = att;
        document.getElementById('lbl_prev_att').style.display = att ? 'block' : 'none';

        const file = document.getElementById('file').files[0];
        if(file) {
            document.getElementById('prev_filename').style.display = 'inline-block';
            document.getElementById('prev_filename').innerHTML = '<i class="fas fa-file-pdf me-1"></i> ' + file.name;
        } else {
            document.getElementById('prev_filename').style.display = 'inline-block';
            document.getElementById('prev_filename').innerHTML = '<i class="fas fa-file-pdf me-1"></i> ใช้ไฟล์ PDF เดิมในระบบ';
        }

        const signerRaw = signerSelect.value.split('—');
        const sName = signerRaw[0].trim();
        const sPos = signerRaw[1] ? signerRaw[1].trim() : '';
        document.getElementById('prev_signer_name1').innerText = sName;
        document.getElementById('prev_signer_name2').innerText = sName;
        document.getElementById('prev_signer_pos').innerText = sPos;

        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        
        document.getElementById('step1-icon').classList.replace('step-active', 'step-completed');
        document.getElementById('step1-icon').innerHTML = '<i class="fas fa-check"></i>';
        document.getElementById('step1-text').classList.replace('text-dark', 'text-success');

        document.getElementById('step2-icon').classList.replace('step-inactive', 'step-active');
        document.getElementById('step2-text').classList.remove('text-muted');
        document.getElementById('step2-text').style.color = '#000';
    }

    function backToEdit() {
        document.getElementById('step2').style.display = 'none';
        document.getElementById('step1').style.display = 'block';

        document.getElementById('step1-icon').classList.replace('step-completed', 'step-active');
        document.getElementById('step1-icon').innerHTML = '1';
        document.getElementById('step1-text').classList.replace('text-success', 'text-dark');

        document.getElementById('step2-icon').classList.replace('step-active', 'step-inactive');
        document.getElementById('step2-text').classList.add('text-muted');
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateBadges();
        // 🌟 ถ้าระบบเห็นว่ามีไฟล์เดิมอยู่แล้ว ให้เปลี่ยนหน้าตากล่องอัปโหลด
        @if($document->attachment_path)
            const display = document.getElementById('file-display');
            const dropzone = document.getElementById('dropzone');
            dropzone.style.borderColor = '#10b981';
            dropzone.style.background = '#f0fdf4';
            display.innerHTML = `<i class="fas fa-check-circle text-success mb-2" style="font-size: 32px;"></i>
                                 <div style="font-size:14px;color:#065f46;font-weight:bold;">ระบบมีไฟล์แนบเดิมเก็บไว้แล้ว</div>
                                 <div class="small text-success mt-1">หากต้องการแก้ไขไฟล์ ให้ลากไฟล์ใหม่มาวางทับได้เลย</div>`;
        @endif
    });

    document.addEventListener('click', function(e) {
        if(!e.target.closest('#addrBookPopup') && !e.target.closest('button[onclick*="addrBookPopup"]')) {
            document.getElementById('addrBookPopup').style.display = 'none';
        }
        if(!e.target.closest('#refPopup') && !e.target.closest('button[onclick*="refPopup"]')) {
            document.getElementById('refPopup').style.display = 'none';
        }
    });
</script>
@endsection
