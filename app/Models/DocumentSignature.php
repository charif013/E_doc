<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'is_approved',
        'comment',
        'signature_path',
    ];

    // ความสัมพันธ์: ลายเซ็นนี้เป็นของเอกสารไหน
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    // ความสัมพันธ์: ลายเซ็นนี้เป็นของ User คนไหน
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}