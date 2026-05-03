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
        Schema::create('accreditation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');   // Example: First Aid Training Providers
            $table->timestamps();
        });

        Schema::create('individual_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('sex');
            $table->date('birthday');
            $table->string('region');
            $table->string('city');
            $table->text('address');
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });


        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // FATPro name
            $table->text('address');
            $table->string('head_name');
            $table->string('designation');
            $table->string('telephone')->nullable(); // 123-4567
            $table->string('fax')->nullable(); // 123-4567
            $table->string('email'); // company@email.com
            $table->timestamps();
        });

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
        Schema::dropIfExists('authorized_representatives');
        Schema::dropIfExists('organization_profiles');
        Schema::dropIfExists('individual_profiles');
        Schema::dropIfExists('accreditation_types');
    }
};
