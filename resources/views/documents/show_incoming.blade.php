@extends('layouts.app')
@section('title', 'รายละเอียดหนังสือรับเข้า')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="mx-auto" style="max-width: 1200px;">

        {{-- 🌟 Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                    <i class="fas fa-file-import text-primary me-2"></i>รายละเอียดหนังสือรับเข้า
                </h4>
                <small class="text-muted">ข้อมูลและสถานะการลงนามของเอกสารรับเข้าภายนอก</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted fw-bold d-none d-md-inline">{{ now()->locale('th')->translatedFormat('d M Y') }}</span>
                
                {{-- 🌟 ปุ่มพิมพ์ใบเกษียน (โชว์เฉพาะตอนอนุมัติแล้ว) - อัปเดตให้ลิงก์ไปหน้า Routing Slip --}}
                @if($document->status === 'APPROVED' && $document->assigned_to)
                <a href="{{ route('documents.routing_slip', $document->uuid ?? $document->id) }}" target="_blank" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm fw-bold">
                    <i class="fas fa-print me-1"></i> พิมพ์ใบเกษียน
                </a>
                @endif

                <a href="{{ route('documents.approve_list') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm bg-white fw-bold">
                    <i class="fas fa-arrow-left me-1"></i> กลับหน้ารายการ
                </a>
            </div>
        </div>

        @php
            $user = auth()->user();
            
            $isReviewer = (
                ($user->hasRole('saraban') && in_array($document->status, ['WAITING_ADMIN', 'WAITING_NUMBERING'])) ||
                ($user->hasRole('head')      && $document->status === 'WAITING_SUPERVISOR') ||
                ($user->hasAnyRole(['palad', 'deputy-palad']) && $document->status === 'WAITING_PALAD') ||
                ($user->hasRole('executive') && $document->status === 'WAITING_NAYOK')
            );
            
            // 🌟 แก้ไขปัญหาสีป้ายสถานะกลืนกับพื้นหลัง โดยใช้สีทึบ (Solid) และบังคับสีข้อความให้ตัดกันชัดเจน
            $badges = [
                'DRAFT'              => ['bg-secondary', 'text-white', 'ฉบับร่าง'],
                'WAITING_ADMIN'      => ['bg-secondary', 'text-white', 'รอธุรการรับเรื่อง'],
                'WAITING_SUPERVISOR' => ['bg-warning', 'text-dark', 'รอหัวหน้าสำนักปลัด'],
                'WAITING_PALAD'      => ['bg-primary', 'text-white', 'รอปลัด อบต.'],
                'WAITING_NAYOK'      => ['bg-info', 'text-dark', 'รอนายกฯ อนุมัติ'], 
                'WAITING_NUMBERING'  => ['bg-dark', 'text-white', 'รอธุรการลงทะเบียนเลข'],
                'APPROVED'           => ['bg-success', 'text-white', 'สั่งการ/ลงเลขเรียบร้อย'],
                'REJECTED'           => ['bg-danger', 'text-white', 'ถูกตีกลับ / แก้ไข'],
                'CANCELED'           => ['bg-dark', 'text-white', 'ยกเลิกเอกสาร'], 
            ];
            $b = $badges[$document->status] ?? ['bg-light', 'text-dark', $document->status];
            
            // ข้อมูลเส้นทางเดินเอกสาร
            $sigSteps = [
                ['label'=>'ผู้เสนอเรื่อง',       'sig'=>$document->creator_signature,    'name'=>$document->creator->name ?? '-',    'pos'=>$document->creator->position ?? 'เจ้าหน้าที่ธุรการ',       'at'=>$document->created_at?->format('d/m/Y H:i'),                                       'icon'=>'fa-user'],
                ['label'=>'หัวหน้าสำนักปลัด',    'sig'=>$document->supervisor_signature, 'name'=>$document->supervisor->name ?? '-', 'pos'=>$document->supervisor->position ?? 'หัวหน้าสำนักปลัด อบต.', 'at'=>$document->supervisor_approved_at ? \Carbon\Carbon::parse($document->supervisor_approved_at)->format('d/m/Y H:i') : null, 'icon'=>'fa-user-tie'],
                ['label'=>'ปลัด อบต.',           'sig'=>$document->palad_signature,      'name'=>$document->palad->name ?? '-',      'pos'=>$document->palad->position ?? 'ปลัด อบต.',               'at'=>$document->palad_approved_at      ? \Carbon\Carbon::parse($document->palad_approved_at)->format('d/m/Y H:i')      : null, 'icon'=>'fa-user-shield'],
                ['label'=>'นายก อบต.',           'sig'=>$document->nayok_signature,      'name'=>$document->nayok->name ?? '-',      'pos'=>$document->nayok->position ?? 'นายก อบต.',               'at'=>$document->nayok_approved_at      ? \Carbon\Carbon::parse($document->nayok_approved_at)->format('d/m/Y H:i')      : null, 'icon'=>'fa-star'],
            ];

            // 🌟 ตัวช่วยจัดการ URL ลายเซ็นแบบครอบจักรวาล ป้องกัน Error (Malformed HTTP request)
            if (!function_exists('getSigUrl')) {
                function getSigUrl($path) {
                    if (empty($path)) return '';
                    $path = trim($path); // ตัดช่องว่างซ่อนเร้น
                    
                    if (str_starts_with($path, 'data:image')) return $path;
                    if (preg_match('/^https?:\/\//', $path)) return $path;
                    
                    $path = preg_replace('/^(public|storage)\//', '', ltrim($path, '/'));
                    
                    return asset('storage/' . $path);
                }
            }

            // 🌟 ฟังก์ชันแปลงเลขไทย
            if (!function_exists('toThaiNum')) {
                function toThaiNum($string) {
                    if (!$string) return '';
                    $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                    $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
                    return str_replace($arabic, $thai, $string);
                }
            }
        @endphp

        {{-- 🌟 กล่องแสดงข้อมูล: เลขเสีย (CANCELED) --}}
        @if($document->status === 'CANCELED')
            <div class="mb-4 no-print">
                <div class="alert alert-dark border-0 shadow-sm d-flex align-items-center" style="border-left: 5px solid #1e293b !important; border-radius: 12px;">
                    <i class="fas fa-ban fs-3 text-secondary me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">หนังสือรับเข้าฉบับนี้ถูกยกเลิก</h6>
                        <span class="small text-muted"><strong>เหตุผล:</strong> {{ $document->reject_reason ?? 'ผู้บริหารไม่อนุมัติ / ยกเลิกคำสั่งการ' }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- 🌟 ส่วนแจ้งเตือนสำหรับ ผอ.กอง ให้กด "รับทราบคำสั่ง" --}}
        @if($document->status === 'APPROVED' && $document->assigned_to === $user->department && $user->hasRole('head'))
            <div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 12px; background: {{ $document->acknowledged_at ? '#f0fdf4' : '#e0f2fe' }}; border: 1px solid {{ $document->acknowledged_at ? '#bbf7d0' : '#bae6fd' }}; border-left: 5px solid {{ $document->acknowledged_at ? '#16a34a' : '#0284c7' }} !important;">
                <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            @if($document->acknowledged_at)
                                <i class="fas fa-check-double text-success me-2"></i>รับทราบคำสั่งเรียบร้อยแล้ว
                            @else
                                <i class="fas fa-bell text-primary me-2"></i>หนังสือสั่งการถึง: {{ $user->department }}
                            @endif
                        </h5>
                        
                        @if($document->acknowledged_at)
                            <p class="mb-0 text-muted small">
                                ผู้รับทราบ: <strong>{{ $document->acknowledger->name ?? 'ไม่ทราบชื่อ' }}</strong><br>
                                เวลาที่บันทึก: {{ \Carbon\Carbon::parse($document->acknowledged_at)->addYears(543)->format('d/m/Y H:i') }} น.
                            </p>
                        @else
                            <p class="mb-0 text-muted small">นายกฯ อบต. / ปลัด อบต. ได้มอบหมายหนังสือฉบับนี้ให้กองของคุณดำเนินการ กรุณากดรับทราบเพื่อยืนยันการรับเรื่อง</p>
                        @endif
                    </div>
                    
                    @if(!$document->acknowledged_at)
                        <form action="{{ route('documents.acknowledge', $document->uuid ?? $document->id) }}" method="POST" class="m-0">
                            @csrf
                            <button type="button" class="btn btn-primary rounded-pill fw-bold px-4 shadow-sm" onclick="confirmAcknowledge(this)">
                                <i class="fas fa-check-circle me-2"></i>กดรับทราบคำสั่ง
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- 🌟 โหลดกล่องพิจารณา (Review Box) สำหรับหนังสือรับเข้าโดยเฉพาะ --}}
        @if($isReviewer)
            <div class="no-print mb-4">
                @include('documents.partials.review_box_incoming')
            </div>
        @endif

        {{-- 🌟 กล่องจัดการคำขอสิทธิ์ (เห็นเฉพาะธุรการและเจ้าของเรื่อง) 🌟 --}}
        @php
            $pendingRequests = \App\Models\DocumentAccessRequest::where('document_id', $document->id)->where('status', 'pending')->get();
            $canManageAccess = auth()->user()->id === $document->created_by || auth()->user()->hasRole('saraban');
        @endphp

        @if(in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด']) && $canManageAccess && $pendingRequests->count() > 0)
        <div class="card border-0 shadow-sm mb-4 no-print" style="border-radius: 12px; border-left: 5px solid #f59e0b !important;">
            <div class="card-header bg-white py-3 border-bottom-0">
                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-key text-warning me-2"></i>มีผู้ส่งคำขอสิทธิ์เข้าถึงเอกสารลับฉบับนี้</h6>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach($pendingRequests as $req)
                            <tr>
                                <td class="py-2">
                                    <strong>{{ $req->user->name ?? 'ไม่ทราบชื่อ' }}</strong> 
                                    <span class="text-muted small">({{ $req->user->department ?? '-' }})</span>
                                </td>
                                <td class="text-end py-2">
                                    <form action="{{ route('documents.approve_access', ['request_id' => $req->id, 'status' => 'approved']) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success rounded-pill px-3 shadow-sm fw-bold"><i class="fas fa-check me-1"></i> อนุมัติ</button>
                                    </form>
                                    <form action="{{ route('documents.approve_access', ['request_id' => $req->id, 'status' => 'rejected']) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm fw-bold ms-1"><i class="fas fa-times me-1"></i> ปฏิเสธ</button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- 🌟 Main Card (ข้อมูลเอกสาร) --}}
        <div class="card border-0 mb-4 shadow-sm print-container" style="border-radius:16px;overflow:hidden;border:1px solid #e4eaf0;">

            {{-- Hero Section สีน้ำเงิน --}}
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 px-4 py-4" style="background:var(--primary-dark);">
                <div>
                    <div style="font-size:13px;color:rgba(255,255,255,.7);margin-bottom:4px;">เลขที่เอกสาร: <span class="text-white fw-bold">{{ $document->doc_number ?? '-' }}</span></div>
                    <h4 class="mb-0 fw-bold text-white">{{ $document->title }}</h4>
                </div>
                <span class="badge {{ $b[0] }} {{ $b[1] }} border rounded-pill px-3 py-2 fw-bold shadow-sm" style="font-size:14px;">{{ $b[2] }}</span>
            </div>

            <div class="p-4 bg-white">

                {{-- กล่องแสดงเหตุผลการตีกลับ --}}
                @if($document->status === 'REJECTED')
                <div class="mb-4 p-3 rounded-3 shadow-sm no-print" style="background: var(--danger-bg); border: 1px solid var(--danger-border); border-left: 4px solid var(--danger-text);">
                    <div class="d-flex gap-3 align-items-start">
                        <div style="font-size: 24px; color: var(--danger-text);"><i class="fas fa-times-circle"></i></div>
                        <div>
                            <div style="font-size: 14px; font-weight: 700; color: var(--danger-text); margin-bottom: 4px;">เอกสารฉบับนี้ถูกตีกลับ / ให้แก้ไข</div>
                            <div style="font-size: 14.5px; color: #7f1d1d;"><strong>เหตุผล:</strong> {{ $document->reject_reason ?? 'ไม่มีการระบุเหตุผลเพิ่มเติม' }}</div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- ข้อมูลทั่วไป (หนังสือรับเข้า) --}}
                @php
                    $infoRows = [
                        [['เลขที่รับ',$document->receive_number??'-',false], ['วันที่รับ',$document->receive_date?\Carbon\Carbon::parse($document->receive_date)->addYears(543)->format('d/m/Y'):'-',false], ['ลงวันที่บนเอกสาร',$document->doc_date?\Carbon\Carbon::parse($document->doc_date)->addYears(543)->format('d/m/Y'):'-',false], ['ประเภทหนังสือ',$document->doc_type_category??'-',true]],
                        [['จาก (หน่วยงาน/บุคคล)',$document->doc_from??'-',false,'2'], ['ชั้นความเร็ว',$document->doc_speed??'ปกติ',false], ['ชั้นความลับ',$document->doc_secret??'ไม่มีชั้นความลับ',false]],
                    ];
                @endphp
                <div class="mb-4 no-print">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <span style="width:4px;height:18px;background:var(--primary);border-radius:4px;display:inline-block;"></span>
                        <span style="font-size:14px;font-weight:700;color:var(--primary-dark);">ข้อมูลทั่วไป (หนังสือรับเข้า)</span>
                    </div>
                    @foreach($infoRows as $row)
                    <div class="row g-0 mb-px" style="background:#edf0f4;gap:1px;">
                        @foreach($row as $cell)
                        <div class="col p-3 bg-white {{ isset($cell[3]) ? 'col-md-'.(6*$cell[3]) : '' }}">
                            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">{{ $cell[0] }}</div>
                            <div style="font-size:15px;font-weight:600;color:{{ $cell[2] ? 'var(--primary-dark)' : 'var(--text-primary)' }};">{{ $cell[1] }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                    
                    {{-- 🌟 ส่วนแสดงการมอบหมายงาน --}}
                    @if($document->assigned_to)
                    <div class="row g-0 mt-1" style="background:#edf0f4;gap:1px;">
                        <div class="col p-3 bg-white">
                            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:4px;">มอบหมายให้ส่วนราชการ</div>
                            <div style="font-size:16px;font-weight:bold;color:#b45309;"><i class="fas fa-share-square me-2"></i>{{ $document->assigned_to }}</div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- โซนเนื้อหา และ ไฟล์แนบ --}}
                <div class="mb-4 no-print">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <span style="width:4px;height:18px;background:var(--primary);border-radius:4px;display:inline-block;"></span>
                        <span style="font-size:14px;font-weight:700;color:var(--primary-dark);">เนื้อหา / ไฟล์แนบ</span>
                    </div>
                    
                    <div class="rounded-3 p-4" style="background:var(--bg-page);border:1px solid #edf0f4;">
                        @if($document->content && $document->content !== 'อ้างอิงจากไฟล์แนบในระบบ')
                            <div style="font-size:15px;line-height:1.85;color:var(--text-primary);">
                                {!! nl2br(e($document->content)) !!}
                            </div>
                        @endif

                        @if($document->attachment_path)
                            @if($document->content && $document->content !== 'อ้างอิงจากไฟล์แนบในระบบ')
                                <hr class="my-4 no-print" style="border-color: #cbd5e1;">
                            @endif
                            <div class="ratio ratio-16x9 rounded-3 overflow-hidden shadow-sm border no-print">
                                <iframe src="{{ asset('storage/'.$document->attachment_path) }}" allow="autoplay"></iframe>
                            </div>
                            <div class="text-center mt-3 no-print">
                                <a href="{{ asset('storage/'.$document->attachment_path) }}" target="_blank"
                                   class="btn rounded-pill px-4 fw-bold shadow-sm bg-white" style="color:var(--primary-dark);border:1px solid #cbd5e1;font-size:14px;">
                                    <i class="fas fa-external-link-alt me-2"></i>เปิดดูไฟล์ฉบับเต็ม
                                </a>
                            </div>
                        @endif

                        @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
                            <hr class="my-4 no-print" style="border-color: #cbd5e1;">
                            <div class="bg-white p-3 rounded-3 border shadow-sm no-print">
                                <form action="{{ route('documents.upload', $document->uuid ?? $document->id) }}" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap align-items-end gap-3">
                                    @csrf
                                    <div class="flex-grow-1">
                                        <label class="form-label small fw-bold text-muted mb-2">
                                            <i class="fas fa-paperclip me-1"></i> แนบไฟล์เพิ่มเติม หรือ เปลี่ยนไฟล์ใหม่ (PDF, JPG, PNG)
                                        </label>
                                        <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="border-radius: 8px;">
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" style="background:var(--primary);">
                                        <i class="fas fa-upload me-1"></i> อัปโหลดไฟล์
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 🌟 โซนพรีวิวใบเกษียนหนังสือรับเข้า (ดึงมาจากหน้า Routing Slip โดยตรง) 🌟 --}}
                @if($document->assigned_to)
                <div class="card border-0 shadow-sm mb-5 no-print" style="border-radius: 16px; overflow: hidden;">
                    <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="fas fa-file-signature text-primary me-2"></i>พรีวิวใบเกษียนหนังสือ</h6>
                        <a href="{{ route('documents.routing_slip', $document->uuid ?? $document->id) }}" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3">
                            <i class="fas fa-external-link-alt me-1"></i> เปิดดูเต็มจอ
                        </a>
                    </div>
                    <div class="card-body p-0 bg-secondary">
                        <iframe src="{{ route('documents.routing_slip', $document->uuid ?? $document->id) }}?preview=1" width="100%" height="850px" style="border: none;"></iframe>
                    </div>
                </div>
                @endif

                {{-- Signature Track (กล่องแนวนอน 4 ช่อง) --}}
                <div class="page-break-inside-avoid no-print">
                    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
                        <span style="width:4px;height:18px;background:var(--accent-green);border-radius:4px;display:inline-block;"></span>
                        <span style="font-size:14px;font-weight:700;color:var(--primary-dark);">เส้นทางเดินเอกสารและการลงนามสั่งการ</span>
                    </div>
                    <div class="row g-3">
                        @foreach($sigSteps as $i => $s)
                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="rounded-3 p-3 text-center h-100 d-flex flex-column align-items-center justify-content-center shadow-sm print-sig-box"
                                 style="border:1px solid {{ $s['sig'] ? '#bbf7d0' : '#e2e8f0' }}; border-top:3px solid {{ $s['sig'] ? 'var(--accent-green)' : '#cbd5e1' }}; background:{{ $s['sig'] ? '#f0fdf4' : '#f8fafc' }}; min-height:180px; transition: 0.3s;">
                                <div class="mb-2 rounded-circle d-flex align-items-center justify-content-center fw-bold no-print" 
                                     style="width:28px;height:28px;background:{{ $s['sig'] ? 'var(--accent-green)' : '#e2e8f0' }};color:{{ $s['sig'] ? '#fff' : '#64748b' }};font-size:13px;">{{ $i+1 }}</div>
                                <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:8px;">{{ $s['label'] }}</div>
                                
                                @if($s['sig'])
                                    <img src="{{ getSigUrl($s['sig']) }}" style="max-height:55px;mix-blend-mode:multiply;margin-bottom:6px;" alt="ลายเซ็น">
                                    <div style="font-size:13px;font-weight:700;color:var(--text-primary);">{{ $s['name'] }}</div>
                                    <div style="font-size:12px;color:var(--text-muted);">{{ $s['pos'] }}</div>
                                    <div class="mt-2 rounded-pill px-2 fw-bold shadow-sm no-print" style="font-size:10px;background:#fff;border:1px solid #d1fae5;color:var(--accent-green);padding:3px 8px;"><i class="far fa-clock me-1"></i>{{ $s['at'] }}</div>
                                @else
                                    <i class="fas {{ $s['icon'] }} mb-2 mt-2 no-print" style="font-size:24px;color:#cbd5e1;"></i>
                                    <div class="no-print" style="font-size:13px;color:#94a3b8;font-weight:600;">รอการพิจารณา</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>

        {{-- Draft Sign & Edit (สำหรับคนสร้าง) --}}
        @if(($document->status === 'DRAFT' || $document->status === 'REJECTED') && $document->created_by === auth()->id())
        <div class="text-center mt-5 p-4 rounded-3 shadow-sm no-print" style="background:var(--primary-light);border:1px solid var(--primary-border);">
            <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm" style="width:55px;height:55px;background:var(--primary);font-size:22px;">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h5 class="fw-bold mb-1" style="color:var(--primary-dark);">ขั้นตอนสุดท้าย: ยืนยันการส่งเรื่อง</h5>
            <p class="mb-4" style="font-size:14px;color:var(--primary);">กรอกรหัส PIN 6 หลักเพื่อส่งเอกสารรับเข้าสู่ระบบ</p>
            <div class="d-flex justify-content-center align-items-center flex-wrap gap-3">
                <a href="{{ route('documents.edit', $document->uuid ?? $document->id) }}" class="btn rounded-pill fw-bold px-4 shadow-sm" style="background:#fef08a; border:2px solid #fde047; color:#854d0e; padding:10px 22px; font-size:14px;">
                    <i class="fas fa-edit me-2"></i>แก้ไขข้อมูล
                </a>
                <form action="{{ route('documents.destroy', $document->uuid ?? $document->id) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn rounded-pill fw-bold px-4 bg-white" style="border:2px solid #fca5a5;color:#dc2626;padding:10px 22px;font-size:14px;"
                            onclick="return confirm('ต้องการลบเอกสารนี้?\n(ข้อมูลและไฟล์แนบจะถูกลบถาวร)')">
                        <i class="fas fa-trash-alt me-2"></i>ลบทิ้ง
                    </button>
                </form>
                <form action="{{ route('documents.sign', $document->uuid ?? $document->id) }}" method="POST" class="d-flex align-items-center gap-2 flex-wrap ms-md-3">
                    @csrf
                    <input type="password" name="pin" maxlength="6" placeholder="• • • • • •" required autocomplete="off"
                           style="border:2px solid var(--primary-border);border-radius:10px;padding:10px 16px;font-size:18px;letter-spacing:10px;text-align:center;width:160px;outline:none;background:#fff;"
                           onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--primary-border)'">
                    <button type="submit" class="btn rounded-pill fw-bold text-white px-4 shadow-sm" style="background:var(--primary);border:none;padding:12px 28px;font-size:15px;white-space:nowrap;">
                        <i class="fas fa-paper-plane me-2"></i>ส่งเรื่องทันที
                    </button>
                </form>
            </div>
        </div>
        @endif

    </div>
