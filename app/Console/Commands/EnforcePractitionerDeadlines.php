<?php

namespace App\Console\Commands;

use App\Services\PractitionerArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Accreditation division (OSH Practitioner / Consultant) deadlines, daily:
 *
 *   • archives applications whose rejected documents were not re-uploaded
 *     within 5 working days (the applicant has the whole last day)
 *   • deletes archived applications whose waiting period is over (1 month
 *     after a lapsed re-upload, 6 after a failed interview)
 */
class EnforcePractitionerDeadlines extends Command
{
    protected $signature = 'practitioner:enforce-deadlines';

    protected $description = 'Archive practitioner applications not re-uploaded within '
        . PractitionerArchiveService::COMPLIANCE_WORKING_DAYS
        . ' working days, and delete archived ones whose waiting period is over.';

    public function handle(PractitionerArchiveService $archives): int
    {
        $archived = $archives->archiveLapsed();
        $purged   = $archives->purgeExpired();

        $this->info("Archived {$archived} " . Str::plural('application', $archived) . "; deleted {$purged} archived " . Str::plural('application', $purged) . '.');

        return self::SUCCESS;
    }
}
