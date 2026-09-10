<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;

abstract class V2Model extends Model
{
    protected $connection = 'mysql_v2';

    protected $guarded = [];
}
