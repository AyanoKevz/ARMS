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

            // ── Type 1: Legal Requirements to Operate Business ─────────────────
            // 7 File inputs
            'LEGAL_REQ' => [
                ['name' => 'Business Permit / Mayor\'s Permit',                 'code' => 'LEGAL_01', 'input_type' => 'file'],
                ['name' => 'SEC / DTI / CDA Registration Certificate',          'code' => 'LEGAL_02', 'input_type' => 'file'],
                ['name' => 'BIR Certificate of Registration',                   'code' => 'LEGAL_03', 'input_type' => 'file'],
                ['name' => 'SSS / PhilHealth / Pag-IBIG Registration',          'code' => 'LEGAL_04', 'input_type' => 'file'],
                ['name' => 'Organizational Chart',                              'code' => 'LEGAL_05', 'input_type' => 'file'],
                ['name' => 'Proof of Office / Training Facility Ownership/Lease','code' => 'LEGAL_06', 'input_type' => 'file'],
                ['name' => 'List of Current Trainers and Staff',                'code' => 'LEGAL_07', 'input_type' => 'file'],
            ],

            // ── Type 2: Training Management and Staff ──────────────────────────
            // 3 File inputs
            'TRAINING_MGMT' => [
                ['name' => 'Training Program / Course Outline',                 'code' => 'TRAIN_01', 'input_type' => 'file'],
                ['name' => 'Trainer\'s Certificates / Credentials',             'code' => 'TRAIN_02', 'input_type' => 'file'],
                ['name' => 'Staff Job Descriptions or Employment Contracts',    'code' => 'TRAIN_03', 'input_type' => 'file'],
            ],

            // ── Type 3: Premises Including Occupational Safety ─────────────────
            // 7 File + 1 Date = 8 inputs
            'PREMISES_SAFETY' => [
                ['name' => 'Floor Plan of Training Facility',                   'code' => 'PREM_01', 'input_type' => 'file'],
                ['name' => 'Occupancy Permit',                                  'code' => 'PREM_02', 'input_type' => 'file'],
                ['name' => 'Fire Safety Inspection Certificate',                'code' => 'PREM_03', 'input_type' => 'file'],
                ['name' => 'Sanitary Permit',                                   'code' => 'PREM_04', 'input_type' => 'file'],
                ['name' => 'Emergency Evacuation Plan',                         'code' => 'PREM_05', 'input_type' => 'file'],
                ['name' => 'Electrical Inspection Certificate',                 'code' => 'PREM_06', 'input_type' => 'file'],
                ['name' => 'Photos of Training Premises',                       'code' => 'PREM_07', 'input_type' => 'file'],
                ['name' => 'Date of Last Safety Inspection',                    'code' => 'PREM_DATE', 'input_type' => 'date'],
            ],

            // ── Type 4: Policies on Intellectual Property and Data Protection ──
            // 1 Text + 2 File = 3 inputs
            'IP_DATA_POLICY' => [
                ['name' => 'Name of Data Protection Officer',                   'code' => 'IP_DPO_NAME', 'input_type' => 'text'],
                ['name' => 'Data Privacy Policy Document',                      'code' => 'IP_01', 'input_type' => 'file'],
                ['name' => 'Intellectual Property Policy Document',             'code' => 'IP_02', 'input_type' => 'file'],
            ],

            // ── Type 5: Quality Assurance and Enhancement ──────────────────────
            // 9 File inputs
            'QUALITY_ASSURANCE' => [
                ['name' => 'Quality Management Manual / Handbook',              'code' => 'QA_01', 'input_type' => 'file'],
                ['name' => 'Internal Audit Reports',                            'code' => 'QA_02', 'input_type' => 'file'],
                ['name' => 'Trainee Feedback / Evaluation Forms',               'code' => 'QA_03', 'input_type' => 'file'],
                ['name' => 'Post-Training Evaluation Results',                  'code' => 'QA_04', 'input_type' => 'file'],
                ['name' => 'Certificates Issued to Graduates (sample)',         'code' => 'QA_05', 'input_type' => 'file'],
                ['name' => 'Annual Report or Operations Report',                'code' => 'QA_06', 'input_type' => 'file'],
                ['name' => 'List of Completed Trainings / Batches',             'code' => 'QA_07', 'input_type' => 'file'],
                ['name' => 'Corrective Action Plan (if applicable)',            'code' => 'QA_08', 'input_type' => 'file'],
                ['name' => 'Continuous Improvement Documentation',              'code' => 'QA_09', 'input_type' => 'file'],
            ],

            // ── Type 6: Training Equipment and Materials ───────────────────────
            // 1 File input
            'TRAINING_EQUIPMENT' => [
                ['name' => 'Inventory of Training Equipment and Materials',     'code' => 'EQUIP_01', 'input_type' => 'file'],
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
