<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบเกษียนหนังสือรับเข้า - {{ $document->doc_number ?? 'DRAFT' }}</title>
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

        body {
            font-family: 'MySarabun', sans-serif;
            background: #525659;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .a4-paper {
            width: 210mm;
            min-height: 297mm;
            background: white;
            padding: 20mm 20mm 20mm 25mm; /* ขอบกระดาษราชการ */
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            box-sizing: border-box;
            color: #000;
        }

        h1 { text-align: center; font-size: 26pt; font-weight: bold; margin-bottom: 20px; }
        
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .header-table td { padding: 5px; font-size: 16pt; vertical-align: top; }
        
        .routing-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .routing-table th, .routing-table td { border: 1px solid #000; padding: 10px; vertical-align: top; }
        .routing-table th { font-size: 16pt; font-weight: bold; background-color: #f8f9fa; text-align: center; }
        .routing-table td { font-size: 16pt; }

        .signature-box { text-align: center; margin-top: 20px; }
        .signature-img { max-height: 40px; mix-blend-mode: multiply; margin-bottom: 5px; }

        .assigned-box { border: 2px solid #000; padding: 15px; margin-top: 30px; text-align: center; border-radius: 8px; }
        .assigned-box h3 { margin: 0; font-size: 20pt; font-weight: bold; }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #2563eb;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 50px;
            font-size: 16px;
            font-family: sans-serif;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }

        @media print {
            body { background: white; padding: 0; }
            .a4-paper { box-shadow: none; margin: 0; width: 100%; min-height: auto; padding: 15mm; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">🖨️ พิมพ์ใบเกษียน</button>

    <div class="a4-paper">
        
        <h1>ใบเกษียนหนังสือรับเข้า</h1>

        <table class="header-table">
            <tr>
                <td width="15%"><strong>เลขรับ:</strong></td>
                <td width="35%">{{ $document->receive_number ?? '-' }}</td>
                <td width="20%"><strong>วันที่รับ:</strong></td>
                <td width="30%">{{ $document->receive_date ? \Carbon\Carbon::parse($document->receive_date)->addYears(543)->format('d/m/Y') : '-' }}</td>
            </tr>
            <tr>
                <td><strong>ที่:</strong></td>
                <td>{{ $document->doc_number ?? '-' }}</td>
                <td><strong>ลงวันที่:</strong></td>
                <td>{{ $document->doc_date ? \Carbon\Carbon::parse($document->doc_date)->addYears(543)->format('d/m/Y') : '-' }}</td>
            </tr>
            <tr>
                <td><strong>จาก:</strong></td>
                <td colspan="3">{{ $document->doc_from ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>เรื่อง:</strong></td>
                <td colspan="3">{{ $document->title ?? '-' }}</td>
            </tr>
        </table>

        <div style="border-top: 1px solid #000; margin: 15px 0;"></div>

        <table class="routing-table">
            <tr>
                <th width="33%">ความเห็น หัวหน้าสำนักปลัด</th>
                <th width="33%">ความเห็น ปลัด อบต.</th>
                <th width="34%">คำสั่งการ นายก อบต.</th>
            </tr>
            <tr>
                <td style="min-height: 150px;">
                    <div>{{ $document->supervisor_comment ?? '-' }}</div>
                    @if($document->supervisor_signature)
                        <div class="signature-box">
                            <img src="{{ $document->supervisor_signature }}" class="signature-img"><br>
                            ( {{ $document->supervisor->name ?? '.........................................' }} )<br>
                            <span style="font-size: 14pt;">{{ $document->supervisor->position ?? 'หัวหน้าส่วนราชการ' }}</span><br>
                            <span style="font-size: 14pt;">{{ \Carbon\Carbon::parse($document->supervisor_approved_at)->addYears(543)->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </td>

                <td>
                    <div>{{ $document->palad_comment ?? '-' }}</div>
                    @if($document->palad_signature)
                        <div class="signature-box">
                            <img src="{{ $document->palad_signature }}" class="signature-img"><br>
                            ( {{ $document->palad->name ?? '.........................................' }} )<br>
                            <span style="font-size: 14pt;">{{ $document->palad->position ?? 'ปลัด อบต.' }}</span><br>
                            <span style="font-size: 14pt;">{{ \Carbon\Carbon::parse($document->palad_approved_at)->addYears(543)->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </td>

                <td>
                    <div>{{ $document->nayok_comment ?? '-' }}</div>
                    @if($document->nayok_signature)
                        <div class="signature-box">
                            <img src="{{ $document->nayok_signature }}" class="signature-img"><br>
                            ( {{ $document->nayok->name ?? '.........................................' }} )<br>
                            <span style="font-size: 14pt;">{{ $document->nayok->position ?? 'นายก อบต.' }}</span><br>
                            <span style="font-size: 14pt;">{{ \Carbon\Carbon::parse($document->nayok_approved_at)->addYears(543)->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                </td>
            </tr>
        </table>

        @if($document->assigned_to)
        <div class="assigned-box">
            <span style="font-size: 16pt;">ผู้บริหารมอบหมายให้ส่วนราชการดำเนินการ:</span>
            <h3>" {{ $document->assigned_to }} "</h3>
        </div>
        @endif

    </div>
    
    <script>
    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        const isPreview = urlParams.has('preview');

        if (!isPreview) {
            setTimeout(() => {
                window.print();
            }, 500); 
        }
    };
</script>
</body>
</html>