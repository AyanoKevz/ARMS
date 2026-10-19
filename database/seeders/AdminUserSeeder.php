<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\AdminProfile;
use App\Models\Division;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Divisions FIRST
        $divisions = ['HCD', 'SCD', 'ECD', 'TPID', 'Accreditation'];

        foreach ($divisions as $divisionName) {
            Division::firstOrCreate([
                'name' => $divisionName
            ]);
        }

        // Get Admin Role
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        // Get Divisions
        $hcdDivision = Division::firstOrCreate(['name' => 'HCD']);
        $accreditationDivision = Division::firstOrCreate(['name' => 'Accreditation']);

        // Get Admin Roles
        $evaluatorRole = \App\Models\AdminRole::firstOrCreate(['name' => 'Evaluator']);
        $verifierRole  = \App\Models\AdminRole::firstOrCreate(['name' => 'Verifier']);
        $teamLeadRole  = \App\Models\AdminRole::firstOrCreate(['name' => 'Team Lead']);
        $trainingEvaluatorRole = \App\Models\AdminRole::firstOrCreate(['name' => 'Training Evaluator']);

        // Create Admin 1: Evaluator
        $evaluator = $this->seedAdmin(
            ['email' => 'data@oshc.dole.gov.ph'],
            [
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $evaluator->id],
            [
                'division_id'   => $hcdDivision->id,
                'first_name'    => 'HCD',
                'last_name'     => 'Evaluator',
                'position'      => 'LSO III',
                'admin_role_id' => $evaluatorRole ? $evaluatorRole->id : null,
            ]
        );

        // Create Admin 2: Verifier
        $verifier = $this->seedAdmin(
            ['email' => 'oshc1987accreditation@gmail.com'],
            [
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $verifier->id],
            [
                'division_id'   => $accreditationDivision->id,
                'first_name'    => 'Accreditation',
                'last_name'     => 'Verifier',
                'position'      => 'LSO VI',
                'admin_role_id' => $verifierRole ? $verifierRole->id : null,
            ]
        );

        // Create Admin 3: Verifier (Queenie Francisco)
        $verifierQueenie = $this->seedAdmin(
            ['email' => 'queeniefrancisco2002@gmail.com'],
            [
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $verifierQueenie->id],
            [
                'division_id'   => $accreditationDivision->id,
                'first_name'    => 'Queenie',
                'last_name'     => 'Francisco',
                'position'      => 'LSO VI',
                'admin_role_id' => $verifierRole ? $verifierRole->id : null,
            ]
        );

        // Create Admin 4: Team Lead (HCD Arms 2026)
        $teamLead = $this->seedAdmin(
            ['email' => 'hcdarms2026@gmail.com'],
            [
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $teamLead->id],
            [
                'division_id'   => $hcdDivision->id,
                'first_name'    => 'HCD',
                'last_name'     => 'Team Lead',
                'position'      => 'LSO III',
                'admin_role_id' => $teamLeadRole ? $teamLeadRole->id : null,
            ]
        );

        // Create Admin 5: Training Evaluator (OSHC Devs)
        $trainingEvaluator = $this->seedAdmin(
            ['email' => 'oshcdevs@gmail.com'],
            [
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $trainingEvaluator->id],
            [
                'division_id'   => $hcdDivision->id,
                'first_name'    => 'OSHC',
                'last_name'     => 'Training Evaluator',
                'position'      => 'LSO III',
                'admin_role_id' => $trainingEvaluatorRole ? $trainingEvaluatorRole->id : null,
            ]
        );

        // Create Admin 6: Evaluator (Queenie 2)
        $evaluator2 = $this->seedAdmin(
            ['email' => 'queenief102702@gmail.com'],
            [
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $evaluator2->id],
            [
                'division_id'   => $hcdDivision->id,
                'first_name'    => 'Queenie',
                'last_name'     => 'Evaluator',
                'position'      => 'LSO III',
                'admin_role_id' => $evaluatorRole ? $evaluatorRole->id : null,
            ]
        );
    }

    /**
     * Create or refresh one seeded admin account.
     *
     * The password is set ONLY when the account is first created. Re-running
     * the seeder on a live system (php artisan db:seed) must never put back a
     * password an admin has since changed.
     *
     * The initial password comes from SEED_ADMIN_PASSWORD in .env, so the real
     * one need not live in the repository. Without it the old default is used,
     * which keeps a fresh install loggable-into — change it after first login.
     */
    private function seedAdmin(array $match, array $attributes): User
    {
        $user = User::firstOrNew($match);
        $user->fill($attributes);

        if (!$user->exists) {
            $user->password = Hash::make(config('app.seed_admin_password') ?: 'Hcd@2026');
        }

        $user->save();

        return $user;
    }
}
