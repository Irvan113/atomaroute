<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->longText('prompt_text')->nullable()->after('feature');
            $table->longText('response_text')->nullable()->after('prompt_text');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['prompt_text', 'response_text']);
        });
    }
};
