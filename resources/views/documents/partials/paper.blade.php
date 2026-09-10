@php
    // 🌟 ดักจับฟังก์ชันแปลงเลขไทย ป้องกัน Error: Call to undefined function
    if (!function_exists('toThaiNum')) {
        function toThaiNum($string) {
            if (!$string) return '';
            $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
            return str_replace($arabic, $thai, $string);
        }
    }
@endphp

{{-- ป้ายบอกสถานะ มุมขวาบนกระดาษ (ซ่อนตอน Print ผ่าน CSS no-print) --}}
<div class="position-absolute top-0 end-0 p-4 no-print">
    <span class="badge {{ $b[0] }} {{ $b[1] }} border rounded-pill px-3 py-2 fw-bold shadow-sm" style="font-size:14px;">{{ $b[2] }}</span>
</div>

{{-- 🌟 โครงสร้างกระดาษ (อัปเดตฟอนต์เป็น TH Sarabun IT๙) 🌟 --}}
<div class="p-0" style="color: #000; font-family: 'TH Sarabun IT๙', 'THSarabunNew', 'Sarabun', sans-serif;">
    
    {{-- 1. หัวกระดาษ (ครุฑ + บันทึกข้อความ) --}}
    <div class="position-relative mb-3" style="min-height: 1.5cm; margin-top: 1cm;">
        <div class="position-absolute" style="top: -0.5cm; left: 0;">
            <img src="{{ asset('images/krut.png') }}" alt="ตราครุฑ" style="height: 1.5cm; width: auto; object-fit: contain;">
        </div>
        <div class="text-center">
            <span style="font-size: 29pt; font-weight: bold; letter-spacing: 1px;">บันทึกข้อความ</span>
        </div>
    </div>

    {{-- 2. ส่วนราชการ --}}
    <div style="margin-bottom: 8px; display: block;">
        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px;">ส่วนราชการ</span> 
        <span style="font-size: 16pt; border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 120px); padding-left: 5px;">
            {{ $document->creator->department ?? '-' }} องค์การบริหารส่วนตำบลพร่อน <span style="margin-left: 1.5rem;">โทร. ๐ ๗๓๒๙ ๙๔๑๒</span>
        </span>
    </div>

    {{-- 3. ที่ และ วันที่ --}}
    @php
        $displayDocNumber = $document->running_number
            ? 'ยล 77301/' . toThaiNum($document->running_number)
            : 'ยล 77301/....................';
    @endphp

    <div style="margin-bottom: 8px; display: block; width: 100%;">
        <div style="display: inline-block; width: 50%;">
            <span style="font-size: 20pt; font-weight: bold; margin-right: 12px;">ที่</span>
            <span style="font-size: 16pt; border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 60px); padding-left: 5px;">
                {{ $displayDocNumber }}
            </span>
        </div>
        <div style="display: inline-block; width: 49%;">
            <span style="font-size: 20pt; font-weight: bold; margin-right: 12px; margin-left: 10px;">วันที่</span>
            <span style="font-size: 16pt; border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 70px); padding-left: 5px;">
                {{ toThaiNum(\Carbon\Carbon::parse($document->doc_date)->addYears(543)->locale('th')->translatedFormat('j F Y')) }}
            </span>
        </div>
    </div>

    {{-- 4. เรื่อง --}}
    <div style="margin-bottom: 8px; display: block;">
        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px;">เรื่อง</span> 
        <span style="font-size: 16pt; border-bottom: 1px dotted #000; display: inline-block; width: calc(100% - 70px); padding-left: 5px;">
            {{ toThaiNum($document->title) }}
        </span>
    </div>

    {{-- 5. เรียน --}}
    <div style="margin-bottom: 15px; padding-top: 5px; display: block; border-top: 1.5px solid #000 !important;">
        <span style="font-size: 20pt; font-weight: bold; margin-right: 12px;">เรียน</span> 
        <span style="font-size: 16pt;">นายกองค์การบริหารส่วนตำบลพร่อน</span>
    </div>

    {{-- 6. เนื้อหาข้อความ --}}
    <div class="document-content" style="font-size: 16pt; line-height: 1.5; text-align: justify; text-indent: 2.5cm; white-space: pre-wrap; margin-top: 6pt; margin-bottom: 12pt;">{{ toThaiNum($document->content) }}</div>

    {{-- 7. คำลงท้าย --}}
    <div style="font-size: 16pt; margin-left: 2.5cm; margin-top: 6pt; margin-bottom: 24pt;">จึงเรียนมาเพื่อโปรดทราบ</div>


    {{-- 🔏 8. ส่วนลายเซ็น (🌟 ปลด Bootstrap ทิ้ง ใช้ CSS ธรรมดา ห้ามหั่นครึ่ง และดันขวา 100%) --}}
    <div style="font-size: 16pt; margin-top: 1.5em;">
        
        {{-- ผู้เสนอเรื่อง/ผู้รายงาน --}}
        <div class="document-signature-block" style="page-break-inside: avoid; break-inside: avoid; margin-bottom: 20px;">
            <div style="display: block; width: 50%; margin-left: 50%; text-align: center;">
                <div style="margin-bottom: 5px; white-space: nowrap;">
                    <span style="font-size: 16pt; display: inline-block; vertical-align: bottom; margin-right: 8px;">(ลงชื่อ)</span>
                    <div style="display: inline-block; border-bottom: 1px dotted #000; width: 180px; position: relative; height: 30px; vertical-align: bottom;">
                        @if($document->creator_signature)
                            <img src="{{ $document->creator_signature }}" style="max-height: 50px; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); mix-blend-mode: multiply; page-break-inside: avoid;">
                        @endif
                    </div>
                </div>
                <div style="margin-top: 5px;">( {{ $document->creator->name }} )</div>
                <div>{{ $document->creator->position }}</div>
            </div>
        </div>

        @php
            $dynamicReviewRoutes = $document->relationLoaded('routes')
                ? $document->routes->where('step_order', '>', 1)->sortBy('step_order')
                : collect();
        @endphp

        @if($dynamicReviewRoutes->isNotEmpty())
            {{-- ลายเซ็นผู้พิจารณาจากเส้นทางที่ผู้สร้างเลือกจริง --}}
            @foreach($dynamicReviewRoutes as $routeIndex => $approvalRoute)
                @php
                    $routeStatus = strtolower($approvalRoute->status instanceof \BackedEnum
                        ? $approvalRoute->status->value
                        : (string) $approvalRoute->status);
                    $routeApproved = $routeStatus === 'approved';
                @endphp
                <div class="document-signature-block" style="page-break-inside: avoid; break-inside: avoid; margin-bottom: 20px; padding-top: 10px;">
                    <div style="font-size: 16pt; font-weight: bold; margin-bottom: 10px;">
                        ความเห็นผู้พิจารณาลำดับที่ {{ $loop->iteration }}
                    </div>
                    <div style="border-bottom: 1px dotted #555; min-height: 28px; width: 85%; color: #000; font-size: 16pt; margin-bottom: 15px; padding-left: 10px;">
                        {{ $routeApproved ? toThaiNum($approvalRoute->comment ?: 'พิจารณาเห็นชอบ') : '' }}
                    </div>

                    <div style="display: block; width: 50%; margin-left: 50%; text-align: center;">
                        <div style="margin-bottom: 5px; white-space: nowrap;">
                            <span style="font-size: 16pt; display: inline-block; vertical-align: bottom; margin-right: 8px;">(ลงชื่อ)</span>
                            <div style="display: inline-block; border-bottom: 1px dotted #000; width: 180px; position: relative; height: 30px; vertical-align: bottom;">
                                @if($routeApproved && !empty($approvalRoute->user?->signature))
                                    <img src="{{ getSigUrl($approvalRoute->user->signature) }}" style="max-height: 50px; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); mix-blend-mode: multiply; page-break-inside: avoid;">
                                @endif
                            </div>
                        </div>
                        <div style="margin-top: 5px;">( {{ $approvalRoute->user->name ?? '......................................................' }} )</div>
                        <div>{{ $approvalRoute->user->position ?? 'ผู้พิจารณา' }}</div>
                    </div>
                </div>
            @endforeach
        @else
        {{-- ความเห็นหัวหน้าสำนักปลัด --}}
        @if($document->supervisor_signature || in_array($document->status, ['WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED', 'REJECTED']))
        <div class="document-signature-block" style="page-break-inside: avoid; break-inside: avoid; margin-bottom: 20px; padding-top: 10px;">
            <div style="font-size: 16pt; font-weight: bold; margin-bottom: 10px;">ความเห็นหัวหน้าสำนักปลัด/ผอ.กอง</div>
            <div style="border-bottom: 1px dotted #555; min-height: 28px; width: 85%; color: #000; font-size: 16pt; margin-bottom: 15px; padding-left: 10px;">
                {{ toThaiNum($document->supervisor_comment ?? ($document->supervisor_signature ? 'ทราบ / เพื่อโปรดพิจารณาอนุมัติ' : '')) }}
            </div>
            
            <div style="display: block; width: 50%; margin-left: 50%; text-align: center;">
                <div style="margin-bottom: 5px; white-space: nowrap;">
                    <span style="font-size: 16pt; display: inline-block; vertical-align: bottom; margin-right: 8px;">(ลงชื่อ)</span>
                    <div style="display: inline-block; border-bottom: 1px dotted #000; width: 180px; position: relative; height: 30px; vertical-align: bottom;">
                        @if($document->supervisor_signature)
                            <img src="{{ $document->supervisor_signature }}" style="max-height: 50px; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); mix-blend-mode: multiply; page-break-inside: avoid;">
                        @endif
                    </div>
                </div>
                <div style="margin-top: 5px;">( {{ $document->supervisor->name ?? '......................................................' }} )</div>
                <div>หัวหน้าสำนักปลัด/ผอ.กอง</div>
            </div>
        </div>
        @endif

        {{-- ความเห็นปลัด --}}
        @if($document->palad_signature || in_array($document->status, ['WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED']))
        <div class="document-signature-block" style="page-break-inside: avoid; break-inside: avoid; margin-bottom: 20px; padding-top: 10px;">
            <div style="font-size: 16pt; font-weight: bold; margin-bottom: 10px;">ความเห็นปลัดองค์การบริหารส่วนตำบลพร่อน</div>
            <div style="border-bottom: 1px dotted #555; min-height: 28px; width: 85%; color: #000; font-size: 16pt; margin-bottom: 15px; padding-left: 10px;">
                {{ toThaiNum($document->palad_comment ?? ($document->palad_signature ? 'ทราบ / เพื่อโปรดพิจารณาอนุมัติ' : '')) }}
            </div>
            
            <div style="display: block; width: 50%; margin-left: 50%; text-align: center;">
                <div style="margin-bottom: 5px; white-space: nowrap;">
                    <span style="font-size: 16pt; display: inline-block; vertical-align: bottom; margin-right: 8px;">(ลงชื่อ)</span>
                    <div style="display: inline-block; border-bottom: 1px dotted #000; width: 180px; position: relative; height: 30px; vertical-align: bottom;">
                        @if($document->palad_signature)
                            <img src="{{ $document->palad_signature }}" style="max-height: 50px; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); mix-blend-mode: multiply; page-break-inside: avoid;">
                        @endif
                    </div>
                </div>
                <div style="margin-top: 5px;">( {{ $document->palad->name ?? '......................................................' }} )</div>
                <div>ปลัดองค์การบริหารส่วนตำบลพร่อน</div>
            </div>
        </div>
        @endif

        {{-- คำสั่งนายก --}}
        @if($document->nayok_signature || in_array($document->status, ['WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED']))
        <div class="document-signature-block" style="page-break-inside: avoid; break-inside: avoid; margin-bottom: 20px; padding-top: 10px;">
            <div style="font-size: 16pt; font-weight: bold; margin-bottom: 10px;">คำสั่งนายกองค์การบริหารส่วนตำบลพร่อน</div>
            
            <div style="margin-bottom: 15px; padding-left: 10px; display: block;">
                <span style="display: inline-block; text-align: center; width: 20px; height: 20px; border: 1.5px solid #000000; font-size: 14pt; font-weight: bold; vertical-align: middle;">
                    @if($document->nayok_signature) ✓ @endif
                </span>
                <span style="color: #000000; font-size: 16pt; margin-left: 10px; vertical-align: middle;">
                    {{ toThaiNum($document->nayok_comment ?? ($document->nayok_signature ? 'อนุมัติ / ทราบ' : '')) }}
                </span>
            </div>

            <div style="display: block; width: 50%; margin-left: 50%; text-align: center;">
                <div style="margin-bottom: 5px; white-space: nowrap;">
                    <span style="font-size: 16pt; display: inline-block; vertical-align: bottom; margin-right: 8px;">(ลงชื่อ)</span>
                    <div style="display: inline-block; border-bottom: 1px dotted #000; width: 180px; position: relative; height: 30px; vertical-align: bottom;">
                        @if($document->nayok_signature)
                            <img src="{{ $document->nayok_signature }}" style="max-height: 50px; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); mix-blend-mode: multiply; page-break-inside: avoid;">
                        @endif
                    </div>
                </div>
                <div style="margin-top: 5px;">( {{ $document->nayok->name ?? '......................................................' }} )</div>
                <div>นายกองค์การบริหารส่วนตำบลพร่อน</div>
            </div>
        </div>
        @endif

        @endif {{-- จบการแยกระหว่างเส้นทางไดนามิกกับระบบตำแหน่งเดิม --}}

    </div>
