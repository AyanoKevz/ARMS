<?php

namespace Database\Seeders;

use App\Models\ApplicationStatus;
use Illuminate\Database\Seeder;

class ApplicationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'Submitted',
            'Under Evaluation',
            'For Update',
            'Scheduled for Interview',
            'Approved',
            'Rejected',
        ];

        foreach ($statuses as $name) {
            ApplicationStatus::firstOrCreate(['name' => $name]);
        }
    }
}
