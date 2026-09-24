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
        // ── Lookup: Post Training Report Document Types ───────────────────────
        Schema::create('ptr_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Example: Directory of Participants
            $table->string('code')->unique();
            // DIRECTORY, INSTRUCTORS, PROGRAM, ATTENDANCE, PREPOST, EVALUATION, VIDEO

            $table->string('entry_type', 20)->default('file');
            // file    = uploaded as an attachment (four scanned PDFs)
            // encoded = captured in the portal (Directory of Participants)
            // roster  = chosen in the portal (instructors, carried from the NTC)
            // link    = a URL typed in (the training video)

            $table->string('accepted_extensions', 100)->nullable()->default('pdf');
            // Comma-separated extension whitelist, e.g. "pdf".
            // Null for an encoded type, which accepts no file at all.

            $table->unsignedTinyInteger('sort_order')->default(0);
            // Display order on both the applicant upload form and the admin evaluation page

            $table->timestamps();
        });

        // ── Main: Post Training Reports ───────────────────────────────────────
        Schema::create('post_training_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ntc_report_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            // The acknowledged NTC whose training this report covers. One per NTC.

            $table->foreignId('accreditation_id')
                ->constrained()
                ->cascadeOnDelete();
            // Denormalised from the NTC so admin listings can scope by FATPro cheaply

            $table->string('status', 50)->default('submitted');
            // Lifecycle: submitted → accepted. Declines are tracked per document.

            $table->date('due_date')->nullable();
            // Snapshot of the submission deadline at the time of submission

            $table->string('training_video_url', 500)->nullable();
            // Where the FATPro uploaded the recording of the training. A link
            // rather than a file: a full training runs to gigabytes, which no
            // upload here is going to carry.

            $table->text('applicant_remarks')->nullable();
            // The FATPro's own note on the submission. Distinct from `remarks`
            // below, which belongs to the evaluator.

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Admin user who accepted this post training report

            $table->text('remarks')->nullable();
            // The evaluator's note, set when the report is acted on

            $table->timestamps();

            $table->index(['accreditation_id', 'status']);

            // The admin list orders every report by created_at.
            $table->index(['status', 'created_at'], 'ptr_reports_status_created_idx');
        });

        // ── File Attachments: Post Training Report Documents ──────────────────
        Schema::create('ptr_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_training_report_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('ptr_document_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('file_path')->nullable();
            // Nulled out when a document is rejected, so the file cannot linger

            // Null for an encoded type: the Directory of Participants still
            // gets a row here — it is the section the evaluator acts on and
            // where its single set of remarks lives — but it has no file.
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            // File size in bytes; max enforced at controller (25 MB)

            $table->timestamp('uploaded_at')->useCurrent();

            $table->string('status')->default('pending');
            // pending → approved | rejected | returned (re-uploaded, awaiting review)

            $table->text('remarks')->nullable();

            $table->foreignId('evaluated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('evaluated_at')->nullable();

            $table->timestamps();

            $table->index('post_training_report_id');
        });

        // ── Instructors Who Conducted the Training ────────────────────────────
        // Requirement 2 of the report. It is no longer a scanned PDF: the list
        // arrives pre-filled from the parent NTC's declared instructors and the
        // FATPro either proceeds or amends it before submitting.
        //
        // It is a COPY of ntc_report_instructor rather than a read of it. The
        // NTC records who was declared, this records who actually taught, and
        // an amendment here must not rewrite history there.
        Schema::create('ptr_instructors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_training_report_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('instructor_person_id')
                ->constrained('instructor_people')
                ->cascadeOnDelete();
            // The person, not their per-application `instructors` row — see
            // ntc_report_instructor for why.

            $table->timestamps();

            $table->unique(['post_training_report_id', 'instructor_person_id'], 'ptr_instructors_report_person_unique');
            $table->index('instructor_person_id', 'ptr_instructors_person_idx');
        });

        // ── Encoded Directory of Participants ─────────────────────────────────
        // The Directory is encoded row by row in the portal rather than filed as
        // a spreadsheet, so each participant is a record the evaluator can act on
        // individually. Verdicts live per row; the explanation for them lives once,
        // on the Directory's ptr_documents section row.
        Schema::create('ptr_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('post_training_report_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('row_no')->default(0);
            // Encoding order, so the grid and the evaluator see the same sequence

            // ── Identity ──────────────────────────────────────────────────────
            $table->string('certificate_number', 100);
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->string('sex', 20);
            $table->unsignedTinyInteger('age');

            // ── Employment ────────────────────────────────────────────────────
            $table->string('company', 255);
            $table->string('position', 255);
            $table->string('company_city', 255);
            // Company Address (City / Municipality)
            $table->string('company_region', 255);
            // Company Address (Region)
            $table->string('industry', 255);
            $table->unsignedInteger('total_workers')->nullable();
            // Total No. of Workers at the participant's company

            // ── Contact ───────────────────────────────────────────────────────
            $table->string('company_email', 255)->nullable();
            $table->string('personal_email', 255)->nullable();
            $table->string('mobile_no', 50);
            $table->string('company_landline', 50)->nullable();

            // ── ID picture ────────────────────────────────────────────────────
            $table->string('id_picture_path')->nullable();
            // Required at submit; nullable so a row can be saved mid-encoding
            $table->string('id_picture_filename')->nullable();
            $table->unsignedInteger('id_picture_size')->nullable();
            // Bytes; ceiling enforced at 5 MB in the controller

            // ── Training context ──────────────────────────────────────────────
            $table->string('mode_of_training', 100);
            // Pre-filled from the parent NTC's training mode, editable per row
            $table->string('batch_no', 50)->nullable();

            // ── Per-row evaluation ────────────────────────────────────────────
            $table->string('status', 20)->default('pending');
            // pending → approved | rejected

            $table->foreignId('evaluated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('evaluated_at')->nullable();

            $table->timestamps();

            $table->index(['post_training_report_id', 'row_no']);

            // A certificate number may repeat across trainings but not within one
            $table->unique(['post_training_report_id', 'certificate_number'], 'ptr_participants_report_cert_unique');
        });
        // ── Reminder bookkeeping on the NTC that owes the report ──────────────
        // The obligation exists before any post_training_reports row does, so the
        // "already told them" markers have to live on the NTC itself.
        Schema::table('ntc_reports', function (Blueprint $table) {
            // One-shot: the opening "your report is due" notice.
            $table->timestamp('ptr_reminder_sent_at')->nullable()->after('remarks');

            // One-shot: the "you have missed the deadline" notice.
            $table->timestamp('ptr_overdue_notified_at')->nullable()->after('ptr_reminder_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ntc_reports', function (Blueprint $table) {
            $table->dropColumn([
                'ptr_reminder_sent_at',
                'ptr_overdue_notified_at',
            ]);
        });

        Schema::dropIfExists('ptr_instructors');
        Schema::dropIfExists('ptr_participants');
        Schema::dropIfExists('ptr_documents');
        Schema::dropIfExists('post_training_reports');
        Schema::dropIfExists('ptr_document_types');
    }
};
