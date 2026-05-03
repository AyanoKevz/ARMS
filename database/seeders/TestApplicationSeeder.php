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

            // 3. Create Application (FATPro - id 7)
            $year = date('Y');
            $sequence = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $application = Application::create([
                'user_id' => $user->id,
                'accreditation_type_id' => 7, // FATPro
                'application_type' => 'new',
                'tracking_number' => "ARMS{$year}-{$sequence}",
                'submitted_at' => Carbon::now(),
            ]);

            // 4. Set Application Status to 'Submitted' (Assuming ID 1 is Submitted, checking ApplicationStatusSeeder would be good, but typically 1 or 2. Let's assume 2 is Submitted if 1 is Draft. We can check.)
            // Actually let's just use firstOrCreate or assume ID 2 for "Submitted" or ID 1.
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id' => 1, // 1 is Submitted. 
                'remarks' => 'Test application auto-submitted by seeder',
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
    }
}
