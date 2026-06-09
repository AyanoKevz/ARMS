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
        $divisions = ['HCD', 'SCD', 'ECD', 'TPID'];

        foreach ($divisions as $divisionName) {
            Division::firstOrCreate([
                'name' => $divisionName
            ]);
        }

        // Get Admin Role
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        // Get HCD Division
        $hcdDivision = Division::firstOrCreate(['name' => 'HCD']);

        // Get Admin Roles
        $evaluatorRole = \App\Models\AdminRole::firstOrCreate(['name' => 'Evaluator']);
        $verifierRole  = \App\Models\AdminRole::firstOrCreate(['name' => 'Verifier']);

        // Create Admin 1: Evaluator
        $evaluator = User::updateOrCreate(
            ['email' => 'data@oshc.dole.gov.ph'],
            [
                'password'          => Hash::make('Hcd@2026'),
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
        $verifier = User::updateOrCreate(
            ['email' => 'verifier@oshc.com'],
            [
                'password'          => Hash::make('Hcd@2026'),
                'role_id'           => $adminRole->id,
                'profile_type'      => 'Individual',
                'user_photo'        => 'images/profile_picture/default_photo.jpg',
                'email_verified_at' => now(),
            ]
        );

        AdminProfile::updateOrCreate(
            ['user_id' => $verifier->id],
            [
                'division_id'   => $hcdDivision->id,
                'first_name'    => 'HCD',
                'last_name'     => 'Verifier',
                'position'      => 'LSO VI',
                'admin_role_id' => $verifierRole ? $verifierRole->id : null,
            ]
        );
    }
}
