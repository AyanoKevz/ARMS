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
            $table->string('application_type');
            $table->foreignId('assigned_evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number')->unique(); // Example: ARMS-2026-000001
            $table->timestamp('submitted_at')->nullable();

            // Accreditation division (OSH Practitioner / Consultant) deadlines.
            $table->date('compliance_due_date')->nullable();
            // Last day to re-upload rejected documents (5 working days)
            $table->timestamp('archived_at')->nullable();
            $table->string('archive_reason', 40)->nullable();
            // non_compliance | interview_failed
            $table->date('reapply_after')->nullable();
            // When the applicant may apply again; the archived record is deleted on that day

            $table->timestamps();

            $table->index('submitted_at', 'idx_applications_submitted_at');
            // Also serves the user_id foreign key.
            $table->index(['user_id', 'application_type'], 'idx_apps_user_type');
            // Also serves application_type on its own, and the dashboard's
            // per-year date range on created_at.
            $table->index(['application_type', 'created_at'], 'idx_apps_type_created');
            $table->index('compliance_due_date');
            $table->index('reapply_after');
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
            $table->string('input_type'); // What kind of input

            $table->timestamps();
        });

        Schema::create('user_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_field_id')->constrained()->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('document_field_id');
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_document_id')->constrained('user_documents')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable(); // Example: "File is blurred"
            $table->timestamps();

            $table->index('status', 'idx_app_docs_status');
            // Also serves the application_id foreign key.
            $table->index(['application_id', 'status'], 'idx_app_docs_application_status');
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

            // The "latest status" lookups (MAX(id) per application) run on almost
            // every application list; these composites are what keep them fast.
            // They also serve the application_id foreign key.
            $table->index(['application_id', 'created_at'], 'idx_status_logs_app_created');
            $table->index(['application_id', 'id'], 'idx_status_logs_app_id');
            $table->index(['application_id', 'status_id'], 'idx_status_logs_app_status');
        });

        // Temporary holding table for unverified registrations
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();             // verification link token
            $table->string('email')->unique();              // applicant email
            $table->string('password');                     // bcrypt hashed
            $table->string('profile_type');
            $table->unsignedBigInteger('accreditation_type_id');
            $table->json('form_data');                      // org / individual profile fields
            $table->json('documents_data')->nullable();     // temp file paths keyed by doc code
            $table->timestamp('expires_at');                // token valid for 5 minutes
            $table->timestamps();

            $table->index('expires_at', 'idx_pending_reg_expires_at');
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

            $table->string('status');

            $table->string('scanned_certificate')->nullable();

            // OSH Practitioner certificate issuance: besides the signed
            // certificate (scanned_certificate, as FATPro), the digital or
            // scanned Identification Card, and when both were released.
            $table->string('id_card')->nullable();
            $table->timestamp('released_at')->nullable();

            // Reminder tracking columns
            $table->timestamp('reminder_3mo_sent_at')->nullable();
            $table->timestamp('reminder_2mo_sent_at')->nullable();
            $table->timestamp('reminder_1mo_sent_at')->nullable();

            // First-aider certificate (document field PREM_07, validity in
            // PREM_DATE) reminders: 3/2/1 months before its validity date and
            // once after it passes. Reset when a renewed certificate is approved.
            $table->timestamp('fa_cert_reminder_3mo_sent_at')->nullable();
            $table->timestamp('fa_cert_reminder_2mo_sent_at')->nullable();
            $table->timestamp('fa_cert_reminder_1mo_sent_at')->nullable();
            $table->timestamp('fa_cert_expired_notified_at')->nullable();

            $table->timestamps();

            $table->index('validity_date', 'idx_accreditations_validity_date');
            // The dashboard's "accredited per month" chart filters by this
            // date alone, without a status.
            $table->index('date_of_accreditation', 'idx_accreditations_date');
            // Also serves the user_id foreign key.
            $table->index(['user_id', 'status'], 'idx_accreditations_user_status');
            // Also serves status on its own.
            $table->index(['status', 'date_of_accreditation'], 'idx_accred_status_date');
        });

        // ── First-aider certificate renewals during an accreditation ──────────
        // The FATPro re-uploads the certificate with a new validity date; the
        // assigned evaluator approves or rejects it. The approved certificate
        // in user_documents stays in force until a submission is approved, so
        // a rejected upload never wipes out a valid one.
        Schema::create('first_aider_certificate_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('accreditation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The FATPro who submitted it

            $table->string('file_path')->nullable();
            // Null once a rejected upload's file has been removed
            $table->string('original_filename');
            $table->date('validity_date');

            $table->string('status', 20)->default('pending');
            // pending → approved | rejected
            $table->text('remarks')->nullable();
            // Why the evaluator rejected it

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // "The pending renewal for this accreditation" and its history.
            $table->index(['accreditation_id', 'status'], 'fa_cert_subs_acc_status_idx');
        });

        // ── Foreign keys PostgreSQL leaves unindexed ──────────────────────────
        // MySQL indexes every foreign key on its own; PostgreSQL (the live
        // Supabase database) does not, and an unindexed foreign key makes every
        // "children of this parent" lookup a full table scan. Foreign keys that
        // lead a composite index above are already served by it. The table
        // drops in down() take these indexes with them.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('applications', function (Blueprint $table) {
                $table->index('accreditation_type_id', 'idx_fk_applications_accreditation_type_id');
                $table->index('assigned_evaluator_id', 'idx_fk_applications_assigned_evaluator_id');
            });

            Schema::table('document_fields', function (Blueprint $table) {
                $table->index('document_type_id', 'idx_fk_document_fields_document_type_id');
            });

            Schema::table('application_documents', function (Blueprint $table) {
                $table->index('document_field_id', 'idx_fk_application_documents_document_field_id');
                $table->index('user_document_id', 'idx_fk_application_documents_user_document_id');
            });

            Schema::table('application_status_logs', function (Blueprint $table) {
                $table->index('status_id', 'idx_fk_application_status_logs_status_id');
                $table->index('updated_by', 'idx_fk_application_status_logs_updated_by');
            });

            Schema::table('accreditations', function (Blueprint $table) {
                $table->index('application_id', 'idx_fk_accreditations_application_id');
                $table->index('accreditation_type_id', 'idx_fk_accreditations_accreditation_type_id');
            });

            Schema::table('first_aider_certificate_submissions', function (Blueprint $table) {
                $table->index('user_id', 'idx_fk_first_aider_certificate_submissions_user_id');
                $table->index('reviewed_by', 'idx_fk_first_aider_certificate_submissions_reviewed_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('first_aider_certificate_submissions');
        Schema::dropIfExists('accreditations');
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
