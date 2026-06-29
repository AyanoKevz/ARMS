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
        // ── Lookup: Training Types ────────────────────────────────────────────
        Schema::create('ntc_training_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Example: Emergency First Aid, Occupational First Aid, Standard First Aid
            $table->string('code')->unique();
            // Example: EFA, OFA, SFA
            $table->timestamps();
        });

        // ── Lookup: Training Modes ────────────────────────────────────────────
        Schema::create('ntc_training_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Example: Face to Face, Blended
            $table->string('code')->unique();
            // Example: F2F, BLENDED
            $table->timestamps();
        });

        // ── Lookup: Document Types (Form variants) ────────────────────────────
        Schema::create('ntc_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Example: DOLE-OSHC-STO-RTCMan Form, DOLE-OSHC-STO-PROG Form
            $table->string('code')->unique();
            // Example: RTCMAN, PROG
            $table->timestamps();
        });

        // ── Main: NTC Reports ─────────────────────────────────────────────────
        Schema::create('ntc_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('accreditation_id')
                ->constrained()
                ->cascadeOnDelete();
            // Links to the FATPro's active accreditation record

            $table->foreignId('ntc_training_type_id')
                ->constrained()
                ->restrictOnDelete();
            // EFA / OFA / SFA

            $table->foreignId('ntc_training_mode_id')
                ->constrained()
                ->restrictOnDelete();
            // Face to Face / Blended

            $table->date('training_start_date');
            // NTC Training Start Date

            $table->date('training_end_date');
            // NTC Training End Date

            $table->enum('status', ['draft', 'submitted', 'acknowledged'])
                ->default('draft');
            // Lifecycle: draft → submitted → acknowledged

            $table->timestamp('submitted_at')->nullable();
            // When the FATPro formally submitted this NTC

            $table->timestamp('acknowledged_at')->nullable();
            // When a DOLE-OSHC admin acknowledged the NTC

            $table->foreignId('acknowledged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Admin user who acknowledged this NTC

            $table->text('remarks')->nullable();
            // Admin remarks / notes

            $table->timestamps();

            // Performance indexes
            $table->index(['accreditation_id', 'status']);
            $table->index('training_start_date');
        });

        // ── File Attachments: NTC Documents ───────────────────────────────────
        Schema::create('ntc_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ntc_report_id')
                ->constrained()
                ->cascadeOnDelete();
            // Parent NTC report

            $table->foreignId('ntc_document_type_id')
                ->constrained()
                ->restrictOnDelete();
            // Which form: RTCMAN or PROG

            $table->string('file_path');
            // Storage path to the uploaded file

            $table->string('original_filename');
            // The original filename as uploaded by the user

            $table->string('mime_type');
            // application/pdf, application/msword, etc.

            $table->unsignedBigInteger('file_size');
            // File size in bytes; max enforced at controller (100 MB = 104857600 bytes)

            $table->timestamp('uploaded_at')->useCurrent();

            $table->timestamps();

            $table->index('ntc_report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ntc_documents');
        Schema::dropIfExists('ntc_reports');
        Schema::dropIfExists('ntc_document_types');
        Schema::dropIfExists('ntc_training_modes');
        Schema::dropIfExists('ntc_training_types');
    }
};
