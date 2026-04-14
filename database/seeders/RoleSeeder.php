<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Applicant', 'Admin'];

        foreach ($roles as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
    }
}
