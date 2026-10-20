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
        Schema::table('journals', function (Blueprint $table) {
            if (!Schema::hasColumn('journals', 'reference_url')) {
                $table->string('reference_url')->nullable()->after('images');
            }
        });

        if (!Schema::hasTable('journal_links')) {
            Schema::create('journal_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('source_journal_id')->constrained('journals')->cascadeOnDelete();
                $table->foreignId('target_journal_id')->constrained('journals')->cascadeOnDelete();
                $table->string('link_text')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_links');
        Schema::table('journals', function (Blueprint $table) {
            if (Schema::hasColumn('journals', 'reference_url')) {
                $table->dropColumn('reference_url');
            }
        });
    }
};
