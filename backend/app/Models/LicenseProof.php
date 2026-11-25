<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
class LicenseProof extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
