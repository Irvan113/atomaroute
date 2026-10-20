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
        Schema::table('skills', function (Blueprint $table) {
            $table->string('mode')->default('systematic')->after('status');
        });

        Schema::table('skill_targets', function (Blueprint $table) {
            $table->json('pending_evaluation_data')->nullable()->after('evaluation_feedback');
            $table->json('evaluation_ai_suggestion')->nullable()->after('pending_evaluation_data');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete()->after('user_id');
            $table->foreignId('skill_target_id')->nullable()->constrained('skill_targets')->nullOnDelete()->after('skill_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['skill_target_id']);
            $table->dropForeign(['skill_id']);
            $table->dropColumn(['skill_target_id', 'skill_id']);
        });

        Schema::table('skill_targets', function (Blueprint $table) {
            $table->dropColumn(['pending_evaluation_data', 'evaluation_ai_suggestion']);
        });

        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn(['mode']);
        });
    }
};
