@extends('layouts.app')
@section('title', 'ขอสิทธิ์เข้าถึงเอกสารลับ')

@section('content')
<div class="container-fluid px-4 py-5 d-flex align-items-center justify-content-center" style="min-height: 80vh; background-color: var(--bg-page);">
    <div class="card border-0 shadow-lg" style="border-radius: 20px; max-width: 450px; width: 100%;">
        <div class="card-body p-5 text-center">
            
            <div class="mx-auto mb-4 rounded-circle d-flex align-items-center justify-content-center text-white shadow" style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #d97706); font-size: 32px;">
                <i class="fas fa-user-shield"></i>
            </div>
            
            <h4 class="fw-bold mb-1 text-dark">จำกัดสิทธิ์การเข้าถึง</h4>
            <p class="text-muted small mb-4">เรื่อง: {{ $document->title }}<br><span class="text-warning fw-bold"><i class="fas fa-exclamation-circle me-1"></i>({{ $document->doc_secret }})</span></p>
            
            @if(!$accessRequest)
                <p class="mb-4" style="font-size: 14.5px; color: #475569;">
                    คุณไม่มีสิทธิ์เปิดดูเอกสารฉบับนี้<br>ต้องการส่งคำขออนุญาตไปยัง <strong>เจ้าของเรื่อง</strong> หรือ <strong>ธุรการ</strong> หรือไม่?
                </p>
                {{-- 🌟 จุดที่ 1: เปลี่ยนลิงก์ Action ของ Form เป็น UUID --}}
                <form action="{{ route('documents.request_access', $document->uuid ?? $document->id) }}" method="POST">
                    @csrf
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <a href="{{ route('documents.approve_list') }}" class="ds-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้ารายการ</a>
                        <button type="submit" class="btn rounded-pill fw-bold text-white px-4 shadow-sm" style="background: #f59e0b; border: none;"><i class="fas fa-paper-plane me-2"></i> ส่งคำขอสิทธิ์</button>
                    </div>
                </form>

            @elseif($accessRequest->status === 'pending')
                <div class="alert alert-warning border-0 rounded-3 shadow-sm mb-4"><i class="fas fa-hourglass-half me-2"></i> คำขอของคุณอยู่ระหว่างรอการอนุมัติ</div>
                <a href="{{ route('documents.approve_list') }}" class="ds-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้ารายการ</a>

            @elseif($accessRequest->status === 'rejected')
                <div class="alert alert-danger border-0 rounded-3 shadow-sm mb-4"><i class="fas fa-times-circle me-2"></i> คำขอของคุณถูกปฏิเสธโดยเจ้าของเรื่อง</div>
                {{-- 🌟 จุดที่ 2: เปลี่ยนลิงก์ Action ของ Form (กรณีขอใหม่) เป็น UUID --}}
                <form action="{{ route('documents.request_access', $document->uuid ?? $document->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger rounded-pill fw-bold px-4"><i class="fas fa-redo me-2"></i> ส่งคำขอใหม่อีกครั้ง</button>
                </form>
            @endif
            
        </div>
    </div>
</div>
@endsection
