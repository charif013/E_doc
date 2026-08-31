{{-- 🌟 สำหรับ: หนังสือส่งออก (Outgoing) โดยเฉพาะ --}}
@php
    $user = auth()->user();
    $isSarabanNumbering = ($user->hasRole('saraban') && $document->status === 'WAITING_NUMBERING');
    $hasReservedNumber = !empty($document->doc_number);
@endphp

<form action="{{ route('documents.review', $document->uuid ?? $document->id) }}" method="POST" id="reviewFormOutgoing">
    @csrf
    <input type="hidden" name="is_approved" id="is_approved_outgoing" value="1">

    <div class="p-3">
        @if($isSarabanNumbering)
            {{-- 🌟 โซนลงเลขสำหรับธุรการ --}}
            <div class="bg-yellow-50 p-3 rounded-3 border border-warning mb-3">
                <label class="form-label fw-bold text-dark text-danger">ระบุเลขที่เอกสารเพื่อลงทะเบียนส่ง *</label>
                <div class="input-group shadow-sm mb-2" style="border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1;">
                    <input type="hidden" name="running_number" id="running_number" value="{{ $document->running_number }}">
                    <input type="text" name="doc_number" id="doc_number" 
                           class="form-control border-0 bg-white fw-bold text-primary px-3" 
                           placeholder="คลิกปุ่มรันเลข หรือพิมพ์เลขเอง..." 
                           value="{{ $document->formatted_doc_number }}" required>
                    
                    @if($hasReservedNumber)
                        <button type="button" class="btn btn-success fw-bold px-3" style="cursor: default;" title="ใช้เลขที่จองไว้">
                            <i class="fas fa-check-circle me-1"></i> จองไว้แล้ว
                        </button>
                        <button type="button" onclick="autoDocNo()" class="btn btn-outline-primary fw-bold px-3" title="ดึงเลขใหม่">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    @else
                        <button type="button" onclick="autoDocNo()" class="btn btn-primary fw-bold px-3">
                            <i class="fas fa-magic me-1"></i> รันเลข
                        </button>
                    @endif
                </div>
                
                <div class="alert alert-info py-2 px-3 mt-2 mb-0 border-0 shadow-sm" style="font-size: 13.5px; background: #e0f2fe; color: #0369a1;">
                    <i class="fas fa-info-circle me-1"></i> <strong>หนังสือส่งออก:</strong> เมื่อออกเลขเสร็จ ระบบจะส่งเรื่องไปให้ <u>{{ explode('—', $document->signer_name)[0] ?? 'ผู้ลงนาม' }}</u> เพื่อพิจารณาและลงนามอัตโนมัติ
                </div>
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
                        <button type="submit" onclick="document.getElementById('is_approved_outgoing').value='1'" 
                                class="btn btn-success py-2 fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-check-circle me-1"></i> ยืนยันออกเลข และส่งให้ผู้ลงนาม
                        </button>
                        <button type="submit" onclick="document.getElementById('is_approved_outgoing').value='0'" 
                                class="btn btn-danger py-2 fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-times-circle me-1"></i> ส่งคืนผู้เสนอเรื่อง
                        </button>
                    @else
                        <div class="d-flex gap-2">
                            <button type="submit" onclick="document.getElementById('is_approved_outgoing').value='1'" 
                                    class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-pill shadow-sm">
                                <i class="fas fa-check-circle me-1"></i> อนุมัติ และลงนาม
                            </button>
                            <button type="submit" onclick="document.getElementById('is_approved_outgoing').value='0'" 
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
                title: 'ดึงเลขหนังสือส่งออกสำเร็จ', showConfirmButton: false, timer: 1500
            });
        } catch (e) {
            Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อระบบรันเลขได้', 'error');
        }
    }
</script>
@endif
