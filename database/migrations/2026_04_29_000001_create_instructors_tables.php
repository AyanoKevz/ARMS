<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Instructor People ──────────────────────────────────────────
        // One row per actual human on a FATPro's roster, and the only stable
        // way to refer to one.
        //
        // The `instructors` table below is NOT a person: it is one person's
        // submission against one application, carrying that round's documents
        // and evaluation verdicts. A renewal re-submits everybody, so the same
        // human gains a new `instructors` row — and a new id — every cycle.
        // Anything that has to outlive a renewal (which NTC declared whom,
        // which instructors conducted a training) points here instead.
        Schema::create('instructor_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The FATPro this person belongs to

            // Their current name, kept in step with the newest submission. The
            // per-application copies keep whatever was filed at the time.
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('ins_sex')->nullable();

            $table->timestamps();

            $table->index('user_id', 'instructor_people_user_idx');
        });

        // ── Instructors ────────────────────────────────────────────────
        // One person's submission against one application — their documents
        // and the evaluator's verdicts on them, for that round only.
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
            // Belongs to the FATPro applicant (Organization user)

            $table->foreignId('instructor_person_id')
                ->nullable()
                ->constrained('instructor_people')
                ->cascadeOnDelete();
            // Which human this submission is for. Nullable only so a row can be
            // built before its person is; every write path sets it.

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('ins_sex')->nullable();
            $table->string('service_agreement_path')->nullable();
            // PDF path stored in local disk (public/instructors/{user_id}/{instructor_id}/sa.pdf)
            $table->string('cv_path')->nullable();
            // Instructor CV / resume PDF, stored alongside the service agreement
            $table->string('cv_status')->default('pending');
            $table->text('cv_remarks')->nullable();
            // The CV is evaluated in its own right — `status`/`remarks` below cover
            // the service agreement, so a wrong CV can be rejected on its own.
            // An instructor with no cv_path is treated as "not applicable" by the
            // approval gates rather than as pending (Instructor::cvApproved()).
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->string('update_request_status')->default('none');
            $table->text('update_request_reason')->nullable();
            $table->json('update_request_fields')->nullable();

            $table->timestamps();
        });

        // ── Instructor Credentials ──────────────────────────────────────
        Schema::create('instructor_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();

            $table->string('type');
            // EMS  = TESDA EMS NC II/III
            // TM1  = TESDA TM1
            // NTTC = TESDA NTTC

            $table->string('number')->nullable();
            // Certificate / credential number

            $table->date('issued_date')->nullable();
            // Issued date

            $table->date('validity_date')->nullable();
            // Validity / expiry date

            $table->string('pdf_path')->nullable();
            // Path to the credential PDF

            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('reminder_3mo_sent_at')->nullable();
            $table->timestamp('reminder_2mo_sent_at')->nullable();
            $table->timestamp('reminder_1mo_sent_at')->nullable();

            $table->timestamps();

            // One credential type per instructor
            $table->unique(['instructor_id', 'type']);

            // The nightly expiry sweep asks for approved credentials lapsing
            // inside a window, and scanned the whole table to answer it.
            $table->index(['status', 'validity_date'], 'instructor_creds_status_validity_idx');
        });

        // ── Add instructors_data staging column to pending_registrations ─
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->json('instructors_data')->nullable();
            // Temporary JSON array of instructor data (personal info + credential data + temp file paths)
            // Flushed into instructors / instructor_credentials on email verification
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('instructors_data');
        });

        Schema::dropIfExists('instructor_credentials');
        Schema::dropIfExists('instructors');
        Schema::dropIfExists('instructor_people');
    }
};
