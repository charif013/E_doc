<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends V2Model
{
    protected $casts = ['is_active' => 'boolean'];

    public function instances(): HasMany { return $this->hasMany(WorkflowInstance::class); }
}
