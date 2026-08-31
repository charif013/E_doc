@extends('layouts.app')
@section('title', 'สมุดคุมเลขสารบรรณ')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0" style="color: var(--primary-dark);">
                <i class="fas fa-book-open text-primary me-2"></i>สมุดคุมเลขสารบรรณ
            </h4>
            <small class="text-muted">ตรวจสอบสถานะการออกเลข จองเลข และดูหมายเลขที่ยังว่าง</small>
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3 shadow-sm bg-white fw-bold">← แดชบอร์ด</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success shadow-sm rounded-3"><i class="fas fa-check-circle me-2"></i> {{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <form action="{{ route('documents.number_ledger') }}" method="GET" class="d-flex gap-2 flex-wrap">
                <select name="type" class="form-select form-select-sm shadow-sm fw-bold" onchange="this.form.submit()" style="width: 180px;">
                    <option value="outgoing" {{ $type == 'outgoing' ? 'selected' : '' }}>📤 หนังสือส่งออก</option>
                    <option value="incoming" {{ $type == 'incoming' ? 'selected' : '' }}>📥 หนังสือรับเข้า</option>
                    <option value="internal" {{ $type == 'internal' ? 'selected' : '' }}>📝 บันทึกข้อความ</option>
                    <option value="leave" {{ $type == 'leave' ? 'selected' : '' }}>🏖️ ทะเบียนใบลา</option>
                </select>

                {{-- 🌟 ถ้าเป็นบันทึกข้อความ ให้โชว์ตัวเลือกกอง --}}
                @if($type === 'internal')
                <select name="department" class="form-select form-select-sm shadow-sm text-primary fw-bold" onchange="this.form.submit()" style="width: 200px;">
                    @foreach($departments as $d)
                        <option value="{{ $d }}" {{ $dept == $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
                @endif

                <select name="year" class="form-select form-select-sm shadow-sm fw-bold" onchange="this.form.submit()" style="width: 150px;">
                    @for($y = date('Y') + 1; $y >= 2024; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>ปีงบประมาณ {{ $y + 543 }}</option>
                    @endfor
                </select>
            </form>
            <span class="badge bg-primary rounded-pill px-3 py-2 shadow-sm">เลขล่าสุดที่ออก: {{ $maxNumber }}</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="bg-light text-secondary small" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th width="10%" class="py-3">ลำดับ (รันนิ่ง)</th>
                            <th width="15%" class="py-3">สถานะ</th>
                            <th width="20%" class="py-3">เลขที่หนังสือ</th>
                            <th width="35%" class="text-start py-3">เรื่อง / รายละเอียด</th>
                            <th width="20%" class="py-3">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $reservePrefix = '';
                            $thDigits = ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'];
                            if($type === 'incoming') {
                                $reservePrefix = 'ยล 77301/';
                            } elseif(in_array($type, ['outgoing', 'internal'])) {
                                $reservePrefix = 'ยล 77301/';
                            }
                        @endphp

                        @foreach($ledger as $num => $data)
                        @php
                            $status = $data['status'];
                            $doc = $data['doc'];

                            $thNum = ($type === 'outgoing') ? str_replace(range(0,9), $thDigits, $num) : $num;
                            $formattedReserveNum = $reservePrefix . $thNum;

                            $bgRow = '#ffffff';
                            if ($status === 'ว่าง') $bgRow = '#f8fafc'; 
                            elseif (in_array($status, ['จองเลขมือ', 'จองรออนุมัติ'])) $bgRow = '#fffbeb'; 
                            elseif ($status === 'ยกเลิก') $bgRow = '#fef2f2'; 
                        @endphp

                        <tr style="background: {{ $bgRow }}">
                            <td class="fw-bold fs-5" style="color: {{ $status === 'ว่าง' ? '#cbd5e1' : 'var(--primary)' }}">{{ $num }}</td>
                            <td>
                                @if($status === 'ว่าง')
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">ยังไม่ออกเลข</span>
                                @elseif($status === 'จองรออนุมัติ')
                                    <span class="badge bg-warning text-dark rounded-pill px-3 shadow-sm"><i class="fas fa-hourglass-half me-1"></i> จอง (รออนุมัติ)</span>
                                @elseif($status === 'จองเลขมือ')
                                    <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-3"><i class="fas fa-lock me-1"></i> ธุรการจองล็อกไว้</span>
                                @elseif($status === 'ยกเลิก')
                                    <span class="badge bg-danger text-white rounded-pill px-3 shadow-sm"><i class="fas fa-ban me-1"></i> ยกเลิก/เลขเสีย</span>
                                @else
                                    <span class="badge bg-success text-white rounded-pill px-3 shadow-sm"><i class="fas fa-check-circle me-1"></i> อนุมัติแล้ว</span>
                                @endif
                            </td>
                            <td class="fw-bold text-dark">
                                {{ $type === 'leave'
                                    ? ($doc->leave_number ?? '-')
                                    : ($type === 'incoming' ? ($doc->formatted_receive_number ?? '-') : ($doc->formatted_doc_number ?? '-')) }}
                            </td>
                            <td class="text-start">
                                @if($doc && $type === 'leave')
                                    <a href="{{ route('leaves.show', $doc->id) }}" class="text-decoration-none text-success-emphasis fw-bold">
                                        {{ $doc->leave_type }} — {{ $doc->user->name ?? 'ไม่ทราบชื่อ' }}
                                    </a>
                                    <div class="small text-muted mt-1">
                                        {{ \Carbon\Carbon::parse($doc->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($doc->end_date)->format('d/m/Y') }}
                                        · ลงเลขโดย {{ $doc->numberedBy->name ?? 'ระบบ' }}
                                    </div>
                                @elseif($doc)
                                    @if($status === 'จองรออนุมัติ' || $status === 'จองเลขมือ')
                                        {{-- 🌟 ลิงก์ UUID --}}
                                        <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="text-warning-emphasis fw-bold text-decoration-none">{{ $doc->title }}</a><br>
                                        <div class="small mt-1">
                                            <span class="text-muted"><i class="fas fa-user-edit"></i> จองโดย: {{ $doc->creator->name ?? 'ระบบ' }}</span> 
                                            <span class="text-primary fw-bold ms-1">({{ $doc->creator->department ?? '-' }})</span>
                                        </div>
                                    @elseif($status === 'ยกเลิก')
                                        <span class="text-danger fw-bold text-decoration-line-through">{{ $doc->title }}</span><br>
                                        <small class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> เหตุผล: {{ $doc->reject_reason ?? 'ยกเลิกการออกเลข/ไม่มีการระบุเหตุผล' }}</small>
                                    @else
                                        {{-- 🌟 ลิงก์ UUID --}}
                                        <a href="{{ route('documents.show', $doc->uuid ?? $doc->id) }}" class="text-decoration-none text-success-emphasis fw-bold">{{ $doc->title }}</a>
                                        <div class="small text-muted mt-1">{{ \Carbon\Carbon::parse($doc->created_at)->addYears(543)->format('d M Y H:i') }} น.</div>
                                    @endif
                                @else
                                    <span class="text-muted fst-italic">พื้นที่ว่างสามารถใช้งานได้ (ธุรการกดปุ่มจองได้ทันที)</span>
                                @endif
                            </td>
                            <td>
                                @if($status === 'ว่าง' && $type !== 'leave' && auth()->user()->hasAnyRole(['super-admin', 'palad', 'saraban']))
                                    <form action="{{ route('documents.reserve_number_slot') }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $type }}">
                                        <input type="hidden" name="running_number" value="{{ $num }}">
                                        <input type="hidden" name="doc_number" value="{{ $formattedReserveNum }}">
                                        <button type="submit" class="btn btn-outline-warning btn-sm fw-bold rounded-pill shadow-sm" onclick="return confirm('ยืนยันการจองเลขที่: {{ $formattedReserveNum }} ?')">
                                            <i class="fas fa-lock"></i> จองเลขนี้
                                        </button>
                                    </form>
                                @elseif($status === 'ว่าง')
                                    <button class="btn btn-light btn-sm text-muted rounded-pill" disabled>ออกเลขจากหน้าใบลา</button>
                                @else
                                    <button class="btn btn-light btn-sm text-muted rounded-pill shadow-none" disabled>ถูกใช้งานแล้ว</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
