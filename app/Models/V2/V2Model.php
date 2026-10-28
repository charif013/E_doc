<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

abstract class V2Model extends Model
{
    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return (string) config('edoc.v2.connection', parent::getConnectionName());
    }
}
