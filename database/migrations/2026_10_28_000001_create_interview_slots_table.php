<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interview slots the Accreditation division opens for OSH Practitioner and
 * OSH Consultant applicants to choose from on the tracking page. A slot holds
 * one applicant; booking it also writes the application's `interviews` row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_slots', function (Blueprint $table) {
            $table->id();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('mode');               // f2f | online
            $table->string('venue')->nullable();  // address or meeting link
            $table->foreignId('application_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('booked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['slot_date', 'start_time']);
        });

        // ── Foreign key PostgreSQL leaves unindexed ───────────────────────────
        // MySQL indexes every foreign key on its own; PostgreSQL (the live
        // Supabase database) does not. Nothing searches by it, but deleting an
        // admin account would scan the table to null out the slots they opened.
        // The table drop in down() takes this index with it.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('interview_slots', function (Blueprint $table) {
                $table->index('created_by', 'idx_fk_interview_slots_created_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_slots');
    }
};
