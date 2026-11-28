<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'active', 'referee_id'])]
#[Hidden(['password', 'remember_token', 'email_verified_at'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(Referee::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /** Macht ausstehende Zugangs-Tokens ungültig und löscht die Refresh-Tokens. */
    public function revokeSessions(): void
    {
        $this->increment('token_version');
        $this->refreshTokens()->delete();
    }

    /** Legt bei Bedarf ein leeres Schiedsrichter-Profil an, damit Lizenz und Standort zum Konto gehören. */
    public function ensureReferee(): Referee
    {
        if ($this->referee) {
            return $this->referee;
        }
        $referee = Referee::create([
            'name' => $this->name,
            'email' => $this->email,
            'available' => true,
        ]);
        $this->update(['referee_id' => $referee->id]);
        $this->setRelation('referee', $referee);

        return $referee;
    }
}
