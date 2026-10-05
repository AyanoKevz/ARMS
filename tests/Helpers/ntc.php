<?php

/*
|--------------------------------------------------------------------------
| NTC test fixtures
|--------------------------------------------------------------------------
|
| Building an accredited FATPro with an eligible instructor takes a user, an
| application, an approved status log, an accreditation, an instructor person,
| an instructor record and three credentials. More than one feature needs that,
| so it lives here rather than in whichever test file happened to want it first.
|
| Loaded by tests/Pest.php, which makes these available to every test.
|
*/

use App\Models\Accreditation;
use App\Models\AdminProfile;
use App\Models\AdminRole;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\Division;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\InstructorPerson;
use App\Models\Role;
use App\Models\User;

/**
 * An accredited FATPro with one instructor who clears every eligibility gate.
 *
 * @return array{0: User, 1: Instructor, 2: Application}
 */
function ntcFixture(string $suffix = '01', array $instructorOverrides = []): array
{
    $applicant = User::forceCreate([
        'email'        => "ntc_days_{$suffix}@example.com",
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Applicant'])->id,
        'profile_type' => 'Organization',
    ]);

    $typeId = \App\Models\AccreditationType::firstOrCreate(
        ['name' => 'First Aid Training Providers']
    )->id;

    $application = Application::create([
        'user_id'               => $applicant->id,
        'accreditation_type_id' => $typeId,
        'application_type'      => 'new',
        'tracking_number'       => "ARMS-DAYS-{$suffix}",
    ]);

    ApplicationStatusLog::create([
        'application_id' => $application->id,
        'status_id'      => ApplicationStatus::firstOrCreate(['name' => 'Approved'])->id,
    ]);

    Accreditation::create([
        'user_id'               => $applicant->id,
        'application_id'        => $application->id,
        'accreditation_type_id' => $typeId,
        'accreditation_number'  => "FATPRO-DAYS-{$suffix}",
        'date_of_accreditation' => now()->subMonths(2)->format('Y-m-d'),
        'validity_date'         => now()->addYears(2)->format('Y-m-d'),
        'status'                => 'active',
    ]);

    $person = InstructorPerson::create([
        'user_id'    => $applicant->id,
        'first_name' => 'Rosa',
        'last_name'  => "Trainer{$suffix}",
    ]);

    $instructor = Instructor::create(array_merge([
        'user_id'              => $applicant->id,
        'application_id'       => $application->id,
        'instructor_person_id' => $person->id,
        'first_name'           => 'Rosa',
        'last_name'            => "Trainer{$suffix}",
        'cv_path'              => 'dummy_files/cv.pdf',
        'cv_status'            => 'approved',
        'status'               => 'approved',
    ], $instructorOverrides));

    foreach (['EMS', 'TM1', 'NTTC'] as $type) {
        InstructorCredential::create([
            'instructor_id' => $instructor->id,
            'type'          => $type,
            'number'        => "{$type}-{$suffix}",
            'validity_date' => now()->addYear()->format('Y-m-d'),
            'status'        => 'approved',
        ]);
    }

    return [$applicant, $instructor, $application];
}

/** A Training Evaluator who can act on the admin NTC page. */
function ntcEvaluator(string $suffix): User
{
    $evaluator = User::forceCreate([
        'email'        => "ntc_evaluator_{$suffix}@example.com",
        'password'     => bcrypt('password'),
        'role_id'      => Role::firstOrCreate(['name' => 'Admin'])->id,
        'profile_type' => 'Individual',
    ]);

    AdminProfile::create([
        'user_id'       => $evaluator->id,
        'division_id'   => Division::firstOrCreate(['name' => 'HCD'])->id,
        'first_name'    => 'Tess',
        'last_name'     => 'Evaluator',
        'position'      => 'LSO III',
        'admin_role_id' => AdminRole::firstOrCreate(['name' => 'Training Evaluator'])->id,
    ]);

    return $evaluator;
}
