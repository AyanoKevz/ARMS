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
        Schema::create('pct_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('step_name');          // e.g. "Submission", "Evaluation"
            $table->tinyInteger('step_number');    // 1–8
            $table->tinyInteger('target_days');    // SLA target for this step (working days)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->bigInteger('elapsed_seconds')->default(0); // accumulated admin time
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['application_id', 'is_active']);
            $table->index(['application_id', 'step_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pct_entries');
    }
};
