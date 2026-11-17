<?php

namespace App\Services;

use App\Mail\ApplicationResultEmail;
use App\Mail\PractitionerApplicationArchivedEmail;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\ApplicantStoragePath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Archiving for the Accreditation division's applications (OSH Practitioner /
 * Consultant), and the waiting period that follows it.
 *
 *   Rejected documents not re-uploaded within 5 working days
 *       → archived; may apply again after 1 month
 *   Rejected proof of payment not re-uploaded within 5 working days
 *       → archived; may apply again after 1 month
 *   Did not pass the panel interview
 *       → archived; may apply again after 6 months
 *   Archived by an admin ("Archive Application", as FATPro's)
 *       → archived; no waiting period, and the record is kept
 *
 * Until reapply_after the applicant's email (or name and birthday) cannot
 * open a new application. On that day the archived record — the application,
 * its files and, when it was their only one, the applicant's account — is
 * deleted (practitioner:enforce-deadlines, daily), which frees the email for
 * the new application.
 */
class PractitionerArchiveService
{
    public const NON_COMPLIANCE         = 'non_compliance';
    public const PAYMENT_NON_COMPLIANCE = 'payment_non_compliance';
    public const INTERVIEW_FAILED       = 'interview_failed';
    public const MANUAL                 = 'manual';

    /** Working days the applicant has to re-upload rejected documents or payment. */
    public const COMPLIANCE_WORKING_DAYS = 5;

    /** Months before an archived applicant may apply again; a reason not listed has no wait. */
    public const REAPPLY_MONTHS = [
        self::NON_COMPLIANCE         => 1,
        self::PAYMENT_NON_COMPLIANCE => 1,
        self::INTERVIEW_FAILED       => 6,
    ];

    public function __construct(private readonly PctService $pct)
    {
    }

    /** The last day to re-upload documents rejected today. */
    public function complianceDueDate(?Carbon $from = null): Carbon
    {
        return PctService::addWorkingDays(($from ?? Carbon::now())->copy()->startOfDay(), self::COMPLIANCE_WORKING_DAYS);
    }

    /**
     * Archive the application: status Rejected, PCT closed, the waiting
     * period set and the applicant told when they may apply again.
     */
    public function archive(Application $application, string $reason, ?int $updatedBy = null): void
    {
        $now          = Carbon::now();
        $months       = self::REAPPLY_MONTHS[$reason] ?? null;
        $reapplyAfter = $months ? $now->copy()->startOfDay()->addMonthsNoOverflow($months) : null;

        DB::transaction(function () use ($application, $reason, $updatedBy, $now, $reapplyAfter) {
            $application->forceFill([
                'compliance_due_date' => null,
                'archived_at'         => $now,
                'archive_reason'      => $reason,
                'reapply_after'       => $reapplyAfter,
            ])->save();

            $rejected = ApplicationStatus::findByName('Rejected');
            if ($rejected) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $rejected->id,
                    'updated_by'     => $updatedBy,
                    'remarks'        => $this->reasonLabel($reason) . ' Application archived'
                        . ($reapplyAfter ? '; the applicant may apply again on ' . $reapplyAfter->format('F j, Y') . '.' : '.'),
                ]);
            }
        });

        try {
            $this->pct->completeAllSteps($application);
        } catch (\Throwable $e) {
            Log::error('PCT close failed for archived application ' . $application->id . ': ' . $e->getMessage());
        }

        $application->unsetRelation('latestStatus');
        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        $email = $application->user?->email;
        if ($email) {
            try {
                // An admin's archive sends FATPro's "not passed" result, as
                // FATPro's Archive Application does.
                Mail::to($email)->send($reason === self::MANUAL
                    ? new ApplicationResultEmail($application, 'not_passed', null)
                    : new PractitionerApplicationArchivedEmail($application));
            } catch (\Throwable $e) {
                Log::error('Archived application email failed for ' . $application->tracking_number . ': ' . $e->getMessage());
            }
        }
    }

    /** "Did not pass the panel interview." */
    public function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            self::NON_COMPLIANCE         => 'Rejected documents were not re-uploaded within ' . self::COMPLIANCE_WORKING_DAYS . ' working days.',
            self::PAYMENT_NON_COMPLIANCE => 'Rejected proof of payment was not re-uploaded within ' . self::COMPLIANCE_WORKING_DAYS . ' working days.',
            self::INTERVIEW_FAILED       => 'Did not pass the panel interview.',
            self::MANUAL                 => 'Application will not proceed. Manually archived/rejected.',
            default                      => 'Application rejected.',
        };
    }

    /** Short label for lists: "Did not pass the interview". */
    public function shortReason(?string $reason): string
    {
        return match ($reason) {
            self::NON_COMPLIANCE         => 'Documents not re-uploaded',
            self::PAYMENT_NON_COMPLIANCE => 'Payment not re-uploaded',
            self::INTERVIEW_FAILED       => 'Did not pass the interview',
            self::MANUAL                 => 'Archived by an admin',
            default                      => 'Rejected',
        };
    }

    /**
     * Archive every application whose rejected documents or proof of payment
     * were not re-uploaded by the due date (the applicant has the whole last
     * day).
     */
    public function archiveLapsed(): int
    {
        $archived = 0;

        Application::query()
            ->whereIn('accreditation_type_id', AdminAccess::accreditationDivisionTypes())
            ->whereNull('archived_at')
            ->whereNotNull('compliance_due_date')
            ->whereDate('compliance_due_date', '<', Carbon::today()->toDateString())
            ->whereHas('latestStatus.status', fn ($q) => $q->whereIn('name', ['For Update', 'Awaiting Payment']))
            ->with(['user', 'latestStatus.status'])
            ->chunkById(100, function ($applications) use (&$archived) {
                foreach ($applications as $application) {
                    $this->archive($application, $this->lapseReason($application));
                    $archived++;
                }
            });

        return $archived;
    }

    /** Which deadline an application missed: its documents' or its payment's. */
    public function lapseReason(Application $application): string
    {
        return $application->latestStatus?->status?->name === 'Awaiting Payment'
            ? self::PAYMENT_NON_COMPLIANCE
            : self::NON_COMPLIANCE;
    }

    /** Delete every archived record whose waiting period is over. */
    public function purgeExpired(): int
    {
        $purged = 0;

        Application::query()
            ->whereIn('accreditation_type_id', AdminAccess::accreditationDivisionTypes())
            ->whereNotNull('archived_at')
            ->whereNotNull('reapply_after')
            ->whereDate('reapply_after', '<=', Carbon::today()->toDateString())
            ->get()
            ->each(function (Application $application) use (&$purged) {
                $this->purge($application);
                $purged++;
            });

        return $purged;
    }

    /**
     * The archived application that still stops this person from applying,
     * if any: matched on the email, or on the name and birthday (an applicant
     * cannot get round the wait by switching email address).
     */
    public function blockingApplication(?string $email, ?string $firstName = null, ?string $lastName = null, ?string $birthday = null): ?Application
    {
        $this->purgeExpired();

        $query = Application::query()
            ->whereIn('accreditation_type_id', AdminAccess::accreditationDivisionTypes())
            ->whereNotNull('archived_at')
            ->whereDate('reapply_after', '>', Carbon::today()->toDateString());

        $byEmail = $email
            ? (clone $query)->whereHas('user', fn ($q) => $q->where('email', $email))->latest('archived_at')->first()
            : null;

        if ($byEmail || ! $firstName || ! $lastName || ! $birthday) {
            return $byEmail;
        }

        try {
            $birthdate = Carbon::parse($birthday)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        return $query->whereHas('user.individualProfile', fn ($q) => $q
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($firstName))])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($lastName))])
            ->whereDate('birthday', $birthdate))
            ->latest('archived_at')
            ->first();
    }

    /**
     * Delete an archived application with its files. When it was the
     * applicant's only application the account goes too (its rows cascade),
     * so the email can register again.
     */
    public function purge(Application $application): void
    {
        $application->loadMissing(['user.role', 'accreditationType', 'documents.userDocument', 'practitionerProfile']);

        $user           = $application->user;
        $trackingNumber = $application->tracking_number;
        $disk           = Storage::disk('local');

        $files = $application->documents->map(fn ($doc) => $doc->userDocument?->file_path)
            ->push($application->practitionerProfile?->photo_path, $application->practitionerProfile?->application_form_path)
            ->filter()
            ->unique();

        $deleteAccount = $user
            && strtolower($user->role?->name ?? '') === 'applicant'
            && $user->applications()->whereKeyNot($application->id)->doesntExist();

        DB::transaction(function () use ($application, $user, $deleteAccount) {
            if ($deleteAccount) {
                $user->individualProfile()?->delete();
                $user->organizationProfile()?->delete();
                $user->userDocuments()?->delete();
                $user->delete();
            } else {
                $application->documents()->delete();
                $application->delete();
            }
        });

        foreach ($files as $path) {
            $disk->delete($path);
        }

        if ($deleteAccount) {
            $disk->deleteDirectory(ApplicantStoragePath::base($application->accreditationType?->name, $user->id));
        }
        $disk->deleteDirectory("applications/{$application->id}");

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($trackingNumber);

        Log::info("Archived application {$trackingNumber} deleted after its waiting period.");
    }
}
