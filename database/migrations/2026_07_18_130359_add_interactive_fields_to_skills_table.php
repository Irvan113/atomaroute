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
            $table->json('interview_context')->nullable();
            $table->string('setup_status')->default('active'); // interviewing, active, completed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropColumn(['interview_context', 'setup_status']);
        });
    }
};
