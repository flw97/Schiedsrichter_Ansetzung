<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['id'])]
#[Hidden(['path'])]
class RulebookVersion extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['replaced_at' => 'datetime'];
    }
}
