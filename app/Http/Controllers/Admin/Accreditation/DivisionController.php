<?php

namespace App\Http\Controllers\Admin\Accreditation;

use App\Http\Controllers\Controller;
use App\Models\Accreditation;
use App\Models\Application;
use App\Services\PctService;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lists that span every accreditation type the Accreditation division owns
 * (OSH Practitioner and OSH Consultant): interviews, active and
 * revoked/expired accreditations, archived applications.
 *
 * Read-only for now — the interview and later stages of the practitioner
 * cycle have not been defined yet, so these pages list what reaches them.
 * HCD's equivalents leave these types out (AdminAccess::accreditationDivisionTypes).
 */
class DivisionController extends Controller
{
    /**
     * Applications whose applicant has chosen an interview slot. Those still
     * choosing are listed on "Manage Interview Slots" (InterviewSlotController).
     */
    public function scheduledInterviews()
    {
        // Starts interviews whose time has come and stops any past their limit.
        app(PctService::class)->autoResumeAllScheduledInterviews();

        // Those still choosing a slot are listed on Manage Interview Slots.
        $applications = $this->applications(fn (Builder $q) => $q
            ->whereHas('latestStatus.status', fn ($s) => $s->where('name', 'Scheduled for Interview'))
            ->whereHas('interview'));

        return view('admin.accreditation.division.applications', [
            'title'         => 'Schedule Interviews — Scheduled',
            'heading'       => 'Scheduled Interviews',
            'hint'          => 'Applications with an interview on the calendar.',
            'applications'  => $applications,
            'showInterview' => true,
        ]);
    }

    /** Rejected applications and archived accreditations. */
    public function archived()
    {
        $applications = $this->applications(fn (Builder $q) => $q->where(fn ($w) => $w
            ->whereHas('latestStatus.status', fn ($s) => $s->where('name', 'Rejected'))
            ->orWhereHas('accreditation', fn ($a) => $a->where('status', 'archived'))));

        return view('admin.accreditation.division.applications', [
            'title'         => 'Archived Applications',
            'heading'       => 'Archived Applications',
            'hint'          => 'Rejected applications and archived accreditations. An archived practitioner application is deleted automatically on the date the applicant may apply again (1 month after missed re-uploads, 6 months after a failed interview).',
            'applications'  => $applications,
            'showInterview' => false,
            'showArchive'   => true,
        ]);
    }

    public function activeAccreditations()
    {
        return view('admin.accreditation.division.accreditations', [
            'title'          => 'Active Accreditations',
            'heading'        => 'Active Accreditations',
            'accreditations' => $this->accreditations(['active']),
            'status'         => null,
        ]);
    }

    /** Revoked and expired, optionally narrowed by ?status=revoked|expired. */
    public function inactiveAccreditations(Request $request)
    {
        $status   = in_array($request->input('status'), ['revoked', 'expired'], true) ? $request->input('status') : '';
        $statuses = $status ? [$status] : ['revoked', 'expired'];

        return view('admin.accreditation.division.accreditations', [
            'title'          => 'Revoked / Expired Accreditations',
            'heading'        => 'Revoked / Expired Accreditations',
            'accreditations' => $this->accreditations($statuses),
            'status'         => $status,
        ]);
    }

    private function applications(callable $scope): Collection
    {
        $query = Application::with([
            'user.individualProfile',
            'user.organizationProfile',
            'latestStatus.status',
            'assignedEvaluator.adminProfile',
            'interview',
        ])->whereIn('accreditation_type_id', AdminAccess::accreditationDivisionTypes());

        return $scope($query)->orderByDesc('updated_at')->get();
    }

    private function accreditations(array $statuses): Collection
    {
        return Accreditation::with(['user.individualProfile', 'user.organizationProfile', 'application'])
            ->whereIn('accreditation_type_id', AdminAccess::accreditationDivisionTypes())
            ->whereIn('status', $statuses)
            ->orderByDesc('updated_at')
            ->get();
    }
}
