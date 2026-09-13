<?php

namespace Database\Seeders;

use App\Models\AccreditationType;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Document groups are scoped to the accreditation type that requires
        // them. Before the Practitioner type existed every group was global,
        // which was harmless while FATPro was the only open application — it is
        // not once a second checklist exists.
        $fatpro       = AccreditationType::where('name', 'First Aid Training Providers')->first();
        $practitioner = AccreditationType::where('name', 'Practitioners')->first();

        $types = [
            // ── First Aid Training Providers (organization) ──────────────
            [
                'name' => 'Legal Requirements to Operate Business',
                'code' => 'LEGAL_REQ',
                'accreditation_type_id' => $fatpro?->id,
            ],
            [
                'name' => 'Training Management and Staff',
                'code' => 'TRAINING_MGMT',
                'accreditation_type_id' => $fatpro?->id,
            ],
            [
                'name' => 'Premises Including Occupational Safety',
                'code' => 'PREMISES_SAFETY',
                'accreditation_type_id' => $fatpro?->id,
            ],
            [
                'name' => 'Policies on Intellectual Property and Data Protection',
                'code' => 'IP_DATA_POLICY',
                'accreditation_type_id' => $fatpro?->id,
            ],
            [
                'name' => 'Quality Assurance and Enhancement',
                'code' => 'QUALITY_ASSURANCE',
                'accreditation_type_id' => $fatpro?->id,
            ],
            [
                'name' => 'Training Equipment and Materials',
                'code' => 'TRAINING_EQUIPMENT',
                'accreditation_type_id' => $fatpro?->id,
            ],

            // ── Practitioners (individual) ───────────────────────────────
            // A single group: the practitioner checklist is seven documents
            // with no sub-grouping in the Bureau form.
            [
                'name' => 'Practitioner Documentary Requirements',
                'code' => 'PRAC_DOCS',
                'accreditation_type_id' => $practitioner?->id,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name'                  => $type['name'],
                    'accreditation_type_id' => $type['accreditation_type_id'],
                ]
            );
        }
    }
}
