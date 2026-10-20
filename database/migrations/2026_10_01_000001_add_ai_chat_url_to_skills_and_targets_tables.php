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
            $table->string('ai_chat_url')->nullable()->after('reference_url');
        });
        Schema::table('skill_targets', function (Blueprint $table) {
            $table->string('ai_chat_url')->nullable()->after('reference_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn('ai_chat_url');
        });
        Schema::table('skill_targets', function (Blueprint $table) {
            $table->dropColumn('ai_chat_url');
        });
    }
};
