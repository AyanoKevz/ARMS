<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentField;
use App\Models\IndividualProfile;
use App\Models\IndividualWorkplaceProfile;
use App\Models\PractitionerAward;
use App\Models\PractitionerEducation;
use App\Models\PractitionerExamination;
use App\Models\PractitionerLecture;
use App\Models\PractitionerMembership;
use App\Models\PractitionerProfessionalLicense;
use App\Models\PractitionerProfile;
use App\Models\PractitionerReference;
use App\Models\PractitionerSkill;
use App\Models\PractitionerTraining;
use App\Models\PractitionerWorkExperience;
use App\Models\User;
use App\Models\UserDocument;
use App\Support\PhLocations;
use App\Support\PractitionerForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * OSH Practitioner and OSH Consultant submission: validation, staging,
 * persistence and the generated DOLE-BWC AF-PCN-A1 application form. The two
 * types share all of it; only their documentary requirements differ.
 *
 * Registration happens in two requests, like FATPro: POST /register stages
 * everything on the pending registration, and the email verification link
 * turns it into real rows. Field names match
 * 2026_09_13_000001_create_practitioner_tables.php and the landing partials
 * in resources/views/landing/partials/practitioner.
 */
class PractitionerApplicationService
{
    /** individual_profiles columns from Step 3 — Personal Information, shared with every individual type. */
    public const CORE_FIELDS = ['first_name', 'middle_name', 'last_name', 'suffix', 'sex', 'birthday', 'region', 'city', 'address'];

    /** individual_profiles columns collected by the practitioner form. */
    public const PROFILE_FIELDS = [
        'civil_status', 'citizenship', 'religion', 'height_cm', 'weight_kg', 'blood_type',
        'home_provincial_address', 'business_address',
        'tin_no', 'prc_license_no', 'sss_gsis_no',
        'contact_number', 'home_telephone_no', 'company_telephone_no',
        'type_of_industry', 'type_of_industry_other',
    ];

    /** individual_workplace_profiles columns. */
    public const WORKPLACE_FIELDS = [
        'hazard_level', 'employees_male', 'employees_female', 'total_workforce_size',
        'psic_code', 'region_of_employment', 'geo_code', 'zip_code',
    ];

    /** The five yes/no disclosures on page 3. */
    public const DECLARATIONS = [
        'has_pending_admin_case', 'has_pending_criminal_case', 'has_crime_conviction',
        'has_admin_offense_conviction', 'has_forced_separation',
    ];

    /**
     * The "give details" line under each disclosure, and the question(s) it
     * belongs to. Required, and kept, only when one of them is answered Yes;
     * the paper form gives the two pending-case questions one shared line.
     */
    public const DECLARATION_DETAILS = [
        'pending_case_details'      => ['has_pending_admin_case', 'has_pending_criminal_case'],
        'crime_conviction_details'  => ['has_crime_conviction'],
        'admin_offense_details'     => ['has_admin_offense_conviction'],
        'forced_separation_reasons' => ['has_forced_separation'],
    ];

    /**
     * The ten repeatable CV sections.
     *
     * key => [model, every field, fields required on a row, minimum rows].
     * A section with no required fields is optional: rows left completely
     * blank are dropped instead of stored.
     */
    public const SECTIONS = [
        'education'    => [PractitionerEducation::class, ['degree', 'school_name_address', 'date_from', 'date_to', 'awards_honors'], ['degree', 'school_name_address'], 1],
        'licenses'     => [PractitionerProfessionalLicense::class, ['license_type', 'prc_license_no', 'date_issued', 'validity_period'], [], 0],
        'work'         => [PractitionerWorkExperience::class, ['company', 'designation', 'date_from', 'date_to', 'length_of_service', 'appointment_status'], ['company', 'designation'], 1],
        'trainings'    => [PractitionerTraining::class, ['title', 'conducted_by', 'date_from', 'date_to', 'no_of_hours', 'venue'], ['title', 'conducted_by'], 1],
        'lectures'     => [PractitionerLecture::class, ['topic', 'conducted_for', 'date_from', 'date_to', 'no_of_hours', 'venue'], ['topic', 'conducted_for'], 1],
        'skills'       => [PractitionerSkill::class, ['trade_occupation', 'field_of_expertise', 'description', 'years_of_experience'], ['trade_occupation', 'field_of_expertise'], 1],
        // description: the AF-PCR-A2 renewal form's "Brief Description"; the
        // AF-PCN-A1 has no such column, so a new application leaves it empty.
        'awards'       => [PractitionerAward::class, ['title', 'description', 'issued_by', 'date_issued'], [], 0],
        'examinations' => [PractitionerExamination::class, ['title', 'given_by', 'year_taken', 'rating'], [], 0],
        'memberships'  => [PractitionerMembership::class, ['organization', 'designation', 'validity'], [], 0],
        'references'   => [PractitionerReference::class, ['full_name', 'designation', 'company_address', 'contact_number'], ['full_name', 'designation', 'company_address', 'contact_number'], 3],
    ];

