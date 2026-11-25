<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded(['id'])]
class Referee extends Model
{
    protected function casts(): array
    {
        return [
            'license_valid_until' => 'date:Y-m-d',
            'license_synced_at' => 'date:Y-m-d',
            'license_reminded_for' => 'date:Y-m-d',
            'persp_national' => 'boolean',
            'persp_international' => 'boolean',
            'available' => 'boolean',
            'lat' => 'float',
            'lon' => 'float',
            'fairness' => 'float',
        ];
    }

    public function blackouts(): HasMany
    {
        return $this->hasMany(RefereeBlackout::class)->orderBy('from');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(LicenseProof::class)->orderBy('date');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** Lizenz-Erinnerung 1 Jahr vor Lizenzende, einmal je Lizenzzeitraum. */
    public function reminderDue(): bool
    {
        return $this->license_valid_until !== null
            && now()->gte($this->license_valid_until->copy()->subYear())
            && $this->license_valid_until->toDateString() !== $this->license_reminded_for?->toDateString();
    }
}
