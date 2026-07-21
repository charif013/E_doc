<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบแนบท้ายการอนุมัติ</title>
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: normal;
            src: url("{{ public_path('fonts/THSarabunNew.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'THSarabunNew';
            font-style: normal;
            font-weight: bold;
            src: url("{{ public_path('fonts/THSarabunNew_Bold.ttf') }}") format('truetype');
        }

        body {
            font-family: 'THSarabunNew', sans-serif;
            padding: 30px 40px;
            color: #000;
            line-height: 1.1;
        }

        .approval-block { margin-bottom: 30px; }
        .opinion-title { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
        .opinion-text { font-size: 20px; margin-left: 20px; margin-bottom: 10px; color: #1e3a8a; }
        
        .signature-img { max-height: 45px; mix-blend-mode: multiply; }
        
        /* ตารางผลักไปด้านขวาแบบไม่ตกขอบ */
        .layout-table { width: 100%; border-collapse: collapse; border: none; }
        .layout-left { width: 45%; } 
        .layout-right { width: 55%; } 

        .sig-inner-table { width: 100%; border-collapse: collapse; }
        .sig-label { width: 20%; text-align: right; vertical-align: bottom; font-size: 18px; padding-bottom: 3px; padding-right: 5px;}
        .sig-line { width: 80%; text-align: center; border-bottom: 1px dotted #000; height: 45px; }
        .sig-text { text-align: center; font-size: 18px; padding-top: 5px; }
        .sig-date { text-align: center; font-size: 14px; color: #555; }
    </style>
</head>
<body>

    @php
        // 🌟 1. ดักจับฟังก์ชันแปลงเลขไทย
        if (!function_exists('toThaiNum')) {
            function toThaiNum($string) {
                if (!$string) return '';
                $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
                $thai = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
                return str_replace($arabic, $thai, $string);
            }
        }

        // 🌟 2. ดักจับ Path รูปภาพให้ DOMPDF หาไฟล์เจอแน่นอน 100%
        if (!function_exists('getPdfSigPath')) {
            function getPdfSigPath($path) {
                if (empty($path)) return '';
                if (str_starts_with($path, 'data:image')) return $path;
                // แปลง URL ให้เป็น Path ในเครื่อง Server เพื่อให้ PDF Gen ได้รูปไม่แตก
                $cleanPath = preg_replace('/^(public|storage)\//', '', ltrim($path, '/'));
                return storage_path('app/public/' . $cleanPath);
            }
        }
    @endphp

    {{-- 2. หัวหน้าสำนักปลัด / ผอ.กอง --}}
    @if($document->supervisor_signature)
    <div class="approval-block">
        <div class="opinion-title">ความเห็นหัวหน้าสำนักปลัด / ผอ.กอง</div>
        <div class="opinion-text">{{ toThaiNum($document->supervisor_comment ?: 'ทราบ / เพื่อโปรดพิจารณาอนุมัติ') }}</div>
        <table class="layout-table">
            <tr>
                <td class="layout-left"></td>
                <td class="layout-right">
                    <table class="sig-inner-table">
                        <tr>
                            <td class="sig-label">(ลงชื่อ)</td>
                            <td class="sig-line">
                                <img src="{{ getPdfSigPath($document->supervisor_signature) }}" class="signature-img">
                            </td>
                        </tr>
                        <tr><td></td><td class="sig-text">( {{ $document->supervisor->name ?? '-' }} )</td></tr>
                        <tr><td></td><td class="sig-text">{{ $document->supervisor->position ?? 'หัวหน้าสำนักปลัด' }}</td></tr>
                        <tr><td></td><td class="sig-date">{{ toThaiNum(\Carbon\Carbon::parse($document->supervisor_approved_at)->addYears(543)->locale('th')->translatedFormat('d M Y H:i น.')) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- 3. ปลัด อบต. --}}
    @if($document->palad_signature)
    <div class="approval-block">
        <div class="opinion-title">ความเห็นปลัดองค์การบริหารส่วนตำบลพร่อน</div>
        <div class="opinion-text">{{ toThaiNum($document->palad_comment ?: 'ทราบ / เพื่อโปรดพิจารณาอนุมัติ') }}</div>
        <table class="layout-table">
            <tr>
                <td class="layout-left"></td>
                <td class="layout-right">
                    <table class="sig-inner-table">
                        <tr>
                            <td class="sig-label">(ลงชื่อ)</td>
                            <td class="sig-line">
                                <img src="{{ getPdfSigPath($document->palad_signature) }}" class="signature-img">
                            </td>
                        </tr>
                        <tr><td></td><td class="sig-text">( {{ $document->palad->name ?? '-' }} )</td></tr>
                        <tr><td></td><td class="sig-text">{{ $document->palad->position ?? 'ปลัด อบต.' }}</td></tr>
                        <tr><td></td><td class="sig-date">{{ toThaiNum(\Carbon\Carbon::parse($document->palad_approved_at)->addYears(543)->locale('th')->translatedFormat('d M Y H:i น.')) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- 4. นายก อบต. --}}
    @if($document->nayok_signature)
    <div class="approval-block">
        <div class="opinion-title">คำสั่งนายกองค์การบริหารส่วนตำบลพร่อน</div>
        
        <table style="border-collapse: separate; border-spacing: 0; margin-left: 20px; margin-bottom: 10px;">
            <tr>
                <td style="width: 16px; height: 16px; border: 1px solid #000; text-align: center; vertical-align: middle; padding: 0; line-height: 14px;">
                    @if($document->nayok_signature)
                        <span style="font-family: 'DejaVu Sans', sans-serif; font-size: 14px; color: #000;">&#10003;</span>
                    @else
                        &nbsp;
                    @endif
                </td>
                <td style="font-size: 20px; color: #1e3a8a; padding-left: 10px; vertical-align: middle; border: none;">
                    {{ toThaiNum($document->nayok_comment ?: 'อนุมัติ / ทราบ') }}
                </td>
            </tr>
        </table>

        <table class="layout-table">
            <tr>
                <td class="layout-left"></td>
                <td class="layout-right">
                    <table class="sig-inner-table">
                        <tr>
                            <td class="sig-label">(ลงชื่อ)</td>
                            <td class="sig-line">
                                <img src="{{ getPdfSigPath($document->nayok_signature) }}" class="signature-img">
                            </td>
                        </tr>
                        <tr><td></td><td class="sig-text">( {{ $document->nayok->name ?? '-' }} )</td></tr>
                        <tr><td></td><td class="sig-text">{{ $document->nayok->position ?? 'นายก อบต.' }}</td></tr>
                        <tr><td></td><td class="sig-date">{{ toThaiNum(\Carbon\Carbon::parse($document->nayok_approved_at)->addYears(543)->locale('th')->translatedFormat('d M Y H:i น.')) }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @endif

</body>
</html>