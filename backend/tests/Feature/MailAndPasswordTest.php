<?php

namespace Tests\Feature;

use App\Mail\NotificationsMail;
use App\Mail\PasswordResetMail;
use App\Models\PushMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MailAndPasswordTest extends ApiTestCase
{
    private const HINT = 'Wenn ein Konto mit dieser Angabe und einer hinterlegten E-Mail existiert, haben wir einen Link zum Zurücksetzen geschickt.';

    public function test_cron_is_closed_without_a_matching_token(): void
    {
        $this->getJson('/api/cron')->assertForbidden();
        $this->getJson('/api/cron?token=local')->assertForbidden();

        config(['services.cron.token' => 'cron-secret']);
        $this->getJson('/api/cron?token=falsch')->assertForbidden();
        $this->withHeader('X-Cron-Token', 'cron-secret')->postJson('/api/cron')->assertNoContent();
    }

    public function test_cron_mails_pending_notifications_once_and_skips_unreachable_accounts(): void
    {
        config(['services.cron.token' => 'cron-secret', 'app.frontend_url' => 'http://localhost:3000']);
        Mail::fake();
        $me = User::firstWhere('username', 'k.rehberg');
        PushMessage::create(['user_id' => $me->id, 'text' => 'eins']);
        PushMessage::create(['user_id' => $me->id, 'text' => 'zwei']);
        User::firstWhere('username', 't.lang')->update(['email' => null]);
        PushMessage::create(['user_id' => User::firstWhere('username', 't.lang')->id, 'text' => 'ohne']);
        User::firstWhere('username', 's.weber')->update(['active' => false]);
        PushMessage::create(['user_id' => User::firstWhere('username', 's.weber')->id, 'text' => 'inaktiv']);

        $this->getJson('/api/cron?token=cron-secret')
            ->assertOk()
            ->assertJsonPath('sent', 2)
            ->assertJsonPath('skipped', 2)
            ->assertJsonPath('failed', 0);

        Mail::assertSent(NotificationsMail::class, function (NotificationsMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('katja.rehberg@web.de')
                && $mail->messages->count() === 2
                && str_contains($html, 'eins')
                && str_contains($html, 'http://localhost:3000');
        });
        $this->assertNotNull(PushMessage::firstWhere('text', 'eins')->emailed_at);
        $this->assertNull(PushMessage::firstWhere('text', 'ohne')->emailed_at);
        $this->assertNull(PushMessage::firstWhere('text', 'inaktiv')->emailed_at);

        $this->getJson('/api/cron?token=cron-secret')->assertNoContent();
        Mail::assertSentCount(1);
    }

    public function test_cron_keeps_messages_when_sending_fails(): void
    {
        config(['services.cron.token' => 'cron-secret']);
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp'));
        PushMessage::create(['user_id' => User::firstWhere('username', 'k.rehberg')->id, 'text' => 'bleibt']);

        $this->getJson('/api/cron?token=cron-secret')->assertStatus(500)->assertJsonPath('failed', 1);
        $this->assertNull(PushMessage::firstWhere('text', 'bleibt')->emailed_at);
    }

    public function test_forgot_password_sends_a_link_without_revealing_unknown_accounts(): void
    {
        config(['app.frontend_url' => 'http://localhost:3000']);
        Mail::fake();

        $this->postJson('/api/password/forgot', ['login' => 'niemand'])->assertOk()->assertJsonPath('message', self::HINT);
        User::firstWhere('username', 't.lang')->update(['email' => null]);
        $this->postJson('/api/password/forgot', ['login' => 't.lang'])->assertOk()->assertJsonPath('message', self::HINT);
        User::firstWhere('username', 's.weber')->update(['active' => false]);
        $this->postJson('/api/password/forgot', ['login' => 's.weber'])->assertOk()->assertJsonPath('message', self::HINT);
        Mail::assertNothingSent();

        $this->postJson('/api/password/forgot', ['login' => 'K.Rehberg'])->assertOk()->assertJsonPath('message', self::HINT);
        $this->postJson('/api/password/forgot', ['login' => 'KATJA.REHBERG@web.de'])->assertOk();
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) {
            return $mail->hasTo('katja.rehberg@web.de')
                && str_starts_with($mail->url, 'http://localhost:3000/passwort-zuruecksetzen?')
                && str_contains($mail->render(), $mail->token);
        });
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('audit_logs', ['text' => 'Katja Rehberg (Schiedsrichter): Link zum Zurücksetzen des Passworts verschickt']);
    }

    public function test_reset_replaces_the_password_and_revokes_tokens(): void
    {
        config(['app.frontend_url' => 'http://localhost:3000']);
        Mail::fake();
        $this->postJson('/api/password/forgot', ['login' => 'k.rehberg'])->assertOk();
        $sent = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$sent) {
            $sent = $mail;

            return true;
        });

        $user = User::firstWhere('username', 'k.rehberg');
        $session = $this->postJson('/api/login', ['username' => 'k.rehberg', 'password' => DatabaseSeeder::PASSWORD])->assertOk();
        $this->postJson('/api/password/reset', [
            'email' => 'katja.rehberg@web.de', 'token' => $sent->token,
            'password' => 'kurz', 'password_confirmation' => 'kurz',
        ])->assertStatus(422);
        $this->postJson('/api/password/reset', [
            'email' => 'katja.rehberg@web.de', 'token' => $sent->token,
            'password' => 'neuespass', 'password_confirmation' => 'anders123',
        ])->assertStatus(422);

        $this->postJson('/api/password/reset', [
            'email' => 'katja.rehberg@web.de', 'token' => $sent->token,
            'password' => 'neuespass', 'password_confirmation' => 'neuespass',
        ])->assertOk();

        $this->assertTrue(Hash::check('neuespass', $user->fresh()->password));
        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => hash('sha256', $session->json('refresh_token'))]);
        $this->withToken($session->json('token'))->getJson('/api/state')->assertUnauthorized();
        $this->postJson('/api/refresh', ['refresh_token' => $session->json('refresh_token')])->assertUnauthorized();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'katja.rehberg@web.de']);
        $this->postJson('/api/login', ['username' => 'k.rehberg', 'password' => DatabaseSeeder::PASSWORD])->assertStatus(422);
        $this->postJson('/api/login', ['username' => 'k.rehberg', 'password' => 'neuespass'])->assertOk();
        $this->postJson('/api/password/reset', [
            'email' => 'katja.rehberg@web.de', 'token' => $sent->token,
            'password' => 'nochmals12', 'password_confirmation' => 'nochmals12',
        ])->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['text' => 'Katja Rehberg (Schiedsrichter): Passwort zurückgesetzt']);
    }

    public function test_reset_rejects_a_deactivated_account_and_a_bad_token(): void
    {
        Mail::fake();
        $this->postJson('/api/password/forgot', ['login' => 'k.rehberg'])->assertOk();
        $sent = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$sent) {
            $sent = $mail;

            return true;
        });
        User::firstWhere('username', 'k.rehberg')->update(['active' => false]);

        $this->postJson('/api/password/reset', [
            'email' => 'katja.rehberg@web.de', 'token' => $sent->token,
            'password' => 'neuespass', 'password_confirmation' => 'neuespass',
        ])->assertStatus(422);
        $this->assertTrue(Hash::check(DatabaseSeeder::PASSWORD, User::firstWhere('username', 'k.rehberg')->password));
        $this->postJson('/api/password/reset', [
            'email' => 'katja.rehberg@web.de', 'token' => 'falsch',
            'password' => 'neuespass', 'password_confirmation' => 'neuespass',
        ])->assertStatus(422);
    }
}