    /** Per-field rules shared by every section that uses the name. */
    private const FIELD_RULES = [
        'date_from'           => ['nullable', 'date'],
        'date_to'             => ['nullable', 'date'],
        'date_issued'         => ['nullable', 'date'],
        'no_of_hours'         => ['nullable', 'numeric', 'min:0', 'max:9999'],
        'years_of_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
        'year_taken'          => ['nullable', 'integer', 'min:1950', 'max:2100'],
        'description'         => ['nullable', 'string', 'max:1000'],
        'school_name_address' => ['nullable', 'string', 'max:1000'],
        'company_address'     => ['nullable', 'string', 'max:1000'],
        'awards_honors'       => ['nullable', 'string', 'max:1000'],
        'length_of_service'   => ['nullable', 'string', 'max:100'],
    ];

    /*
     * OSH Practitioner and OSH Consultant share the AF-PCN-A1 form, its
     * fields and the whole process; only the documentary requirements (and
     * the fee and photo background) differ. Keyed by config
     * accreditation.types key.
     */

    /**
     * Documentary requirements each type must upload. The rest of its
     * checklist is optional: PRAC_03 (previous employer) and CONS_06 (PRC ID,
     * if applicable).
     */
    public const REQUIRED_DOCUMENTS = [
        'practitioner' => ['PRAC_01', 'PRAC_02', 'PRAC_04', 'PRAC_05', 'PRAC_06', 'PRAC_07'],
        'consultant'   => ['CONS_01', 'CONS_02', 'CONS_03', 'CONS_04', 'CONS_05'],
    ];

    /**
     * Documentary requirements of a renewal (AF-PCR-A2), the same for OSH
     * Practitioner and OSH Consultant. Item 1, the most recent 1" x 1"
     * picture, is a form field printed on the form, as on a new application.
     * The code's number is the item's number on the checklist.
     *
     * code => [title, note, when it is required: 'always' | 'employer_changed']
     *
     * Not seeded as document fields yet: renewals cannot be submitted online
     * until their process is built.
     */
    public const RENEWAL_DOCUMENTS = [
        'PCR_02' => ['Summary of Accomplishments on OSH',
            'Original summary of your accomplishments, duly certified by your immediate supervisor and employer on company letterhead.', 'always'],
        'PCR_03' => ['Certificate of Accreditation (last issued)',
            'Photocopy of your last Certificate of Accreditation.', 'always'],
        'PCR_04' => ['Certificates of Attendance / Participation',
            'OSH-related trainings and seminars attended since your last accreditation.', 'always'],
        'PCR_05' => ['Proof of Accomplishment or Participation in OSH',
            'e.g. reports on inspection, accident investigation, OSH programs and activities.', 'always'],
        'PCR_06' => ['Certificate of Employment',
            'Indicating your name, position and date of appointment at your present position, on the official letterhead of the company.', 'employer_changed'],
        'PCR_07' => ['Actual Duties and Responsibilities',
            'At your present position, on the official letterhead of the company, signed by your Immediate Supervisor and the Personnel Manager or an authorized official.', 'employer_changed'],
    ];