</div>

<style>
    .document-signature-block {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    @media print {
        .document-page-spacer {
            display: none !important;
        }
    }
</style>

<script>
    (() => {
        const keepSignatureBlocksOnOnePreviewPage = () => {
            document.querySelectorAll('.doc-paper').forEach((paper) => {
                paper.querySelectorAll('.document-page-spacer').forEach((spacer) => spacer.remove());

                const paperWidth = paper.getBoundingClientRect().width;
                if (!paperWidth) return;

                const millimetre = paperWidth / 210;
                const pageHeight = 297 * millimetre;
                const topMargin = 20 * millimetre;
                const bottomMargin = 15 * millimetre;
                const paperTop = paper.getBoundingClientRect().top;

                paper.querySelectorAll('.document-signature-block').forEach((block) => {
                    const rect = block.getBoundingClientRect();
                    const blockTop = rect.top - paperTop;
                    const blockBottom = rect.bottom - paperTop;
                    const nextPageBoundary = (Math.floor(blockTop / pageHeight) + 1) * pageHeight;

                    if (blockBottom > nextPageBoundary - bottomMargin) {
                        const spacer = document.createElement('div');
                        spacer.className = 'document-page-spacer';
                        spacer.setAttribute('aria-hidden', 'true');
                        spacer.style.height = `${Math.max(0, nextPageBoundary + topMargin - blockTop)}px`;
                        block.before(spacer);
                    }
                });
            });
        };

        let resizeTimer;
        const scheduleLayout = () => {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(keepSignatureBlocksOnOnePreviewPage, 50);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', keepSignatureBlocksOnOnePreviewPage, { once: true });
        } else {
            keepSignatureBlocksOnOnePreviewPage();
        }

        window.addEventListener('load', keepSignatureBlocksOnOnePreviewPage, { once: true });
        window.addEventListener('resize', scheduleLayout);
    })();
</script>
