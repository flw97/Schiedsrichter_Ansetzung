<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

class MeProfileTest extends ApiTestCase
{
    public function test_any_account_edits_its_own_user_license_and_place(): void
    {
        $user = $this->as('m.hartmann');
        $this->assertNull($user->referee_id);

        $this->putJson('/api/me', [
            'name' => 'Markus H.',
            'username' => 'M.Hartmann',
            'email' => 'markus@example.de',
        ])->assertOk();

        $user->refresh();
        $this->assertSame('Markus H.', $user->name);
        $this->assertSame('m.hartmann', $user->username);
        $this->assertSame('markus@example.de', $user->email);
        $this->assertNull($user->referee_id);
        $this->getJson('/api/state')->assertJsonPath('me.email', 'markus@example.de');

        $this->putJson('/api/me', ['password' => 'neuespass', 'current_password' => 'falsch'])->assertStatus(422);
        $this->putJson('/api/me', ['password' => 'neuespass', 'current_password' => DatabaseSeeder::PASSWORD])->assertOk();
        $this->assertTrue(Hash::check('neuespass', $user->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['text' => 'Markus H.: Eigenes Passwort geändert']);

        $this->putJson('/api/me', ['username' => 's.weber'])->assertStatus(422);

        $this->putJson('/api/me/license', ['license' => 'National', 'license_valid_until' => '2027-06-30'])->assertOk();
        $user->refresh();
        $this->assertNotNull($user->referee_id);
        $this->assertSame('National', $user->referee->license);
        $this->assertSame('2027-06-30', $user->referee->license_valid_until->toDateString());
        $this->assertSame('Markus H.', $user->referee->name);
        $this->assertSame('markus@example.de', $user->referee->email);

        $this->putJson('/api/me/standort', ['address' => 'Hauptstraße 1, 72810 Gomaringen'])->assertOk();
        $this->assertSame('Hauptstraße 1, 72810 Gomaringen', $user->referee->refresh()->address);
        $this->assertSame($user->referee_id, $user->referee->id);
    }

    public function test_referee_edits_own_license_without_a_second_profile(): void
    {
        $user = $this->as('p.holzmann');
        $id = $user->referee_id;

        $this->putJson('/api/me/license', ['license' => 'BWF-Accredited', 'license_valid_until' => '2028-01-01'])->assertOk();
        $this->putJson('/api/me', ['name' => 'Petra H.'])->assertOk();

        $user->refresh();
        $this->assertSame($id, $user->referee_id);
        $this->assertSame('BWF-Accredited', $user->referee->license);
        $this->assertSame('Petra H.', $user->referee->name);
        $this->assertSame('SG Talheim', $user->referee->club);
    }

    public function test_clearing_the_place_keeps_the_profile(): void
    {
        $user = User::firstWhere('username', 'p.holzmann');
        $this->as('p.holzmann');
        $this->putJson('/api/me/standort', ['address' => 'Ringstraße 1, 72762 Reutlingen', 'lat' => 48.4914, 'lon' => 9.2043])->assertOk();
        $this->putJson('/api/me/standort', ['address' => ''])->assertOk();
        $user->referee->refresh();
        $this->assertNull($user->referee->address);
        $this->assertNull($user->referee->lat);
        $this->assertNull($user->referee->lon);
    }
}
