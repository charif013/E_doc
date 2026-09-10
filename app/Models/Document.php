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
        'current_step',
        'created_by',
        'attachment_path', 
        'external_url',
        'external_attachment_path',
        'external_original_name',
        'external_mime_type',
        'external_file_size',
        'external_sha256',
        'external_downloaded_at',
        'external_download_error',
        
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
        'assigned_to', 'assigned_user_id', 'delegated_by', 'assignment_status', 'assigned_at',
        
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

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->withTrashed();
    }

    public function delegator()
    {
        return $this->belongsTo(User::class, 'delegated_by')->withTrashed();
    }

    protected $casts = [
        'assigned_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'external_downloaded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // 🌟 ความสัมพันธ์: 1 เอกสาร มีได้หลายเส้นทาง (คิวพิจารณา)
    public function routes()
    {
        return $this->hasMany(DocumentRoute::class, 'document_id')->orderBy('step_order', 'asc');
    }

    /** ระบุว่าเอกสารกำลังรอลายเซ็นจากผู้พิจารณาคนสุดท้ายหรือไม่ */
    public function isAtFinalApprovalStep(): bool
    {
        $status = strtoupper((string) $this->status);
        if (in_array($status, ['DRAFT', 'APPROVED', 'COMPLETED', 'ARCHIVED', 'REJECTED', 'CANCELED', 'WAITING_NUMBERING'], true)) {
            return false;
        }

        $routes = $this->relationLoaded('routes') ? $this->routes : $this->routes()->get();
        $currentStep = (int) $this->current_step;
        $currentRoute = $routes->firstWhere('step_order', $currentStep);

        if ($currentStep > 1 && $currentRoute && (string) $currentRoute->status === 'pending') {
            return $currentStep === (int) $routes->max('step_order');
        }

        if ($this->doc_type === 'outgoing') {
            return match (true) {
                str_contains((string) $this->signer_name, 'นายก') => $status === 'WAITING_NAYOK',
                str_contains((string) $this->signer_name, 'ปลัด') => $status === 'WAITING_PALAD',
                default => $status === 'WAITING_SUPERVISOR',
            };
        }

        return $status === 'WAITING_NAYOK';
    }

    /**
     * Find a document by its public UUID or by a legacy numeric ID.
     *
     * Do not combine these columns with OR: MySQL can coerce a UUID beginning
     * with a number (for example "2abc...") to that numeric ID and return a
     * different document.
     */
    public function scopeWhereIdentifier($query, $identifier)
    {
        $identifier = (string) $identifier;

        return ctype_digit($identifier)
            ? $query->whereKey((int) $identifier)
            : $query->where('uuid', $identifier);
    }

    /** เลขทะเบียนมาตรฐานสำหรับหนังสือภายในและหนังสือส่งออก */
    public function getFormattedDocNumberAttribute(): ?string
    {
        if (in_array($this->doc_type, ['internal', 'outgoing'], true) && $this->running_number) {
            return 'ยล 77301/' . $this->running_number;
        }

        return $this->doc_number;
    }

    /** เลขทะเบียนรับมาตรฐานสำหรับหนังสือเข้า */
    public function getFormattedReceiveNumberAttribute(): ?string
    {
        if ($this->doc_type === 'incoming' && $this->running_number) {
            return 'ยล 77301/' . $this->running_number;
        }

        return $this->receive_number;
    }
    
}
