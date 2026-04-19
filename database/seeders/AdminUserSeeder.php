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
        $adminRole = Role::where('name', 'Admin')->first();

        // Get HCD Division
        $hcdDivision = Division::where('name', 'HCD')->first();

        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'hcd@osch.com'],
            [
                'password' => Hash::make('Hcd@2026'),
                'role_id'  => $adminRole->id,
            ]
        );

        // Create Admin Profile with Division
        AdminProfile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'division_id' => $hcdDivision->id,
                'first_name' => 'HCD',
                'last_name'  => 'Admin',
                'position'   => 'Evaluator',
            ]
        );
    }
}
