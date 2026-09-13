<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Practitioner Accreditation Schema
 *
 * The Practitioner application is an *individual* CV rather than an organization
 * dossier, so it needs two things FATPro never did:
 *
 *   1. A much wider personal profile (civil status, licences, three contact
 *      numbers, workplace demographics).
 *   2. Ten repeatable CV sections — education, licences, work history, trainings
 *      attended, lectures given, skills, awards, examinations, memberships and
 *      character references.
 *
 * Every repeatable row hangs off (user_id, application_id) the same way
 * `instructors` does, so a renewal writes a fresh set of rows against the new
 * application while the previous submission stays intact for the evaluator.
 *
 * Indexes live here rather than in add_performance_indexes because these tables
 * are created *after* that migration — its Schema::hasTable guards would be
 * false on a fresh migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Widen individual_profiles ──────────────────────────────────
        // Shared by every individual accreditation type (Practitioner today,
        // Consultant next), so these sit on the profile rather than in a
        // practitioner-only table. `address` remains the City Address line
        // already collected for individual registrations.
        Schema::table('individual_profiles', function (Blueprint $table) {
            $table->string('civil_status')->nullable()->after('sex');
            $table->string('citizenship')->nullable()->after('birthday');
            $table->decimal('height_cm', 5, 2)->nullable()->after('citizenship');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('height_cm');
            $table->string('religion')->nullable()->after('weight_kg');
            $table->string('blood_type')->nullable()->after('religion');

            $table->text('home_provincial_address')->nullable()->after('address');
            $table->text('business_address')->nullable()->after('home_provincial_address');

            $table->string('tin_no')->nullable()->after('business_address');
            $table->string('prc_license_no')->nullable()->after('tin_no');
            $table->string('sss_gsis_no')->nullable()->after('prc_license_no');

            $table->string('contact_number')->nullable()->after('sss_gsis_no');
            $table->string('home_telephone_no')->nullable()->after('contact_number');
            $table->string('company_telephone_no')->nullable()->after('home_telephone_no');

            $table->string('type_of_industry')->nullable()->after('company_telephone_no');
            // Free-text spillover for the "Other" choice in the industry select.
            $table->string('type_of_industry_other')->nullable()->after('type_of_industry');
        });

        // ── Workplace character & demographics (1:1 with the profile) ───
        Schema::create('individual_workplace_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('individual_profile_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('hazard_level')->nullable();       // Hazardous | Non-Hazardous
            $table->unsignedInteger('employees_male')->nullable();
            $table->unsignedInteger('employees_female')->nullable();
            // Stored rather than derived: the form auto-sums the two counts, but
            // keeping the total lets a later revision report a headcount that was
            // never broken down by sex.
            $table->unsignedInteger('total_workforce_size')->nullable();

            $table->string('psic_code')->nullable();
            $table->string('region_of_employment');           // required on the form
            $table->string('geo_code')->nullable();
            $table->string('zip_code')->nullable();

            $table->timestamps();
        });

        // ── Per-application practitioner scalars & declarations ─────────
        Schema::create('practitioner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('total_osh_experience_years')->default(0);

            // The five yes/no disclosures. Nullable so an in-progress record can
            // tell "not answered yet" from an explicit "No".
            $table->boolean('has_pending_admin_case')->nullable();
            $table->boolean('has_pending_criminal_case')->nullable();
            $table->boolean('has_crime_conviction')->nullable();
            $table->boolean('has_admin_offense_conviction')->nullable();
            $table->boolean('has_forced_separation')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'application_id'], 'uniq_prac_profile_user_app');
        });

        // ── 1. Educational attainment ───────────────────────────────────
        Schema::create('practitioner_educations', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('degree');
            $table->text('school_name_address')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->text('awards_honors')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_educ_user_app');
        });

        // ── 2. Official professional licences ───────────────────────────
        Schema::create('practitioner_professional_licenses', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('license_type');
            $table->string('prc_license_no')->nullable();
            $table->date('date_issued')->nullable();
            $table->string('validity_period')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_lic_user_app');
        });

        // ── 3. Work experience ──────────────────────────────────────────
        Schema::create('practitioner_work_experiences', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('company');
            $table->string('designation')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            // Derived from the date range on the client, but stored as text so an
            // ongoing post can read "3 yrs, 2 mos (present)".
            $table->string('length_of_service')->nullable();
            $table->string('appointment_status')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_work_user_app');
        });

        // ── 4. OSH trainings/seminars attended ──────────────────────────
        Schema::create('practitioner_trainings', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('title');
            $table->string('conducted_by')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->decimal('no_of_hours', 6, 2)->nullable();
            $table->string('venue')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_train_user_app');
        });

        // ── 5. OSH lectures conducted (as resource speaker) ─────────────
        Schema::create('practitioner_lectures', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('topic');
            $table->string('conducted_for')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->decimal('no_of_hours', 6, 2)->nullable();
            $table->string('venue')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_lect_user_app');
        });

        // ── 6. Skills / expertise / specialization ──────────────────────
        Schema::create('practitioner_skills', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('trade_occupation');
            $table->string('field_of_expertise')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('years_of_experience')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_skill_user_app');
        });

        // ── 7. Awards / achievements / recognitions ─────────────────────
        Schema::create('practitioner_awards', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('title');
            $table->string('issued_by')->nullable();
            $table->date('date_issued')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_award_user_app');
        });

        // ── 8. Examinations / eligibilities passed ──────────────────────
        Schema::create('practitioner_examinations', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('title');
            $table->string('given_by')->nullable();
            $table->unsignedSmallInteger('year_taken')->nullable();
            $table->string('rating')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_exam_user_app');
        });

        // ── 9. Memberships / affiliations ───────────────────────────────
        Schema::create('practitioner_memberships', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('organization');
            $table->string('designation')->nullable();
            $table->string('validity')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_memb_user_app');
        });

        // ── 10. Character references (minimum of three) ─────────────────
        Schema::create('practitioner_references', function (Blueprint $table) {
            $this->ownerColumns($table);
            $table->string('full_name');
            $table->string('designation')->nullable();
            $table->text('company_address')->nullable();
            $table->string('contact_number')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'application_id'], 'idx_prac_ref_user_app');
        });

        // ── Scope the documentary checklist per accreditation type ──────
        // document_types was global while FATPro was the only open type. Adding
        // the Practitioner checklist without this column would surface seven
        // practitioner uploads inside the FATPro form and the HCD evaluation
        // screens. NULL keeps a type global.
        Schema::table('document_types', function (Blueprint $table) {
            $table->foreignId('accreditation_type_id')
                ->nullable()
                ->after('code')
                ->constrained()
                ->cascadeOnDelete();
        });

        // ── Staging column for the unverified registration ──────────────
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->json('practitioner_data')->nullable()->after('instructors_data');
            // Profile extras + the ten CV sections, flushed into the tables above
            // once the applicant clicks the email verification link.
        });
    }

    /**
     * Owner columns shared by every repeatable CV section.
     *
     * sort_order preserves the order the applicant entered rows in — work history
     * and trainings are captured most-recent-first, which `created_at` cannot
     * reproduce once a row is edited.
     */
    private function ownerColumns(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('application_id')->nullable()->constrained()->cascadeOnDelete();
        $table->unsignedSmallInteger('sort_order')->default(0);
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('practitioner_data');
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accreditation_type_id');
        });

        Schema::dropIfExists('practitioner_references');
        Schema::dropIfExists('practitioner_memberships');
        Schema::dropIfExists('practitioner_examinations');
        Schema::dropIfExists('practitioner_awards');
        Schema::dropIfExists('practitioner_skills');
        Schema::dropIfExists('practitioner_lectures');
        Schema::dropIfExists('practitioner_trainings');
        Schema::dropIfExists('practitioner_work_experiences');
        Schema::dropIfExists('practitioner_professional_licenses');
        Schema::dropIfExists('practitioner_educations');
        Schema::dropIfExists('practitioner_profiles');
        Schema::dropIfExists('individual_workplace_profiles');

        Schema::table('individual_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'civil_status', 'citizenship', 'height_cm', 'weight_kg', 'religion',
                'blood_type', 'home_provincial_address', 'business_address', 'tin_no',
                'prc_license_no', 'sss_gsis_no', 'contact_number', 'home_telephone_no',
                'company_telephone_no', 'type_of_industry', 'type_of_industry_other',
            ]);
        });
    }
};
