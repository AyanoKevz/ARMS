<?php

namespace Database\Seeders;

use App\Models\PtrDocumentType;
use Illuminate\Database\Seeder;

class PostTrainingReportSeeder extends Seeder
{
    public function run(): void
    {
        // ── Post Training Report Document Types ───────────────────────────────
        // All six are required on every submission; only the Directory of
        // Participants is filed as a spreadsheet, the rest are scanned PDFs.
        $documentTypes = [
            [
                'name'                => 'Directory of Participants',
                'code'                => 'DIRECTORY',
                'accepted_extensions' => 'xlsx,xls',
                'sort_order'          => 1,
            ],
            [
                'name'                => 'List of Instructors Who Conducted the Training',
                'code'                => 'INSTRUCTORS',
                'accepted_extensions' => 'pdf',
                'sort_order'          => 2,
            ],
            [
                'name'                => 'Actual Program of Training Activities',
                'code'                => 'PROGRAM',
                'accepted_extensions' => 'pdf',
                'sort_order'          => 3,
            ],
            [
                'name'                => 'Scanned Copies of Daily Attendance Sheets',
                'code'                => 'ATTENDANCE',
                'accepted_extensions' => 'pdf',
                'sort_order'          => 4,
            ],
            [
                'name'                => 'Pre- and Post-Test Summary Evaluation',
                'code'                => 'PREPOST',
                'accepted_extensions' => 'pdf',
                'sort_order'          => 5,
            ],
            [
                'name'                => "General and Trainer's Summary Evaluation",
                'code'                => 'EVALUATION',
                'accepted_extensions' => 'pdf',
                'sort_order'          => 6,
            ],
        ];

        foreach ($documentTypes as $docType) {
            PtrDocumentType::updateOrCreate(['code' => $docType['code']], $docType);
        }
    }
}
