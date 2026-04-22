<?php

namespace Database\Seeders;

use App\Models\DocumentField;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [

            // ── Type 1: Legal Requirements to Operate Business
            'LEGAL_REQ' => [
                ['name' => 'DOLE Registration',           'code' => 'LEGAL_01', 'input_type' => 'file'],
                ['name' => 'Business Registration',       'code' => 'LEGAL_02', 'input_type' => 'file'],
                ['name' => 'Articles of Incorporation',   'code' => 'LEGAL_03', 'input_type' => 'file'],
                ['name' => 'Mayor\'s Permit',             'code' => 'LEGAL_04', 'input_type' => 'file'],
                ['name' => 'BIR Registration & TIN',      'code' => 'LEGAL_05', 'input_type' => 'file'],
                ['name' => 'DOLE clearance',              'code' => 'LEGAL_06', 'input_type' => 'file'],
                ['name' => 'Lease/Ownership Agreement',   'code' => 'LEGAL_07', 'input_type' => 'file'],
            ],

            // ── Type 2: Training Management and Staff
            'TRAINING_MGMT' => [
                ['name' => 'Organizational Chart',        'code' => 'TRAIN_01', 'input_type' => 'file'],
                ['name' => 'TESDA Certificate',           'code' => 'TRAIN_02', 'input_type' => 'file'],
                ['name' => 'Training Monitoring',         'code' => 'TRAIN_03', 'input_type' => 'file'],
            ],

            // ── Type 3: Premises Including Occupational Safety
            'PREMISES_SAFETY' => [
                ['name' => 'Location Map',                'code' => 'PREM_01', 'input_type' => 'file'],
                ['name' => 'Site Floor Plan',             'code' => 'PREM_02', 'input_type' => 'file'],
                ['name' => 'OSH Policy & Program',        'code' => 'PREM_03', 'input_type' => 'file'],
                ['name' => 'Decontamination Procedures',  'code' => 'PREM_04', 'input_type' => 'file'],
                ['name' => 'Safety Officers List',        'code' => 'PREM_05', 'input_type' => 'file'],
                ['name' => 'First-Aiders List',           'code' => 'PREM_06', 'input_type' => 'file'],
                ['name' => 'First-Aider Certificate',      'code' => 'PREM_07', 'input_type' => 'file'],
                ['name' => 'Certificate Validity Date',   'code' => 'PREM_DATE', 'input_type' => 'date'],
            ],

            // ── Type 4: Policies on Intellectual Property and Data Protection
            'IP_DATA_POLICY' => [
                ['name' => 'Data Protection Officer',     'code' => 'IP_DPO_NAME', 'input_type' => 'text'],
                ['name' => 'Data Privacy Policy',         'code' => 'IP_01',       'input_type' => 'file'],
                ['name' => 'Intellectual Property Policy','code' => 'IP_02',       'input_type' => 'file'],
            ],

            // ── Type 5: Quality Assurance and Enhancement
            'QUALITY_ASSURANCE' => [
                ['name' => 'Course Review Procedures',    'code' => 'QA_01', 'input_type' => 'file'],
                ['name' => 'Test Results Summary',        'code' => 'QA_02', 'input_type' => 'file'],
                ['name' => 'Evaluation Summary',          'code' => 'QA_03', 'input_type' => 'file'],
                ['name' => 'Assessment Tools',             'code' => 'QA_04', 'input_type' => 'file'],
                ['name' => 'Participant Directory Template','code' => 'QA_05', 'input_type' => 'file'],
                ['name' => 'Attendance Sheet Template',   'code' => 'QA_06', 'input_type' => 'file'],
                ['name' => 'Emergency First Aid Manual',  'code' => 'QA_07', 'input_type' => 'file'],
                ['name' => 'Occupational First Aid Manual','code' => 'QA_08', 'input_type' => 'file'],
                ['name' => 'Standard First Aid Manual',   'code' => 'QA_09', 'input_type' => 'file'],
            ],

            // ── Type 6: Training Equipment and Materials
            'TRAINING_EQUIPMENT' => [
                ['name' => 'Equipment & Materials List',  'code' => 'EQUIP_01', 'input_type' => 'file'],
            ],
        ];

        foreach ($fields as $typeCode => $typeFields) {
            $docType = DocumentType::where('code', $typeCode)->first();
            if (! $docType) {
                continue;
            }

            foreach ($typeFields as $field) {
                DocumentField::firstOrCreate(
                    ['code' => $field['code']],
                    [
                        'document_type_id' => $docType->id,
                        'name'             => $field['name'],
                        'input_type'       => $field['input_type'],
                    ]
                );
            }
        }
    }
}
