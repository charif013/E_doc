<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentAccessRequest extends Model
{
    protected $fillable = ['document_id', 'user_id', 'status'];

    public function user() {
        return $this->belongsTo(User::class);
    }
    public function document() {
        return $this->belongsTo(Document::class);
    }
}