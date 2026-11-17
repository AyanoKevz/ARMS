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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->date('interview_date');
            $table->time('interview_time');
            $table->string('mode');
            $table->string('venue')->nullable();
            $table->timestamps();

            $table->index('interview_date', 'idx_interviews_date');
            $table->index('mode', 'idx_interviews_mode');
            // Also serves the application_id foreign key.
            $table->index(['application_id', 'interview_date'], 'idx_interviews_app_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
