<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PushMessage;
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

    public function test_notifications_are_only_newer_messages_of_the_current_user(): void
    {
        $me = $this->as('k.rehberg');
        PushMessage::create(['user_id' => User::firstWhere('username', 's.weber')->id, 'text' => 'fremd']);
        $older = PushMessage::create(['user_id' => $me->id, 'text' => 'älter']);
        $newer = PushMessage::create(['user_id' => $me->id, 'text' => 'neu']);

        $this->getJson('/api/notifications?after='.$older->id)
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.id', $newer->id)
            ->assertJsonPath('notifications.0.text', 'neu');
        $this->getJson('/api/notifications?after='.$newer->id)->assertOk()->assertJsonCount(0, 'notifications');
        $this->getJson('/api/notifications')->assertOk()->assertJsonMissing(['text' => 'fremd']);
    }

    public function test_notifications_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
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
        $this->assertNull(User::firstWhere('username', 'neu.konto')->referee_id);
    }

    public function test_referee_account_starts_empty_and_saves_own_profile(): void
    {
        $this->as('m.hartmann');
        $this->postJson('/api/users', [
            'name' => 'Anna Beispiel', 'username' => 'a.beispiel', 'email' => 'anna@example.de',
            'password' => 'geheim123', 'role' => 'schiri',
        ])->assertOk();

        $user = User::firstWhere('username', 'a.beispiel');
        $this->assertNotNull($user->referee_id);
        $this->assertNull($user->referee->address);
        $this->assertNull($user->referee->license);
        $this->assertNull($user->referee->club);

        $this->as('a.beispiel');
        $this->putJson('/api/me/referee', [
            'name' => 'Anna Beispiel-Neu', 'club' => 'TSV Gomaringen',
            'address' => 'Hauptstraße 1, 72810 Gomaringen', 'phone' => '0171 000',
            'email' => 'anna@example.de', 'available' => true,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Anna Beispiel-Neu', $user->name);
        $this->assertSame('Anna Beispiel-Neu', $user->referee->name);
        $this->assertSame('TSV Gomaringen', $user->referee->club);
        $this->assertSame('Hauptstraße 1, 72810 Gomaringen', $user->referee->address);
    }
}
