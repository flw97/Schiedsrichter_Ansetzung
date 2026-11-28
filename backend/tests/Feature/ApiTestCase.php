<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Feature-Tests gegen die Demo-Daten des Seeders. */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function as(string $username): User
    {
        $user = User::firstWhere('username', $username);
        unset($this->defaultHeaders['Authorization']);
        $this->actingAs($user, 'api');

        return $user;
    }

    protected function game(string $nuligaIdOrTitle): Game
    {
        return Game::with('referees')->where('nuliga_id', $nuligaIdOrTitle)->orWhere('title', $nuligaIdOrTitle)->firstOrFail();
    }
}
