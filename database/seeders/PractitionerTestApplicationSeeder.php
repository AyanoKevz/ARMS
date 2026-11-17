<?php

namespace Database\Seeders;

use App\Models\AccreditationType;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\DocumentField;
use App\Models\IndividualProfile;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\PractitionerApplicationService;
use App\Support\ApplicantStoragePath;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Sample new applications for the Accreditation division: one OSH
 * Practitioner and one OSH Consultant, each waiting in its own
 * "New Application → Pending" list.
 *
 * Unlike the FATPro samples (TestApplicationSeeder), these go through the same
 * save path as a real verified registration — PractitionerApplicationService —
 * so every document opens, the ID photo shows, and the attached AF-PCN-A1 form
 * is generated. Like FATPro, no PCT clock runs until an admin presses Start
 * Evaluation.
 *
 * Applicant logins (password Password123!), both real inboxes so emails from
 * the Accreditation team's actions are actually delivered:
 *   - OSH Practitioner: queeniefrancisco2002@gmail.com
 *   - OSH Consultant:   queenief102702@gmail.com
 *
 * Writes real files to the local disk, so DatabaseSeeder runs it on developer
 * machines and opted-in beta servers only — not in the test suite.
 */
class PractitionerTestApplicationSeeder extends Seeder
{
    public function run(PractitionerApplicationService $practitioners): void
    {
        $role   = Role::firstOrCreate(['name' => 'Applicant']);
        $status = ApplicationStatus::findByName('Submitted');

        // Retired samples — removed if an earlier run created them.
        User::whereIn('email', [
            'testpractitioner1@example.com', 'testpractitioner2@example.com', 'testpractitioner3@example.com',
        ])->delete();

        foreach ($this->applicants() as $sample) {
            $email = $sample['email'];
            User::where('email', $email)->delete();

            $typeId   = (int) config("accreditation.types.{$sample['type']}");
            $typeName = AccreditationType::find($typeId)?->name;
            $fields   = DocumentField::forAccreditationType($typeId)->where('input_type', 'file');

            $submittedAt = $sample['submitted_days_ago'] === 0
                ? Carbon::now()->subMinutes(30)
                : Carbon::now()->subWeekdays($sample['submitted_days_ago'])->setTime(9, 30);

            $user = User::create([
                'email'             => $email,
                'email_verified_at' => $submittedAt,
                'password'          => Hash::make('Password123!'),
                'role_id'           => $role->id,
                'profile_type'      => 'Individual',
            ]);

            $profile = IndividualProfile::create(['user_id' => $user->id] + $sample['core']);

            $application = Application::create([
                'user_id'               => $user->id,
                'accreditation_type_id' => $typeId,
                'application_type'      => 'new',
                'tracking_number'       => $this->trackingNumber(),
                'submitted_at'          => $submittedAt,
            ]);
            $application->forceFill(['created_at' => $submittedAt, 'updated_at' => $submittedAt])->save();

            $docPath = ApplicantStoragePath::documents($typeName, $user->id);
            $applicant = trim($sample['core']['first_name'] . ' ' . $sample['core']['last_name']);

            // Documentary requirements — a small readable PDF for each upload.
            // The AF-PCN-A1 (PRAC_00 / CONS_00) is generated below, not uploaded.
            foreach ($fields as $code => $field) {
                if (in_array($code, $sample['skip_documents'], true)
                    || PractitionerApplicationService::isApplicationFormCode($code)) {
                    continue;
                }

                $path = "{$docPath}/{$code}_{$submittedAt->timestamp}.pdf";
                Storage::disk('local')->put($path, $this->samplePdf($field->name, $applicant, $application->tracking_number));

                $userDoc = UserDocument::create([
                    'user_id'           => $user->id,
                    'document_field_id' => $field->id,
                    'file_path'         => $path,
                ]);

                ApplicationDocument::create([
                    'application_id'    => $application->id,
                    'document_field_id' => $field->id,
                    'user_document_id'  => $userDoc->id,
                    'status'            => 'pending',
                ]);
            }

            // Profile, CV sections and ID photo — staged the way POST /register
            // stages them, then saved the way the verification link saves them.
            $staged = "pending/seed-practitioner-{$user->id}/photo.png";
            Storage::disk('local')->put($staged, $this->samplePhoto($sample['core']['first_name'], $sample['core']['last_name'], $sample['type']));

            $data = $practitioners->normalize($sample['practitioner']);
            $data['photo_path'] = $staged;
            $practitioners->persist($data, $user, $application, $profile, $docPath);
            Storage::disk('local')->deleteDirectory(dirname($staged));

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $status->id,
                'remarks'        => 'Application submitted by applicant after email verification.',
            ])->forceFill(['created_at' => $submittedAt, 'updated_at' => $submittedAt])->save();

