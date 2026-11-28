<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_messages', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->index();
        });

        // Bestehende Glocken-Einträge nicht nachträglich mailen.
        DB::table('push_messages')->whereNull('emailed_at')->update(['emailed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('push_messages', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });
    }
};