    /** Document field the generated AF-PCN-A1 is filed under, so it is evaluated with the uploads. */
    public const APPLICATION_FORM_CODES = [
        'practitioner' => 'PRAC_00',
        'consultant'   => 'CONS_00',
    ];

    /** "practitioner" / "consultant" for an accreditation type id, null for any other type. */
    public static function typeKey(?int $accreditationTypeId): ?string
    {
        $key = array_search((int) $accreditationTypeId, array_map('intval', config('accreditation.types', [])), true);

        return isset(self::APPLICATION_FORM_CODES[$key]) ? $key : null;
    }

    /** Whether the type applies with the AF-PCN-A1 form (OSH Practitioner, OSH Consultant). */
    public static function handles(?int $accreditationTypeId): bool
    {
        return self::typeKey($accreditationTypeId) !== null;
    }

    /** The code the type's generated AF-PCN-A1 is filed under. */
    public static function applicationFormCode(?int $accreditationTypeId): string
    {
        return self::APPLICATION_FORM_CODES[self::typeKey($accreditationTypeId) ?? 'practitioner'];
    }

    /** Whether a document code is a generated AF-PCN-A1, of either type. */
    public static function isApplicationFormCode(?string $code): bool
    {
        return in_array($code, self::APPLICATION_FORM_CODES, true);
    }

    /** @return list<string> the uploads the type must make */
    public static function requiredDocuments(?int $accreditationTypeId): array
    {
        return self::REQUIRED_DOCUMENTS[self::typeKey($accreditationTypeId)] ?? [];
    }

    /**
     * Validation rules for the practitioner part of POST /register.
     *
     * @return array<string, mixed>
     */
    public function rules(Request $request): array
    {
        $opt = config('accreditation');

        $rules = [
            'practitioner'                                 => ['required', 'array'],
            'practitioner.profile.civil_status'            => ['required', Rule::in($opt['civil_statuses'])],
            'practitioner.profile.citizenship'             => ['required', 'string', 'max:255'],
            'practitioner.profile.religion'                => ['nullable', 'string', 'max:255'],
            'practitioner.profile.height_cm'               => ['nullable', 'numeric', 'min:0', 'max:999'],
            'practitioner.profile.weight_kg'               => ['nullable', 'numeric', 'min:0', 'max:999'],
            'practitioner.profile.blood_type'              => ['nullable', Rule::in($opt['blood_types'])],
            'practitioner.profile.home_provincial_address' => ['nullable', 'string', 'max:1000'],
            'practitioner.profile.business_address'        => ['nullable', 'string', 'max:1000'],
            'practitioner.profile.tin_no'                  => ['nullable', 'string', 'max:255'],
            'practitioner.profile.prc_license_no'          => ['nullable', 'string', 'max:255'],
            'practitioner.profile.sss_gsis_no'             => ['nullable', 'string', 'max:255'],
            'practitioner.profile.contact_number'          => ['nullable', 'string', 'max:30'],
            'practitioner.profile.home_telephone_no'       => ['nullable', 'string', 'max:30'],
            'practitioner.profile.company_telephone_no'    => ['nullable', 'string', 'max:30'],
            'practitioner.profile.type_of_industry'        => ['required', Rule::in($opt['industries'])],
            'practitioner.profile.type_of_industry_other'  => ['nullable', 'required_if:practitioner.profile.type_of_industry,Other', 'string', 'max:255'],
            'practitioner.profile.photo'                   => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:15360'],

            'practitioner.workplace.hazard_level'          => ['nullable', Rule::in($opt['hazard_levels'])],
            'practitioner.workplace.employees_male'        => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'practitioner.workplace.employees_female'      => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'practitioner.workplace.psic_code'             => ['nullable', 'string', 'max:255'],
            'practitioner.workplace.region_of_employment'  => ['required', Rule::in($opt['regions'])],
            'practitioner.workplace.geo_code'              => ['nullable', 'string', 'max:255'],
            'practitioner.workplace.zip_code'              => ['nullable', 'string', 'max:20'],

            'practitioner.experience.total_osh_experience_years' => ['required', 'integer', 'min:0', 'max:80'],
        ];

        foreach (self::DECLARATIONS as $field) {
            $rules["practitioner.declarations.{$field}"] = ['required', 'boolean'];
        }

        foreach (self::DECLARATION_DETAILS as $field => $questions) {
            $rules["practitioner.declarations.{$field}"] = [
                'nullable',
                Rule::requiredIf(fn () => $this->answeredYes((array) $request->input('practitioner.declarations', []), $questions)),
                'string',
                'max:1000',
            ];
        }

        foreach (self::SECTIONS as $key => [, $fields, $required, $min]) {
            $rules["practitioner.{$key}"] = $min > 0
                ? ['required', 'array', "min:{$min}"]
                : ['nullable', 'array'];

            foreach ($fields as $field) {
                $base = self::FIELD_RULES[$field] ?? ['nullable', 'string', 'max:255'];

                if (in_array($field, $required, true)) {
                    $base = array_merge(['required'], array_diff($base, ['nullable']));
                }

                $rules["practitioner.{$key}.*.{$field}"] = $base;
            }
        }

        $rules['practitioner.work.*.appointment_status'] = ['nullable', Rule::in($opt['appointment_statuses'])];

        foreach (self::requiredDocuments($request->integer('accreditation_type_id')) as $code) {
            $rules["documents.{$code}"] = ['required', 'file', 'mimes:pdf', 'max:15360'];
        }

        return $rules;
    }

