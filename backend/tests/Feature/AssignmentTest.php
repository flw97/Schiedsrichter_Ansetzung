<?php

namespace Tests\Feature;

use App\Models\PushMessage;
use App\Models\Referee;
use App\Models\Setting;

class AssignmentTest extends ApiTestCase
{
    private function ref(string $name): Referee
    {
        return Referee::firstWhere('name', $name);
    }

    public function test_admin_confirms_league_match_with_higher_ranked_leader_and_pushes(): void
    {
        $this->as('s.weber');
        $game = $this->game('s3');
        $ids = [$this->ref('Elif Yildiz')->id, $this->ref('Petra Holzmann')->id];
        $this->postJson("/api/games/{$game->id}/confirm", ['referee_ids' => [$ids[0]]])->assertStatus(422);
        $this->postJson("/api/games/{$game->id}/confirm", ['referee_ids' => $ids])->assertOk()->assertJsonCount(2, 'pushes');

        $game->refresh();
        $this->assertSame('confirmed', $game->status);
        $this->assertSame($ids[1], $game->leader_id);
        $this->assertTrue($game->imported);
        $this->assertSame(1, PushMessage::where('user_id', $this->ref('Petra Holzmann')->user->id)->count());
    }

    public function test_license_is_checked_on_confirm(): void
    {
        $this->as('s.weber');
        $game = $this->game('s4'); // 1. Bundesliga: ab National
        $this->postJson("/api/games/{$game->id}/confirm", ['referee_ids' => [$this->ref('Elif Yildiz')->id, $this->ref('Jonas Krieger')->id]])->assertStatus(422);
    }

    public function test_referee_responds_only_while_abfrage_is_open(): void
    {
        $this->as('e.yildiz');
        $game = $this->game('s8');
        $this->putJson("/api/games/{$game->id}/response", ['answer' => 'yes', 'comment' => 'Gern'])->assertOk();
        $this->assertDatabaseHas('availability_responses', ['game_id' => $game->id, 'answer' => 'yes', 'comment' => 'Gern']);

        Setting::put('abfrage_to', today()->subDay()->toDateString());
        $this->putJson("/api/games/{$game->id}/response", ['answer' => null])->assertStatus(422);
    }

    public function test_withdrawal_reopens_game_and_notifies_admins_and_partner(): void
    {
        $this->as('k.rehberg');
        $game = $this->game('n3');
        $this->postJson("/api/games/{$game->id}/withdraw", ['reason' => 'krank'])->assertOk()->assertJsonCount(2, 'pushes');
        $game->refresh();
        $this->assertSame('open', $game->status);
        $this->assertSame('Von Katja Rehberg zurückgegeben („krank“)', $game->reopened_note);
        $this->postJson("/api/games/{$game->id}/withdraw")->assertForbidden();
    }

    public function test_nuliga_schedule_change_requires_reconfirmation_by_all_assigned(): void
    {
        $this->as('s.weber');
        $game = $this->game('n3');
        $this->assertSame('verschoben · bisher 11:00', $game->nuliga_change);
        $this->postJson("/api/nuliga/import/{$game->id}")->assertOk()->assertJsonCount(2, 'pushes');
        $game->refresh();
        $this->assertSame('11:30', $game->time);
        $this->assertNotNull($game->schedule_change);

        $this->as('k.rehberg');
        $this->postJson("/api/games/{$game->id}/reconfirm")->assertOk();
        $this->assertNotNull($game->refresh()->schedule_change);
        $this->as('j.krieger');
        $this->postJson("/api/games/{$game->id}/reconfirm")->assertOk();
        $this->assertNull($game->refresh()->schedule_change);
    }

    public function test_games_outside_bw_must_be_requested_explicitly(): void
    {
        $this->as('s.weber');
        $game = $this->game('n4');
        $this->postJson("/api/nuliga/import/{$game->id}")->assertStatus(422);
        $this->postJson("/api/nuliga/import/{$game->id}", ['request' => true])->assertOk();
        $this->assertSame('Sabine Weber', $game->refresh()->requested_by);

        $this->postJson('/api/nuliga/import-all')->assertOk();
        $this->assertFalse($this->game('n5')->imported);
        $this->assertTrue($this->game('n1')->imported);
    }

    public function test_season_plan_fixes_full_rows_and_keeps_incomplete_rows_open(): void
    {
        $this->as('s.weber');
        [$a, $b] = [$this->game('s1'), $this->game('s2')];
        $this->postJson('/api/season-plan', ['mode' => 'festlegen', 'rows' => [
            ['game_id' => $a->id, 'referee_ids' => [$this->ref('Elif Yildiz')->id, $this->ref('Petra Holzmann')->id]],
            ['game_id' => $b->id, 'referee_ids' => [$this->ref('Jonas Krieger')->id]],
        ]])->assertOk()->assertJsonCount(2, 'pushes');
        $this->assertSame('confirmed', $a->refresh()->status);
        $this->assertSame('open', $b->refresh()->status);
    }

    public function test_report_is_stored_by_assigned_referee(): void
    {
        $this->as('j.krieger');
        $game = $this->game('n3');
        $this->postJson("/api/games/{$game->id}/report", ['note' => 'Ruhig', 'cards' => [['color' => 'gelb', 'team' => 'SG Talheim', 'given_by' => 'Jonas Krieger']]])->assertOk();
        $this->assertSame('Jonas Krieger', $game->refresh()->report['author']);

        $this->as('e.yildiz');
        $this->postJson("/api/games/{$game->id}/report", ['note' => 'x'])->assertForbidden();
    }
}
