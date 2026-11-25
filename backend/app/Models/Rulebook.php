<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Rulebook extends Model
{
    protected function casts(): array
    {
        return ['mandatory' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RulebookVersion::class)->orderBy('replaced_at');
    }
}
