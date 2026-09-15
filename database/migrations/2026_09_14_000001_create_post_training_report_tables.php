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
            // Example: DIRECTORY, INSTRUCTORS, PROGRAM, ATTENDANCE, PREPOST, EVALUATION

            $table->string('accepted_extensions', 100)->default('pdf');
            // Comma-separated extension whitelist, e.g. "pdf" or "xlsx,xls"

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

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->foreignId('accepted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            // Admin user who accepted this post training report

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['accreditation_id', 'status']);
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

            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
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

        // ── Reminder bookkeeping on the NTC that owes the report ──────────────
        // The obligation exists before any post_training_reports row does, so the
        // "already told them" markers have to live on the NTC itself.
        Schema::table('ntc_reports', function (Blueprint $table) {
            // One-shot: the opening "your report is due" notice.
            $table->timestamp('ptr_reminder_sent_at')->nullable()->after('remarks');

            // One-shot: the "you have missed the deadline" notice.
            $table->timestamp('ptr_overdue_notified_at')->nullable()->after('ptr_reminder_sent_at');

            // Not one-shot. Countdown reminders go out every day across the
            // working-day window the training type allows, so this tracks the
            // last day one was sent rather than merely whether one ever was.
            $table->date('ptr_last_reminded_on')->nullable()->after('ptr_overdue_notified_at');
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
                'ptr_last_reminded_on',
            ]);
        });

        Schema::dropIfExists('ptr_documents');
        Schema::dropIfExists('post_training_reports');
        Schema::dropIfExists('ptr_document_types');
    }
};
