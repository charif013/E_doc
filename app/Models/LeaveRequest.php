<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use HasFactory;

    // 🌟 1. อัปเดตให้รองรับคอลัมน์ของระบบ 5 ด่านครบถ้วน
    protected $fillable = [
        'user_id', 'leave_type', 'start_date', 'end_date', 'total_days',
        'reason', 'contact_info', 'status', 'reject_reason', 'workflow_status',

        // ด่าน 1: ผู้รับมอบงาน
        'delegate_id', 'delegate_status', 'delegate_decline_reason',
        'delegate_requested_at', 'delegate_responded_at', 'delegate_reminded_at', 'delegate_escalated_at',

        // ด่าน 2: ธุรการตรวจสอบ
        'inspector_id', 'inspector_status', 'inspector_signature', 'inspector_at',

        // ด่าน 3: หัวหน้า/ผอ.กอง (เปลี่ยนจาก supervisor เป็น head ตามที่เราสร้างตารางใหม่)
        'head_id', 'head_status', 'head_signature', 'head_at',
        'supervisor_id', 'supervisor_signature', 'supervisor_approved_at', // เก็บไว้เผื่อโค้ดเก่าเรียกใช้

        // ด่าน 4: ปลัด
        'palad_id', 'palad_status', 'palad_signature', 'palad_at',

        // ด่าน 5: นายก
        'nayok_id', 'nayok_status', 'nayok_signature', 'nayok_at',

        // ขั้นตอนสุดท้าย: ธุรการลงเลขใบลา
        'leave_number', 'running_number', 'numbered_by', 'numbered_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'delegate_requested_at' => 'datetime',
        'delegate_responded_at' => 'datetime',
        'delegate_reminded_at' => 'datetime',
        'delegate_escalated_at' => 'datetime',
        'numbered_at' => 'datetime',
    ];

    // ==========================================
    // 🌟 2. ความสัมพันธ์ (Relationships)
    // ==========================================

    // คนเขียนใบลา
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ด่าน 1: ผู้รับมอบงาน
    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    // ด่าน 2: ธุรการ
    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    // ด่าน 3: หัวหน้าสำนัก / ผอ.กอง
    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    // (เก็บอันเก่าไว้กัน error)
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    // ด่าน 4: ปลัด อบต.
    public function palad()
    {
        return $this->belongsTo(User::class, 'palad_id');
    }

    // ด่าน 5: นายก อบต.
    public function nayok()
    {
        return $this->belongsTo(User::class, 'nayok_id');
    }

    public function numberedBy()
    {
        return $this->belongsTo(User::class, 'numbered_by');
    }
}
