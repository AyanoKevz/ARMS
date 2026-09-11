<?php

namespace App\Console\Commands;

use App\Models\Accreditation;
use App\Models\ApplicationPayment;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\NtcDocument;
use App\Models\UserDocument;
use App\Support\ApplicantStoragePath;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class ConsolidateApplicantStorageFolders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:consolidate-applicant-folders {--dry-run : Preview the moves without touching files or the database}';

    /**
     * The console command description.
     */
    protected $description = "One-off cleanup for folders created before ApplicantStoragePath existed. Every upload path used to be derived from the applicant's current business/individual name, so a renewal or reinstatement that resubmitted a slightly different name forked off a new, orphaned folder instead of reusing the old one. This command finds every file still sitting under one of those old name-derived folders, moves it into the single user-id-keyed folder the app now always uses, and re-points the matching database column so existing 'View' links keep working.";

    private int $moved = 0;
    private int $alreadyCorrect = 0;
    private int $missing = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('local');

        if ($dryRun) {
            $this->warn('Dry run — no files will be moved and no database rows will be changed.');
        }

        $this->consolidateUserDocuments($disk, $dryRun);
        $this->consolidateInstructors($disk, $dryRun);
        $this->consolidateInstructorCredentials($disk, $dryRun);
        $this->consolidateAccreditationCertificates($disk, $dryRun);
        $this->consolidateApplicationPayments($disk, $dryRun);
        $this->consolidateNtcDocuments($disk, $dryRun);

        if (!$dryRun) {
            $this->pruneEmptyDirectories($disk);
        }

        $this->info("\nDone. Moved: {$this->moved}, already correct: {$this->alreadyCorrect}, missing on disk: {$this->missing}.");

        return self::SUCCESS;
    }

    /**
     * Moves a file already at $oldPath to $newDirectory (keeping its filename)
     * and returns the new path to save, or null when there is nothing to write
     * back — either the path was already correct, or the source file could not
     * be found on disk (left untouched rather than silently dropping the
     * reference).
     */
    private function relocate(Filesystem $disk, ?string $oldPath, string $newDirectory, bool $dryRun): ?string
    {
        if (!$oldPath) {
            return null;
        }

        $newPath = $newDirectory . '/' . basename($oldPath);

        if ($oldPath === $newPath) {
            $this->alreadyCorrect++;
            return null;
        }

        if (!$disk->exists($oldPath)) {
            $this->warn("Missing on disk, left as-is: {$oldPath}");
            $this->missing++;
            return null;
        }

        $this->line(($dryRun ? '[dry-run] ' : '') . "{$oldPath}  ->  {$newPath}");
        $this->moved++;

        if ($dryRun) {
            return null;
        }

        $disk->move($oldPath, $newPath);

        return $newPath;
    }

    /** General application documents (Legal Requirements, etc.). */
    private function consolidateUserDocuments(Filesystem $disk, bool $dryRun): void
    {
        UserDocument::whereNotNull('file_path')
            ->with('applicationDocuments.application.accreditationType')
            ->chunkById(100, function ($docs) use ($disk, $dryRun) {
                foreach ($docs as $doc) {
                    $application = $doc->applicationDocuments->first()?->application;
                    $accreditationName = $application?->accreditationType?->name;

                    $newDir = ApplicantStoragePath::documents($accreditationName, $doc->user_id);
                    $newPath = $this->relocate($disk, $doc->file_path, $newDir, $dryRun);

                    if ($newPath) {
                        $doc->update(['file_path' => $newPath]);
                    }
                }
            });
    }

    /** Instructor service agreement + CV. */
    private function consolidateInstructors(Filesystem $disk, bool $dryRun): void
    {
        Instructor::where(function ($q) {
            $q->whereNotNull('service_agreement_path')->orWhereNotNull('cv_path');
        })
            ->with('application.accreditationType')
            ->chunkById(100, function ($instructors) use ($disk, $dryRun) {
                foreach ($instructors as $instructor) {
                    $accreditationName = $instructor->application?->accreditationType?->name;
                    $newDir = ApplicantStoragePath::credentials($accreditationName, $instructor->user_id);

                    $update = [];

                    $newSa = $this->relocate($disk, $instructor->service_agreement_path, $newDir, $dryRun);
                    if ($newSa) {
                        $update['service_agreement_path'] = $newSa;
                    }

                    $newCv = $this->relocate($disk, $instructor->cv_path, $newDir, $dryRun);
                    if ($newCv) {
                        $update['cv_path'] = $newCv;
                    }

                    if ($update) {
                        $instructor->update($update);
                    }
                }
            });
    }

    /** Instructor credential PDFs (NTTC / TM1 / EMS). */
    private function consolidateInstructorCredentials(Filesystem $disk, bool $dryRun): void
    {
        InstructorCredential::whereNotNull('pdf_path')
            ->with('instructor.application.accreditationType')
            ->chunkById(100, function ($credentials) use ($disk, $dryRun) {
                foreach ($credentials as $credential) {
                    $instructor = $credential->instructor;
                    if (!$instructor) {
                        continue;
                    }

                    $accreditationName = $instructor->application?->accreditationType?->name;
                    $newDir = ApplicantStoragePath::credentials($accreditationName, $instructor->user_id);
                    $newPath = $this->relocate($disk, $credential->pdf_path, $newDir, $dryRun);

                    if ($newPath) {
                        $credential->update(['pdf_path' => $newPath]);
                    }
                }
            });
    }

    /** Scanned accreditation certificates. */
    private function consolidateAccreditationCertificates(Filesystem $disk, bool $dryRun): void
    {
        Accreditation::whereNotNull('scanned_certificate')
            ->with('accreditationType')
            ->chunkById(100, function ($accreditations) use ($disk, $dryRun) {
                foreach ($accreditations as $accreditation) {
                    $accreditationName = $accreditation->accreditationType?->name;
                    $newDir = ApplicantStoragePath::certificate($accreditationName, $accreditation->user_id);
                    $newPath = $this->relocate($disk, $accreditation->scanned_certificate, $newDir, $dryRun);

                    if ($newPath) {
                        $accreditation->update(['scanned_certificate' => $newPath]);
                    }
                }
            });
    }

    /** Proof of payment + signed recommendation letter. */
    private function consolidateApplicationPayments(Filesystem $disk, bool $dryRun): void
    {
        ApplicationPayment::where(function ($q) {
            $q->whereNotNull('proof_of_payment')->orWhereNotNull('signed_recommendation_letter');
        })
            ->with('application.accreditationType')
            ->chunkById(100, function ($payments) use ($disk, $dryRun) {
                foreach ($payments as $payment) {
                    $application = $payment->application;
                    if (!$application) {
                        continue;
                    }

                    $accreditationName = $application->accreditationType?->name;
                    $update = [];

                    $newProof = $this->relocate(
                        $disk,
                        $payment->proof_of_payment,
                        ApplicantStoragePath::proofOfPayments($accreditationName, $application->user_id),
                        $dryRun
                    );
                    if ($newProof) {
                        $update['proof_of_payment'] = $newProof;
                    }

                    $newLetter = $this->relocate(
                        $disk,
                        $payment->signed_recommendation_letter,
                        ApplicantStoragePath::recommendationLetter($accreditationName, $application->user_id),
                        $dryRun
                    );
                    if ($newLetter) {
                        $update['signed_recommendation_letter'] = $newLetter;
                    }

                    if ($update) {
                        $payment->update($update);
                    }
                }
            });
    }

    /** Submission report (NTC) RTCMan / PROG uploads. */
    private function consolidateNtcDocuments(Filesystem $disk, bool $dryRun): void
    {
        NtcDocument::whereNotNull('file_path')
            ->with('ntcReport.accreditation.accreditationType')
            ->chunkById(100, function ($documents) use ($disk, $dryRun) {
                foreach ($documents as $document) {
                    $accreditation = $document->ntcReport?->accreditation;
                    if (!$accreditation) {
                        continue;
                    }

                    $accreditationName = $accreditation->accreditationType?->name;
                    $newDir = ApplicantStoragePath::ntcReports($accreditationName, $accreditation->user_id);
                    $newPath = $this->relocate($disk, $document->file_path, $newDir, $dryRun);

                    if ($newPath) {
                        $document->update(['file_path' => $newPath]);
                    }
                }
            });
    }

    /**
     * Removes now-empty leftover directories under public/{accreditation_type}/.
     * Safe by construction: a directory only gets removed once every file that
     * used to live in it has already been moved out above, so nothing still
     * referenced by the database is ever touched.
     */
    private function pruneEmptyDirectories(Filesystem $disk): void
    {
        $pruned = 0;

        foreach ($disk->directories('public') as $accreditationDir) {
            foreach ($disk->directories($accreditationDir) as $applicantDir) {
                if (empty($disk->allFiles($applicantDir))) {
                    $disk->deleteDirectory($applicantDir);
                    $pruned++;
                }
            }
        }

        if ($pruned > 0) {
            $this->info("Removed {$pruned} now-empty leftover folder(s).");
        }
    }
}