            $practitioners->generateApplicationForm($application->fresh());
        }
    }

    /**
     * One applicant per type: the practitioner sparse (only the required
     * fields — the form fills the rest with N/A), the consultant fully filled.
     */
    private function applicants(): array
    {
        $no = [
            'has_pending_admin_case' => '0', 'has_pending_criminal_case' => '0', 'has_crime_conviction' => '0',
            'has_admin_offense_conviction' => '0', 'has_forced_separation' => '0',
        ];

        return [
            [
                'type'               => 'consultant',
                'email'              => 'queenief102702@gmail.com',
                'submitted_days_ago' => 0,
                'skip_documents'     => [],
                'core' => [
                    'first_name' => 'Ana', 'middle_name' => 'Gomez', 'last_name' => 'Reyes', 'sex' => 'Female',
                    'birthday' => '1985-03-14', 'region' => 'NCR', 'city' => 'Quezon City', 'address' => '25 Kalayaan Ave., Diliman',
                ],
                'practitioner' => [
                    'profile' => [
                        'civil_status' => 'Married', 'citizenship' => 'Filipino', 'religion' => 'Roman Catholic',
                        'height_cm' => '158', 'weight_kg' => '54', 'blood_type' => 'O+',
                        'home_provincial_address' => 'Brgy. Poblacion, Lipa City, Batangas',
                        'business_address' => '8F Tower One, Ayala Ave., Makati City',
                        'tin_no' => '123-456-789-000', 'prc_license_no' => '0123456', 'sss_gsis_no' => '34-1234567-8',
                        'contact_number' => '09171234567', 'home_telephone_no' => '8123-4567', 'company_telephone_no' => '8888-1000',
                        'type_of_industry' => 'Manufacturing',
                    ],
                    'workplace' => [
                        'hazard_level' => 'Hazardous', 'employees_male' => '320', 'employees_female' => '180',
                        'psic_code' => 'C1079', 'region_of_employment' => 'NCR — National Capital Region', 'geo_code' => '137404', 'zip_code' => '1101',
                    ],
                    'experience'   => ['total_osh_experience_years' => '12'],
                    'declarations' => $no,
                    'education' => [
                        ['degree' => 'BS Chemical Engineering', 'school_name_address' => 'University of Santo Tomas, España, Manila', 'date_from' => '2005-06-01', 'date_to' => '2010-04-01', 'awards_honors' => 'Cum Laude'],
                        ['degree' => 'Master in Occupational Safety and Health', 'school_name_address' => 'UP Open University, Los Baños', 'date_from' => '2014-08-01', 'date_to' => '2016-05-01', 'awards_honors' => ''],
                    ],
                    'licenses' => [
                        ['license_type' => 'Registered Chemical Engineer', 'prc_license_no' => '0123456', 'date_issued' => '2010-11-20', 'validity_period' => '2025–2028'],
                    ],
                    'work' => [
                        ['company' => 'SafeWorks OSH Consulting Group', 'designation' => 'Senior OSH Consultant', 'date_from' => '2020-03-01', 'date_to' => '', 'length_of_service' => '5 yrs, 8 mos (present)', 'appointment_status' => 'Consultant'],
                        ['company' => 'Pacific Foods Manufacturing Corp.', 'designation' => 'Safety Officer III', 'date_from' => '2014-02-01', 'date_to' => '2020-02-15', 'length_of_service' => '6 yrs', 'appointment_status' => 'Permanent'],
                        ['company' => 'Northern Chemicals Inc.', 'designation' => 'Process Engineer', 'date_from' => '2010-12-01', 'date_to' => '2014-01-15', 'length_of_service' => '3 yrs, 1 mo', 'appointment_status' => 'Permanent'],
                    ],
                    'trainings' => [
                        ['title' => 'Basic Occupational Safety and Health (BOSH)', 'conducted_by' => 'OSHC', 'date_from' => '2016-07-04', 'date_to' => '2016-07-08', 'no_of_hours' => '40', 'venue' => 'OSHC, Quezon City'],
                        ['title' => 'Construction Safety and Health (COSH)', 'conducted_by' => 'OSHC', 'date_from' => '2018-03-05', 'date_to' => '2018-03-09', 'no_of_hours' => '40', 'venue' => 'OSHC, Quezon City'],
                        ['title' => 'Chemical Safety Management', 'conducted_by' => 'DOLE-BWC', 'date_from' => '2021-09-13', 'date_to' => '2021-09-14', 'no_of_hours' => '16', 'venue' => 'Online'],
                    ],
                    'lectures' => [
                        ['topic' => 'Hazard Identification and Risk Assessment', 'conducted_for' => 'Pacific Foods Supervisors', 'date_from' => '2023-05-10', 'date_to' => '2023-05-10', 'no_of_hours' => '4', 'venue' => 'Plant Training Room'],
                    ],
                    'skills' => [
                        ['trade_occupation' => 'Safety Officer', 'field_of_expertise' => 'Process Safety', 'description' => 'HIRAC, permit-to-work systems, chemical handling', 'years_of_experience' => '9'],
                        ['trade_occupation' => 'Chemical Engineer', 'field_of_expertise' => 'Industrial Hygiene', 'description' => 'Workplace exposure monitoring', 'years_of_experience' => '6'],
                    ],
                    'awards' => [
                        ['title' => 'Gawad Kaligtasan at Kalusugan — Outstanding Safety Officer', 'issued_by' => 'DOLE-OSHC', 'date_issued' => '2022-10-28'],
                    ],
                    'examinations' => [
                        ['title' => 'Chemical Engineering Licensure Examination', 'given_by' => 'PRC', 'year_taken' => '2010', 'rating' => '84.50'],
                    ],
                    'memberships' => [
                        ['organization' => 'Safety Organization of the Philippines', 'designation' => 'Member', 'validity' => '2026'],
                    ],
                    'references' => [
                        ['full_name' => 'Engr. Roberto Cruz', 'designation' => 'Plant Manager', 'company_address' => 'Pacific Foods, Valenzuela City', 'contact_number' => '09181112222'],
                        ['full_name' => 'Dr. Liza Ramos', 'designation' => 'Professor', 'company_address' => 'UST, Manila', 'contact_number' => '09192223333'],
                        ['full_name' => 'Atty. Jose Villanueva', 'designation' => 'HR Director', 'company_address' => 'Northern Chemicals, Bulacan', 'contact_number' => '09173334444'],
                    ],
                ],
            ],
            [
                // Only the required fields: the AF-PCN-A1 form fills the rest with N/A.
                'type'               => 'practitioner',
                'email'              => 'queeniefrancisco2002@gmail.com',
                'submitted_days_ago' => 1,
                'skip_documents'     => ['PRAC_03'],
                'core' => [
                    'first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'sex' => 'Male',
                    'birthday' => '1995-11-02', 'region' => 'Region VII', 'city' => 'Cebu City', 'address' => '14 Osmeña Blvd.',
                ],
                'practitioner' => [
                    'profile' => [
                        'civil_status' => 'Single', 'citizenship' => 'Filipino', 'contact_number' => '09221234567',
                        'type_of_industry' => 'Construction',
                    ],
                    'workplace'    => ['region_of_employment' => 'Region VII — Central Visayas'],
                    'experience'   => ['total_osh_experience_years' => '2'],
                    'declarations' => $no,
                    'education'  => [['degree' => 'BS Civil Engineering', 'school_name_address' => 'Cebu Institute of Technology – University']],
                    'work'       => [['company' => 'Visayan Builders Inc.', 'designation' => 'Safety Officer II', 'date_from' => '2023-06-01', 'length_of_service' => '2 yrs, 4 mos (present)']],
                    'trainings'  => [['title' => 'Construction Safety and Health (COSH)', 'conducted_by' => 'OSHC Regional', 'no_of_hours' => '40']],
                    'lectures'   => [['topic' => 'N.A.', 'conducted_for' => 'N.A.']],
                    'skills'     => [['trade_occupation' => 'Safety Officer', 'field_of_expertise' => 'Construction Safety']],
                    'references' => [
                        ['full_name' => 'Engr. Paolo Tan', 'designation' => 'Project Manager', 'company_address' => 'Visayan Builders, Cebu', 'contact_number' => '09175556666'],
                        ['full_name' => 'Grace Uy', 'designation' => 'HR Officer', 'company_address' => 'Visayan Builders, Cebu', 'contact_number' => '09176667777'],
                        ['full_name' => 'Engr. Ramon Lim', 'designation' => 'Instructor', 'company_address' => 'CIT-U, Cebu City', 'contact_number' => '09177778888'],
                    ],
                ],
            ],
        ];
    }

    /** ARMS{year}-{2 letters}{4 digits}, the format the tracking page accepts. */
    private function trackingNumber(): string
    {
        do {
            $number = 'ARMS' . date('Y') . '-' . chr(random_int(65, 90)) . chr(random_int(65, 90))
                . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Application::where('tracking_number', $number)->exists());

        return $number;
    }

    /** A one-page placeholder so "View" opens a real PDF. */
    private function samplePdf(string $document, string $applicant, string $trackingNumber): string
    {
        $html = '<h2>' . e($document) . '</h2>'
            . '<p>Sample document for <strong>' . e($applicant) . '</strong> (' . e($trackingNumber) . ').</p>'
            . '<p>Seeded test data — not a real record.</p>';

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    /**
     * A plain 1"x1"-style ID photo with the applicant's initials, on the
     * background the form asks for: red for a Practitioner, blue for a
     * Consultant.
     */
    private function samplePhoto(string $first, string $last, string $type): string
    {
        $img = imagecreatetruecolor(300, 300);
        imagefill($img, 0, 0, $type === 'consultant'
            ? imagecolorallocate($img, 30, 90, 190)
            : imagecolorallocate($img, 190, 35, 45));

        $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
        $font     = 5;
        $x        = (int) ((300 - imagefontwidth($font) * strlen($initials)) / 2);
        $y        = (int) ((300 - imagefontheight($font)) / 2);
        imagestring($img, $font, $x, $y, $initials, imagecolorallocate($img, 255, 255, 255));

        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }
}