    /**
     * Rules for a corrected application form sent from the tracking page:
     * Steps 3–13 as on registration, the person fields under person[...],
     * and no document uploads. The ID photo is already on file, so a new one
     * is optional.
     *
     * @return array<string, mixed>
     */
    public function correctionRules(Request $request): array
    {
        $rules = array_filter(
            $this->rules($request),
            fn ($key) => ! str_starts_with($key, 'documents.'),
            ARRAY_FILTER_USE_KEY
        );

        $rules['practitioner.profile.photo'] = ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:15360'];

        return $rules + [
            'person'             => ['required', 'array'],
            'person.first_name'  => ['required', 'string', 'max:255'],
            'person.middle_name' => ['nullable', 'string', 'max:255'],
            'person.last_name'   => ['required', 'string', 'max:255'],
            'person.suffix'      => ['nullable', 'string', 'max:20'],
            'person.sex'         => ['required', Rule::in(['Male', 'Female'])],
            'person.birthday'    => ['required', 'date'],
            // The PSGC dropdowns (public/data/ph-locations.json), as on registration.
            'person.region'      => ['required', 'string', Rule::in(PhLocations::regionCodes())],
            'person.city'        => ['required', 'string', 'max:255', PhLocations::cityRule($request->input('person.region'))],
            'person.address'     => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * What the tracking page fills the correction form with: the answers
     * just sent back after a failed validation, otherwise the ones on file.
     *
     * @return array{person: array, profile: array, workplace: array, experience: array, declarations: array, sections: array}
     */
    public function correctionValues(Application $application, array $oldInput = []): array
    {
        if (isset($oldInput['practitioner']) && is_array($oldInput['practitioner'])) {
            $person = [];
            foreach (self::CORE_FIELDS as $field) {
                $person[$field] = $this->clean($oldInput['person'][$field] ?? null);
            }

            return ['person' => $person] + $this->normalize($oldInput['practitioner']);
        }

        // A fresh copy: the tracking page's application comes from the cache.
        ['core' => $core, 'data' => $data] = PractitionerForm::storedValues(
            Application::findOrFail($application->id)
        );

        return ['person' => $core] + $data;
    }

    /**
     * Replace the applicant's answers with a corrected set and generate the
     * AF-PCN-A1 again from them. The tracking page's answer to a rejected
     * application form: it is corrected, not re-uploaded. Input must already
     * have passed correctionRules().
     *
     * A failure leaves everything as it was: the rows roll back and a newly
     * stored photo is removed.
     */
    public function correct(Application $application, array $person, array $input, ?UploadedFile $photo, string $baseDocPath): void
    {
        $data    = $this->normalize($input);
        $prac    = $application->practitionerProfile
            ?? PractitionerProfile::create(['user_id' => $application->user_id, 'application_id' => $application->id]);
        $profile = $application->user->individualProfile;

        $oldPhoto = $prac->photo_path;
        $newPhoto = null;
        if ($photo) {
            $folder   = $oldPhoto ? dirname($oldPhoto) : $baseDocPath;
            $newPhoto = $photo->storeAs(
                $folder,
                'id_photo_' . time() . '.' . strtolower($photo->getClientOriginalExtension() ?: 'jpg'),
                'local'
            );
        }

        try {
            DB::transaction(function () use ($application, $person, $data, $prac, $profile, $newPhoto) {
                $core = [];
                foreach (self::CORE_FIELDS as $field) {
                    $core[$field] = $this->clean($person[$field] ?? null);
                }

                $profile->update($core + array_intersect_key($data['profile'], array_flip(self::PROFILE_FIELDS)));

                IndividualWorkplaceProfile::updateOrCreate(
                    ['individual_profile_id' => $profile->id],
                    array_intersect_key($data['workplace'], array_flip(self::WORKPLACE_FIELDS))
                );

                $prac->update(array_merge(
                    ['total_osh_experience_years' => (int) ($data['experience']['total_osh_experience_years'] ?? 0)],
                    array_intersect_key($data['declarations'], array_flip(self::declarationColumns())),
                    $newPhoto ? ['photo_path' => $newPhoto] : []
                ));

                foreach (self::SECTIONS as $key => [$model, $fields]) {
                    $model::where('application_id', $application->id)->delete();

                    foreach (array_values($data['sections'][$key] ?? []) as $i => $row) {
                        $model::create(array_merge(
                            array_intersect_key($row, array_flip($fields)),
                            [
                                'user_id'        => $application->user_id,
                                'application_id' => $application->id,
                                'sort_order'     => $i,
                            ]
                        ));
                    }
                }

                // The form is built from relations this request may already
                // hold; drop them so it reads the rows just written.
                $application->unsetRelation('user')->unsetRelation('practitionerProfile');
                foreach (array_keys(self::SECTIONS) as $key) {
                    $application->unsetRelation(PractitionerForm::SECTION_RELATIONS[$key]);
                }

                $this->generateApplicationForm($application);
            });
        } catch (\Throwable $e) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            }

            throw $e;
        }

        if ($newPhoto && $oldPhoto && $oldPhoto !== $newPhoto && Storage::disk('local')->exists($oldPhoto)) {
            Storage::disk('local')->delete($oldPhoto);
        }
    }

