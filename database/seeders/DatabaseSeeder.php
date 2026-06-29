<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,            
            AdminRoleSeeder::class,       
            AdminUserSeeder::class,       
            AccreditationTypeSeeder::class,
            ApplicationStatusSeeder::class,
            DocumentTypeSeeder::class,
            DocumentFieldSeeder::class,
            NtcSeeder::class,
            TestApplicationSeeder::class,
        ]);
    }
}
