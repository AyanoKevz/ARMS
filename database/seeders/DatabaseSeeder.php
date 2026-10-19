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
        // developer machine, the test suite, or a beta server that opts in with
        // SEED_TEST_DATA=true — never on the real launch, where anyone could
        // log in as them.
        if (app()->environment(['local', 'testing']) || config('app.seed_test_data')) {
            $this->call(TestApplicationSeeder::class);
        } else {
            $this->command?->warn('Skipped TestApplicationSeeder (APP_ENV=' . app()->environment() . '). Set SEED_TEST_DATA=true to include it.');
        }
    }
}
