{{-- 🌟 สำหรับ: บันทึกข้อความ (Internal) - พิจารณา อนุมัติ/ตีกลับ/ออกเลข --}}
@php
    $user = auth()->user();
    $isSarabanNumbering = ($user->hasRole('saraban') && $document->status === 'WAITING_NUMBERING');
    
    // 🌟 เช็คว่ามีการจองเลขล่วงหน้าไว้หรือยัง
    $hasReservedNumber = !empty($document->doc_number);
@endphp

<form action="{{ route('documents.review', $document->uuid ?? $document->id) }}" method="POST" id="reviewFormInternal">
    @csrf
    <input type="hidden" name="is_approved" id="is_approved_internal" value="1">

    <div class="p-3">
        @if($isSarabanNumbering)
            {{-- 🌟 โซนลงเลขสำหรับธุรการ --}}
            <div class="bg-yellow-50 p-3 rounded-3 border border-warning mb-3">
                <label class="form-label fw-bold text-dark text-danger">ระบุเลขที่เอกสารเพื่อลงทะเบียน *</label>
                <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
                    {{-- ดึงเลข running_number ที่จองไว้มาใส่ --}}
                    <input type="hidden" name="running_number" id="running_number" value="{{ $document->running_number }}">
                    
                    {{-- ดึงเลข doc_number ที่จองไว้มาแสดง (ถอดคำว่า ยล 54201/ ที่ถูกล็อกไว้ออก เพื่อให้ระบบจัดการฟอร์แมตเอง) --}}
                    <input type="text" name="doc_number" id="doc_number" 
                           class="form-control border-0 bg-white fw-bold text-primary px-3" 
                           placeholder="คลิกปุ่มรันเลข หรือพิมพ์เลขเอง..." 
                           value="{{ $document->formatted_doc_number }}" required>
                    
                    {{-- 🌟 เปลี่ยนปุ่มตามสถานะการจอง --}}
                    @if($hasReservedNumber)
                        <button type="button" class="btn btn-success fw-bold px-3" style="cursor: default;" title="ใช้เลขที่จองไว้">
                            <i class="fas fa-check-circle me-1"></i> จองไว้แล้ว
                        </button>
                        <button type="button" onclick="autoDocNo()" class="btn btn-outline-primary fw-bold px-3" title="ดึงเลขใหม่ (กรณีต้องการเปลี่ยนเลข)">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    @else
                        <button type="button" onclick="autoDocNo()" class="btn btn-primary fw-bold px-3">
                            <i class="fas fa-magic me-1"></i> รันเลข
                        </button>
                    @endif
                </div>
                
                @if($hasReservedNumber)
                    <small class="text-success mt-2 d-block fw-bold"><i class="fas fa-check"></i> ดึงเลขที่จองล่วงหน้ามาให้แล้ว สามารถใส่ PIN แล้วกดยืนยันออกเลขได้เลย</small>
                @else
                    <small class="text-muted mt-2 d-block"><i class="fas fa-info-circle"></i> ระบบจะใช้วันที่ปัจจุบันเป็น "วันที่ออกเอกสาร" โดยอัตโนมัติ</small>
                @endif
            </div>
        @endif

        <div class="mb-3">
            <label class="form-label fw-bold text-dark">ความเห็นผู้พิจารณา (แสดงบนเอกสาร)</label>
            <textarea name="comment" class="form-control" rows="2" placeholder="ระบุความเห็น หรือ เหตุผลที่ส่งคืน (ถ้ามี)..."></textarea>
        </div>

        <div class="row align-items-end mt-2">
            <div class="col-md-5 mb-2">
                <label class="form-label fw-bold text-dark">รหัส PIN 6 หลัก *</label>
                <input type="password" name="pin" maxlength="6" class="form-control text-center fw-bold" 
                       style="letter-spacing: 10px; font-size: 20px; border-radius: 12px;" placeholder="******" required autocomplete="off">
            </div>
            <div class="col-md-7 mb-2">
                <div class="d-flex flex-column gap-2">
                    
                    @if($isSarabanNumbering)
                        {{-- 🌟 2 ปุ่มหลักสำหรับธุรการ (ขั้นตอนสุดท้าย) --}}
                        <button type="submit" onclick="document.getElementById('is_approved_internal').value='1'" 
                                class="btn btn-success py-2 fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-check-circle me-1"></i> 
                            {{ $hasReservedNumber ? 'ยืนยันการใช้เลขนี้ และออกเลข' : 'ตรวจสอบแล้ว - ออกเลข' }}
                        </button>
                        <button type="submit" onclick="document.getElementById('is_approved_internal').value='0'" 
                                class="btn btn-danger py-2 fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-times-circle me-1"></i> ส่งคืนผู้บริหาร/เจ้าของเรื่อง
                        </button>
                    @else
                        {{-- ปุ่มพิจารณาปกติ (สำหรับผู้บริหาร) --}}
                        <div class="d-flex gap-2">
                            <button type="submit" onclick="document.getElementById('is_approved_internal').value='1'" 
                                    class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-pill shadow-sm">
                                <i class="fas fa-check-circle me-1"></i> อนุมัติ / เห็นชอบ
                            </button>
                            <button type="submit" onclick="document.getElementById('is_approved_internal').value='0'" 
                                    class="btn btn-outline-danger py-2 fw-bold rounded-pill">
                                <i class="fas fa-times-circle me-1"></i> ตีกลับ
                            </button>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</form>

{{-- 🌟 Script สำหรับยิง API ดึงเลขรันนิ่ง (ดึงตามประเภทเอกสาร) --}}
@if($isSarabanNumbering)
<script>
    async function autoDocNo() {
        try {
            const docType = '{{ $document->doc_type }}'; 
            const res = await fetch(`{{ route('documents.api_next_number') }}?type=${docType}`);
            const data = await res.json();
            
            document.getElementById('doc_number').value = data.formatted;
            document.getElementById('running_number').value = data.next_number;
            
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'ดึงเลขล่าสุดสำเร็จ', showConfirmButton: false, timer: 1500
            });
        } catch (e) {
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบรันเลขได้', 'error');
        }
    }
</script>
@endif
