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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('weekly_xp')->default(0)->after('monthly_xp');
            $table->timestamp('weekly_xp_reset_at')->nullable()->after('weekly_xp');
            $table->string('active_theme')->default('default')->after('daily_clear_achieved_at');
            $table->json('unlocked_themes')->nullable()->after('active_theme');
            $table->string('active_sound_pack')->default('default')->after('unlocked_themes');
            $table->json('unlocked_sound_packs')->nullable()->after('active_sound_pack');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'weekly_xp',
                'weekly_xp_reset_at',
                'active_theme',
                'unlocked_themes',
                'active_sound_pack',
                'unlocked_sound_packs',
            ]);
        });
    }
};
