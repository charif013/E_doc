{{-- View: แม่แบบ PDF สำหรับหนังสือส่งออก --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: normal;
            src: url('{{ public_path('fonts/THSarabunNew.ttf') }}') format('truetype');
        }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: bold;
            src: url('{{ public_path('fonts/THSarabunNew_Bold.ttf') }}') format('truetype');
        }
        @page { margin: 2cm 2cm 2cm 3cm; }
        body {
            color: #000;
            font-family: 'THSarabunNew', sans-serif;
            font-size: 16pt;
            line-height: 1.05;
            margin: 0;
        }
        .header { height: 2.65cm; text-align: center; }
        .garuda { width: 2.7cm; }
        .speed { color: #c00000; font-size: 16pt; font-weight: bold; min-height: 0.45cm; }
        table.topline { border-collapse: collapse; width: 100%; margin-bottom: 0.15cm; }
        table.topline td { width: 50%; vertical-align: top; }
        .doc-number { padding-top: 0.1cm; }
        .address { padding-left: 1cm; white-space: pre-line; }
        table.meta { border-collapse: collapse; width: 100%; }
        table.meta td { padding: 0 0 0.12cm; vertical-align: top; }
        table.meta td.label { font-weight: bold; width: 3.2cm; white-space: nowrap; }
        .date { margin: 0.15cm 0 0.35cm; text-align: center; }
        .body { margin-top: 0.2cm; text-align: justify; }
        .body p { margin: 0 0 0.15cm; text-indent: 2.5cm; }
        .closing { margin: 0.35cm 0 0.15cm 9cm; text-align: center; width: 6cm; page-break-inside: avoid; }
        .signature-space { height: 1.05cm; margin-left: 9cm; text-align: center; width: 6cm; }
        .signature-image { display: inline-block; max-height: 1cm; max-width: 4.5cm; }
        .signer { margin-left: 8cm; text-align: center; width: 8cm; white-space: pre-line; page-break-inside: avoid; }
        .contact { margin-top: 0.55cm; white-space: pre-line; font-size: 15pt; page-break-inside: avoid; }
        .secret { color: #c00000; font-size: 15pt; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
@php
    $date = !empty($document['doc_date']) ? \Carbon\Carbon::parse($document['doc_date']) : now();
    $thaiMonths = [1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $thaiDate = $date->day.' '.$thaiMonths[$date->month].' '.($date->year + 543);
    $signerParts = array_map('trim', explode('—', (string) ($document['signer_name'] ?? ''), 2));
    $signerName = $signerParts[0] ?? '';
    $signerPosition = $signerParts[1] ?? '';
    $speed = ($document['doc_speed'] ?? 'ปกติ') !== 'ปกติ' ? $document['doc_speed'] : '';
    $secret = !in_array(($document['doc_secret'] ?? ''), ['', 'ปกติ', 'ไม่มีชั้นความลับ'], true) ? $document['doc_secret'] : '';
    $documentNumber = trim((string) ($document['doc_number'] ?? '')) ?: 'รอธุรการลงทะเบียนเลข';
    $garudaData = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/krut.png')));
    $wrapThai = static fn (string $text): string => preg_replace(
        '/(?<=[\x{0E00}-\x{0E7F}])(?=[\x{0E00}-\x{0E7F}])/u',
        '<wbr>',
        e($text)
    );
@endphp

<div class="header">
    <img class="garuda" src="{{ $garudaData }}" alt="ตราครุฑ">
</div>
<table class="topline">
    <tr>
        <td class="doc-number">
            <div class="speed">{{ $speed }}</div>
            <strong>ที่</strong>&nbsp;&nbsp;{{ $documentNumber }}
        </td>
        <td class="address">{!! $wrapThai((string) ($document['sender_address'] ?: config('edoc.organization.outgoing_address'))) !!}</td>
    </tr>
</table>
<div class="date">{{ $thaiDate }}</div>
<table class="meta">
    <tr><td class="label">เรื่อง</td><td>{!! $wrapThai((string) $document['title']) !!}</td></tr>
    <tr><td class="label">เรียน</td><td>{!! $wrapThai((string) $document['doc_to']) !!}</td></tr>
    @if(!empty($document['reference_doc']))
        <tr><td class="label">อ้างถึง</td><td>{!! $wrapThai((string) $document['reference_doc']) !!}</td></tr>
    @endif
    @if(!empty($document['attachment']))
        <tr><td class="label">สิ่งที่ส่งมาด้วย</td><td>{!! $wrapThai((string) $document['attachment']) !!}</td></tr>
    @endif
</table>

<div class="body">
    @foreach(preg_split('/\n\s*\n/', trim((string) $document['content'])) as $paragraph)
        <p>{!! $wrapThai($paragraph) !!}</p>
    @endforeach
</div>
<div class="closing">{!! $wrapThai((string) ($document['closing_text'] ?: 'ขอแสดงความนับถือ')) !!}</div>
<div class="signature-space">
    @if(!empty($signatureData))
        <img class="signature-image" src="{{ $signatureData }}" alt="ลายเซ็นผู้ลงนาม">
    @endif
</div>
<div class="signer">({!! $wrapThai($signerName) !!})
{!! $wrapThai($signerPosition) !!}</div>

@if(!empty($document['contact_line']))
    <div class="contact">{!! $wrapThai((string) $document['contact_line']) !!}</div>
@endif
@if($secret)
    <div class="secret">{{ $secret }}</div>
@endif
</body>
</html>
