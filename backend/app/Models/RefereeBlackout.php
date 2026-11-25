<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class RefereeBlackout extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['from' => 'date:Y-m-d', 'to' => 'date:Y-m-d'];
    }
}
