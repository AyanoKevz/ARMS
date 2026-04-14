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
            $table->string('name');
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
            $table->string('accreditation_number')->nullable();
            $table->text('address');
            $table->date('date_of_accreditation')->nullable();
            $table->date('validity_date')->nullable();
            $table->string('head_name');
            $table->string('designation');
            $table->string('telephone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accreditation');
        Schema::dropIfExists('individual_profiles');
        Schema::dropIfExists('organization_profiles');
    }
};
