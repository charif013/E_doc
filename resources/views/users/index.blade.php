{{-- View: หน้าจัดการบัญชีผู้ใช้งานและบทบาทในระบบ --}}
@extends('layouts.app')

@section('title', 'จัดการผู้ใช้งานระบบ')

@section('content')
<div class="card shadow-sm border-primary">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">👥 จัดการผู้ใช้งานระบบ (e-Doc อบต.พร่อน)</h5>
        <button class="btn btn-light btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้งาน
        </button>
    </div>
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
                <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover mt-2">
                <thead class="table-light text-nowrap text-center">
                    <tr>
                        <th class="text-start">ชื่อ - นามสกุล</th>
                        <th class="text-start">อีเมล</th>
                        <th class="text-start">หน่วยงาน / ตำแหน่ง</th> 
                        <th>สิทธิ์ในระบบ (Role)</th>
                        <th>ล้างรหัส PIN</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody class="align-middle">
                    @foreach($users as $user)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $user->name }}</div>
                            <div class="mt-1">
                                @php
                                    $displayPrefix = $user->name_prefix ?: ($user->gender === 'male' ? 'นาย' : ($user->gender === 'female' ? 'นางสาว' : null));
                                @endphp
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                    <i class="fas fa-id-card me-1"></i>{{ $displayPrefix ?? 'ไม่ระบุ' }}
                                </span>
                            </div>
                        </td>
                        <td class="text-muted small">{{ $user->email }}</td>
                        <td>
                            <div class="small fw-bold text-primary">{{ $user->department ?? '-' }}</div>
                            <div class="small text-secondary">{{ $user->division ? '> '.$user->division : '' }}</div>
                            <div class="small text-muted italic">ตำแหน่ง: {{ $user->position ?? 'ไม่ระบุ' }}</div>
                            <div class="small text-muted">ประเภทการจ้าง: {{ $employmentTypes[$user->employment_type] ?? 'ยังไม่ระบุ' }}</div>
                        </td>
                        <td class="text-center">
                            @foreach($user->roles as $role)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2">
                                    {{ $role->name }}
                                </span>
                            @endforeach
                        </td>
                        
                        {{-- 🌟 คอลัมน์ล้างรหัส PIN 🌟 --}}
                        <td class="text-center">
                            @if($user->pin_reset_requested)
                                <form action="{{ route('admin.users.clear_pin', $user->id) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm fw-bold shadow-sm rounded-pill px-3" onclick="return confirm('ยืนยันการล้างรหัส PIN ให้ผู้ใช้งาน: {{ $user->name }} ?')">
                                        <i class="fas fa-unlock me-1"></i> ล้างรหัส PIN
                                    </button>
                                </form>
                            @else
                                <button class="btn btn-sm btn-light text-muted fw-bold rounded-pill px-3 border border-secondary-subtle disabled" title="ผู้ใช้ยังไม่ได้ส่งคำขอล้างรหัส" style="cursor: not-allowed;">
                                    - ไม่มีคำขอ -
                                </button>
                            @endif
                        </td>

                        <td class="text-center">
                            <div class="btn-group">
                                <button class="btn btn-info text-white btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#editRoleModal{{ $user->id }}" title="แก้ไขข้อมูล/สิทธิ์">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btn-delete-user shadow-sm" data-id="{{ $user->id }}" title="ลบบัญชีผู้ใช้">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    {{-- 🌟 Modal แก้ไขสิทธิ์และข้อมูล --}}
                    <div class="modal fade text-start" id="editRoleModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                                <div class="modal-header bg-info text-white py-3">
                                    <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูลผู้ใช้</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="{{ route('users.update', $user->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    
                                    {{-- รักษาข้อมูลส่วนที่ยังไม่ได้เปิดแก้ไขไว้ --}}
                                    <input type="hidden" name="email" value="{{ $user->email }}">
                                    <input type="hidden" name="department" value="{{ $user->department }}">
                                    <input type="hidden" name="division" value="{{ $user->division }}">
                                    <input type="hidden" name="position" value="{{ $user->position }}">

                                    <div class="modal-body p-4 text-center">
                                        <div class="mx-auto rounded-circle bg-light d-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width: 70px; height: 70px; font-size: 30px; color: var(--primary);">
                                            {{ mb_substr($user->name, 0, 1) }}
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark">{{ $user->name }}</h6>
                                        <p class="text-muted small mb-4">{{ $user->position }} / {{ $user->department }}</p>

                                         <div class="text-start">
                                             <div class="mb-3">
                                                 <label class="form-label fw-bold">คำนำหน้าชื่อ <span class="text-danger">*</span></label>
                                                 @php $currentPrefix = $user->name_prefix ?: ($user->gender === 'male' ? 'นาย' : 'นางสาว'); @endphp
                                                 <select name="name_prefix" class="form-select border-2" required>
                                                     <option value="นาย" {{ $currentPrefix === 'นาย' ? 'selected' : '' }}>นาย</option>
                                                     <option value="นาง" {{ $currentPrefix === 'นาง' ? 'selected' : '' }}>นาง</option>
                                                     <option value="นางสาว" {{ $currentPrefix === 'นางสาว' ? 'selected' : '' }}>นางสาว</option>
                                                 </select>
                                             </div>

                                             <div class="mb-3">
                                                 <label class="form-label fw-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                                                 <input type="text" name="name" class="form-control border-2" value="{{ $user->name }}" required maxlength="255">
                                             </div>

                                             <div class="mb-3">
                                                 <label class="form-label fw-bold">ประเภทการจ้าง <span class="text-danger">*</span></label>
                                                 <select name="employment_type" class="form-select border-2" required>
                                                     @foreach($employmentTypes as $value => $label)
                                                         <option value="{{ $value }}" {{ $user->employment_type === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                     @endforeach
                                                 </select>
                                             </div>

                                             <div class="row">
                                                 <div class="col-md-6 mb-3">
                                                     <label class="form-label fw-bold">วันเริ่มจ้าง <span class="text-muted fw-normal">(จำเป็นสำหรับจ้างตามภารกิจ)</span></label>
                                                     <input type="date" name="employment_started_at" class="form-control" value="{{ optional($user->employment_started_at)->format('Y-m-d') }}">
                                                 </div>
                                                 <div class="col-md-6 mb-3">
                                                     <label class="form-label fw-bold">วันที่ผ่านประเมิน 6 เดือน <span class="text-muted fw-normal">(ถ้ามี)</span></label>
                                                     <input type="date" name="vacation_eligible_at" class="form-control" value="{{ optional($user->vacation_eligible_at)->format('Y-m-d') }}">
                                                 </div>
                                             </div>

                                            <div class="mb-2">
                                                <label class="form-label fw-bold text-primary">กำหนดสิทธิ์ (Role) ในระบบ e-Doc:</label>
                                                <select name="role" class="form-select form-select-lg border-primary shadow-sm" required>
                                                    <optgroup label="-- ระดับบริหาร --">
                                                        <option value="super-admin" {{ $user->hasRole('super-admin') ? 'selected' : '' }}>🛡️ Super Admin (แอดมินสูงสุด)</option>
                                                        <option value="executive" {{ $user->hasRole('executive') ? 'selected' : '' }}>👑 Executive (นายก อบต.)</option>
                                                        <option value="palad" {{ $user->hasRole('palad') ? 'selected' : '' }}>⭐ Palad (ปลัด อบต.)</option>
                                                    </optgroup>
                                                    <optgroup label="-- งานสายตรง --">
                                                        <option value="saraban" {{ $user->hasRole('saraban') ? 'selected' : '' }}>📧 Saraban (สารบรรณกลาง)</option>
                                                        <option value="head" {{ $user->hasRole('head') ? 'selected' : '' }}>💼 Head (ผอ.กอง / หน.สำนัก)</option>
                                                        <option value="hr" {{ $user->hasRole('hr') ? 'selected' : '' }}>👤 HR (บุคลากร)</option>
                                                    </optgroup>
                                                    <optgroup label="-- บุคลากรอื่น ๆ --">
                                                        <option value="officer" {{ $user->hasRole('officer') ? 'selected' : '' }}>👤 Officer (ผู้ใช้งานทั่วไป)</option>
                                                        <option value="worker" {{ $user->hasRole('worker') ? 'selected' : '' }}>🔧 Worker (พนักงานจ้าง/คนงาน)</option>
                                                    </optgroup>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-light" style="border-radius: 0 0 15px 15px;">
                                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                                        <button type="submit" class="btn btn-info text-white fw-bold rounded-pill px-4 shadow-sm">บันทึกข้อมูล</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- 🌟 Modal สำหรับเพิ่มผู้ใช้งานใหม่ --}}
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg"> 
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
      <div class="modal-header bg-primary text-white py-3" style="border-radius: 20px 20px 0 0;">
        <h5 class="modal-title fw-bold"><i class="fas fa-user-plus me-2"></i>เพิ่มบัญชีผู้ใช้งานใหม่</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <form action="{{ route('users.store') }}" method="POST" autocomplete="off">
        @csrf
        <div class="modal-body p-4">
            <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="fas fa-info-circle me-1"></i> ข้อมูลพื้นฐาน</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">คำนำหน้าชื่อ <span class="text-danger">*</span></label>
                    <select name="name_prefix" class="form-select" required>
                        <option value="">-- เลือกคำนำหน้าชื่อ --</option>
                        <option value="นาย" {{ old('name_prefix') === 'นาย' ? 'selected' : '' }}>นาย</option>
                        <option value="นาง" {{ old('name_prefix') === 'นาง' ? 'selected' : '' }}>นาง</option>
                        <option value="นางสาว" {{ old('name_prefix') === 'นางสาว' ? 'selected' : '' }}>นางสาว</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="ระบุชื่อภาษาไทย" value="{{ old('name') }}" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">อีเมล <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="Admin@gmail.com" value="{{ old('email') }}" autocomplete="off" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">รหัสผ่านเริ่มต้น <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="initial-password" class="form-control" placeholder="Admin123" autocomplete="new-password" data-lpignore="true" required>
                        <button type="button" class="btn btn-outline-secondary toggle-password" aria-label="แสดงรหัสผ่าน" aria-controls="initial-password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">ประเภทการจ้าง <span class="text-danger">*</span></label>
                    <select name="employment_type" class="form-select" required>
                        <option value="">-- เลือกประเภทการจ้าง --</option>
                        @foreach($employmentTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('employment_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">วันเริ่มจ้าง <span class="text-muted fw-normal">(จำเป็นสำหรับจ้างตามภารกิจ)</span></label>
                    <input type="date" name="employment_started_at" class="form-control" value="{{ old('employment_started_at') }}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">วันที่ผ่านประเมิน 6 เดือน <span class="text-muted fw-normal">(พนักงานจ้างตามภารกิจ)</span></label>
                    <input type="date" name="vacation_eligible_at" class="form-control" value="{{ old('vacation_eligible_at') }}">
                </div>
            </div>

            <h6 class="fw-bold text-success border-bottom pb-2 mt-4 mb-3"><i class="fas fa-sitemap me-1"></i> โครงสร้างและตำแหน่ง</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">สำนัก / กอง <span class="text-danger">*</span></label>
                    <select name="department" id="department" class="form-select border-2" required>
    <option value="">-- เลือกสำนัก/กอง --</option>
    <option value="คณะผู้บริหาร">คณะผู้บริหาร (นายก, รองนายกฯ)</option>
    <option value="ผู้บริหารส่วนราชการ">ผู้บริหารส่วนราชการ (ปลัด, รองปลัดฯ)</option>
    <option value="สำนักงานปลัด">สำนักงานปลัด</option>
    <option value="กองคลัง">กองคลัง</option>
    <option value="กองช่าง">กองช่าง</option>
    <option value="กองสาธารณสุขและสิ่งแวดล้อม">กองสาธารณสุขและสิ่งแวดล้อม</option>
    <option value="กองการศึกษา ศาสนา และวัฒนธรรม">กองการศึกษา ศาสนา และวัฒนธรรม</option>
    <option value="หน่วยตรวจสอบภายใน">หน่วยตรวจสอบภายใน</option>
    <option value="แอดมินระบบ">แอดมินระบบ (IT Admin)</option>
</select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-bold">ฝ่าย / งาน <span class="text-danger">*</span></label>
                    <select name="division" id="division" class="form-select" disabled required>
                        <option value="">-- เลือกฝ่าย/งาน --</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label small fw-bold">ตำแหน่งหน้าที่ <span class="text-danger">*</span></label>
                    <select name="position" id="position" class="form-select" disabled required>
                        <option value="">-- เลือกตำแหน่ง --</option>
                    </select> 
                </div>
            </div> 
        </div> 
        <div class="modal-footer bg-light" style="border-radius: 0 0 20px 20px;">
          <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="fas fa-save me-1"></i> บันทึกผู้ใช้งาน</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // ข้อมูลโครงสร้างองค์กร อบต.พร่อน (Dependent Data)
    const orgData = {
        "คณะผู้บริหาร": {
            "บริหาร": ["นายก อบต.", "รองนายก อบต.", "เลขานุการนายก"]
        },
        "ผู้บริหารส่วนราชการ": {
            "บริหารงานราชการ": ["ปลัด อบต.", "รองปลัด อบต."]
        },
        "สำนักงานปลัด": {
            "บริหารสำนักปลัด": ["หัวหน้าสำนักปลัด อบต."],
            "งานธุรการ": ["เจ้าพนักงานธุรการ", "ผช.เจ้าพนักงานธุรการ", "พนักงานขับรถยนต์", "คนงาน", "ภารโรง"],
            "งานนโยบายและแผน": ["นักวิเคราะห์นโยบายและแผน"],
            "งานบุคคล": ["นักทรัพยากรบุคคล"],
            "งานป้องกันฯ": ["ผช.เจ้าพนักงานป้องกันและบรรเทาสาธารณภัย"],
            "งานพัฒนาชุมชน": ["นักพัฒนาชุมชน"]
        },
        "กองคลัง": {
            "บริหารกองคลัง": ["ผู้อำนวยการกองคลัง"],
            "งานการเงินและบัญชี": ["เจ้าพนักงานการเงินและบัญชี", "ผช.เจ้าพนักงานการเงินและบัญชี"],
            "งานจัดเก็บรายได้": ["นักวิชาการจัดเก็บรายได้", "ผช.เจ้าพนักงานจัดเก็บรายได้"],
            "งานพัสดุ": ["เจ้าพนักงานพัสดุ", "ผช.เจ้าพนักงานพัสดุ"]
        },
        "กองช่าง": {
            "บริหารกองช่าง": ["ผู้อำนวยการกองช่าง"],
            "งานโยธา": ["นายช่างโยธา", "ผช.นายช่างโยธา"]
        },
        "กองสาธารณสุขและสิ่งแวดล้อม": {
            "บริหารสาธารณสุข": ["ผู้อำนวยการกองสาธารณสุขฯ"],
            "งานสุขาภิบาล": ["นักวิชาการสุขาภิบาล", "ผช.เจ้าพนักงานธุรการ"]
        },
        "กองการศึกษา ศาสนา และวัฒนธรรม": {
            "บริหารการศึกษา": ["ผู้อำนวยการกองการศึกษาฯ"],
            "งานวิชาการศึกษา": ["นักวิชาการศึกษา"],
            "งานศูนย์พัฒนาเด็กเล็ก": ["ครู", "ผช.ครูผู้ดูแลเด็ก", "ผู้ดูแลเด็ก", "คนงาน"]
        },
        "หน่วยตรวจสอบภายใน": {
            "ตรวจสอบภายใน": ["นักวิชาการตรวจสอบภายใน"]
        },
        "แอดมินระบบ": {
            "IT": ["นักวิชาการคอมพิวเตอร์", "แอดมิน"]
        }
    };

    // หน้าที่: เริ่มตั้งค่าหน้าและผูกเหตุการณ์เมื่อ DOM พร้อมใช้งาน
    $(document).ready(function() {
        $('.toggle-password').on('click', function() {
            const passwordField = $('#initial-password');
            const isHidden = passwordField.attr('type') === 'password';

            passwordField.attr('type', isHidden ? 'text' : 'password');
            $(this)
                .attr('aria-label', isHidden ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน')
                .attr('aria-pressed', isHidden)
                .find('i')
                .toggleClass('fa-eye fa-eye-slash');
        });

        // จัดการ Dropdown สำนัก/กอง
        // หน้าที่: อัปเดตตัวเลือกที่เกี่ยวข้องเมื่อค่าเปลี่ยน
        $('#department').change(function() {
            let dept = $(this).val();
            let divisionSelect = $('#division');
            let positionSelect = $('#position');
            
            divisionSelect.empty().append('<option value="">-- เลือกฝ่าย/งาน --</option>').prop('disabled', true);
            positionSelect.empty().append('<option value="">-- เลือกตำแหน่ง --</option>').prop('disabled', true);

            if(dept && orgData[dept]) {
                // หน้าที่: วนสร้างตัวเลือกจากข้อมูลโครงสร้างองค์กร
                $.each(orgData[dept], function(division, positions) {
                    divisionSelect.append(`<option value="${division}">${division}</option>`);
                });
                divisionSelect.prop('disabled', false);
            }
        });

        // จัดการ Dropdown ฝ่าย -> ตำแหน่ง
        // หน้าที่: อัปเดตตัวเลือกที่เกี่ยวข้องเมื่อค่าเปลี่ยน
        $('#division').change(function() {
            let dept = $('#department').val();
            let div = $(this).val();
            let positionSelect = $('#position');

            positionSelect.empty().append('<option value="">-- เลือกตำแหน่ง --</option>').prop('disabled', true);

            if(dept && div && orgData[dept][div]) {
                // หน้าที่: วนสร้างตัวเลือกจากข้อมูลโครงสร้างองค์กร
                $.each(orgData[dept][div], function(index, pos) {
                    positionSelect.append(`<option value="${pos}">${pos}</option>`);
                });
                positionSelect.prop('disabled', false);
            }
        });

        // ปุ่มลบ
        // หน้าที่: จัดการการคลิกและดำเนินการกับรายการที่เลือก
        $(document).on('click', '.btn-delete-user', function() {
            let id = $(this).data('id');
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "บัญชีนี้จะไม่สามารถเข้าสู่ระบบได้!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'ใช่, ลบเลย',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.delete(`/users/${id}`).then(res => {
                        location.reload();
                    });
                }
            });
        });
    });
</script>
@endsection