    /** Friendlier names for the validation messages. */
    public function attributes(): array
    {
        return [
            'person.first_name'                                  => 'first name',
            'person.last_name'                                   => 'last name',
            'person.sex'                                         => 'sex',
            'person.birthday'                                    => 'date of birth',
            'person.region'                                      => 'region',
            'person.city'                                        => 'city / municipality',
            'person.address'                                     => 'full address',
            'practitioner.profile.photo'                         => '1"x1" ID photo',
            'practitioner.profile.civil_status'                  => 'civil status',
            'practitioner.profile.type_of_industry'              => 'type of industry',
            'practitioner.workplace.region_of_employment'        => 'region of employment',
            'practitioner.experience.total_osh_experience_years' => 'total years of OSH experience',
            'practitioner.references'                            => 'character references',
            'practitioner.declarations.pending_case_details'      => 'details of the pending case',
            'practitioner.declarations.crime_conviction_details'  => 'details of the conviction',
            'practitioner.declarations.admin_offense_details'     => 'details of the administrative offense',
            'practitioner.declarations.forced_separation_reasons' => 'reasons for leaving employment',
        ];
    }

    /** practitioner_profiles columns holding the page 3 declarations. */
    public static function declarationColumns(): array
    {
        return array_merge(self::DECLARATIONS, array_keys(self::DECLARATION_DETAILS));
    }

