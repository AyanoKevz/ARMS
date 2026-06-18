<?php

use App\Models\User;
use App\Models\DocumentField;
use App\Models\UserDocument;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Http\Controllers\Applicant\RenewalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Retrieve the accredited user
$user = User::where('email', 'kevin25.cloudspace@gmail.com')->first();
if (!$user) {
    echo "User not found. Run seeder first.\n";
    exit(1);
}

// Delete previous renewal applications for this user so we can test the store logic cleanly
$previousRenewals = Application::where('user_id', $user->id)
    ->whereIn('application_type', ['renewal', 'reinstatement'])
    ->get();
foreach ($previousRenewals as $prev) {
    ApplicationDocument::where('application_id', $prev->id)->delete();
    $prev->delete();
}

echo "User found: " . $user->email . "\n";

// Count current UserDocuments and ApplicationDocuments
$userDocCountBefore = UserDocument::count();
$appDocCountBefore = ApplicationDocument::count();
$appCountBefore = Application::count();

echo "Before renewal submission:\n";
echo "Applications: {$appCountBefore}\n";
echo "UserDocuments: {$userDocCountBefore}\n";
echo "ApplicationDocuments: {$appDocCountBefore}\n";

// Simulate request data for renewal
$requestData = [
    'application_type' => 'renewal',
    'org_name' => 'Accredited Provider Training Center Updated',
    'org_address' => '456 Excellence Blvd, Safety City',
    'head_name' => 'Dr. Safety Doe',
    'designation' => 'Director',
    'telephone' => '0281234567',
    'org_email' => 'kevin25.cloudspace@gmail.com',
    'rep_full_name' => 'Safety Officer Doe',
    'rep_position' => 'Safety Head',
    'rep_contact_number' => '09987654321',
    'rep_email' => 'safetyhead@example.com',
    'documents' => [
        'PREM_DATE' => '2027-06-17',
        'IP_DPO_NAME' => 'John Doe DPO',
    ],
    'instructors' => [
        [
            'first_name' => 'Safety',
            'middle_name' => 'Instructor',
            'last_name' => 'John',
            'credentials' => [
                'EMS' => [
                    'number' => 'EMS-12345',
                    'issued_date' => '2025-01-01',
                    'validity_date' => '2028-01-01',
                ],
                'TM1' => [
                    'number' => 'TM1-12345',
                    'issued_date' => '2025-01-01',
                    'validity_date' => '2028-01-01',
                ],
                'NTTC' => [
                    'number' => 'NTTC-12345',
                    'issued_date' => '2025-01-01',
                    'validity_date' => '2028-01-01',
                ],
                'BOSH' => [
                    'number' => 'BOSH-12345',
                    'validity_date' => '2028-01-01',
                    'training_dates' => 'Jan 1-5, 2025',
                ],
            ]
        ]
    ]
];

// Mock uploaded files for required documents if needed
// Actually, since we want to check what happens, let's run the controller store logic directly or via request.
// Wait, we can log in the user and run store via container or call the method.
Auth::login($user);

// Create request instance with files
use Illuminate\Http\UploadedFile;

$req = new Request();
$req->setMethod('POST');
$req->request->add($requestData);

// Add mocked files for instructors
$files = [
    'instructors' => [
        0 => [
            'service_agreement' => UploadedFile::fake()->create('service_agreement.pdf', 100, 'application/pdf'),
            'credentials' => [
                'EMS' => ['pdf' => UploadedFile::fake()->create('ems.pdf', 100, 'application/pdf')],
                'TM1' => ['pdf' => UploadedFile::fake()->create('tm1.pdf', 100, 'application/pdf')],
                'NTTC' => ['pdf' => UploadedFile::fake()->create('nttc.pdf', 100, 'application/pdf')],
                'BOSH' => ['pdf' => UploadedFile::fake()->create('bosh.pdf', 100, 'application/pdf')],
            ]
        ]
    ]
];
$req->files->add($files);

// Let's mock the files for required docs so validation passes.
// Required files: LEGAL_01 to LEGAL_06, TRAIN_01, TRAIN_03, PREM_01 to PREM_07, IP_01, IP_02, QA_02 to QA_09, EQUIP_01.
// Since these are required, and the user is already accredited, we expect the validation to allow null files if they exist in DB.
// Let's test if we can run it.
try {
    $controller = new RenewalController();
    $response = $controller->store($req);
    echo "Store called successfully!\n";
} catch (\Exception $e) {
    echo "Error calling store: " . $e->getMessage() . "\n";
    if (isset($e->validator)) {
        print_r($e->validator->errors()->toArray());
    }
}

// Check what application documents were created
$latestApp = Application::orderBy('id', 'desc')->first();
echo "Latest Application ID: " . $latestApp->id . " Type: " . $latestApp->application_type . "\n";
foreach ($latestApp->documents()->with('documentField', 'userDocument')->get() as $doc) {
    $code = $doc->documentField->code;
    $filePath = $doc->userDocument?->file_path;
    $value = $doc->userDocument?->value;
    echo "  Doc Field: {$code} | File Path: {$filePath} | Value: {$value}\n";
}
