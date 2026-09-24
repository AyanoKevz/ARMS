<?php

namespace Database\Seeders;

use App\Models\PtrDocumentType;
use Illuminate\Database\Seeder;

class PostTrainingReportSeeder extends Seeder
{
    public function run(): void
    {
        // ── Post Training Report Document Types ───────────────────────────────
        // All seven are required on every submission, in four flavours:
        //   encoded — the Directory of Participants, keyed in row by row
        //   roster  — the instructors, carried over from the NTC and amendable
        //   file    — four scanned PDFs
        //   link    — the training video, which is far too large to upload
        $documentTypes = [
            [
                'name'                => 'Directory of Participants',
                'code'                => 'DIRECTORY',
                'entry_type'          => 'encoded',
                'accepted_extensions' => null,
                'sort_order'          => 1,
            ],
            [
                'name'                => 'List of Instructors Who Conducted the Training',
                'code'                => 'INSTRUCTORS',
                'entry_type'          => 'roster',
                'accepted_extensions' => null,
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
            [
                'name'                => 'Link to the Training Video',
                'code'                => 'VIDEO',
                'entry_type'          => 'link',
                'accepted_extensions' => null,
                'sort_order'          => 7,
            ],
        ];

        foreach ($documentTypes as $docType) {
            PtrDocumentType::updateOrCreate(['code' => $docType['code']], $docType);
        }
    }
}
