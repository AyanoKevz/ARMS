<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Mail\NtcDetailsUpdatedEmail;
use App\Mail\NtcEvaluationEmail;
use App\Models\Instructor;
use App\Models\NtcDocument;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class NtcController extends Controller
{
    /**
     * Helper to allow ONLY Training Evaluator through — NTC/training reports are their exclusive domain.
     */
    private function requireTrainingEvaluatorAccess()
    {
        $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        if (strtolower($isAdminRole) !== 'training evaluator') {
            abort(403, 'Unauthorized action. Only Training Evaluator has access to this page.');
        }
    }

    /**
     * List all NTC submissions across all FATPros.
     */
    public function index()
    {
        $this->requireTrainingEvaluatorAccess();
        $ntcReports = NtcReport::with([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
        ])
            ->where('status', '!=', 'report_changes')
            ->latest()
            ->get();

        return view('admin.hcd.reports.ntc', compact('ntcReports'));
    }

    /**
     * List all Report of Changes submissions across all FATPros.
     */
    public function reportChangesIndex()
    {
        $this->requireTrainingEvaluatorAccess();
        $ntcReports = NtcReport::with([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
        ])
            ->where('status', 'report_changes')
            ->latest()
            ->get();

        return view('admin.hcd.reports.report_changes', compact('ntcReports'));
    }

    /**
     * Show the detail/evaluation page for a single NTC submission.
     */
    public function show(NtcReport $ntcReport)
    {
        $this->requireTrainingEvaluatorAccess();
        $ntcReport->loadMissing([
            'accreditation.user.organizationProfile.authorizedRepresentatives',
            'accreditation.user.individualProfile',
            'accreditation.accreditationType',
            'trainingType',
            'trainingMode',
            'trainingDates',
            'instructors',
            'documents.documentType',
            'documents.evaluatedByUser',
            'acknowledgedByUser',
        ]);

        $accreditation = $ntcReport->accreditation;
        $fatproUser    = $accreditation->user ?? null;
        $isOrg         = $fatproUser?->profile_type === 'Organization';
        $org           = $fatproUser?->organizationProfile;
        $ind           = $fatproUser?->individualProfile;
        $reps          = $org?->authorizedRepresentatives ?? collect();

        // Only loaded when the evaluator can actually act on them: an editor
        // for a training already under way would be a button that only ever
        // returns an error.
        $canEditDetails = $ntcReport->detailsAreEditable();

        $trainingTypes = $canEditDetails ? NtcTrainingType::all() : collect();
        $trainingModes = $canEditDetails ? NtcTrainingMode::all() : collect();
        $instructorRoster = $canEditDetails && $fatproUser
            ? Instructor::rosterWithEligibilityFor($fatproUser->id, $ntcReport->training_end_date)
            : collect();

        return view('admin.hcd.reports.ntc_show', compact(
            'ntcReport',
            'accreditation',
            'fatproUser',
            'isOrg',
            'org',
            'ind',
            'reps',
            'canEditDetails',
            'trainingTypes',
            'trainingModes',
            'instructorRoster',
        ));
    }

    /**
     * Evaluate (approve/reject) individual NTC documents and optionally
     * mark the overall NTC report as acknowledged when all docs are approved.
     * A document can also be reverted to 'pending' — clicking an already-active
     * Approve/Reject button again undoes a mistaken click.
     */
    public function evaluateDocument(Request $request, NtcDocument $document)
    {
        $this->requireTrainingEvaluatorAccess();
        $validated = $request->validate([
            'status'  => ['required', 'in:approved,rejected,pending'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = Auth::user();

        $document->update([
            'status'       => $validated['status'],
            'remarks'      => $validated['status'] === 'rejected' ? ($validated['remarks'] ?? null) : null,
            'evaluated_by' => $validated['status'] === 'pending' ? null : $admin->id,
            'evaluated_at' => $validated['status'] === 'pending' ? null : now(),
        ]);

        return response()->json([
            'success' => true,
            'status'  => $document->status,
            'remarks' => $document->remarks,
        ]);
    }

    /**
     * Serve an NTC document file to the admin (private storage).
     */
    public function serveDocument(NtcDocument $document)
    {
        $this->requireTrainingEvaluatorAccess();
        if (!$document->file_path || !Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }

    /**
     * Correct an acknowledged NTC's details on the FATPro's behalf.
     *
     * The FATPro's own route for this is the Report of Changes, which closes
     * three working days before the training. An evaluator who spots a wrong
     * venue or a mistyped date after that has no way to fix it, and declining
     * the whole submission is far too blunt — hence this.
     *
     * The lead-time rule is deliberately NOT applied. Ten working days governs
     * how far ahead a FATPro must file; it has nothing to say about correcting
     * something already filed and acknowledged. Dates must simply not be in the
     * past, and the training must not have begun.
     */
    public function updateDetails(Request $request, NtcReport $ntcReport)
    {
        $this->requireTrainingEvaluatorAccess();

        if (!$ntcReport->detailsAreEditable()) {
            return back()->withErrors([
                'error' => $ntcReport->status !== 'acknowledged'
                    ? 'Only an acknowledged Notice to Conduct can have its details corrected.'
                    : 'This training has already started, so its details can no longer be changed.',
            ]);
        }

        $validated = $request->validate([
            'ntc_training_type_id' => ['required', 'exists:ntc_training_types,id'],
            'ntc_training_mode_id' => ['required', 'exists:ntc_training_modes,id'],
            'venue'                => ['required', 'string', 'max:500'],
            'training_dates'       => ['required', 'array'],
            'training_dates.*'     => ['array'],
            'training_dates.*.*'   => ['required', 'date'],
            'instructor_ids'       => ['required', 'array', 'min:1'],
            'instructor_ids.*'     => ['integer', 'exists:instructor_people,id'],
            'remarks'              => ['nullable', 'string', 'max:1000'],
        ], [
            'venue.required'          => 'The venue or Zoom link is required.',
            'training_dates.required' => 'Please choose the training dates.',
            'instructor_ids.required' => 'Select at least one instructor to conduct this training.',
            'instructor_ids.min'      => 'Select at least one instructor to conduct this training.',
        ]);

        $fatproUserId = $ntcReport->accreditation->user_id;

        $trainingDays  = $this->resolveCorrectedDays($validated);
        $instructorIds = $this->resolveCorrectedInstructors(
            $validated['instructor_ids'],
            $fatproUserId,
            Carbon::parse(collect($trainingDays)->flatten()->max())
        );

        // Captured before the write so the notice can say what actually
        // changed. "Your NTC was updated" on its own tells a FATPro nothing.
        $before = $this->detailsSnapshot($ntcReport);

        try {
            DB::transaction(function () use ($ntcReport, $validated, $trainingDays, $instructorIds) {
                $ntcReport->update([
                    'ntc_training_type_id' => $validated['ntc_training_type_id'],
                    'ntc_training_mode_id' => $validated['ntc_training_mode_id'],
                    'venue'                => $validated['venue'],
                    'remarks'              => $validated['remarks'] ?? $ntcReport->remarks,
                ]);

                // Rewrites training_start_date and training_end_date too, so every
                // deadline hanging off them moves with the correction.
                $ntcReport->syncTrainingDates($trainingDays);
                $ntcReport->instructors()->sync($instructorIds);
            });
        } catch (\Exception $e) {
            Log::error('NTC details correction failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while saving the changes. Please try again.']);
        }

        $this->notifyFatproOfEdit($ntcReport, $before, $validated['remarks'] ?? null);

        return back()->with('success', 'The Notice to Conduct details have been updated. The FATPro has been notified.');
    }

    /**
     * The details a correction can touch, as the FATPro would read them.
     *
     * Taken before and after the write and compared, so the notice names what
     * actually moved rather than claiming the whole submission changed.
     *
     * @return array<string, string>
     */
    private function detailsSnapshot(NtcReport $ntcReport): array
    {
        $ntcReport->loadMissing(['trainingType', 'trainingMode', 'trainingDates', 'instructors']);

        return [
            'Training Type'  => $ntcReport->trainingType->name ?? '—',
            'Mode'           => $ntcReport->trainingMode->name ?? '—',
            'Venue'          => (string) $ntcReport->venue,
            'Training Days'  => $ntcReport->trainingPeriodLabel(),
            'Instructors'    => $ntcReport->instructors->map->fullName()->implode(', '),
        ];
    }

    /**
     * Tell the FATPro their acknowledged submission was corrected.
     *
     * Silent when nothing actually differs — re-saving the dialog without
     * changing anything should not send them a notice about it.
     *
     * A failure here never fails the correction: the change is already
     * committed, and refusing it after the fact would be worse than a missed
     * email.
     */
    private function notifyFatproOfEdit(NtcReport $ntcReport, array $before, ?string $note): void
    {
        $after   = $this->detailsSnapshot($ntcReport->refresh());
        $changes = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? '') !== $value) {
                $changes[$field] = ['from' => $before[$field] ?? '', 'to' => $value];
            }
        }

        if (!$changes) {
            return;
        }

        try {
            $email = $ntcReport->accreditation->user->email ?? null;

            if ($email) {
                Mail::to($email)->send(new NtcDetailsUpdatedEmail($ntcReport, $changes, $note));
            }
        } catch (\Exception $e) {
            Log::warning('NTC details update notice to the FATPro failed: ' . $e->getMessage());
        }
    }

    /**
     * The corrected training dates, grouped by the day they belong to.
     *
     * Mirrors the FATPro's own rules — one date minimum per day of the course,
     * all distinct, days in sequence — but swaps the ten-working-day floor for
     * "not in the past", which is the only date rule that still makes sense
     * once a training has been acknowledged.
     *
     * @return array<int, array<int, string>>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveCorrectedDays(array $validated): array
    {
        $code     = NtcTrainingType::whereKey($validated['ntc_training_type_id'])->value('code');
        $required = NtcReport::durationDaysForCode($code);
        $today    = Carbon::today();

        $byDay = [];

        foreach ($validated['training_dates'] as $dayNo => $dates) {
            $dayNo = (int) $dayNo;

            if ($dayNo < 1 || $dayNo > $required) {
                throw ValidationException::withMessages([
                    'training_dates' => 'A date was filed against Day ' . $dayNo
                        . ', which this training does not have.',
                ]);
            }

            foreach ((array) $dates as $date) {
                if (filled($date)) {
                    $byDay[$dayNo][] = Carbon::parse($date)->startOfDay();
                }
            }
        }

        for ($dayNo = 1; $dayNo <= $required; $dayNo++) {
            if (empty($byDay[$dayNo])) {
                throw ValidationException::withMessages([
                    'training_dates' => 'Day ' . $dayNo . ' has no date yet. This training runs for '
                        . $required . ' ' . Str::plural('day', $required)
                        . ', and each one needs at least one date.',
                ]);
            }
        }

        ksort($byDay);

        $flat = collect();

        foreach ($byDay as $dayNo => $dates) {
            usort($dates, fn (Carbon $a, Carbon $b) => $a <=> $b);
            $byDay[$dayNo] = $dates;
            $flat = $flat->merge($dates);
        }

        if ($flat->unique(fn (Carbon $d) => $d->toDateString())->count() !== $flat->count()) {
            throw ValidationException::withMessages([
                'training_dates' => 'Each training date must be a different day.',
            ]);
        }

        if ($flat->contains(fn (Carbon $date) => $date->lessThan($today))) {
            throw ValidationException::withMessages([
                'training_dates' => 'A training cannot be moved into the past.',
            ]);
        }

        $previousEnd = null;

        foreach ($byDay as $dayNo => $dates) {
            if ($previousEnd && $dates[0]->lessThan($previousEnd)) {
                throw ValidationException::withMessages([
                    'training_dates' => 'Day ' . $dayNo . ' cannot begin before Day '
                        . ($dayNo - 1) . ' has finished.',
                ]);
            }

            $previousEnd = end($dates);
        }

        return array_map(
            fn (array $dates) => array_map(fn (Carbon $date) => $date->toDateString(), $dates),
            $byDay
        );
    }

    /**
     * The corrected instructors, checked against the FATPro's own roster.
     *
     * Held to the same bar the FATPro is, and for the same reason: the training
     * has not happened yet, so anyone named on it must actually be able to
     * conduct it on its last day.
     *
     * @return array<int, int>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveCorrectedInstructors(array $personIds, int $fatproUserId, Carbon $lastTrainingDay): array
    {
        $roster = Instructor::accreditedRosterFor($fatproUserId)
            ->filter(fn ($instructor) => $instructor->instructor_person_id)
            ->keyBy('instructor_person_id');

        $chosen = [];

        foreach (array_unique($personIds) as $id) {
            $instructor = $roster->get((int) $id);

            if (!$instructor) {
                throw ValidationException::withMessages([
                    'instructor_ids' => 'One of the selected instructors is not on this FATPro\'s accredited roster.',
                ]);
            }

            if ($reason = $instructor->ineligibilityReason($lastTrainingDay)) {
                throw ValidationException::withMessages([
                    'instructor_ids' => $instructor->fullName()
                        . ' cannot be declared on this training: ' . $reason,
                ]);
            }

            $chosen[] = (int) $instructor->instructor_person_id;
        }

        return $chosen;
    }

    /**
     * Finalize the entire NTC evaluation.
     */
    public function finalizeEvaluation(Request $request, NtcReport $ntcReport)
    {
        $this->requireTrainingEvaluatorAccess();
        $validated = $request->validate([
            'evaluations' => ['required', 'array'],
            'evaluations.*.id' => ['required', 'exists:ntc_documents,id'],
            'evaluations.*.status' => ['required', 'in:approved,rejected,pending,returned'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $evaluations = $request->input('evaluations', []);
        $admin = Auth::user();
        $hasRejections = false;
        $wasReportChanges = $ntcReport->status === 'report_changes';

        // Resolve all submitted documents in one query (still scoped to this
        // report) rather than a SELECT per row inside the loop.
        $docs = NtcDocument::where('ntc_report_id', $ntcReport->id)
            ->whereIn('id', array_column($evaluations, 'id'))
            ->get()
            ->keyBy('id');

        foreach ($evaluations as $eval) {
            $doc = $docs->get($eval['id']);
            if ($doc) {
                $newStatus = $eval['status'];
                if ($newStatus === 'pending') {
                    continue;
                }

                $attributes = [
                    'status'       => $newStatus,
                    'remarks'      => $newStatus === 'rejected' ? ($eval['remarks'] ?? null) : null,
                    'evaluated_by' => $admin->id,
                    'evaluated_at' => now(),
                ];

                if ($newStatus === 'rejected') {
                    $hasRejections = true;
                    if ($doc->file_path && Storage::disk('local')->exists($doc->file_path)) {
                        Storage::disk('local')->delete($doc->file_path);
                    }
                    // Fold the file_path reset into the same UPDATE instead of
                    // issuing a second one.
                    $attributes['file_path'] = null;
                }

                $doc->update($attributes);
            }
        }

        $ntcReport->load('documents');
        $allApproved = $ntcReport->documents->every(fn ($d) => $d->status === 'approved');

        if ($allApproved && $ntcReport->status !== 'acknowledged') {
            $ntcReport->update([
                'status'          => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $admin->id,
            ]);

            try {
                $fatproEmail = $ntcReport->accreditation->user->email ?? null;
                if ($fatproEmail) {
                    Mail::to($fatproEmail)->send(new \App\Mail\NtcEvaluationEmail($ntcReport, null, $wasReportChanges));
                }
            } catch (\Exception $e) {
                Log::warning('NTC acknowledgment email failed: ' . $e->getMessage());
            }
        }

        if ($hasRejections) {
            try {
                $rejectedDocs = $ntcReport->documents->where('status', 'rejected');
                $fatproEmail  = $ntcReport->accreditation->user->email ?? null;

                if ($fatproEmail) {
                    Mail::to($fatproEmail)
                        ->send(new NtcEvaluationEmail($ntcReport, $rejectedDocs, $wasReportChanges));
                }
            } catch (\Exception $e) {
                Log::warning('NTC document rejection email failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success'          => true,
            'message'          => $hasRejections ? 'Rejection notice sent successfully.' : 'Evaluation saved successfully.',
            'ntc_acknowledged' => $allApproved,
            'has_rejections'   => $hasRejections,
        ]);
    }
}
