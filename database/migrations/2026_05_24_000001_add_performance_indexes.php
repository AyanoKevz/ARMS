<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Indexes Migration
 *
 * Adds indexes for heavily queried columns that nothing else already covers.
 *
 * Deliberately NOT here: a plain index on a foreign key column. MySQL creates
 * one automatically for every foreign key when the table is created, so a
 * second copy only costs disk and slows every INSERT/UPDATE. Likewise a
 * single-column index whose column already leads a composite index below —
 * MySQL uses the leftmost column of a composite on its own just as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ─────────────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->index('profile_type', 'idx_users_profile_type');
        });

        // ── admin_profiles ────────────────────────────────────────────────────
        Schema::table('admin_profiles', function (Blueprint $table) {
            // "Admins of this division with this role" — the evaluator lookups.
            $table->index(['division_id', 'admin_role_id'], 'idx_admin_profiles_division_role');
        });

        // ── organization_profiles ─────────────────────────────────────────────
        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->index('name', 'idx_org_profiles_name');
        });

        // ── applications ──────────────────────────────────────────────────────
        Schema::table('applications', function (Blueprint $table) {
            $table->index('submitted_at', 'idx_applications_submitted_at');
            $table->index(['user_id', 'application_type'], 'idx_apps_user_type');
            // Also serves application_type on its own, and the dashboard's
            // per-year date range on created_at.
            $table->index(['application_type', 'created_at'], 'idx_apps_type_created');
        });

        // ── application_documents ─────────────────────────────────────────────
        Schema::table('application_documents', function (Blueprint $table) {
            $table->index('status', 'idx_app_docs_status');
            $table->index(['application_id', 'status'], 'idx_app_docs_application_status');
        });

        // ── application_status_logs ───────────────────────────────────────────
        // The "latest status" lookups (MAX(id) per application) run on almost
        // every application list; these composites are what keep them fast.
        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->index(['application_id', 'created_at'], 'idx_status_logs_app_created');
            $table->index(['application_id', 'id'], 'idx_status_logs_app_id');
            $table->index(['application_id', 'status_id'], 'idx_status_logs_app_status');
        });

        // ── accreditations ────────────────────────────────────────────────────
        Schema::table('accreditations', function (Blueprint $table) {
            $table->index('validity_date', 'idx_accreditations_validity_date');
            // The dashboard's "accredited per month" chart filters by this
            // date alone, without a status.
            $table->index('date_of_accreditation', 'idx_accreditations_date');
            $table->index(['user_id', 'status'], 'idx_accreditations_user_status');
            // Also serves status on its own.
            $table->index(['status', 'date_of_accreditation'], 'idx_accred_status_date');
        });

        // ── interviews ────────────────────────────────────────────────────────
        Schema::table('interviews', function (Blueprint $table) {
            $table->index('interview_date', 'idx_interviews_date');
            $table->index('mode', 'idx_interviews_mode');
            $table->index(['application_id', 'interview_date'], 'idx_interviews_app_date');
        });

        // ── instructors ───────────────────────────────────────────────────────
        Schema::table('instructors', function (Blueprint $table) {
            $table->index('status', 'idx_instructors_status');
            $table->index('update_request_status', 'idx_instructors_update_request_status');
        });

        // ── instructor_credentials ────────────────────────────────────────────
        // instructor_id is covered by unique(instructor_id, type) and status by
        // the (status, validity_date) index, both in the create migration.
        Schema::table('instructor_credentials', function (Blueprint $table) {
            $table->index('type', 'idx_instructor_creds_type');
        });

        // ── pending_registrations ─────────────────────────────────────────────
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->index('expires_at', 'idx_pending_reg_expires_at');
        });

        // ── Foreign key columns (PostgreSQL / SQLite only) ────────────────────
        // MySQL indexes every foreign key on its own; PostgreSQL — the live
        // Supabase database — does not. There an unindexed foreign key makes
        // every "children of this parent" lookup a full table scan, so the
        // ones not already led by a composite above are indexed explicitly.
        // On MySQL they would only duplicate the automatic index.
        foreach ($this->foreignKeyIndexes() as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column, "idx_fk_{$table}_{$column}");
                }
            });
        }

        // ── Tables created after this migration ───────────────────────────────
        // Nothing for application_payments, pct_entries, ntc_reports,
        // ntc_documents, practitioner_* or the post training tables lives here.
        // They are created by later migrations, so their indexes are declared
        // inline in their own create migrations instead.
    }

    /**
     * Foreign key columns that need an index of their own, keyed by table —
     * on a database that does not create one automatically. Columns that lead
     * a composite or unique index above (or in their create migration) are
     * left out: that index already serves them.
     *
     * @return array<string, array<int, string>>
     */
    private function foreignKeyIndexes(): array
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return [];
        }

        return [
            'users'                      => ['role_id'],
            'admin_profiles'             => ['user_id', 'admin_role_id'],
            'pending_admins'             => ['admin_role_id', 'division_id'],
            'individual_profiles'        => ['user_id'],
            'organization_profiles'      => ['user_id'],
            'authorized_representatives' => ['organization_profile_id'],
            'applications'               => ['accreditation_type_id', 'assigned_evaluator_id'],
            'document_fields'            => ['document_type_id'],
            'application_documents'      => ['document_field_id', 'user_document_id'],
            'application_status_logs'    => ['status_id', 'updated_by'],
            'accreditations'             => ['application_id', 'accreditation_type_id'],
            'instructors'                => ['user_id', 'application_id', 'instructor_person_id'],
        ];
    }

    public function down(): void
    {
        // A composite index that starts with a foreign key column is also what
        // MySQL uses to enforce that foreign key — it silently drops its own
        // single-column index once the composite exists. Such a composite can
        // only be dropped after a plain index on the column is put back, hence
        // the index() before each of those dropIndex() calls below.

        foreach ($this->foreignKeyIndexes() as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex("idx_fk_{$table}_{$column}");
                }
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_profile_type');
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->index('division_id');
            $table->dropIndex('idx_admin_profiles_division_role');
        });

        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_org_profiles_name');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_submitted_at');
            $table->index('user_id');
            $table->dropIndex('idx_apps_user_type');
            $table->dropIndex('idx_apps_type_created');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex('idx_app_docs_status');
            $table->index('application_id');
            $table->dropIndex('idx_app_docs_application_status');
        });

        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->index('application_id');
            $table->dropIndex('idx_status_logs_app_created');
            $table->dropIndex('idx_status_logs_app_id');
            $table->dropIndex('idx_status_logs_app_status');
        });

        Schema::table('accreditations', function (Blueprint $table) {
            $table->dropIndex('idx_accreditations_validity_date');
            $table->dropIndex('idx_accreditations_date');
            $table->index('user_id');
            $table->dropIndex('idx_accreditations_user_status');
            $table->dropIndex('idx_accred_status_date');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex('idx_interviews_date');
            $table->dropIndex('idx_interviews_mode');
            $table->index('application_id');
            $table->dropIndex('idx_interviews_app_date');
        });

        Schema::table('instructors', function (Blueprint $table) {
            $table->dropIndex('idx_instructors_status');
            $table->dropIndex('idx_instructors_update_request_status');
        });

        Schema::table('instructor_credentials', function (Blueprint $table) {
            $table->dropIndex('idx_instructor_creds_type');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropIndex('idx_pending_reg_expires_at');
        });
    }
};
