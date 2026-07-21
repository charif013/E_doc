@extends('layouts.app')
@section('title', 'แฟ้มเอกสารความลับ')

@section('content')
<div class="container-fluid px-4 py-4">
    <div class="mx-auto" style="max-width: 1200px;">

        {{-- 🌟 Header (โทนสีแดง) --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color: #991b1b;">
                    <i class="fas fa-lock text-danger me-2"></i>แฟ้มเอกสารความลับ (Confidential)
                </h4>
                <p class="text-danger small mb-0 fw-bold"><i class="fas fa-exclamation-triangle me-1"></i>จำกัดสิทธิ์การเข้าถึงเฉพาะผู้บริหารระดับสูงเท่านั้น</p>
            </div>
        </div>

        {{-- 🌟 ตารางแสดงเอกสาร --}}
        <div class="card border-0 shadow-sm" style="border-radius: 12px; border-top: 5px solid #dc2626 !important; overflow: hidden;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap">
                    <thead style="background-color: #fef2f2; color: #991b1b; font-size: 14px;">
                        <tr>
                            <th class="ps-4 py-3">ชั้นความลับ</th>
                            <th>วันที่รับเรื่อง</th>
                            <th>เลขที่หนังสือ</th>
                            <th>เรื่อง (หัวข้อเอกสาร)</th>
                            <th>จาก (หน่วยงาน)</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($secretDocs as $doc)
                            <tr>
                                <td class="ps-4">
                                    @if($doc->doc_secret == 'ลับที่สุด')
                                        <span class="badge bg-dark text-white px-3 py-2 border border-danger shadow-sm"><i class="fas fa-radiation text-danger me-1"></i>ลับที่สุด</span>
                                    @elseif($doc->doc_secret == 'ลับเฉพาะ')
                                        <span class="badge bg-danger text-white px-3 py-2 shadow-sm"><i class="fas fa-shield-alt me-1"></i>ลับเฉพาะ</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2"><i class="fas fa-lock me-1"></i>ลับ</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ \Carbon\Carbon::parse($doc->created_at)->addYears(543)->locale('th')->translatedFormat('j M y') }}</td>
                                <td><span class="fw-bold text-dark">{{ $doc->doc_number ?? 'รอออกเลข' }}</span></td>
                                <td>
                                    <div class="fw-bold text-dark text-wrap" style="max-width: 300px;">{{ $doc->title }}</div>
                                </td>
                                <td><div class="text-muted small">{{ $doc->creator->department ?? '-' }}</div></td>
                                <td class="text-center">
                                    {{-- 🌟 จุดที่ 1: เปลี่ยนพารามิเตอร์ที่ส่งไปให้ JS เป็น UUID (ต้องใส่เครื่องหมายคำพูดครอบไว้ด้วยเพราะเป็น string) --}}
                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="promptUnlock('{{ $doc->uuid ?? $doc->id }}')">
                                        <i class="fas fa-key me-1"></i> ปลดล็อกเพื่อดู
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted mb-2"><i class="fas fa-shield-check fa-3x" style="color: #cbd5e1;"></i></div>
                                    <h6 class="fw-bold text-dark">ปลอดภัย</h6>
                                    <span class="small text-muted">ไม่มีแฟ้มเอกสารความลับในระบบขณะนี้</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function promptUnlock(docId) {
        Swal.fire({
            title: 'เอกสารปกปิด',
            text: "กรุณากรอกรหัส PIN 6 หลักเพื่อเข้าดูเอกสาร",
            icon: 'warning',
            input: 'password',
            inputAttributes: {
                autocapitalize: 'off',
                autocorrect: 'off',
                maxlength: 6,
                pattern: '[0-9]*',
                placeholder: '••••••'
            },
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-unlock me-1"></i> ยืนยันรหัสผ่าน',
            cancelButtonText: 'ยกเลิก',
            showLoaderOnConfirm: true,
            preConfirm: (pin) => {
                if (!pin) {
                    Swal.showValidationMessage('กรุณากรอกรหัส PIN ก่อนกดยืนยัน');
                    return false;
                }
                
                // 🌟 ส่งรหัสไปเช็คความถูกต้องที่หลังบ้าน (ปลอดภัย 100%)
                return fetch("{{ route('documents.verify_pin') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ pin: pin })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        if (data.message === 'no_pin') {
                            Swal.showValidationMessage('คุณยังไม่ได้ตั้งรหัส PIN ในหน้าโปรไฟล์!');
                        } else {
                            Swal.showValidationMessage('รหัส PIN ไม่ถูกต้อง!');
                        }
                    }
                    return data.success; // คืนค่า true ถ้าผ่าน
                })
                .catch(error => {
                    Swal.showValidationMessage('เกิดข้อผิดพลาดในการเชื่อมต่อระบบ');
                });
            }
        }).then((result) => {
            // ถ้า result.value เป็น true (รหัสผ่านถูกต้อง)
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    icon: 'success',
                    title: 'ปลดล็อกสำเร็จ',
                    text: 'กำลังเปิดแฟ้มเอกสารความลับ...',
                    timer: 1500,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    willClose: () => {
                        // 🌟 จุดที่ 2: ใช้ docId ซึ่งรับค่ามาจาก UUID ในการเปลี่ยนหน้า
                        window.location.href = `/documents/${docId}/show`; 
                    }
                });
            }
        });
    }
</script>
@endsection