</div>

<style>
    @font-face {
        font-family: 'MySarabun';
        src: url('{{ asset("fonts/THSarabunIT๙.ttf") }}') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    @font-face {
        font-family: 'MySarabun';
        src: url('{{ asset("fonts/THSarabunIT๙_Bold.ttf") }}') format('truetype');
        font-weight: bold;
        font-style: normal;
    }

    @media print {
        @page {
            size: A4;
            margin: 15mm !important;
        }
        body { background: white !important; -webkit-print-color-adjust: exact; }
        .no-print, nav, aside, .navbar, footer { display: none !important; }
        .print-container { display: none !important; }
    }
</style>
@endsection

@section('scripts')
<script>
    function checkReason() {
        const reasonInput = document.getElementById('comment');
        if (reasonInput && reasonInput.value.trim() === '') {
            reasonInput.style.borderColor = '#ef4444';
            reasonInput.style.backgroundColor = '#fef2f2';
            const reasonAst = document.getElementById('reason_asterisk');
            if(reasonAst) reasonAst.style.display = 'block';
            
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาระบุเหตุผล',
                text: 'จำเป็นต้องระบุเหตุผลในช่องความเห็น เมื่อต้องการตีกลับเอกสาร',
                confirmButtonColor: '#dc2626'
            }).then(() => { reasonInput.focus(); });
            return false;
        }
        return true;
    }

    document.getElementById('comment')?.addEventListener('input', function() {
        this.style.borderColor = '#dde3ea';
        this.style.backgroundColor = '#ffffff';
        const reasonAst = document.getElementById('reason_asterisk');
        if(reasonAst) reasonAst.style.display = 'none';
    });

    document.addEventListener('DOMContentLoaded', function() {
        @if(session('error_signature'))
            Swal.fire({ icon: 'warning', title: 'ยังไม่พร้อมใช้งาน', text: '{{ session("error_signature") }}', confirmButtonText: 'ไปหน้าตั้งค่าโปรไฟล์', confirmButtonColor: '#0284c7' }).then((result) => { if (result.isConfirmed) window.location.href = "{{ route('profile.index') }}"; });
        @endif
        @if(session('error_pin')) Swal.fire({ icon: 'error', title: 'รหัสไม่ถูกต้อง', text: '{{ session("error_pin") }}', confirmButtonText: 'ลองอีกครั้ง', confirmButtonColor: '#dc2626' }); @endif
        @if(session('error')) Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: '{{ session("error") }}', confirmButtonText: 'ตกลง', confirmButtonColor: '#dc2626' }); @endif
        @if(session('success')) Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '{{ session("success") }}', confirmButtonText: 'ตกลง', confirmButtonColor: '#10b981', timer: 2500 }); @endif
    });

    function confirmAcknowledge(btn) {
        Swal.fire({
            icon: 'question',
            title: 'ยืนยันการรับทราบคำสั่ง?',
            html: '<span style="font-size:14px;color:#475569;">ระบบจะบันทึก<strong>เวลาและชื่อ</strong>ของคุณไว้เป็นหลักฐาน</span>',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check-circle me-1"></i> ยืนยัน',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#0284c7',
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
            focusConfirm: false,
        }).then((result) => {
            if (result.isConfirmed) {
                btn.closest('form').submit();
            }
        });
    }
</script>
@endsection