    /** Whether any of $questions is answered Yes ("1" from the radios, or true once normalized). */
    private function answeredYes(array $declarations, array $questions): bool
    {
        foreach ($questions as $question) {
            if (in_array($declarations[$question] ?? null, [true, 1, '1'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean the practitioner input into the stored shape.
     *
     * Blank strings become null and fully blank rows of an optional section are
     * dropped. Used for both the stored pending registration and the "Review
     * Details" preview, so the preview shows exactly what would be saved.
     *
     * @return array{profile: array, workplace: array, experience: array, declarations: array, sections: array}
     */
    public function normalize(array $input): array
    {
        $profile = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $profile[$field] = $this->clean($input['profile'][$field] ?? null);
        }
        if ($profile['type_of_industry'] !== 'Other') {
            $profile['type_of_industry_other'] = null;
        }

        $workplace = [];
        foreach (self::WORKPLACE_FIELDS as $field) {
            $workplace[$field] = $this->clean($input['workplace'][$field] ?? null);
        }

        // Derived on the client but recomputed here: the field is read-only
        // there, which a hand-crafted request can ignore.
        $male   = $workplace['employees_male'];
        $female = $workplace['employees_female'];
        $workplace['total_workforce_size'] = ($male === null && $female === null)
            ? null
            : (int) $male + (int) $female;

        $declarations = [];
        foreach (self::DECLARATIONS as $field) {
            $value = $input['declarations'][$field] ?? null;
            $declarations[$field] = ($value === null || $value === '') ? null : (bool) (int) $value;
        }

        // Details typed before an answer was switched back to No are dropped.
        foreach (self::DECLARATION_DETAILS as $field => $questions) {
            $declarations[$field] = $this->answeredYes($declarations, $questions)
                ? $this->clean($input['declarations'][$field] ?? null)
                : null;
        }

        $sections = [];
        foreach (self::SECTIONS as $key => [, $fields]) {
            $rows = [];
            foreach ((array) ($input[$key] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $clean = [];
                foreach ($fields as $field) {
                    $clean[$field] = $this->clean($row[$field] ?? null);
                }

                if (array_filter($clean, fn ($v) => $v !== null) === []) {
                    continue;
                }

                $rows[] = $clean;
            }
            $sections[$key] = $rows;
        }

        return [
            'profile'      => $profile,
            'workplace'    => $workplace,
            'experience'   => [
                'total_osh_experience_years' => (int) ($input['experience']['total_osh_experience_years'] ?? 0),
            ],
            'declarations' => $declarations,
            'sections'     => $sections,
        ];
    }

    /**
     * Normalize the request and park the ID photo next to the other pending
     * uploads. Stored on pending_registrations.practitioner_data.
     */
    public function stage(Request $request, string $token): array
    {
        $data = $this->normalize((array) $request->input('practitioner', []));

        /** @var UploadedFile|null $photo */
        $photo = $request->file('practitioner.profile.photo');
        $data['photo_path'] = $photo
            ? $photo->storeAs("pending/{$token}", 'photo.' . strtolower($photo->getClientOriginalExtension() ?: 'jpg'), 'local')
            : null;

        return $data;
    }

    /**
     * Write the staged practitioner data for a freshly verified applicant.
     * Runs inside RegistrationController::verify()'s transaction.
     */
    public function persist(array $data, User $user, Application $application, IndividualProfile $profile, string $baseDocPath): void
    {
        $profile->update(array_intersect_key($data['profile'] ?? [], array_flip(self::PROFILE_FIELDS)));

        IndividualWorkplaceProfile::updateOrCreate(
            ['individual_profile_id' => $profile->id],
            array_intersect_key($data['workplace'] ?? [], array_flip(self::WORKPLACE_FIELDS))
        );

        $photoPath = null;
        $staged    = $data['photo_path'] ?? null;
        if ($staged && Storage::disk('local')->exists($staged)) {
            $extension = pathinfo($staged, PATHINFO_EXTENSION) ?: 'jpg';
            $photoPath = "{$baseDocPath}/id_photo_" . time() . ".{$extension}";
            Storage::disk('local')->move($staged, $photoPath);
        }

        PractitionerProfile::create(array_merge(
            [
                'user_id'                    => $user->id,
                'application_id'             => $application->id,
                'total_osh_experience_years' => (int) ($data['experience']['total_osh_experience_years'] ?? 0),
                'photo_path'                 => $photoPath,
            ],
            array_intersect_key($data['declarations'] ?? [], array_flip(self::declarationColumns()))
        ));

        foreach (self::SECTIONS as $key => [$model, $fields]) {
            foreach (array_values($data['sections'][$key] ?? []) as $i => $row) {
                $model::create(array_merge(
                    array_intersect_key($row, array_flip($fields)),
                    [
                        'user_id'        => $user->id,
                        'application_id' => $application->id,
                        'sort_order'     => $i,
                    ]
                ));
            }
        }
    }

    /**
     * Render the AF-PCN-A1 form to PDF and attach it to the application.
     *
     * @return string the stored path on the local disk
     */
    public function generateApplicationForm(Application $application): string
    {
        $form = PractitionerForm::fromApplication($application);

        $pdf = Pdf::loadView('practitioner.form.pdf', ['form' => $form])
            ->setPaper('a4', 'portrait');

        $profile = $application->practitionerProfile;
        $folder  = $profile?->photo_path ? dirname($profile->photo_path) : "applications/{$application->id}";
        $path    = "{$folder}/AF-PCN-A1_{$application->tracking_number}.pdf";

        Storage::disk('local')->put($path, $pdf->output());

        if ($profile) {
            $old = $profile->application_form_path;
            $profile->update(['application_form_path' => $path]);

            if ($old && $old !== $path && Storage::disk('local')->exists($old)) {
                Storage::disk('local')->delete($old);
            }
        }

        $this->attachApplicationForm($application, $path);

        return $path;
    }

    /**
     * The AF-PCN-A1 as one of the application's documents (PRAC_00 for a
     * practitioner, CONS_00 for a consultant).
     *
     * Applications submitted before the form was filed as a document get it
     * attached here; a missing form is generated first. Returns null only
     * when the type's form field has not been seeded.
     */
    public function ensureApplicationFormDocument(Application $application): ?ApplicationDocument
    {
        $existing = $this->applicationFormDocument($application);
        $file     = $existing?->userDocument?->file_path;
        if ($file && Storage::disk('local')->exists($file)) {
            return $existing;
        }

        $path = $application->practitionerProfile?->application_form_path;
        if ($path && Storage::disk('local')->exists($path)) {
            return $this->attachApplicationForm($application, $path);
        }

        if (! $application->practitionerProfile) {
            return $existing;
        }

        $this->generateApplicationForm($application);

        return $this->applicationFormDocument($application);
    }

    public function applicationFormDocument(Application $application): ?ApplicationDocument
    {
        return $application->documents()
            ->with('userDocument')
            ->whereHas('documentField', fn ($q) => $q->where('code', self::applicationFormCode($application->accreditation_type_id)))
            ->first();
    }

    /**
     * Point the form document (PRAC_00 / CONS_00) at $path. An existing
     * document keeps its evaluation status; a new one starts pending like
     * every upload.
     */
    private function attachApplicationForm(Application $application, string $path): ?ApplicationDocument
    {
        $field = DocumentField::where('code', self::applicationFormCode($application->accreditation_type_id))->first();
        if (! $field) {
            return null;
        }

        $userDoc = UserDocument::updateOrCreate(
            ['user_id' => $application->user_id, 'document_field_id' => $field->id],
            ['file_path' => $path, 'value' => null]
        );

        $document = ApplicationDocument::firstOrCreate(
            ['application_id' => $application->id, 'document_field_id' => $field->id],
            ['user_document_id' => $userDoc->id, 'status' => 'pending']
        );

        if ($document->user_document_id !== $userDoc->id) {
            $document->update(['user_document_id' => $userDoc->id]);
        }

        return $document->setRelation('userDocument', $userDoc);
    }

    private function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return ($value === '' || $value === null) ? null : $value;
    }
}
