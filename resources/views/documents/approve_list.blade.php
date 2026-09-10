@extends('layouts.app')

@section('title', 'เอกสารรอพิจารณา / อนุมัติ')

@section('content')
<div class="review-queue-page container-fluid px-3 px-lg-4 py-4">
    <div class="mx-auto" style="max-width: 1320px;">
        <header class="review-queue-heading">
            <div class="review-queue-heading__copy">
                <div class="review-queue-heading__icon" aria-hidden="true"><i class="fas fa-folder-open"></i></div>
                <div>
                    <span class="review-queue-eyebrow">แฟ้มพิจารณาอนุมัติ</span>
                    <h1>รายการเอกสารรอพิจารณา</h1>
                    <p>แสดงเฉพาะเอกสารที่ถึงลำดับดำเนินการของคุณแล้ว</p>
                </div>
            </div>
            <div class="review-queue-heading__actions">
                <span class="review-queue-date"><i class="far fa-calendar" aria-hidden="true"></i>{{ now()->locale('th')->translatedFormat('d M') }} {{ now()->year + 543 }}</span>
                <a href="{{ route('home') }}" class="ds-back-link"><i class="fas fa-arrow-left" aria-hidden="true"></i>กลับหน้าหลัก</a>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show review-queue-alert" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show review-queue-alert" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
            </div>
        @endif

        <section class="review-queue-summary" aria-labelledby="document-queue-title">
            <div class="review-queue-summary__copy">
                <span class="review-queue-summary__icon"><i class="fas fa-inbox" aria-hidden="true"></i></span>
                <div>
                    <h2 id="document-queue-title">งานเอกสารที่ต้องดำเนินการ</h2>
                    <p>{{ $documents->count() > 0 ? 'เรียงจากรายการที่ส่งเข้ามาล่าสุด' : 'ขณะนี้ไม่มีเอกสารค้างในลำดับของคุณ' }}</p>
                </div>
            </div>
            <span class="review-queue-count"><strong>{{ number_format($documents->count()) }}</strong> รายการ</span>
        </section>

        <div class="review-queue-list">
            @forelse($documents as $doc)
                @php
                    $documentStatuses = [
                        'DRAFT'              => ['gray', 'ฉบับร่าง'],
                        'WAITING_ADMIN'      => ['gray', 'รอธุรการรับเรื่อง'],
                        'WAITING_SUPERVISOR' => ['amber', 'รอหัวหน้าส่วนราชการ'],
                        'WAITING_PALAD'      => ['blue', 'รอปลัด อบต.'],
                        'WAITING_NAYOK'      => ['cyan', 'รอนายกฯ อนุมัติ'],
                        'WAITING_NUMBERING'  => ['purple', 'รอธุรการลงเลข'],
                        'APPROVED'           => ['green', 'อนุมัติแล้ว'],
                        'REJECTED'           => ['red', 'ถูกตีกลับ'],
                        'CANCELED'           => ['gray', 'ยกเลิก / เลขเสีย'],
                        'REGISTERED'         => ['blue', 'รับเข้าระบบแล้ว'],
                        'IN_REVIEW'          => ['blue', 'อยู่ระหว่างพิจารณา'],
                        'PROCESSING'         => ['blue', 'อยู่ระหว่างพิจารณา'],
                        'WAITING_APPROVER'   => ['blue', 'รออนุมัติ'],
                        'COMPLETED'          => ['green', 'ดำเนินการเสร็จสิ้น'],
                        'ARCHIVED'           => ['gray', 'จัดเก็บแล้ว'],
                    ];
                    $documentStatus = $documentStatuses[strtoupper((string) $doc->status)] ?? ['gray', (string) $doc->status];
                    if ($doc->isAtFinalApprovalStep()) {
                        $documentStatus = ['blue', 'รออนุมัติ'];
                    }
                    $documentTypes = [
                        'internal' => ['บันทึกข้อความ', 'fa-file-signature'],
                        'incoming' => ['หนังสือรับเข้า', 'fa-file-import'],
                        'outgoing' => ['หนังสือส่งออก', 'fa-file-export'],
                        'upload'   => ['เอกสารอัปโหลด', 'fa-file-upload'],
                    ];
                    $documentType = $documentTypes[strtolower((string) $doc->doc_type)] ?? ['เอกสารสารบรรณ', 'fa-file-alt'];
                    $isFinished = in_array(strtoupper((string) $doc->status), ['APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED', 'ARCHIVED'], true)
                        || (config('edoc.v2.document_reads') && !config('edoc.v2.write_enabled'));
                @endphp

                <article class="review-queue-item">
                    <div class="review-queue-item__icon" aria-hidden="true"><i class="fas {{ $documentType[1] }}"></i></div>
                    <div class="review-queue-item__main">
                        <div class="review-queue-item__eyebrow">
                            <span class="review-queue-type">{{ $documentType[0] }}</span>
                            <span>{{ $doc->formatted_doc_number ?? 'ยังไม่มีเลขที่เอกสาร' }}</span>
                        </div>
                        <h3><a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}">{{ $doc->title }}</a></h3>
                        <div class="review-queue-item__meta">
                            <span><i class="fas fa-user" aria-hidden="true"></i>{{ $doc->creator->name ?? 'ไม่ทราบผู้สร้าง' }}</span>
                            <span><i class="fas fa-building" aria-hidden="true"></i>{{ $doc->creator->department ?? 'ไม่ระบุส่วนราชการ' }}</span>
                            <span><i class="far fa-clock" aria-hidden="true"></i>{{ $doc->created_at ? $doc->created_at->locale('th')->translatedFormat('d M').' '.($doc->created_at->year + 543).' '.$doc->created_at->format('H:i') : '-' }}</span>
                        </div>
                    </div>
                    <div class="review-queue-item__state">
                        <span class="review-queue-status review-queue-status--{{ $documentStatus[0] }}">{{ $documentStatus[1] }}</span>
                    </div>
                    <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="review-queue-item__action {{ $isFinished ? 'review-queue-item__action--secondary' : '' }}">
                        <i class="fas {{ $isFinished ? 'fa-search' : 'fa-pen-nib' }} me-1" aria-hidden="true"></i>{{ $isFinished ? 'ดูรายละเอียด' : 'พิจารณา' }}
                    </a>
                </article>
            @empty
                <div class="review-queue-empty" role="status">
                    <span class="review-queue-empty__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                    <h2>ไม่มีเอกสารรอการพิจารณา</h2>
                    <p>งานเอกสารที่ถึงลำดับของคุณได้รับการดำเนินการครบแล้ว</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@include('partials.review_queue_styles')
@endsection
