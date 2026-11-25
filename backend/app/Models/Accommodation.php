<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Accommodation extends Model
{
    protected function casts(): array
    {
        return ['lat' => 'float', 'lon' => 'float'];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AccommodationReview::class);
    }
}
