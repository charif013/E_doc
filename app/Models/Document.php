<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'running_number',
        // 🌟 ข้อมูลพื้นฐานและสถานะ
        'doc_type', 
        'doc_number', 
        'doc_date', 
        'title', 
        'content', 
        'status', 
        'created_by',
        'attachment_path', 
        
        // 🌟 ข้อมูลหนังสือรับเข้า (Incoming)
        'receive_number', 
        'receive_date', 
        'doc_from', 
        'doc_type_category', 
        'doc_speed', 
        'doc_secret',

        // 🌟 ข้อมูลหนังสือส่งออก 
        'doc_to', 'signer_name', 'reference_doc', 'remark',

        // 🌟 การมอบหมายส่วนราชการ (สำหรับ ปลัด/นายก สั่งการ)
        'assigned_to',
        
        // 🌟  ฟิลด์ใหม่นี้สำหรับระบบรับทราบคำสั่ง
        'acknowledged_at',
        'acknowledged_by',

        // 🌟 ข้อมูลการพิจารณาและลายเซ็น (รวม Comment ของทุกระดับ)
        'supervisor_id', 'supervisor_signature', 'supervisor_approved_at', 'supervisor_comment',
        'palad_id', 'palad_signature', 'palad_approved_at', 'palad_comment',
        'nayok_id', 'nayok_signature', 'nayok_approved_at', 'nayok_comment',
        'reject_reason'
    ];
    

    // ความสัมพันธ์: ผู้เสนอเรื่อง/คนบันทึกข้อมูล
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed(); 
    }

    /**
     * ความสัมพันธ์: หัวหน้าส่วนราชการชั้นต้น (Supervisor)
     * 💡 ฟังก์ชันนี้เป็นแบบ Generic คือจะดึงข้อมูล User ตาม supervisor_id 
     * ไม่ว่าจะเป็น หัวหน้าสำนักปลัด, ผอ.กองช่าง หรือ ผอ.กองคลัง 
     * ถ้ารหัส ID ของเขาอยู่ในช่อง supervisor_id ระบบจะดึงชื่อและตำแหน่งของคนนั้นมาแสดงโดยอัตโนมัติ
     */
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id')->withTrashed();
    }

    // ความสัมพันธ์: ปลัด อบต.
    public function palad()
    {
        return $this->belongsTo(User::class, 'palad_id')->withTrashed();
    }

    // ความสัมพันธ์: นายก อบต.
    public function nayok()
    {
        return $this->belongsTo(User::class, 'nayok_id')->withTrashed();
    }

    //  ดึงข้อมูลคนที่กดรับทราบคำสั่ง
    public function acknowledger()
    {
        return $this->belongsTo(User::class, 'acknowledged_by')->withTrashed();
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
    
}