<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referees', function (Blueprint $table) {
            $table->id();
            $table->string('nuliga_id')->nullable()->unique();
            $table->string('name');
            $table->string('club')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->string('license');
            $table->date('license_valid_until')->nullable();
            $table->date('license_synced_at')->nullable();
            $table->date('license_reminded_for')->nullable();
            $table->boolean('persp_national')->default(false);
            $table->boolean('persp_international')->default(false);
            $table->boolean('available')->default(true);
            // Einsatzstatistik (bis zur Ableitung aus der Einsatzhistorie gepflegte Kennzahlen)
            $table->unsignedInteger('season_count')->default(0);
            $table->unsignedInteger('monthly_load')->default(0);
            $table->float('fairness')->default(0.5);
            $table->string('last_game')->nullable();
            $table->string('trend')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('role')->default('schiri')->after('password');
            $table->boolean('active')->default(true)->after('role');
            $table->foreignId('referee_id')->nullable()->after('active')->constrained()->nullOnDelete();
        });

        Schema::create('referee_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->date('from');
            $table->date('to');
            $table->string('reason', 100)->nullable();
        });

        Schema::create('license_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('text');
        });

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('nuliga_id')->nullable()->unique();
            $table->string('title');
            $table->string('type'); // match | tournament
            $table->date('date');
            $table->string('time', 5)->nullable(); // null = ganztägig
            $table->string('venue');
            $table->string('competition');
            $table->unsignedTinyInteger('needed_count')->default(1);
            $table->string('status')->default('open'); // open | proposed | confirmed
            $table->string('home_team')->nullable();
            $table->string('away_team')->nullable();
            $table->string('home_state', 2)->nullable();
            $table->string('away_state', 2)->nullable();
            // false = liegt nur in NuLiga vor und wurde noch nicht übernommen
            $table->boolean('imported')->default(true);
            // Ausstehende NuLiga-Änderung („neu“ oder Terminverschiebung)
            $table->string('nuliga_change')->nullable();
            $table->date('pending_date')->nullable();
            $table->string('pending_time', 5)->nullable();
            $table->string('pending_venue')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('referees')->nullOnDelete();
            $table->string('requested_by')->nullable();
            $table->string('reopened_note')->nullable();
            $table->json('schedule_change')->nullable();
            $table->json('report')->nullable();
            $table->timestamps();
        });

        // kind: confirmed | planned | head (Oberschiedsrichter) | planned_head
        Schema::create('game_referee', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->boolean('reconfirmed')->default(false);
            $table->primary(['game_id', 'referee_id', 'kind']);
        });

        Schema::create('availability_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->string('answer', 3); // yes | no
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['game_id', 'referee_id']);
        });

        Schema::create('observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('game');
            $table->json('scores');
            $table->text('strengths')->nullable();
            $table->text('development')->nullable();
            $table->string('recommendation');
            $table->string('observer');
            $table->timestamps();
        });

        Schema::create('rulebooks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category');
            $table->string('version');
            $table->string('valid_from')->nullable();
            $table->boolean('mandatory')->default(false);
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->string('updated_by');
            $table->timestamps();
        });

        Schema::create('rulebook_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rulebook_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('valid_from')->nullable();
            $table->string('filename');
            $table->string('path');
            $table->timestamp('replaced_at');
        });

        Schema::create('rulebook_reads', function (Blueprint $table) {
            $table->foreignId('rulebook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->timestamps();
            $table->primary(['rulebook_id', 'user_id']);
        });

        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('street', 80)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('city', 60);
            $table->string('venue');
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lon', 9, 6)->nullable();
            $table->string('added_by');
            $table->timestamps();
        });

        Schema::create('accommodation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('author');
            $table->unsignedTinyInteger('stars');
            $table->string('text', 300)->nullable();
            $table->timestamps();
            $table->unique(['accommodation_id', 'user_id']);
        });

        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10); // access | change
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'audit_logs', 'push_messages', 'accommodation_reviews', 'accommodations', 'rulebook_reads', 'rulebook_versions', 'rulebooks', 'observations', 'availability_responses', 'game_referee', 'games', 'license_proofs', 'referee_blackouts'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referee_id');
            $table->dropColumn(['username', 'role', 'active']);
        });
        Schema::dropIfExists('referees');
    }
};
