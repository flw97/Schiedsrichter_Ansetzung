<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt den Demo-Bestand des Seeders aus bereits befüllten Datenbanken (lokal und Produktion).
 * Die Konten m.hartmann, s.weber und t.lang bleiben, damit eine Anmeldung möglich ist.
 * Frische Tests laden den Bestand danach erneut über den Seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $refereeIds = DB::table('referees')
            ->whereIn('nuliga_id', ['p1', 'p2', 'p3', 'p4', 'p5'])
            ->orWhereIn('name', ['Petra Holzmann', 'Jonas Krieger', 'Elif Yildiz', 'Malte Sondheim', 'Katja Rehberg'])
            ->pluck('id');

        DB::table('games')->whereIn('nuliga_id', [
            'n1', 'n2', 'n3', 'n4', 'n5', 'n6', 'n7',
            's1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10', 's11',
        ])->orWhereIn('title', [
            'Regionalliga Südost · TSV Waldkirch vs. BC Nordstadt',
            'Regionalliga Südost · BC Nordstadt vs. TSV Waldkirch',
            'Landesmeisterschaft U19',
        ])->delete();

        $users = DB::table('users')->whereIn('username', ['p.holzmann', 'j.krieger', 'e.yildiz', 'm.sondheim', 'k.rehberg']);
        if ($refereeIds->isNotEmpty()) {
            $users->orWhereIn('referee_id', $refereeIds);
        }
        $users->delete();
        if ($refereeIds->isNotEmpty()) {
            DB::table('referees')->whereIn('id', $refereeIds)->delete();
        }
        DB::table('accommodations')->where('name', 'Hotel am Fluss')->delete();

        Schema::table('referees', function ($table) {
            $table->string('license')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('referees')->whereNull('license')->update(['license' => 'Grundlizenz']);
        Schema::table('referees', function ($table) {
            $table->string('license')->nullable(false)->change();
        });
    }
};
