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
        Schema::create('accreditations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            // Works for BOTH individual and organization

            $table->foreignId('application_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('accreditation_type_id')
                ->constrained()
                ->cascadeOnDelete();
            // Example: FATPro, Practitioner, Consultant

            $table->string('accreditation_number')->unique();
            // Example: FATPRO-2026-0001

            $table->date('date_of_accreditation');
            // Example: 2026-05-01

            $table->date('validity_date');
            // Example: 2028-05-01

            $table->enum('status', ['active', 'expired', 'revoked']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accreditations');
    }
};
