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

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // admin, applicant
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Example: HCD, SCD, ECD, TPID
            $table->timestamps();
        });

        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });


        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('profile_type');
            // Example: individual (Practitioner), organization (FATPro)
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            // Example: 1 = admin, 2 = applicant
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable()->unique();
            $table->string('user_photo')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('profile_type', 'idx_users_profile_type');
        });


        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position')->nullable();
            $table->foreignId('admin_role_id')->nullable()->constrained('admin_roles')->onDelete('set null');
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            // Example: 1 = HCD;
            $table->timestamps();

            // "Admins of this division with this role" — the evaluator lookups.
            // Also serves the division_id foreign key.
            $table->index(['division_id', 'admin_role_id'], 'idx_admin_profiles_division_role');
        });

        Schema::create('pending_admins', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->string('email')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('position')->nullable();
            $table->foreignId('admin_role_id')->nullable()->constrained('admin_roles')->onDelete('set null');
            $table->foreignId('division_id')->nullable()->constrained('divisions')->onDelete('cascade');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // ── Audit trail of admin actions ──────────────────────────────────────
        // Append-only: who did what, when, and to which record. Written by the
        // RecordAdminActivity middleware and the login/logout listeners.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Who. Null once the account is deleted — the snapshot below stays.
            $table->string('actor_name');
            $table->string('actor_email');
            $table->string('admin_role', 50);
            // The role held WHEN the action was taken. The audit page filters on
            // this, so a later role change never moves past entries around.

            $table->string('action', 100);
            // Machine key, e.g. "application.document_evaluated"
            $table->string('description', 255);
            // What a person reads, e.g. "Evaluated a document"

            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            // Which record, e.g. "ARMS2026-AB1234 — Business Permit"
            $table->string('subject_url', 500)->nullable();

            $table->json('properties')->nullable();
            // Decision details: status chosen, remarks, interview date…
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // The audit page: one role's entries, newest first.
            $table->index(['admin_role', 'created_at'], 'audit_logs_role_created_idx');
            // One admin's own history, and the user_id foreign key.
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
            // Everything ever done to one record.
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_idx');
        });

        // ── Foreign keys PostgreSQL leaves unindexed ──────────────────────────
        // MySQL indexes every foreign key on its own; PostgreSQL (the live
        // Supabase database) does not, and an unindexed foreign key makes every
        // "children of this parent" lookup a full table scan. Foreign keys that
        // lead a composite index above are already served by it. The table
        // drops in down() take these indexes with them.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->index('role_id', 'idx_fk_users_role_id');
            });

            Schema::table('admin_profiles', function (Blueprint $table) {
                $table->index('user_id', 'idx_fk_admin_profiles_user_id');
                $table->index('admin_role_id', 'idx_fk_admin_profiles_admin_role_id');
            });

            Schema::table('pending_admins', function (Blueprint $table) {
                $table->index('admin_role_id', 'idx_fk_pending_admins_admin_role_id');
                $table->index('division_id', 'idx_fk_pending_admins_division_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Children before the tables their foreign keys point at.
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('pending_admins');
        Schema::dropIfExists('admin_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('roles');
    }
};
