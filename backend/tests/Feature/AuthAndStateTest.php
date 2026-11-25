<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

class AuthAndStateTest extends ApiTestCase
{
    public function test_login_returns_token_and_rejects_wrong_password_and_inactive_accounts(): void
    {
        $this->postJson('/api/login', ['username' => 'S.Weber', 'password' => DatabaseSeeder::PASSWORD])->assertOk()->assertJsonStructure(['token', 'pushes']);
        $this->postJson('/api/login', ['username' => 's.weber', 'password' => 'falsch'])->assertStatus(422);

        User::firstWhere('username', 't.lang')->update(['active' => false]);
        $this->postJson('/api/login', ['username' => 't.lang', 'password' => DatabaseSeeder::PASSWORD])->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['text' => 'Thomas Lang (Lesend): Anmeldung abgelehnt (Konto deaktiviert)']);
    }

    public function test_token_expires_after_inactivity(): void
    {
        $token = $this->postJson('/api/login', ['username' => 's.weber', 'password' => DatabaseSeeder::PASSWORD])->json('token');
        $this->withToken($token)->getJson('/api/state')->assertOk();

        $this->travel(16)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/state')->assertUnauthorized();
    }

    public function test_state_is_filtered_by_role(): void
    {
        $this->as('k.rehberg');
        $state = $this->getJson('/api/state')->assertOk()->json();
        $this->assertArrayNotHasKey('nuliga_licenses', $state);
        $this->assertArrayNotHasKey('users', $state);
        $me = collect($state['referees'])->firstWhere('name', 'Katja Rehberg');
        $other = collect($state['referees'])->firstWhere('name', 'Jonas Krieger');
        $this->assertSame('katja.rehberg@web.de', $me['email']);
        $this->assertSame(['id', 'name', 'license', 'club'], array_keys($other));
        $this->assertTrue(collect($state['responses'])->every(fn ($r) => $r['referee_id'] === $me['id']));

        $this->as('s.weber');
        $state = $this->getJson('/api/state')->json();
        $this->assertSame('j•••@gmx.de', collect($state['referees'])->firstWhere('name', 'Jonas Krieger')['email']);
        $this->assertArrayHasKey('nuliga_licenses', $state);

        $this->as('m.hartmann');
        $this->assertArrayHasKey('permission_matrix', $this->getJson('/api/state')->json());
    }

    public function test_denied_actions_are_rejected_and_logged(): void
    {
        $this->as('t.lang');
        $this->postJson('/api/nuliga/sync')->assertForbidden();
        $this->assertTrue(AuditLog::where('text', 'like', '%Aktion abgelehnt — Berechtigung fehlt (ansetzen)%')->exists());
    }

    public function test_contact_reveal_is_logged(): void
    {
        $this->as('s.weber');
        $this->getJson('/api/referees/contacts')->assertOk()->assertJsonFragment(['email' => 'j.krieger@gmx.de']);
        $this->assertDatabaseHas('audit_logs', ['text' => 'Sabine Weber (Admin): Kontaktdaten in der Schiedsrichter-Liste angezeigt']);
    }

    public function test_last_active_master_cannot_be_demoted(): void
    {
        $master = $this->as('m.hartmann');
        $this->putJson("/api/users/{$master->id}", ['role' => 'admin'])->assertStatus(422);
        $this->postJson('/api/users', ['name' => 'Neu', 'username' => 'Neu.Konto', 'password' => 'geheim123', 'role' => 'master'])->assertOk();
        $this->putJson("/api/users/{$master->id}", ['role' => 'admin'])->assertOk();
        $this->assertDatabaseHas('users', ['username' => 'neu.konto', 'role' => 'master']);
    }
}
