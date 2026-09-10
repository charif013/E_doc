@extends('layouts.app')
@section('title', 'แก้ไขหนังสือรับเข้า')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mx-auto" style="max-width: 1000px;">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                    <i class="fas fa-edit text-warning me-2"></i>แก้ไขหนังสือรับเข้า
                </h4>
                <small class="text-muted">แก้ไขข้อมูลหนังสือรับเข้าที่ถูกตีกลับ</small>
            </div>
            {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ปุ่มยกเลิกเป็น UUID --}}
            <a href="{{ route('documents.show', $document->uuid ?? $document->id) }}" class="ds-back-link">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>ยกเลิกและกลับ
            </a>
        </div>

        <div class="card shadow-sm border-0" style="border-radius: 16px;">
            <div class="card-body p-4 p-md-5">
                {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ Action ของ Form เป็น UUID --}}
                <form action="{{ route('documents.update', $document->uuid ?? $document->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">เลขที่รับ <span class="text-danger">*</span></label>
                            <input type="text" name="receive_number" class="form-control" value="{{ $document->formatted_receive_number }}" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">วันที่รับ <span class="text-danger">*</span></label>
                            {{-- ดึงค่าเดิมมาแปลงให้อยู่ในฟอร์แมต Y-m-d เพื่อแสดงในช่อง input type="date" --}}
                            <input type="date" name="receive_date" class="form-control" value="{{ $document->receive_date ? \Carbon\Carbon::parse($document->receive_date)->format('Y-m-d') : '' }}" required style="border-radius: 10px;">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">เลขที่หนังสือ <span class="text-danger">*</span></label>
                            <input type="text" name="doc_number" class="form-control" value="{{ $document->doc_number }}" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ลงวันที่ (บนหนังสือ) <span class="text-danger">*</span></label>
                            <input type="date" name="doc_date" class="form-control" value="{{ $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->format('Y-m-d') : '' }}" required style="border-radius: 10px;">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold text-secondary">เรื่อง <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ $document->title }}" required style="border-radius: 10px;">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">จาก (หน่วยงาน/บุคคล) <span class="text-danger">*</span></label>
                            <input type="text" name="doc_from" class="form-control" value="{{ $document->doc_from }}" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">หมวดหมู่เอกสาร <span class="text-danger">*</span></label>
                            <input type="text" name="doc_type_category" class="form-control" value="{{ $document->doc_type_category }}" required style="border-radius: 10px;">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ชั้นความเร็ว</label>
                            <select name="doc_speed" class="form-select" style="border-radius: 10px;">
                                <option value="ปกติ" {{ $document->doc_speed == 'ปกติ' ? 'selected' : '' }}>ปกติ</option>
                                <option value="ด่วน" {{ $document->doc_speed == 'ด่วน' ? 'selected' : '' }}>ด่วน</option>
                                <option value="ด่วนมาก" {{ $document->doc_speed == 'ด่วนมาก' ? 'selected' : '' }}>ด่วนมาก</option>
                                <option value="ด่วนที่สุด" {{ $document->doc_speed == 'ด่วนที่สุด' ? 'selected' : '' }}>ด่วนที่สุด</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold text-secondary">ชั้นความลับ</label>
                            <select name="doc_secret" class="form-select" style="border-radius: 10px;">
                                <option value="ไม่มีชั้นความลับ" {{ $document->doc_secret == 'ไม่มีชั้นความลับ' ? 'selected' : '' }}>ไม่มีชั้นความลับ</option>
                                <option value="ลับ" {{ $document->doc_secret == 'ลับ' ? 'selected' : '' }}>ลับ</option>
                                <option value="ลับมาก" {{ $document->doc_secret == 'ลับมาก' ? 'selected' : '' }}>ลับมาก</option>
                                <option value="ลับที่สุด" {{ $document->doc_secret == 'ลับที่สุด' ? 'selected' : '' }}>ลับที่สุด</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info rounded-3" style="font-size: 13px; background-color: var(--primary-light); color: var(--primary-dark); border: 1px solid var(--primary-border);">
                        <i class="fas fa-info-circle me-1"></i> ไฟล์เอกสารแนบ (หรือ QR Code) จะใช้ข้อมูลเดิมที่เคยอัปโหลดไว้ หากอัปโหลดผิดไฟล์และต้องการเปลี่ยนไฟล์ ต้องลบเอกสารฉบับนี้ทิ้งแล้วสร้างใหม่
                    </div>

                    <hr class="mb-4 text-muted">
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" style="padding: 12px 30px; background-color: var(--primary);">
                            <i class="fas fa-save me-2"></i>บันทึกการแก้ไข
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
