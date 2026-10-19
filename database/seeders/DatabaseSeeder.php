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
            PostTrainingReportSeeder::class,
        ]);

        // Sample FATPros and applications, with known passwords. Only for a
        // developer machine and the test suite — never on the live site, where
        // real users would see them and anyone could log in as them.
        if (app()->environment(['local', 'testing'])) {
            $this->call(TestApplicationSeeder::class);
        } else {
            $this->command?->warn('Skipped TestApplicationSeeder (APP_ENV=' . app()->environment() . ').');
        }
    }
}
