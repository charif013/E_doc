<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends V2Model
{
    protected $casts = ['is_active' => 'boolean'];

    public function documents(): HasMany { return $this->hasMany(Document::class); }
}
