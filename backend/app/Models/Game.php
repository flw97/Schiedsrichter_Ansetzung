<?php

namespace App\Models;

use App\Support\Licenses;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Guarded(['id'])]
class Game extends Model
{
    private const WEEKDAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    private const MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'pending_date' => 'date:Y-m-d',
            'imported' => 'boolean',
            'schedule_change' => 'array',
            'report' => 'array',
        ];
    }

    public function referees(): BelongsToMany
    {
        return $this->belongsToMany(Referee::class)->withPivot('kind', 'reconfirmed');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AvailabilityResponse::class);
    }

    public function crew(string ...$kinds): Collection
    {
        return $this->referees->filter(fn (Referee $r) => in_array($r->pivot->kind, $kinds, true))->values();
    }

    /** Alle angesetzten Personen: Schiedsrichter und Referee (Oberschiedsrichter). */
    public function assigned(): Collection
    {
        return $this->crew('confirmed', 'head');
    }

    public function isLeague(): bool
    {
        return Licenses::isLeague($this->type, $this->competition);
    }

    public function isPast(): bool
    {
        return $this->date->lt(today());
    }

    public static function label(CarbonInterface $date, ?string $time): string
    {
        return self::WEEKDAYS[$date->dayOfWeek].", {$date->day}. ".self::MONTHS[$date->month - 1].' · '.($time ?? 'ganztägig');
    }

    public function dateLabel(): string
    {
        return self::label($this->date, $this->time);
    }

    /** Besetzung festlegen: Schiedsrichter und ggf. Referee; Leiter bei Ligaspielen ist der Ranghöhere. */
    public function confirm(Collection $referees, ?Referee $head = null): void
    {
        $this->syncCrew(['confirmed' => $referees, 'head' => collect($head ? [$head] : [])]);
        $this->update([
            'status' => 'confirmed',
            'leader_id' => $this->isLeague() ? Licenses::leader($referees)?->id : null,
            'reopened_note' => null,
            'schedule_change' => null,
            'imported' => true,
        ]);
    }

    /** Auswahl als Entwurf/Vorschlag ablegen (ohne Benachrichtigung). */
    public function plan(Collection $referees, ?Referee $head, string $status): void
    {
        $this->syncCrew(['planned' => $referees, 'planned_head' => collect($head ? [$head] : [])]);
        $this->update(['status' => $status, 'imported' => true]);
    }

    /** Ansetzung zurücknehmen: Spiel ist wieder offen und erscheint erneut in der Abfrage. */
    public function reopen(string $note): void
    {
        $this->responses()->whereIn('referee_id', $this->assigned()->pluck('id'))->delete();
        $this->syncCrew([]);
        $this->update(['status' => 'open', 'leader_id' => null, 'reopened_note' => $note, 'schedule_change' => null]);
    }

    /** @param array<string, Collection<int, Referee>> $crews */
    private function syncCrew(array $crews): void
    {
        $this->referees()->detach();
        foreach ($crews as $kind => $refs) {
            $refs->each(fn (Referee $r) => $this->referees()->attach($r->id, ['kind' => $kind]));
        }
        $this->load('referees');
    }

    public function toApi(): array
    {
        $ids = fn (string $kind) => $this->crew($kind)->pluck('id')->all();

        return [
            ...$this->only(['id', 'nuliga_id', 'title', 'type', 'time', 'venue', 'competition', 'needed_count', 'status', 'home_team', 'away_team', 'home_state', 'away_state', 'imported', 'nuliga_change', 'pending_time', 'pending_venue', 'leader_id', 'requested_by', 'reopened_note', 'schedule_change', 'report']),
            'date' => $this->date->toDateString(),
            'pending_date' => $this->pending_date?->toDateString(),
            'confirmed_ids' => $ids('confirmed'),
            'planned_ids' => $ids('planned'),
            'head_referee_id' => $ids('head')[0] ?? null,
            'planned_head_referee_id' => $ids('planned_head')[0] ?? null,
            'reconfirmed_ids' => $this->assigned()->filter(fn ($r) => $r->pivot->reconfirmed)->pluck('id')->values()->all(),
        ];
    }
}
