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

class TestApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // We will create 2 test registrations
        for ($i = 1; $i <= 2; $i++) {
            $email = "testprovider{$i}@example.com";
            User::where('email', $email)->delete();

            // 1. Create User
            $user = User::create([
                'email' => $email,
                'password' => Hash::make('Password123!'),
                'role_id' => 1, // Applicant role
                'profile_type' => 'Organization',
            ]);

            // 2. Create Organization Profile
            $orgProfile = OrganizationProfile::create([
                'user_id' => $user->id,
                'name' => "Test Provider {$i} Training Center",
                'address' => "123 Test Street, Dummy City {$i}",
                'head_name' => "Head Name {$i}",
                'designation' => 'President',
                'telephone' => '09123456789',
                'email' => "testprovider{$i}@example.com",
            ]);

            // 2.5 Create Authorized Representative
            \App\Models\AuthorizedRepresentative::create([
                'organization_profile_id' => $orgProfile->id,
                'full_name' => "Authorized Rep {$i}",
                'position' => 'Manager',
                'contact_number' => '09987654321',
                'email' => "authrep{$i}@example.com",
            ]);

            // 3. Create Application (FATPro - New Registration Only)
            $year = date('Y');
            $sequence = str_pad(mt_rand(1, 9999), 6, '0', STR_PAD_LEFT);
            $application = Application::create([
                'user_id' => $user->id,
                'accreditation_type_id' => 7, // FATPro
                'application_type' => 'new', // New Registration
                'tracking_number' => "ARMS{$year}-{$sequence}",
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
                'first_name' => "John {$i}",
                'middle_name' => 'Test',
                'last_name' => 'Doe',
                'service_agreement_path' => "dummy_files/service_agreement_{$i}.pdf",
                'status' => 'pending',
            ]);

            // 7. Create Instructor Credentials
            $credentialTypes = ['EMS', 'TM1', 'NTTC', 'BOSH'];
            foreach ($credentialTypes as $type) {
                InstructorCredential::create([
                    'instructor_id' => $instructor->id,
                    'type' => $type,
                    'number' => strtoupper(Str::random(8)),
                    'issued_date' => Carbon::now()->subMonths(6),
                    'validity_date' => Carbon::now()->addYears(2),
                    'training_dates' => 'Jan 1-5, 2026',
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
            'password' => Hash::make('Password123!'),
            'role_id' => 1,
            'profile_type' => 'Organization',
        ]);

        // 2. Create Organization Profile
        $accOrgProfile = OrganizationProfile::create([
            'user_id' => $accUser->id,
            'name' => "Accredited Provider Training Center",
            'address' => "456 Excellence Blvd, Safety City",
            'head_name' => "Dr. Safety Doe",
            'designation' => 'Director',
            'telephone' => '09123456789',
            'email' => $accEmail,
        ]);

        // 2.5 Create Authorized Representative
        \App\Models\AuthorizedRepresentative::create([
            'organization_profile_id' => $accOrgProfile->id,
            'full_name' => "Safety Officer Doe",
            'position' => 'Safety Head',
            'contact_number' => '09987654321',
            'email' => "safetyhead@example.com",
        ]);

        // 3. Create Application
        $accYear = date('Y') - 3;
        $accApplication = Application::create([
            'user_id' => $accUser->id,
            'accreditation_type_id' => 7, // FATPro
            'application_type' => 'new',
            'tracking_number' => "ARMS{$accYear}-000470",
            'submitted_at' => Carbon::now()->subYears(2)->subMonths(9),
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
            'first_name' => "Safety",
            'middle_name' => 'Instructor',
            'last_name' => 'John',
            'service_agreement_path' => "dummy_files/service_agreement_acc.pdf",
            'status' => 'approved',
        ]);

        // 7. Create Approved Instructor Credentials
        $credentialTypes = ['EMS', 'TM1', 'NTTC', 'BOSH'];
        foreach ($credentialTypes as $type) {
            InstructorCredential::create([
                'instructor_id' => $accInstructor->id,
                'type' => $type,
                'number' => strtoupper(Str::random(8)),
                'issued_date' => Carbon::now()->subMonths(30),
                'validity_date' => Carbon::now()->addMonths(6),
                'training_dates' => 'Jan 1-5, 2024',
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
        $accInterview->save();

        // 9. Create Accreditation (Expires in 3 months)
        \App\Models\Accreditation::create([
            'user_id' => $accUser->id,
            'application_id' => $accApplication->id,
            'accreditation_type_id' => 7, // FATPro
            'accreditation_number' => '235-' . Carbon::now()->subYears(2)->subMonths(9)->addWeeks(2)->format('ymd') . '-047',
            'date_of_accreditation' => Carbon::now()->subYears(2)->subMonths(9)->addWeeks(2)->toDateString(),
            'validity_date' => Carbon::now()->addMonths(3)->toDateString(),
            'status' => 'active',
        ]);
    }
}
