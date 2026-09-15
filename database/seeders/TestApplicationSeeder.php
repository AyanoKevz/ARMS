<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\OrganizationProfile;
use App\Models\Application;
use App\Models\ApplicationStatusLog;
use App\Models\DocumentField;
use App\Models\UserDocument;
use App\Models\ApplicationDocument;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;

use App\Models\PctEntry;

class TestApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $applicantRole = \App\Models\Role::firstOrCreate(['name' => 'Applicant']);
        $applicantRoleId = $applicantRole->id;

        $fatproType = \App\Models\AccreditationType::firstOrCreate(['name' => 'First Aid Training Providers']);
        $fatproTypeId = $fatproType->id;

        // The HCD Evaluator seeded by AdminUserSeeder (which runs before this one).
        // Applications need an in-charge or the notifications addressed to
        // $application->assignedEvaluator — instructor updates, document
        // re-uploads — are logged as skipped and never sent.
        $evaluatorId = User::where('email', 'data@oshc.dole.gov.ph')->value('id');

        // We will create 2 test registrations
        for ($i = 1; $i <= 2; $i++) {
            $email = "testprovider{$i}@example.com";
            User::where('email', $email)->delete();

            // 1. Create User
            $user = User::create([
                'email' => $email,
                'email_verified_at' => Carbon::now(),
                'password' => Hash::make('Password123!'),
                'role_id' => $applicantRoleId, // Applicant role
                'profile_type' => 'Organization',
            ]);

            // 2. Create Organization Profile
            $orgProfile = OrganizationProfile::create([
                'user_id' => $user->id,
                'name' => "Test Provider {$i} Training Center",
                'address' => "123 Test Street, Dummy City {$i}",
                'head_name' => "Head Name {$i}",
                'head_sex' => ($i % 2 === 0 ? 'Female' : 'Male'),
                'designation' => 'President',
                'telephone' => '0281234567',
                'email' => "testprovider{$i}@example.com",
            ]);

            // 2.5 Create Authorized Representative
            \App\Models\AuthorizedRepresentative::create([
                'organization_profile_id' => $orgProfile->id,
                'full_name' => "Authorized Rep {$i}",
                'rep_sex' => ($i % 2 === 0 ? 'Male' : 'Female'),
                'position' => 'Manager',
                'contact_number' => '09987654321',
                'email' => "authrep{$i}@example.com",
            ]);

            // 3. Create Application (FATPro - New Registration Only)
            $year = date('Y');
            $letters = chr(random_int(65, 90)) . chr(random_int(65, 90));
            $digits = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $application = Application::create([
                'user_id' => $user->id,
                'accreditation_type_id' => $fatproTypeId, // FATPro
                'application_type' => 'new', // New Registration
                'tracking_number' => "ARMS{$year}-{$letters}{$digits}",
                'submitted_at' => Carbon::now(),
            ]);

            // 4. Set Application Status to 'Submitted' (Pending Evaluation)
            $submittedStatus = \App\Models\ApplicationStatus::where('name', 'Submitted')->first();

            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id' => $submittedStatus->id ?? 1,
                'remarks' => 'New registration submitted and awaiting evaluation process.',
            ]);

            // 5. Create Documents
            $documentFields = DocumentField::all();
            foreach ($documentFields as $field) {
                // Create User Document
                $value = null;
                $filePath = null;

                if ($field->input_type === 'file') {
                    $filePath = "dummy_files/test_document_{$field->code}.pdf";
                } elseif ($field->input_type === 'date') {
                    $value = Carbon::now()->addYears(1)->format('Y-m-d');
                } else {
                    $value = "Dummy text for {$field->name}";
                }

                $userDocument = UserDocument::create([
                    'user_id' => $user->id,
                    'document_field_id' => $field->id,
                    'file_path' => $filePath,
                    'value' => $value,
                ]);

                // Create Application Document
                ApplicationDocument::create([
                    'application_id' => $application->id,
                    'document_field_id' => $field->id,
                    'user_document_id' => $userDocument->id,
                    'status' => 'pending', // usually pending review
                ]);
            }

            // 6. Create Instructor
            $instructor = Instructor::create([
                'user_id' => $user->id,
                'application_id' => $application->id,
                'first_name' => "John {$i}",
                'middle_name' => 'Test',
                'last_name' => 'Doe',
                'ins_sex' => ($i % 2 === 0 ? 'Female' : 'Male'),
                'service_agreement_path' => "dummy_files/service_agreement_{$i}.pdf",
                'cv_path' => "dummy_files/instructor_cv_{$i}.pdf",
                'status' => 'pending',
            ]);

            // 7. Create Instructor Credentials
            $credentialTypes = ['EMS', 'TM1', 'NTTC'];
            foreach ($credentialTypes as $type) {
                InstructorCredential::create([
                    'instructor_id' => $instructor->id,
                    'type' => $type,
                    'number' => strtoupper(Str::random(8)),
                    'issued_date' => Carbon::now()->subMonths(6),
                    'validity_date' => Carbon::now()->addYears(2),
                    'pdf_path' => "dummy_files/instructor_{$type}_{$i}.pdf",
                    'status' => 'pending',
                ]);
            }
        }

        // --- SEED ACCREDITED FATPRO WITH NEAR-EXPIRING ACCREDITATION (3 MONTHS FROM NOW) ---
        $accEmail = "kevin25.cloudspace@gmail.com";
        User::where('email', $accEmail)->delete();

        // 1. Create User
        $accUser = User::create([
            'email' => $accEmail,
            'email_verified_at' => Carbon::now(),
            'password' => Hash::make('Password123!'),
            'role_id' => $applicantRoleId,
            'profile_type' => 'Organization',
        ]);

        // 2. Create Organization Profile
        $accOrgProfile = OrganizationProfile::create([
            'user_id' => $accUser->id,
            'name' => "Accredited Provider Training Center",
            'address' => "456 Excellence Blvd, Safety City",
            'head_name' => "Dr. Safety Doe",
            'head_sex' => 'Male',
            'designation' => 'Director',
            'telephone' => '0281234567',
            'email' => $accEmail,
        ]);

        // 2.5 Create Authorized Representative
        \App\Models\AuthorizedRepresentative::create([
            'organization_profile_id' => $accOrgProfile->id,
            'full_name' => "Safety Officer Doe",
            'rep_sex' => 'Female',
            'position' => 'Safety Head',
            'contact_number' => '09987654321',
            'email' => "safetyhead@example.com",
        ]);

        // 3. Create Application
        $accYear = date('Y') - 3;
        $accApplication = Application::create([
            'user_id' => $accUser->id,
            'accreditation_type_id' => $fatproTypeId, // FATPro
            'application_type' => 'new',
            'tracking_number' => "ARMS{$accYear}-000470",
            'submitted_at' => Carbon::now()->subYears(2)->subMonths(9),
            'assigned_evaluator_id' => $evaluatorId,
        ]);

        // 4. Set Application Status logs up to Approved
        $statusNames = ['Submitted', 'Under Evaluation', 'Scheduled for Interview', 'Approved'];
        foreach ($statusNames as $sName) {
            $statusModel = \App\Models\ApplicationStatus::where('name', $sName)->first();
            if ($statusModel) {
                ApplicationStatusLog::create([
                    'application_id' => $accApplication->id,
                    'status_id' => $statusModel->id,
                    'remarks' => "Application reached {$sName} stage.",
                ]);
            }
        }

        // 5. Create Approved Documents
        $documentFields = DocumentField::all();
        foreach ($documentFields as $field) {
            $value = null;
            $filePath = null;

            if ($field->input_type === 'file') {
                $filePath = "dummy_files/test_document_{$field->code}.pdf";
            } elseif ($field->input_type === 'date') {
                $value = Carbon::now()->addYears(1)->format('Y-m-d');
            } else {
                $value = "Approved dummy text for {$field->name}";
            }

            $userDoc = UserDocument::create([
                'user_id' => $accUser->id,
                'document_field_id' => $field->id,
                'file_path' => $filePath,
                'value' => $value,
            ]);

            ApplicationDocument::create([
                'application_id' => $accApplication->id,
                'document_field_id' => $field->id,
                'user_document_id' => $userDoc->id,
                'status' => 'approved',
            ]);
        }

        // 6. Create Approved Instructor
        $accInstructor = Instructor::create([
            'user_id' => $accUser->id,
            'application_id' => $accApplication->id,
            'first_name' => "Safety",
            'middle_name' => 'Instructor',
            'last_name' => 'John',
            'ins_sex' => 'Male',
            'service_agreement_path' => "dummy_files/service_agreement_acc.pdf",
            'cv_path' => "dummy_files/instructor_cv_acc.pdf",
            'status' => 'approved',
        ]);

        // 7. Create Approved Instructor Credentials
        $credentialTypes = ['EMS', 'TM1', 'NTTC'];
        foreach ($credentialTypes as $type) {
            InstructorCredential::create([
                'instructor_id' => $accInstructor->id,
                'type' => $type,
                'number' => strtoupper(Str::random(8)),
                'issued_date' => Carbon::now()->subMonths(30),
                'validity_date' => Carbon::now()->addMonths(6),
                'pdf_path' => "dummy_files/instructor_{$type}_acc.pdf",
                'status' => 'approved',
            ]);
        }

        // 8. Create Interview Record
        $accInterview = new \App\Models\Interview();
        $accInterview->application_id = $accApplication->id;
        $accInterview->interview_date = Carbon::now()->subYears(2)->subMonths(9)->addWeeks(2)->toDateString();
        $accInterview->interview_time = '10:00:00';
        $accInterview->mode = 'online';
        $accInterview->venue = 'https://meet.google.com/kevin-interview-link';
        $accInterview->save();

        // 9. Create Accreditation (Expires in 3 months)
        $accAccreditation = \App\Models\Accreditation::create([
            'user_id' => $accUser->id,
            'application_id' => $accApplication->id,
            'accreditation_type_id' => $fatproTypeId, // FATPro
            'accreditation_number' => '235-' . Carbon::now()->subYears(2)->subMonths(9)->addWeeks(2)->format('ymd') . '-047',
            'date_of_accreditation' => Carbon::now()->subYears(2)->subMonths(9)->addWeeks(2)->toDateString(),
            'validity_date' => Carbon::now()->addMonths(3)->toDateString(),
            'status' => 'active',
            'scanned_certificate' => 'dummy_files/scanned_certificate_acc.pdf',
        ]);

        // 10. Seed PCT entries for accredited application (completed timeline)
        $pctBaseDate = Carbon::now()->subYears(2)->subMonths(9);
        $pctSteps = [
            ['step' => 1, 'name' => 'Submission',               'target' => 3, 'elapsed' => 0,      'offset_days' => 0,   'duration_days' => 0],
            ['step' => 2, 'name' => 'Receipt of Requirements',  'target' => 1, 'elapsed' => 16200,  'offset_days' => 0,   'duration_days' => 0.5],
            ['step' => 3, 'name' => 'Evaluation',               'target' => 5, 'elapsed' => 129600, 'offset_days' => 0.5, 'duration_days' => 4],
            ['step' => 4, 'name' => 'Pending Interview',        'target' => 3, 'elapsed' => 64800,  'offset_days' => 4.5, 'duration_days' => 2],
            ['step' => 5, 'name' => 'Interview',                'target' => 1, 'elapsed' => 6480,   'offset_days' => 6.5, 'duration_days' => 0.5],
            ['step' => 6, 'name' => 'Interview Result',         'target' => 1, 'elapsed' => 3240,   'offset_days' => 7,   'duration_days' => 0.1],
            ['step' => 7, 'name' => 'Recommendation & Payment', 'target' => 5, 'elapsed' => 129600, 'offset_days' => 7.1, 'duration_days' => 5],
            ['step' => 8, 'name' => 'Certificate Issuance',     'target' => 1, 'elapsed' => 16200,  'offset_days' => 12.1,'duration_days' => 0.5],
        ];

        foreach ($pctSteps as $ps) {
            PctEntry::create([
                'application_id' => $accApplication->id,
                'step_name'      => $ps['name'],
                'step_number'    => $ps['step'],
                'target_days'    => $ps['target'],
                'started_at'     => $pctBaseDate->copy()->addDays($ps['offset_days']),
                'completed_at'   => $pctBaseDate->copy()->addDays($ps['offset_days'] + $ps['duration_days']),
                'elapsed_seconds' => $ps['elapsed'],
                'is_active'      => false,
            ]);
        }

        // 11. Acknowledged NTCs so the Post Training Report portal has something
        //     to work on the moment the database is seeded.
        $this->seedAcknowledgedNtcs($accAccreditation);
    }

    /**
     * Seed acknowledged Notices to Conduct for the accredited FATPro.
     *
     * The dates are relative to the seed run, not fixed, so a freshly seeded
     * database always lands in the same testable state:
     *
     *   • EFA ending TODAY  — post training report is open right now
     *   • SFA ended 10 days ago — already past its deadline (overdue path)
     *   • OFA starting in 3 weeks — still upcoming, nothing owed yet
     *
     * Deleting the user cascades accreditations → ntc_reports → ptr rows, so
     * re-seeding never stacks duplicates.
     */
    private function seedAcknowledgedNtcs(\App\Models\Accreditation $accreditation): void
    {
        // firstOrCreate rather than a plain lookup so this still works when the
        // seeder is run on its own, without NtcSeeder having gone first.
        $types = [
            'EFA' => \App\Models\NtcTrainingType::firstOrCreate(['code' => 'EFA'], ['name' => 'Emergency First Aid']),
            'OFA' => \App\Models\NtcTrainingType::firstOrCreate(['code' => 'OFA'], ['name' => 'Occupational First Aid']),
            'SFA' => \App\Models\NtcTrainingType::firstOrCreate(['code' => 'SFA'], ['name' => 'Standard First Aid']),
        ];
        $f2f     = \App\Models\NtcTrainingMode::firstOrCreate(['code' => 'F2F'], ['name' => 'Face to Face']);
        $blended = \App\Models\NtcTrainingMode::firstOrCreate(['code' => 'BLENDED'], ['name' => 'Blended']);

        $today = Carbon::today();

        $plans = [
            [
                // THE one to test with: a one-day Emergency First Aid course
                // finishing today, so the report can be filed immediately.
                'type'       => 'EFA',
                'mode'       => $f2f,
                'venue'      => 'Accredited Provider Training Center, 456 Excellence Blvd, Safety City',
                'start'      => $today->copy(),
                'end'        => $today->copy(),
                'submitted'  => $today->copy()->subDays(21),
                'acked'      => $today->copy()->subDays(18),
            ],
            [
                // Already past its 4-working-day deadline — exercises the
                // overdue badge, the dashboard alert and the overdue email.
                'type'       => 'SFA',
                'mode'       => $f2f,
                'venue'      => 'Safety City Convention Hall',
                'start'      => $today->copy()->subDays(13),
                'end'        => $today->copy()->subDays(10),
                'submitted'  => $today->copy()->subDays(35),
                'acked'      => $today->copy()->subDays(30),
            ],
            [
                // Not yet held — should sit under "Upcoming & Ongoing" with no
                // report owed.
                'type'       => 'OFA',
                'mode'       => $blended,
                'venue'      => 'https://zoom.us/j/900112233',
                'start'      => $today->copy()->addWeeks(3),
                'end'        => $today->copy()->addWeeks(3)->addDay(),
                'submitted'  => $today->copy()->subDays(2),
                'acked'      => $today->copy()->subDay(),
            ],
        ];

        $evaluatorId = User::whereHas('adminProfile.adminRole', function ($q) {
            $q->where('name', 'Training Evaluator');
        })->value('id');

        foreach ($plans as $plan) {
            $ntc = \App\Models\NtcReport::create([
                'accreditation_id'     => $accreditation->id,
                'ntc_training_type_id' => $types[$plan['type']]->id,
                'ntc_training_mode_id' => $plan['mode']->id,
                'venue'                => $plan['venue'],
                'training_start_date'  => $plan['start']->toDateString(),
                'training_end_date'    => $plan['end']->toDateString(),
                'status'               => 'acknowledged',
                'submitted_at'         => $plan['submitted'],
                'acknowledged_at'      => $plan['acked'],
                'acknowledged_by'      => $evaluatorId,
            ]);

            // Both NTC forms, already approved — that is what "acknowledged" means.
            foreach (['RTCMAN' => 'DOLE-OSHC-STO-RTCMan Form', 'PROG' => 'DOLE-OSHC-STO-PROG Form'] as $code => $name) {
                $docType = \App\Models\NtcDocumentType::firstOrCreate(['code' => $code], ['name' => $name]);

                \App\Models\NtcDocument::create([
                    'ntc_report_id'        => $ntc->id,
                    'ntc_document_type_id' => $docType->id,
                    'file_path'            => "dummy_files/ntc_{$ntc->id}_" . strtolower($code) . '.pdf',
                    'original_filename'    => strtolower($code) . '_form.pdf',
                    'mime_type'            => 'application/pdf',
                    'file_size'            => 245760,
                    'uploaded_at'          => $plan['submitted'],
                    'status'               => 'approved',
                    'evaluated_by'         => $evaluatorId,
                    'evaluated_at'         => $plan['acked'],
                ]);
            }

            $this->command?->info(sprintf(
                '  NTC-%s  %s  %s → %s  | post training due %s',
                str_pad($ntc->id, 6, '0', STR_PAD_LEFT),
                str_pad($plan['type'], 3),
                $plan['start']->format('M d'),
                $plan['end']->format('M d'),
                $ntc->postTrainingDeadlineDate()?->format('M d, Y') ?? 'n/a'
            ));
        }
    }
}
