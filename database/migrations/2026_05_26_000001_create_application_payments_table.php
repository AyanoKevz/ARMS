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
        Schema::create('application_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            
            // Payment requirements (file paths and statuses)
            $table->string('proof_of_payment')->nullable();
            $table->string('proof_of_payment_status')->default('pending');
            $table->text('proof_of_payment_remarks')->nullable();

            // Verifier uploaded recommendation letter
            $table->string('signed_recommendation_letter')->nullable();

            $table->timestamps();

            // Indexes for fast lookups (Normalization & Indexing requirement)
            $table->index('application_id');
            $table->index('proof_of_payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_payments');
    }
};
