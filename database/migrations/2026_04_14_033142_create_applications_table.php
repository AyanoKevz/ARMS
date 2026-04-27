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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_type_id')->constrained()->cascadeOnDelete(); // 7 = FATPro
            $table->enum('application_type', [
                'new',
                'renewal',
                'reinstatement'
            ]);
            $table->foreignId('handled_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number')->unique(); // Example: ARMS-2026-000001
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');   // Example: Legal Requirements to Operate Business
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('document_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Example: Name of Data Protection Officer
            $table->string('code'); // Example: DPO_NAME
            $table->enum('input_type', ['file', 'text', 'date']); // What kind of input

            $table->timestamps();
        });

        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_field_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'document_field_id']);
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_document_id')->constrained('user_documents')->cascadeOnDelete();
            $table->enum('status', [
                'pending',
                'approved',
                'rejected'
            ])->default('pending');
            $table->text('remarks')->nullable(); // Example: "File is blurred"
            $table->timestamps();
        });

        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');   // Example: Submitted, Under Evaluation, For Revision
            $table->timestamps();
        });

        Schema::create('application_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('application_statuses')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->json('required_updates')->nullable();
            $table->timestamps();
        });

        // Temporary holding table for unverified registrations
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();             // verification link token
            $table->string('email')->unique();              // applicant email
            $table->string('password');                     // bcrypt hashed
            $table->enum('profile_type', ['Individual', 'Organization']);
            $table->unsignedBigInteger('accreditation_type_id');
            $table->json('form_data');                      // org / individual profile fields
            $table->json('documents_data')->nullable();     // temp file paths keyed by doc code
            $table->timestamp('expires_at');                // token valid for 5 minutes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
        Schema::dropIfExists('application_status_logs');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('document_fields');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('application_statuses');
        Schema::dropIfExists('document_types');
    }
};
