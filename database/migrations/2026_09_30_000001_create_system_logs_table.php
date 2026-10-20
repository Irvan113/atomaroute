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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->default('INFO')->index(); // DEBUG, INFO, WARN, ERROR, CRITICAL
            $table->string('category', 40)->default('system')->index(); // troubleshooting, performance, security, audit, system
            $table->string('action', 100)->index(); // e.g. auth.login, auth.failed, app.exception, http.slow
            $table->text('message');
            $table->json('context')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('method', 10)->nullable();
            $table->integer('execution_time_ms')->nullable()->index();
            $table->timestamps();

            // Composite indexes for rapid filtering and reporting
            $table->index(['level', 'created_at']);
            $table->index(['category', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
