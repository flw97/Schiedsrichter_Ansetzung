<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\AuditLog;
use App\Models\AvailabilityResponse;
use App\Models\Game;
use App\Models\Observation;
use App\Models\PushMessage;
use App\Models\Referee;
use App\Models\Rulebook;
use App\Models\Setting;
use App\Models\User;
use App\Services\NuLigaSync;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gesamtzustand für die SPA, gefiltert nach den Rechten der angemeldeten Person.
 */
class StateController extends Controller
{
    public function show(Request $request, NuLigaSync $nuliga): JsonResponse
    {
        $user = $request->user();
        $can = fn (string $p) => Permissions::allows($user->role, $p);
        $staff = $can('einsehen');
        $meId = $user->referee_id;

        $state = [
            'me' => [...$user->only(['id', 'name', 'username', 'email', 'role', 'referee_id']), 'permissions' => Permissions::of($user->role)],
            'referees' => Referee::with(['blackouts', 'proofs', 'user:id,referee_id'])->orderBy('name')->get()
                ->map(fn (Referee $r) => $this->referee($r, $staff, $r->id === $meId)),
            'games' => Game::with('referees')->orderBy('date')->orderBy('time')->get()->map->toApi(),
            'responses' => AvailabilityResponse::when(! $staff, fn ($q) => $q->where('referee_id', $meId))
                ->get(['game_id', 'referee_id', 'answer', 'comment', 'updated_at']),
            'settings' => [
                'abfrage_from' => Setting::get('abfrage_from'),
                'abfrage_to' => Setting::get('abfrage_to'),
                'nuliga_last_sync' => Setting::get('nuliga_last_sync'),
            ],
            'pushes' => PushMessage::where('user_id', $user->id)->latest('id')->limit(30)->get(['id', 'text', 'created_at']),
            'rulebooks' => Rulebook::with('versions')->orderBy('title')->get()
                ->map(fn (Rulebook $b) => [...$b->only(['id', 'title', 'category', 'version', 'valid_from', 'mandatory', 'filename', 'size', 'updated_by', 'updated_at']), 'versions' => $can('regel_verwalten') ? $b->versions : []]),
            'rulebook_reads' => DB::table('rulebook_reads')->when(! $can('regel_verwalten'), fn ($q) => $q->where('user_id', $user->id))
                ->get(['rulebook_id', 'user_id', 'version']),
            'accommodations' => Accommodation::with('reviews:id,accommodation_id,user_id,author,stars,text,updated_at')->get(),
        ];
        if ($can('personen')) {
            $state['nuliga_licenses'] = $nuliga->licenses();
            $state['observations'] = Observation::orderBy('date')->get();
        }
        if ($can('rollen')) {
            $state['users'] = User::orderBy('name')->get(['id', 'name', 'username', 'email', 'role', 'active', 'referee_id']);
            $state['audit'] = AuditLog::latest('id')->limit(100)->get(['id', 'kind', 'text', 'created_at']);
            $state['permission_matrix'] = collect(Permissions::MATRIX)->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'roles' => $p[1]])->values();
        }

        return response()->json($state);
    }

    /** Neue Push-Mitteilungen der angemeldeten Person. Die SPA fragt das einmal pro Sekunde ab. */
    public function notifications(Request $request): JsonResponse
    {
        $after = max(0, $request->integer('after'));

        return response()->json([
            'notifications' => PushMessage::query()
                ->where('user_id', $request->user()->id)
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'text', 'created_at']),
        ]);
    }

    /** Kontaktdaten sind maskiert (außer im eigenen Profil); Personen ohne Einsichtsrecht sehen nur Name, Lizenz und Verein. */
    private function referee(Referee $r, bool $staff, bool $own): array
    {
        if (! $staff && ! $own) {
            return $r->only(['id', 'name', 'license', 'club']);
        }
        $data = [...$r->toArray(), 'user_id' => $r->user?->id];
        unset($data['user']);
        if (! $own) {
            $data['email'] = self::maskMail($r->email);
            $data['phone'] = self::maskPhone($r->phone);
        }

        return $data;
    }

    public static function maskPhone(?string $p): string
    {
        $t = (string) $p;

        return mb_strlen($t) <= 6 ? '•••' : mb_substr($t, 0, 4).str_repeat('•', max(3, mb_strlen($t) - 6)).mb_substr($t, -2);
    }

    public static function maskMail(?string $e): string
    {
        $t = (string) $e;
        $at = mb_strpos($t, '@');

        return $at === false || $at < 1 ? '•••' : mb_substr($t, 0, 1).'•••'.mb_substr($t, $at);
    }
}
