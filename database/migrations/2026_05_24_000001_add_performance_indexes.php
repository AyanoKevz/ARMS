<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance and Foreign Key Indexes Migration
 *
 * Adds explicit indexes for foreign key constraints and heavily queried columns across all tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users ─────────────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->index('role_id', 'idx_users_role_id');
            $table->index('profile_type', 'idx_users_profile_type');
        });

        // ── admin_profiles ────────────────────────────────────────────────────
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->index('user_id', 'idx_admin_profiles_user_id');
            $table->index('division_id', 'idx_admin_profiles_division_id');
            $table->index('admin_role_id', 'idx_admin_profiles_admin_role_id');
            $table->index(['division_id', 'admin_role_id'], 'idx_admin_profiles_division_role');
        });

        // ── pending_admins ────────────────────────────────────────────────────
        Schema::table('pending_admins', function (Blueprint $table) {
            $table->index('admin_role_id', 'idx_pending_admins_role_id');
            $table->index('division_id', 'idx_pending_admins_division_id');
        });

        // ── individual_profiles ───────────────────────────────────────────────
        Schema::table('individual_profiles', function (Blueprint $table) {
            $table->index('user_id', 'idx_ind_profiles_user_id');
        });

        // ── organization_profiles ─────────────────────────────────────────────
        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->index('user_id', 'idx_org_profiles_user_id');
            $table->index('name', 'idx_org_profiles_name');
        });

        // ── authorized_representatives ────────────────────────────────────────
        Schema::table('authorized_representatives', function (Blueprint $table) {
            $table->index('organization_profile_id', 'idx_auth_reps_org_id');
        });

        // ── applications ──────────────────────────────────────────────────────
        Schema::table('applications', function (Blueprint $table) {
            $table->index('user_id', 'idx_applications_user_id');
            $table->index('accreditation_type_id', 'idx_applications_accreditation_type_id');
            $table->index('application_type', 'idx_applications_type');
            $table->index('submitted_at', 'idx_applications_submitted_at');
            $table->index('handled_by_admin_id', 'idx_applications_handled_by');
        });

        // ── document_fields ───────────────────────────────────────────────────
        Schema::table('document_fields', function (Blueprint $table) {
            $table->index('document_type_id', 'idx_doc_fields_type_id');
        });

        // ── application_documents ─────────────────────────────────────────────
        Schema::table('application_documents', function (Blueprint $table) {
            $table->index('application_id', 'idx_app_docs_application_id');
            $table->index('document_field_id', 'idx_app_docs_document_field_id');
            $table->index('user_document_id', 'idx_app_docs_user_document_id');
            $table->index('status', 'idx_app_docs_status');
            $table->index(['application_id', 'status'], 'idx_app_docs_application_status');
        });

        // ── application_status_logs ───────────────────────────────────────────
        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->index('application_id', 'idx_app_status_logs_application_id');
            $table->index('updated_by', 'idx_app_status_logs_updated_by');
            $table->index(['application_id', 'created_at'], 'idx_status_logs_app_created');
            $table->index('status_id', 'idx_status_logs_status_id');
        });

        // ── accreditations ────────────────────────────────────────────────────
        Schema::table('accreditations', function (Blueprint $table) {
            $table->index('user_id', 'idx_accreditations_user_id');
            $table->index('application_id', 'idx_accreditations_app_id');
            $table->index('accreditation_type_id', 'idx_accreditations_type_id');
            $table->index('status', 'idx_accreditations_status');
            $table->index('validity_date', 'idx_accreditations_validity_date');
            $table->index(['user_id', 'status'], 'idx_accreditations_user_status');
        });

        // ── interviews ────────────────────────────────────────────────────────
        Schema::table('interviews', function (Blueprint $table) {
            $table->index('application_id', 'idx_interviews_application_id');
            $table->index('interview_date', 'idx_interviews_date');
            $table->index('mode', 'idx_interviews_mode');
        });

        // ── instructors ───────────────────────────────────────────────────────
        Schema::table('instructors', function (Blueprint $table) {
            $table->index('user_id', 'idx_instructors_user_id');
            $table->index('application_id', 'idx_instructors_application_id');
            $table->index('status', 'idx_instructors_status');
            $table->index('update_request_status', 'idx_instructors_update_request_status');
        });

        // ── instructor_credentials ────────────────────────────────────────────
        Schema::table('instructor_credentials', function (Blueprint $table) {
            $table->index('instructor_id', 'idx_instructor_creds_instructor_id');
            $table->index('status', 'idx_instructor_creds_status');
            $table->index('type', 'idx_instructor_creds_type');
        });

        // ── pending_registrations ─────────────────────────────────────────────
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->index('expires_at', 'idx_pending_reg_expires_at');
        });

        // ── application_payments (if created) ─────────────────────────────────
        if (Schema::hasTable('application_payments')) {
            Schema::table('application_payments', function (Blueprint $table) {
                $table->index('application_id', 'idx_app_payments_application_id');
            });
        }

        // ── pct_entries (if created) ──────────────────────────────────────────
        if (Schema::hasTable('pct_entries')) {
            Schema::table('pct_entries', function (Blueprint $table) {
                $table->index('application_id', 'idx_pct_entries_application_id');
            });
        }

        // ── NTC Reports & Documents (if created) ──────────────────────────────
        if (Schema::hasTable('ntc_reports')) {
            Schema::table('ntc_reports', function (Blueprint $table) {
                $table->index('accreditation_id', 'idx_ntc_reports_accreditation_id');
                $table->index('ntc_training_type_id', 'idx_ntc_reports_type_id');
                $table->index('ntc_training_mode_id', 'idx_ntc_reports_mode_id');
                $table->index('acknowledged_by', 'idx_ntc_reports_acknowledged_by');
            });
        }

        if (Schema::hasTable('ntc_documents')) {
            Schema::table('ntc_documents', function (Blueprint $table) {
                $table->index('ntc_document_type_id', 'idx_ntc_docs_document_type_id');
                if (Schema::hasColumn('ntc_documents', 'evaluated_by')) {
                    $table->index('evaluated_by', 'idx_ntc_docs_evaluated_by');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_id');
            $table->dropIndex('idx_users_profile_type');
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_admin_profiles_user_id');
            $table->dropIndex('idx_admin_profiles_division_id');
            $table->dropIndex('idx_admin_profiles_admin_role_id');
            $table->dropIndex('idx_admin_profiles_division_role');
        });

        Schema::table('pending_admins', function (Blueprint $table) {
            $table->dropIndex('idx_pending_admins_role_id');
            $table->dropIndex('idx_pending_admins_division_id');
        });

        Schema::table('individual_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_ind_profiles_user_id');
        });

        Schema::table('organization_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_org_profiles_user_id');
            $table->dropIndex('idx_org_profiles_name');
        });

        Schema::table('authorized_representatives', function (Blueprint $table) {
            $table->dropIndex('idx_auth_reps_org_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_user_id');
            $table->dropIndex('idx_applications_accreditation_type_id');
            $table->dropIndex('idx_applications_type');
            $table->dropIndex('idx_applications_submitted_at');
            $table->dropIndex('idx_applications_handled_by');
        });

        Schema::table('document_fields', function (Blueprint $table) {
            $table->dropIndex('idx_doc_fields_type_id');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex('idx_app_docs_application_id');
            $table->dropIndex('idx_app_docs_document_field_id');
            $table->dropIndex('idx_app_docs_user_document_id');
            $table->dropIndex('idx_app_docs_status');
            $table->dropIndex('idx_app_docs_application_status');
        });

        Schema::table('application_status_logs', function (Blueprint $table) {
            $table->dropIndex('idx_app_status_logs_application_id');
            $table->dropIndex('idx_app_status_logs_updated_by');
            $table->dropIndex('idx_status_logs_app_created');
            $table->dropIndex('idx_status_logs_status_id');
        });

        Schema::table('accreditations', function (Blueprint $table) {
            $table->dropIndex('idx_accreditations_user_id');
            $table->dropIndex('idx_accreditations_app_id');
            $table->dropIndex('idx_accreditations_type_id');
            $table->dropIndex('idx_accreditations_status');
            $table->dropIndex('idx_accreditations_validity_date');
            $table->dropIndex('idx_accreditations_user_status');
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropIndex('idx_interviews_application_id');
            $table->dropIndex('idx_interviews_date');
            $table->dropIndex('idx_interviews_mode');
        });

        Schema::table('instructors', function (Blueprint $table) {
            $table->dropIndex('idx_instructors_user_id');
            $table->dropIndex('idx_instructors_application_id');
            $table->dropIndex('idx_instructors_status');
            $table->dropIndex('idx_instructors_update_request_status');
        });

        Schema::table('instructor_credentials', function (Blueprint $table) {
            $table->dropIndex('idx_instructor_creds_instructor_id');
            $table->dropIndex('idx_instructor_creds_status');
            $table->dropIndex('idx_instructor_creds_type');
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropIndex('idx_pending_reg_expires_at');
        });

        if (Schema::hasTable('application_payments')) {
            Schema::table('application_payments', function (Blueprint $table) {
                $table->dropIndex('idx_app_payments_application_id');
            });
        }

        if (Schema::hasTable('pct_entries')) {
            Schema::table('pct_entries', function (Blueprint $table) {
                $table->dropIndex('idx_pct_entries_application_id');
            });
        }

        if (Schema::hasTable('ntc_reports')) {
            Schema::table('ntc_reports', function (Blueprint $table) {
                $table->dropIndex('idx_ntc_reports_accreditation_id');
                $table->dropIndex('idx_ntc_reports_type_id');
                $table->dropIndex('idx_ntc_reports_mode_id');
                $table->dropIndex('idx_ntc_reports_acknowledged_by');
            });
        }

        if (Schema::hasTable('ntc_documents')) {
            Schema::table('ntc_documents', function (Blueprint $table) {
                $table->dropIndex('idx_ntc_docs_document_type_id');
                if (Schema::hasColumn('ntc_documents', 'evaluated_by')) {
                    $table->dropIndex('idx_ntc_docs_evaluated_by');
                }
            });
        }
    }
};
