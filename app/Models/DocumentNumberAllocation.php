<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentNumberAllocation extends Model
{
    protected $fillable = [
        'number_type', 'scope', 'fiscal_year', 'running_number',
        'formatted_number', 'document_id', 'leave_request_id', 'allocated_by',
    ];
}
