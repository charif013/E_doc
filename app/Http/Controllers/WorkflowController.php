<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\DocumentSignature;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WorkflowController extends Controller
{
    public function reviewDocument(Request $request, $documentId)
    {
        $request->validate([
            'is_approved' => 'required|boolean',
            'comment' => 'nullable|string',
        ]);

        $user = Auth::user();
        $document = Document::findOrFail($documentId);

        // 1. บันทึกการลงนามของปลัด
        DocumentSignature::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'is_approved' => $request->is_approved,
            'comment' => $request->comment,
        ]);

        // 2. กรณี "ตีกลับ"
        if (!$request->is_approved) {
            $document->update(['status' => 'REJECTED']);
            
            $this->sendLineNotify("❌ เอกสารเลขที่ {$document->doc_number} ถูกตีกลับโดยปลัด {$user->name}\nเหตุผล: " . ($request->comment ?? 'ไม่ระบุ'));

            return response()->json([
                'status' => 'success',
                'message' => 'บันทึกการตีกลับเอกสารเรียบร้อยแล้ว'
            ]);
        }

        // 3. กรณี "เห็นชอบ" -> เช็คว่าปลัดเซ็นครบ 3 คนหรือยัง
        $approvedCount = DocumentSignature::where('document_id', $document->id)
            ->where('is_approved', true)
            ->whereHas('user', function ($query) {
                $query->role('reviewer'); // เช็คว่าเป็นปลัด (reviewer) เท่านั้น
            })->count();

        if ($approvedCount >= 3) {
            // ครบ 3 คน ดันไปให้นายกฯ (approver)
            $document->update(['status' => 'WAITING_APPROVER']);
            $this->sendLineNotify("📄 มีเอกสารด่วนรอการอนุมัติจาก นายก อบต.\nเลขที่: {$document->doc_number}\nเรื่อง: {$document->title}");
        }

        return response()->json([
            'status' => 'success',
            'message' => 'บันทึกการพิจารณาเห็นชอบเรียบร้อย'
        ]);
    }

    // ฟังก์ชันส่ง LINE Notify
    private function sendLineNotify($message)
    {
        $token = env('LINE_NOTIFY_TOKEN'); // ดึง Token จากไฟล์ .env
        if ($token) {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->asForm()->post('https://notify-api.line.me/api/notify', [
                'message' => $message
            ]);
        }
    }

    public function index()
    {
        // ดึงเอกสารที่มีสถานะ "รอปลัดพิจารณา" และเรียงจากใหม่ไปเก่า
        $documents = Document::with('creator')
            ->where('status', 'WAITING_REVIEWER')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('documents.review', compact('documents'));
    }

    // --- โซนของ นายก อบต. ---

    // 1. แสดงรายการเอกสารที่รอนายกฯ อนุมัติ
    public function approverIndex()
    {
        // ดึงเฉพาะเอกสารที่ผ่านปลัดมาแล้ว (WAITING_APPROVER)
        $documents = Document::with('creator')
            ->where('status', 'WAITING_APPROVER')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('documents.approve', compact('documents'));
    }

    // 2. รับข้อมูลการกดปุ่ม "อนุมัติ" หรือ "ไม่อนุมัติ"
    public function finalApprove(Request $request, $documentId)
    {
        $request->validate([
            'is_approved' => 'required|boolean',
            'comment' => 'nullable|string',
        ]);

        $user = Auth::user();
        $document = Document::findOrFail($documentId);

        // บันทึกลายเซ็นนายกฯ
        DocumentSignature::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'is_approved' => $request->is_approved,
            'comment' => $request->comment,
        ]);

        // อัปเดตสถานะเอกสาร
        if ($request->is_approved) {
            $document->update(['status' => 'APPROVED']); // อนุมัติสำเร็จ
            $this->sendLineNotify("✅ เอกสารเลขที่ {$document->doc_number} ได้รับการอนุมัติเรียบร้อยแล้ว");
            $msg = 'บันทึกการอนุมัติเอกสารเรียบร้อย';
        } else {
            $document->update(['status' => 'REJECTED']); // ไม่อนุมัติ (ตีกลับ)
            $this->sendLineNotify("❌ เอกสารเลขที่ {$document->doc_number} ไม่อนุมัติโดยนายก อบต.\nเหตุผล: " . ($request->comment ?? 'ไม่ระบุ'));
            $msg = 'บันทึกการไม่อนุมัติเรียบร้อย';
        }

        return response()->json([
            'status' => 'success',
            'message' => $msg
        ]);
    }
}
