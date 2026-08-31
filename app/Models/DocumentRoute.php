<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRoute extends Model
{
    use HasFactory;

    // 🌟 1. อนุญาตให้บันทึกข้อมูลลงฟิลด์เหล่านี้ได้
    protected $fillable = [
        'document_id',
        'user_id',
        'step_order',
        'status',
        'comment',
        'actioned_at',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
    ];

    // 🌟 2. ความสัมพันธ์: เส้นทางนี้เป็นของ "เอกสาร" อะไร?
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    // 🌟 3. ความสัมพันธ์: เส้นทางนี้ต้องส่งไปให้ "ใคร" (User)?
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
