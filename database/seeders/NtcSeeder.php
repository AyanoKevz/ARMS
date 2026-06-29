<?php

namespace Database\Seeders;

use App\Models\NtcDocumentType;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use Illuminate\Database\Seeder;

class NtcSeeder extends Seeder
{
    public function run(): void
    {
        // ── Training Types ────────────────────────────────────────────────────
        $trainingTypes = [
            ['name' => 'Emergency First Aid',      'code' => 'EFA'],
            ['name' => 'Occupational First Aid',   'code' => 'OFA'],
            ['name' => 'Standard First Aid',       'code' => 'SFA'],
        ];

        foreach ($trainingTypes as $type) {
            NtcTrainingType::firstOrCreate(['code' => $type['code']], $type);
        }

        // ── Training Modes ────────────────────────────────────────────────────
        $trainingModes = [
            ['name' => 'Face to Face', 'code' => 'F2F'],
            ['name' => 'Blended',      'code' => 'BLENDED'],
        ];

        foreach ($trainingModes as $mode) {
            NtcTrainingMode::firstOrCreate(['code' => $mode['code']], $mode);
        }

        // ── Document Types ────────────────────────────────────────────────────
        $documentTypes = [
            ['name' => 'DOLE-OSHC-STO-RTCMan Form', 'code' => 'RTCMAN'],
            ['name' => 'DOLE-OSHC-STO-PROG Form',   'code' => 'PROG'],
        ];

        foreach ($documentTypes as $docType) {
            NtcDocumentType::firstOrCreate(['code' => $docType['code']], $docType);
        }
    }
}
