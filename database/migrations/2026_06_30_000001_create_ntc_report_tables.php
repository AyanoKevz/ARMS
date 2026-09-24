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

            $table->string('venue', 500)->nullable();
            // Training venue (F2F) or Zoom/Meeting link (Blended)

            $table->date('training_start_date');
            // NTC Training Start Date

            $table->date('training_end_date');
            // NTC Training End Date

            $table->string('status', 50)->default('draft');
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

            // The admin lists filter on status and order by created_at. Without
            // this the query is a full scan plus a filesort on every page load.
            $table->index(['status', 'created_at'], 'ntc_reports_status_created_idx');

            // "Acknowledged trainings that have already ended" — the dashboard
            // banner and the post training reminder commands both ask this.
            $table->index(['status', 'training_end_date'], 'ntc_reports_status_end_idx');
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

            $table->string('file_path')->nullable();
            // Storage path to the uploaded file

            $table->string('original_filename');
            // The original filename as uploaded by the user

            $table->string('mime_type');
            // application/pdf, application/msword, etc.

            $table->unsignedBigInteger('file_size');
            // File size in bytes; max enforced at controller (100 MB = 104857600 bytes)

            $table->timestamp('uploaded_at')->useCurrent();

            // ── Evaluation ────────────────────────────────────────────────
            $table->string('status')->default('pending');
            // pending → approved | rejected | returned (re-uploaded, awaiting review)

            $table->text('remarks')->nullable();
            // Why an evaluator declined this document

            $table->foreignId('evaluated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Admin user who last evaluated this document

            $table->timestamp('evaluated_at')->nullable();

            $table->timestamps();

            $table->index('ntc_report_id');
        });

        // ── Training Days ─────────────────────────────────────────────────────
        // A training runs for a fixed number of days set by its type (EFA 1,
        // OFA 2, SFA 4), but those are calendar days chosen one by one: weekends
        // are allowed and the days need not be consecutive. One curriculum day
        // may also be delivered over more than one date, so a day is a GROUP of
        // rows here rather than a single one.
        //
        // ntc_reports.training_start_date and training_end_date are kept as the
        // MIN and MAX of this table, rewritten whenever the set changes. Every
        // deadline in the system still reads those two columns and so needs no
        // knowledge of this one.
        Schema::create('ntc_training_dates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ntc_report_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('training_date');

            $table->unsignedTinyInteger('day_no');
            // Which day of the course this date belongs to, 1-based — NOT a
            // position in the sorted set, since several rows may share a day.
            // Day 1 opens on the training start date, which the FATPro picks
            // directly rather than adding to the list of further dates.

            $table->timestamps();

            // The same day cannot be counted twice in one training
            $table->unique(['ntc_report_id', 'training_date'], 'ntc_training_dates_report_date_unique');
            $table->index(['ntc_report_id', 'day_no'], 'ntc_training_dates_report_day_idx');
        });

        // ── Instructors Declared on the NTC ───────────────────────────────────
        // Who the FATPro says will conduct the training, drawn from their own
        // accredited roster. Frozen once submitted: the Post Training Report
        // takes its own copy (ptr_instructors) so amending who ACTUALLY taught
        // never rewrites who was declared here.
        //
        // Points at the PERSON rather than their `instructors` submission: that
        // row is replaced on every renewal, which would leave older trainings
        // referring to a roster entry the FATPro no longer appears to have.
        Schema::create('ntc_report_instructor', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ntc_report_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('instructor_person_id')
                ->constrained('instructor_people')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['ntc_report_id', 'instructor_person_id'], 'ntc_report_instructor_unique');
            $table->index('instructor_person_id', 'ntc_report_instructor_person_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ntc_report_instructor');
        Schema::dropIfExists('ntc_training_dates');
        Schema::dropIfExists('ntc_documents');
        Schema::dropIfExists('ntc_reports');
        Schema::dropIfExists('ntc_document_types');
        Schema::dropIfExists('ntc_training_modes');
        Schema::dropIfExists('ntc_training_types');
    }
};
