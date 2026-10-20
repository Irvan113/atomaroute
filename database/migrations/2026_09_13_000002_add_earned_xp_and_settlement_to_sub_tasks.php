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
        Schema::table('sub_tasks', function (Blueprint $table) {
            $table->unsignedInteger('earned_xp')->default(0)->after('is_completed');
            $table->timestamp('xp_settled_at')->nullable()->after('earned_xp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_tasks', function (Blueprint $table) {
            $table->dropColumn(['earned_xp', 'xp_settled_at']);
        });
    }
};
