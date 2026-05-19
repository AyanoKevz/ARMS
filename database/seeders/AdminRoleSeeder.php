<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['Evaluator', 'Verifier'];

        foreach ($roles as $name) {
            AdminRole::firstOrCreate([
                'name' => $name
            ]);
        }
    }
}
