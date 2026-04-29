<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Instructors ────────────────────────────────────────────────
        Schema::create('instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Belongs to the FATPro applicant (Organization user)

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');

            $table->string('service_agreement_path')->nullable();
            // PDF path stored in local disk (public/instructors/{user_id}/{instructor_id}/sa.pdf)

            $table->enum('status', ['pending', 'approved', 'returned', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();

            $table->timestamps();
        });

        // ── Instructor Credentials ──────────────────────────────────────
        Schema::create('instructor_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['EMS', 'TM1', 'NTTC', 'BOSH']);
            // EMS  = TESDA EMS NC II/III
            // TM1  = TESDA TM1
            // NTTC = TESDA NTTC
            // BOSH = BOSH SO1/SO2

            $table->string('number')->nullable();
            // Certificate / credential number

            $table->date('issued_date')->nullable();
            // Issued date — not used for BOSH

            $table->date('validity_date')->nullable();
            // Validity / expiry date

            $table->text('training_dates')->nullable();
            // Training date(s) — BOSH only (free-text since multiple dates possible)

            $table->string('pdf_path')->nullable();
            // Path to the credential PDF

            $table->enum('status', ['pending', 'approved', 'returned', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();

            $table->timestamps();

            // One credential type per instructor
            $table->unique(['instructor_id', 'type']);
        });

        // ── Add instructors_data staging column to pending_registrations ─
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->json('instructors_data')->nullable()->after('documents_data');
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
    }
};
