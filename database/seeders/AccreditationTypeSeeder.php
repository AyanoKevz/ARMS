<?php

namespace Database\Seeders;

use App\Models\AccreditationType;
use Illuminate\Database\Seeder;

class AccreditationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Practitioners',
            'Consultant',
            'Work and Environment Measurement Providers',
            'Construction Heavy Equipment Testing Organizations',
            'Safety Training Organizations',
            'Safety Consultancy Organizations',
            'First Aid Training Providers',
        ];

        foreach ($types as $name) {
            AccreditationType::firstOrCreate(['name' => $name]);
        }
    }
}
