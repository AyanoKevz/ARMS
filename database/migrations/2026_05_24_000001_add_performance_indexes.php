
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Indexes Migration
 *
 * Adds indexes to columns that are frequently used in:
 * - WHERE clauses (filtering)
 * - ORDER BY clauses (sorting)
 * - JOIN conditions (beyond foreign keys, which are already indexed)
 * - Queries that check for specific statuses or types
 *
 * NOTE: Foreign key columns (foreignId) already get implicit indexes in MySQL/MariaDB.
 * This migration targets additional non-FK columns that are heavily queried.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ─────────────────────────────────────────────────────────────
        // `role_id` FK is already indexed. Add `profile_type` which is used to
        // distinguish admin vs applicant on many queries.
        Schema::table('users', function (Blueprint $table) {
            $table->index('profile_type', 'idx_users_profile_type');
        });

        // ── admin_profiles ────────────────────────────────────────────────────
        // Used in whereHas filters to find admins by division and role.
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->index(['division_id', 'admin_role_id'], 'idx_admin_profiles_division_role');
        });

        // ── applications ──────────────────────────────────────────────────────
        // `user_id` and `accreditation_type_id` FKs are already indexed.
        // `application_type` is used in whereIn() to separate new/renewal/reinstatement.
        // `submitted_at` is used for ordering and filtering by date.
        Schema::table('applications', function (Blueprint $table) {
            $table->index('application_type', 'idx_applications_type');
            $table->index('submitted_at', 'idx_applications_submitted_at');
            $table->index('handled_by_admin_id', 'idx_applications_handled_by');
        });

        // ── application_documents ─────────────────────────────────────────────
        // `status` is heavily queried to find pending/approved/rejected docs.
        // Composite index for the most common query: all docs for an application + their status.
        Schema::table('application_documents', function (Blueprint $table) {
            $table->index('status', 'idx_app_docs_status');
            $table->index(['application_id', 'status'], 'idx_app_docs_application_status');
        });

        // ── application_status_logs ───────────────────────────────────────────
        // Used constantly to find the latest status of an application.
        // A composite index on (application_id, created_at DESC) greatly speeds up latestOfMany().
        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->index(['application_id', 'created_at'], 'idx_status_logs_app_created');
            $table->index('status_id', 'idx_status_logs_status_id');
        });

        // ── accreditations ────────────────────────────────────────────────────
        // `status` is used on every active FATPro and revoked/expired query.
        // `validity_date` is used by the automated reminder scheduler.
        // Composite on (user_id, status) for per-user latest accreditation lookups.
        Schema::table('accreditations', function (Blueprint $table) {
            $table->index('status', 'idx_accreditations_status');
            $table->index('validity_date', 'idx_accreditations_validity_date');
            $table->index(['user_id', 'status'], 'idx_accreditations_user_status');
        });

        // ── interviews ────────────────────────────────────────────────────────
        // `interview_date` is used for calendar queries and ordering.
        // `mode` may be filtered when distinguishing online vs F2F.
        Schema::table('interviews', function (Blueprint $table) {
            $table->index('interview_date', 'idx_interviews_date');
            $table->index('mode', 'idx_interviews_mode');
        });

        // ── instructors ───────────────────────────────────────────────────────
        // `status` is used to find pending/approved instructors in whereHas queries.
        // `update_request_status` is checked to flag pending review items.
        Schema::table('instructors', function (Blueprint $table) {
            $table->index('status', 'idx_instructors_status');
            $table->index('update_request_status', 'idx_instructors_update_request_status');
        });

        // ── instructor_credentials ────────────────────────────────────────────
        // `status` used in whereHas for evaluating instructor credentials.
        // `type` used to filter by credential type (EMS, TM1, NTTC, BOSH).
        Schema::table('instructor_credentials', function (Blueprint $table) {
            $table->index('status', 'idx_instructor_creds_status');
            $table->index('type', 'idx_instructor_creds_type');
        });

        // ── organization_profiles ─────────────────────────────────────────────
        // `name` is used in search/display queries for FATPro organization listing.
        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->index('name', 'idx_org_profiles_name');
        });

        // ── pending_registrations ─────────────────────────────────────────────
        // `expires_at` is used to prune/validate expired tokens.
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->index('expires_at', 'idx_pending_reg_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_profile_type');
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_admin_profiles_division_role');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_type');
            $table->dropIndex('idx_applications_submitted_at');
            $table->dropIndex('idx_applications_handled_by');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex('idx_app_docs_status');
            $table->dropIndex('idx_app_docs_application_status');
        });

        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->dropIndex('idx_status_logs_app_created');
            $table->dropIndex('idx_status_logs_status_id');
        });

        Schema::table('accreditations', function (Blueprint $table) {
            $table->dropIndex('idx_accreditations_status');
            $table->dropIndex('idx_accreditations_validity_date');
            $table->dropIndex('idx_accreditations_user_status');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex('idx_interviews_date');
            $table->dropIndex('idx_interviews_mode');
        });

        Schema::table('instructors', function (Blueprint $table) {
            $table->dropIndex('idx_instructors_status');
            $table->dropIndex('idx_instructors_update_request_status');
        });

        Schema::table('instructor_credentials', function (Blueprint $table) {
            $table->dropIndex('idx_instructor_creds_status');
            $table->dropIndex('idx_instructor_creds_type');
        });

        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_org_profiles_name');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropIndex('idx_pending_reg_expires_at');
        });
    }
};
