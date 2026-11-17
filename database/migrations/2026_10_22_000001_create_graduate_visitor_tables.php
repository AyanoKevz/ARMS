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
        // ── Visitors of the public Graduate Lookup ────────────────────────────
        // The lookup is public but not anonymous: before searching, a visitor
        // states who they are and why, and proves the e-mail by opening the
        // link sent to it. One row per request, so a returning visitor shows
        // up once for every time they asked for access.
        Schema::create('graduate_visitors', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('organization', 255);
            $table->string('email', 255);
            $table->string('purpose', 500);

            $table->timestamp('verified_at')->nullable();
            // First time the e-mailed link was opened. Null = never verified.
            $table->timestamp('last_accessed_at')->nullable();
            // Latest time the link was opened

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            // Captured when the access was requested

            $table->timestamps();

            // The admin log lists newest first.
            $table->index('created_at');
            $table->index('email');
        });

        // ── What each visitor searched for ────────────────────────────────────
        Schema::create('graduate_searches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('graduate_visitor_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('search_term', 150);
            $table->unsignedInteger('results_count')->default(0);
            // Graduates shown for the term; 0 means nothing matched

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();
            // Append-only: there is no updated_at

            $table->index(['graduate_visitor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graduate_searches');
        Schema::dropIfExists('graduate_visitors');
    }
};
