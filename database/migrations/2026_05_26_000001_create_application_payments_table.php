<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
            $table->enum('proof_of_payment_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('proof_of_payment_remarks')->nullable();

            $table->string('e_signature')->nullable();
            $table->enum('e_signature_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('e_signature_remarks')->nullable();

            $table->string('id_photo')->nullable();
            $table->enum('id_photo_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('id_photo_remarks')->nullable();

            // Verifier uploaded recommendation letter
            $table->string('signed_recommendation_letter')->nullable();

            $table->timestamps();

            // Indexes for fast lookups (Normalization & Indexing requirement)
            $table->index('application_id');
            $table->index('proof_of_payment_status');
            $table->index('e_signature_status');
            $table->index('id_photo_status');
        });

        // Insert new application status
        DB::table('application_statuses')->updateOrInsert(
            ['name' => 'Awaiting Payment'],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_payments');
        
        DB::table('application_statuses')
            ->where('name', 'Awaiting Payment')
            ->delete();
    }
};
