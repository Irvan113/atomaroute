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
            if (!Schema::hasColumn('sub_tasks', 'skill_target_id')) {
                $table->foreignId('skill_target_id')->nullable()->after('task_id')->constrained('skill_targets')->cascadeOnDelete();
            }
            if (Schema::hasColumn('sub_tasks', 'task_id')) {
                $table->foreignId('task_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('sub_tasks', 'skill_target_id')) {
                $table->dropForeign(['skill_target_id']);
                $table->dropColumn('skill_target_id');
            }
        });
    }
};
