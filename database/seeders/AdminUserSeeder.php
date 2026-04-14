<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Admin')->first();

        $admin = User::firstOrCreate(
            ['email' => 'hcd@osch.com'],
            [
                'password' => Hash::make('Hcd@2026'),
                'role_id'  => $adminRole->id,
            ]
        );

        AdminProfile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'first_name' => 'HCD',
                'last_name'  => 'Admin',
                'position'   => 'Evaluator',
            ]
        );
    }
}
