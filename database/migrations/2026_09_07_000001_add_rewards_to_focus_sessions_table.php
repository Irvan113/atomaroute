<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->unsignedInteger('xp_earned')->default(0)->after('ambient_sound');
            $table->unsignedInteger('coins_earned')->default(0)->after('xp_earned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('focus_sessions', function (Blueprint $table) {
            $table->dropColumn(['xp_earned', 'coins_earned']);
        });
    }
};
