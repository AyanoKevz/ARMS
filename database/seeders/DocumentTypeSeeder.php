<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Legal Requirements to Operate Business',
                'code' => 'LEGAL_REQ',
            ],
            [
                'name' => 'Training Management and Staff',
                'code' => 'TRAINING_MGMT',
            ],
            [
                'name' => 'Premises Including Occupational Safety',
                'code' => 'PREMISES_SAFETY',
            ],
            [
                'name' => 'Policies on Intellectual Property and Data Protection',
                'code' => 'IP_DATA_POLICY',
            ],
            [
                'name' => 'Quality Assurance and Enhancement',
                'code' => 'QUALITY_ASSURANCE',
            ],
            [
                'name' => 'Training Equipment and Materials',
                'code' => 'TRAINING_EQUIPMENT',
            ],
        ];

        foreach ($types as $type) {
            DocumentType::firstOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name']]
            );
        }
    }
}
