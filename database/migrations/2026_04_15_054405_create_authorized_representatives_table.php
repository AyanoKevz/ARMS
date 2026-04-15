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
        Schema::create('authorized_representatives', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_profile_id')
                ->constrained()
                ->cascadeOnDelete();
            // Link to organization (FATPro)

            $table->string('full_name');
            // Example: Juan Dela Cruz

            $table->string('position');
            // Example: Operations Manager

            $table->string('contact_number');
            // Example: 09171234567

            $table->string('email');
            // Example: juan@email.com

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authorized_representatives');
    }
};
