<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Mail\AdminNtcSubmittedEmail;
use App\Models\Accreditation;
use App\Models\Application;
use App\Models\Instructor;
use App\Models\NtcDocument;
use App\Models\NtcDocumentType;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use App\Models\PtrDocumentType;
use App\Models\User;
use App\Support\ApplicantStoragePath;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NtcController extends Controller
{
    /**
     * The training dates for a submission, grouped by the day they belong to.
     *
     * The training type fixes how MANY days a course runs (EFA 1, OFA 2, SFA 4)
     * but not which dates: those are calendar days the FATPro picks, so
     * weekends count and they need not be consecutive. A single day of the
     * course may also be delivered over more than one date, which is why the
     * dates arrive grouped — the grouping cannot be recovered afterwards.
     *
     * Every date arrives under training_dates[dayNo][]. The start and end dates
     * are outputs of this set, so the form posts neither — there is nothing to
     * cross-check, only a set to validate.
     *
     * Everything is re-checked here rather than trusted from the form, which
     * can be bypassed.
     *
     * @return array<int, array<int, string>>  dayNo => Y-m-d list, ascending.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveTrainingDays(array $validated): array
    {
        $code     = NtcTrainingType::whereKey($validated['ntc_training_type_id'])->value('code');
        $required = NtcReport::durationDaysForCode($code);
        $earliest = NtcReport::earliestAllowedStartDate()->startOfDay();

        $byDay = [];

        foreach ($validated['training_dates'] ?? [] as $dayNo => $dates) {
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

        // Every day of the course has to be accounted for. A day with no date
        // is the common case — the FATPro simply has not filled it in yet.
        for ($dayNo = 1; $dayNo <= $required; $dayNo++) {
            if (empty($byDay[$dayNo])) {
                throw ValidationException::withMessages([
                    'training_dates' => 'Day ' . $dayNo . ' has no date yet. This training'
                        . ' runs for ' . $required . ' ' . Str::plural('day', $required)
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

        $distinct = $flat->unique(fn (Carbon $date) => $date->toDateString());

        if ($distinct->count() !== $flat->count()) {
            throw ValidationException::withMessages([
                'training_dates' => 'Each training date must be a different day.',
            ]);
        }

        // The lead time applies to every date, not just the first — a course
        // cannot start inside the window by putting a legal date first.
        if ($flat->contains(fn (Carbon $date) => $date->lessThan($earliest))) {
            throw ValidationException::withMessages([
                'training_dates' => 'Every training date must be on or after '
                    . $earliest->format('F d, Y') . ' (10 working days from today).',
            ]);
        }

        // The days run in order: a course cannot reach Day 3 before Day 2 has
        // been delivered, however the dates within each day are spread.
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
     * The instructors declared to conduct a training, checked against the
     * FATPro's own roster.
     *
     * Takes and returns PERSON ids, because the record outlives the renewal
     * that would replace the underlying `instructors` row. Eligibility is still
     * judged on that row, which is where the documents and verdicts live.
     *
     * Two things are re-verified here because the form cannot be trusted to:
     * that each instructor really belongs to this FATPro — otherwise a posted
     * id could name someone else's staff — and that each is still eligible on
     * the LAST training day, not merely today.
     *
     * @param  array<int, mixed>  $personIds
     * @return array<int, int>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveInstructors(array $personIds, User $user, Carbon $lastTrainingDay): array
    {
        $roster = Instructor::accreditedRosterFor($user->id)
            ->filter(fn ($instructor) => $instructor->instructor_person_id)
            ->keyBy('instructor_person_id');
        $chosen = [];

        foreach (array_unique($personIds) as $id) {
            $instructor = $roster->get((int) $id);

            if (!$instructor) {
                throw ValidationException::withMessages([
                    'instructor_ids' => 'One of the selected instructors is not on your accredited roster.',
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
     * Show the NTC report list / creation page.
     */
    public function index()
    {
        $user = Auth::user();

        // Block access if accreditation is revoked
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'Your accreditation has been revoked. You cannot access or submit a Submission report.');
        }

        // Block access if there is an ongoing renewal/reinstatement application
        $hasOngoingRenewal = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->whereIn('name', [
                        'Submitted',
                        'Under Evaluation',
                        'For Update',
                        'Scheduled for Interview',
                        'Awaiting Payment',
                        'Payment Verification',
                    ]);
                });
            })
            ->exists();

        if ($hasOngoingRenewal) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'You cannot access or submit a Submission report while you have an ongoing renewal or reinstatement application.');
        }

        // Only active accreditations can submit NTC
        $accreditation = Accreditation::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['user.organizationProfile', 'user.individualProfile'])
            ->latest()
            ->first();

        // All NTC reports for this user (via their accreditations)
        $ntcReports = NtcReport::whereHas('accreditation', fn($q) => $q->where('user_id', $user->id))
            ->with([
                'trainingType',
                'trainingMode',
                'documents.documentType',
                'postTrainingReport.documents.documentType',
                // The corrections dialog reads all three to work out what is
                // still outstanding on a report that was sent back.
                'postTrainingReport.participants',
                'postTrainingReport.instructors',
                // Both feed the Report of Changes dialog, which reopens the
                // submission with its days and instructors already filled in.
                'trainingDates',
                // The person's submissions come along for the Instructors
                // dialog, which reads credentials off the relevant one.
                'instructors.records.credentials',
                'accreditation',
            ])
            ->latest()
            ->get();

        $trainingTypes  = NtcTrainingType::all();
        $trainingModes  = NtcTrainingMode::all();
        $documentTypes  = NtcDocumentType::all();

        // ── Post Training Report ──────────────────────────────────────────────
        // Filed from this same page: every training submission starts at the
        // NTC, so the post training obligation is shown against it rather than
        // on a page of its own.
        $acknowledgedNtcs = $ntcReports->where('status', 'acknowledged');

        $ptrPending = $acknowledgedNtcs->filter(
            fn($ntc) => $ntc->hasTrainingConcluded() && !$ntc->postTrainingReport
        );

        $ptrUpcoming = $acknowledgedNtcs->filter(
            fn($ntc) => !$ntc->hasTrainingConcluded()
        );

        $ptrSubmitted = $acknowledgedNtcs
            ->filter(fn($ntc) => (bool) $ntc->postTrainingReport)
            ->map(fn($ntc) => $ntc->postTrainingReport->setRelation('ntcReport', $ntc))
            ->sortByDesc('submitted_at')
            ->values();

        $ptrDocumentTypes = PtrDocumentType::orderBy('sort_order')->get();

        // Earliest allowed training start date (10 working days from today)
        $earliestStartDate = NtcReport::earliestAllowedStartDate()->format('Y-m-d');

        // The FATPro's own instructors, each tagged with why it may not be
        // picked. Ineligible entries are rendered disabled rather than hidden,
        // so a missing name is explained instead of merely absent.
        $instructorRoster = Instructor::rosterWithEligibilityFor($user->id);

        return view('applicant.ntc', compact(
            'accreditation',
            'ntcReports',
            'trainingTypes',
            'trainingModes',
            'documentTypes',
            'earliestStartDate',
            'instructorRoster',
            'ptrPending',
            'ptrUpcoming',
            'ptrSubmitted',
            'ptrDocumentTypes',
        ));
    }

    /**
     * Store a new NTC report submission.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Block submission if accreditation is revoked
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'Your accreditation has been revoked. You cannot access or submit a Submission report.');
        }

        // Block submission if there is an ongoing renewal/reinstatement application
        $hasOngoingRenewal = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->whereIn('name', [
                        'Submitted',
                        'Under Evaluation',
                        'For Update',
                        'Scheduled for Interview',
                        'Awaiting Payment',
                        'Payment Verification',
                    ]);
                });
            })
            ->exists();

        if ($hasOngoingRenewal) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'You cannot submit a Submission report while you have an ongoing renewal or reinstatement application.');
        }

        // Verify active accreditation (with type for path building)
        $accreditation = Accreditation::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('accreditationType')
            ->latest()
            ->first();

        if (!$accreditation) {
            return back()->withErrors(['error' => 'You do not have an active accreditation to submit an NTC.']);
        }

        // Validate inputs
        $earliestDate = NtcReport::earliestAllowedStartDate()->format('Y-m-d');

        $validated = $request->validate([
            'ntc_training_type_id' => ['required', 'exists:ntc_training_types,id'],
            'ntc_training_mode_id' => ['required', 'exists:ntc_training_modes,id'],
            'venue'                => ['required', 'string', 'max:500'],
            // Every training date, under the day of the course it belongs to,
            // since one day may be delivered over several dates. The start and
            // end dates are outputs of this, not inputs, so the form posts
            // neither and both are derived below.
            'training_dates'     => ['required', 'array'],
            'training_dates.*'   => ['array'],
            'training_dates.*.*' => ['required', 'date'],
            'instructor_ids'              => ['required', 'array', 'min:1'],
            'instructor_ids.*'            => ['integer', 'exists:instructor_people,id'],
            'file_rtcman'          => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
            'file_prog'            => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ], [
            'venue.required' => 'The venue or Zoom link is required.',
            'training_dates.required' => 'Please choose the training dates.',
            'training_dates.*.*.required' => 'Please fill in every training date, or remove the blank one.',
            'instructor_ids.required' => 'Select at least one instructor to conduct this training.',
            'instructor_ids.min'      => 'Select at least one instructor to conduct this training.',
            'file_rtcman.required' => 'The DOLE-OSHC-STO-RTCMan Form is required.',
            'file_prog.required'   => 'The DOLE-OSHC-STO-PROG Form is required.',
            'file_rtcman.max'      => 'The RTCMan Form must not exceed 100 MB.',
            'file_prog.max'        => 'The PROG Form must not exceed 100 MB.',
        ]);

        // Training days are picked, not derived. Start and end are simply the
        // extremes of the chosen set, so the two columns every deadline reads
        // stay meaningful even though the days in between may skip about.
        $trainingDays = $this->resolveTrainingDays($validated);
        $everyDate    = collect($trainingDays)->flatten()->sort()->values();
        $validated['training_start_date'] = $everyDate->first();
        $validated['training_end_date']   = $everyDate->last();

        $instructorIds = $this->resolveInstructors(
            $validated['instructor_ids'],
            $user,
            Carbon::parse($validated['training_end_date'])
        );

        try {
            DB::transaction(function () use ($validated, $request, $accreditation, $user, $trainingDays, $instructorIds) {
                // Create the NTC report
                $ntcReport = NtcReport::create([
                    'accreditation_id'     => $accreditation->id,
                    'ntc_training_type_id' => $validated['ntc_training_type_id'],
                    'ntc_training_mode_id' => $validated['ntc_training_mode_id'],
                    'venue'                => $validated['venue'],
                    'training_start_date'  => $validated['training_start_date'],
                    'training_end_date'    => $validated['training_end_date'],
                    'status'               => 'submitted',
                    'submitted_at'         => Carbon::now(),
                ]);

                $ntcReport->syncTrainingDates($trainingDays);
                $ntcReport->instructors()->sync($instructorIds);

                // Store file uploads
                $fileFields = [
                    'file_rtcman' => 'RTCMAN',
                    'file_prog'   => 'PROG',
                ];

                // Build base path using the same convention as application documents:
                // public/{accreditation_type}/{fatpro_name}/reports/ntc/
                $accreditationType   = $accreditation->accreditationType;
                $accreditationName   = $accreditationType ? $accreditationType->name : null;
                $ntcBasePath         = ApplicantStoragePath::ntcReports($accreditationName, $user->id);

                foreach ($fileFields as $inputName => $docCode) {
                    if ($request->hasFile($inputName)) {
                        $file    = $request->file($inputName);
                        $docType = NtcDocumentType::where('code', $docCode)->first();

                        $ext      = $file->getClientOriginalExtension() ?: 'pdf';
                        $filename = strtolower($docCode) . '_' . time() . '.' . $ext;
                        $path     = $file->storeAs($ntcBasePath, $filename, 'local');

                        NtcDocument::create([
                            'ntc_report_id'        => $ntcReport->id,
                            'ntc_document_type_id' => $docType->id,
                            'file_path'            => $path,
                            'original_filename'    => $file->getClientOriginalName(),
                            'mime_type'            => $file->getMimeType(),
                            'file_size'            => $file->getSize(),
                            'uploaded_at'          => Carbon::now(),
                        ]);
                    }
                }

                // Notify Admin Evaluators via email
                try {
                    $evaluators = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                        $q->where('name', 'Training Evaluator');
                    })->get();

                    if ($evaluators->isNotEmpty()) {
                        $ntcReport->loadMissing([
                            'accreditation.user.organizationProfile',
                            'accreditation.user.individualProfile',
                            'trainingType',
                            'trainingMode',
                            'trainingDates',
                            'instructors',
                            'documents.documentType',
                        ]);

                        $evaluatorEmails = $evaluators->pluck('email');
                        Mail::to($evaluatorEmails)->send(new AdminNtcSubmittedEmail($ntcReport));
                    }
                } catch (\Exception $mailEx) {
                    Log::warning('Admin NTC submission email notification failed: ' . $mailEx->getMessage());
                }
            });

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your Notice to Conduct has been successfully submitted. Admin has been notified.');
        } catch (\Exception $e) {
            Log::error('NTC submission failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while submitting your NTC. Please try again.']);
        }
    }

    /**
     * Serve an NTC document file (private storage).
     */
    public function serveDocument(NtcDocument $document)
    {
        $user = Auth::user();

        // Block access if accreditation is revoked
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            abort(403, 'Your accreditation has been revoked.');
        }

        // Ensure the document belongs to this user's NTC report
        $belongsToUser = $document->ntcReport->accreditation->user_id === $user->id;
        if (!$belongsToUser) {
            abort(403);
        }

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
     * Re-upload a rejected NTC document.
     */
    public function reuploadDocument(Request $request, NtcDocument $document)
    {
        $user = Auth::user();

        // Block if accreditation is revoked
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'Your accreditation has been revoked. You cannot access or submit a Submission report.');
        }

        // Security: document must belong to this user
        $belongsToUser = $document->ntcReport->accreditation->user_id === $user->id;
        if (!$belongsToUser) {
            abort(403);
        }

        // Only allow re-upload if the document is rejected/returned
        if (!in_array($document->status, ['rejected', 'returned'])) {
            return back()->withErrors(['error' => 'This document is not eligible for re-upload.']);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ], [
            'file.required' => 'Please select a file to upload.',
            'file.mimes'    => 'Accepted formats: PDF, DOC, DOCX.',
            'file.max'      => 'File must not exceed 100 MB.',
        ]);

        try {
            $file = $request->file('file');

            // Build path using same convention as application docs:
            // public/{accreditation_type}/{fatpro_name}/reports/ntc/
            $ntcReport           = $document->ntcReport->loadMissing('accreditation.accreditationType');
            $accreditationType   = $ntcReport->accreditation->accreditationType;
            $accreditationName   = $accreditationType ? $accreditationType->name : null;
            $ntcBasePath         = ApplicantStoragePath::ntcReports($accreditationName, $user->id);

            // Delete old file — no stacking
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            $ext      = $file->getClientOriginalExtension() ?: 'pdf';
            $docCode  = strtolower($document->documentType->code ?? 'doc');
            $filename = $docCode . '_' . time() . '.' . $ext;
            $path     = $file->storeAs($ntcBasePath, $filename, 'local');

            $document->update([
                'file_path'         => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type'         => $file->getMimeType(),
                'file_size'         => $file->getSize(),
                'uploaded_at'       => Carbon::now(),
                'status'            => 'returned', // awaiting re-evaluation by admin
                'remarks'           => null,
                'evaluated_by'      => null,
                'evaluated_at'      => null,
            ]);

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your document has been re-uploaded successfully. Admin has been notified for re-evaluation.');
        } catch (\Exception $e) {
            Log::error('NTC document re-upload failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while uploading your document. Please try again.']);
        }
    }

    /**
     * Batch re-upload rejected NTC documents.
     */
    public function reuploadBatch(Request $request, NtcReport $ntcReport)
    {
        $user = Auth::user();

        // Block if accreditation is revoked
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'Your accreditation has been revoked. You cannot access or submit a Submission report.');
        }

        // Security check: must belong to this user
        if ($ntcReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ], [
            'files.*.required' => 'Please select a file to upload.',
            'files.*.mimes'    => 'Accepted formats: PDF, DOC, DOCX.',
            'files.*.max'      => 'File must not exceed 100 MB.',
        ]);

        $filesUploaded = 0;
        try {
            $accreditationType = $ntcReport->accreditation->accreditationType;
            $accreditationName = $accreditationType ? $accreditationType->name : null;
            $ntcBasePath       = ApplicantStoragePath::ntcReports($accreditationName, $user->id);

            $reuploadedDocsInfo = [];
            foreach ($request->file('files') as $docId => $file) {
                $document = NtcDocument::where('ntc_report_id', $ntcReport->id)->find($docId);
                if ($document && in_array($document->status, ['rejected', 'returned'])) {
                    // Delete old file
                    if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                        Storage::disk('local')->delete($document->file_path);
                    }

                    $ext      = $file->getClientOriginalExtension() ?: 'pdf';
                    $docCode  = strtolower($document->documentType->code ?? 'doc');
                    $filename = $docCode . '_' . time() . '_' . $docId . '.' . $ext;
                    $path     = $file->storeAs($ntcBasePath, $filename, 'local');

                    $document->update([
                        'file_path'         => $path,
                        'original_filename' => $file->getClientOriginalName(),
                        'mime_type'         => $file->getMimeType(),
                        'file_size'         => $file->getSize(),
                        'uploaded_at'       => Carbon::now(),
                        'status'            => 'returned',
                        'remarks'           => null,
                        'evaluated_by'      => null,
                        'evaluated_at'      => null,
                    ]);
                    $filesUploaded++;

                    $reuploadedDocsInfo[] = [
                        'type' => $document->documentType->name ?? 'Document',
                        'filename' => $file->getClientOriginalName()
                    ];
                }
            }

            if ($filesUploaded > 0) {
                // Find all Evaluators
                $evaluators = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                    $q->where('name', 'Training Evaluator');
                })->get();

                if ($evaluators->isNotEmpty()) {
                    // Send Email
                    try {
                        $evaluatorEmails = $evaluators->pluck('email');
                        Mail::to($evaluatorEmails)->send(new \App\Mail\AdminNtcReuploadedEmail($ntcReport, $reuploadedDocsInfo));
                    } catch (\Exception $mailEx) {
                        Log::warning('Admin NTC re-upload email notification failed: ' . $mailEx->getMessage());
                    }

                    // Send database/in-app portal notifications
                    foreach ($evaluators as $evaluator) {
                        $evaluator->notifications()->create([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'type' => 'App\Notifications\NtcReuploadedNotification',
                            'data' => [
                                'ntc_report_id' => $ntcReport->id,
                                'reference_number' => 'NTC-' . str_pad($ntcReport->id, 6, '0', STR_PAD_LEFT),
                                'message' => 'NTC report NTC-' . str_pad($ntcReport->id, 6, '0', STR_PAD_LEFT) . ' has been updated with re-uploaded documents by ' . $user->name . ' and is ready for re-evaluation.',
                                'link' => "/admin/hcd/reports/ntc/{$ntcReport->id}"
                            ],
                            'read_at' => null,
                        ]);
                    }
                }
            }

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your documents have been re-uploaded successfully. Admin has been notified for re-evaluation.');
        } catch (\Exception $e) {
            Log::error('NTC document batch re-upload failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while uploading your documents. Please try again.']);
        }
    }

    /**
     * Submit a Report of Changes for an acknowledged NTC report.
     */
    public function submitReportChanges(Request $request, NtcReport $ntcReport)
    {
        $user = Auth::user();

        // Security check: must belong to this user
        if ($ntcReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        // Only allow if currently acknowledged
        if ($ntcReport->status !== 'acknowledged') {
            return back()->withErrors(['error' => 'This Notice to Conduct is not acknowledged and cannot submit a Report of Changes.']);
        }

        if (!$ntcReport->canSubmitReportChanges()) {
            return back()->withErrors(['error' => 'The deadline to submit a Report of Changes for this Notice to Conduct has already passed.']);
        }

        // Block if accreditation is revoked
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'Your accreditation has been revoked. You cannot access or submit a Submission report.');
        }

        // Block if there is an ongoing renewal/reinstatement application
        $hasOngoingRenewal = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->whereIn('name', [
                        'Submitted',
                        'Under Evaluation',
                        'For Update',
                        'Scheduled for Interview',
                        'Awaiting Payment',
                        'Payment Verification',
                    ]);
                });
            })
            ->exists();

        if ($hasOngoingRenewal) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'You cannot submit a Report of Changes while you have an ongoing renewal or reinstatement application.');
        }

        $earliestDate = NtcReport::earliestAllowedStartDate()->format('Y-m-d');

        $validated = $request->validate([
            'ntc_training_type_id' => ['required', 'exists:ntc_training_types,id'],
            'ntc_training_mode_id' => ['required', 'exists:ntc_training_modes,id'],
            'venue'                => ['required', 'string', 'max:500'],
            // Every training date, under the day of the course it belongs to,
            // since one day may be delivered over several dates. The start and
            // end dates are outputs of this, not inputs, so the form posts
            // neither and both are derived below.
            'training_dates'     => ['required', 'array'],
            'training_dates.*'   => ['array'],
            'training_dates.*.*' => ['required', 'date'],
            'instructor_ids'              => ['required', 'array', 'min:1'],
            'instructor_ids.*'            => ['integer', 'exists:instructor_people,id'],
            'file_rtcman'          => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
            'file_prog'            => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ], [
            'venue.required' => 'The venue or Zoom link is required.',
            'training_dates.required' => 'Please choose the training dates.',
            'training_dates.*.*.required' => 'Please fill in every training date, or remove the blank one.',
            'instructor_ids.required' => 'Select at least one instructor to conduct this training.',
            'instructor_ids.min'      => 'Select at least one instructor to conduct this training.',
            'file_rtcman.required' => 'The DOLE-OSHC-STO-RTCMan Form is required.',
            'file_prog.required'   => 'The DOLE-OSHC-STO-PROG Form is required.',
            'file_rtcman.max'      => 'The RTCMan Form must not exceed 100 MB.',
            'file_prog.max'        => 'The PROG Form must not exceed 100 MB.',
        ]);
        // A Report of Changes can move the type, the days or the instructors,
        // so the whole set is re-resolved here rather than carried over.
        $trainingDays = $this->resolveTrainingDays($validated);
        $everyDate    = collect($trainingDays)->flatten()->sort()->values();
        $validated['training_start_date'] = $everyDate->first();
        $validated['training_end_date']   = $everyDate->last();

        $instructorIds = $this->resolveInstructors(
            $validated['instructor_ids'],
            $user,
            Carbon::parse($validated['training_end_date'])
        );

        try {
            DB::transaction(function () use ($validated, $request, $ntcReport, $user, $trainingDays, $instructorIds) {
                // Update NTC Report details
                $ntcReport->update([
                    'ntc_training_type_id' => $validated['ntc_training_type_id'],
                    'ntc_training_mode_id' => $validated['ntc_training_mode_id'],
                    'venue'                => $validated['venue'],
                    'training_start_date'  => $validated['training_start_date'],
                    'training_end_date'    => $validated['training_end_date'],
                    'status'               => 'report_changes',
                    'submitted_at'         => Carbon::now(),
                    'acknowledged_at'      => null,
                    'acknowledged_by'      => null,
                ]);

                $ntcReport->syncTrainingDates($trainingDays);
                $ntcReport->instructors()->sync($instructorIds);

                $fileFields = [
                    'file_rtcman' => 'RTCMAN',
                    'file_prog'   => 'PROG',
                ];

                $accreditation = $ntcReport->accreditation;
                $accreditationType = $accreditation->accreditationType;
                $accreditationName = $accreditationType ? $accreditationType->name : null;
                $ntcBasePath       = ApplicantStoragePath::ntcReports($accreditationName, $user->id);

                foreach ($fileFields as $inputName => $docCode) {
                    $docType = NtcDocumentType::where('code', $docCode)->first();
                    $document = NtcDocument::where('ntc_report_id', $ntcReport->id)
                        ->where('ntc_document_type_id', $docType->id)
                        ->first();

                    if ($request->hasFile($inputName)) {
                        $file = $request->file($inputName);

                        // Delete old file - no stacking!
                        if ($document && $document->file_path && Storage::disk('local')->exists($document->file_path)) {
                            Storage::disk('local')->delete($document->file_path);
                        }

                        $ext      = $file->getClientOriginalExtension() ?: 'pdf';
                        $filename = strtolower($docCode) . '_' . time() . '.' . $ext;
                        $path     = $file->storeAs($ntcBasePath, $filename, 'local');

                        if ($document) {
                            $document->update([
                                'file_path'         => $path,
                                'original_filename' => $file->getClientOriginalName(),
                                'mime_type'         => $file->getMimeType(),
                                'file_size'         => $file->getSize(),
                                'uploaded_at'       => Carbon::now(),
                                'status'            => 'pending',
                                'remarks'           => null,
                                'evaluated_by'      => null,
                                'evaluated_at'      => null,
                            ]);
                        } else {
                            NtcDocument::create([
                                'ntc_report_id'        => $ntcReport->id,
                                'ntc_document_type_id' => $docType->id,
                                'file_path'            => $path,
                                'original_filename'    => $file->getClientOriginalName(),
                                'mime_type'            => $file->getMimeType(),
                                'file_size'            => $file->getSize(),
                                'uploaded_at'          => Carbon::now(),
                                'status'               => 'pending',
                            ]);
                        }
                    } else {
                        // Even if no new file is uploaded, reset the status to pending for review
                        if ($document) {
                            $document->update([
                                'status'       => 'pending',
                                'remarks'      => null,
                                'evaluated_by' => null,
                                'evaluated_at' => null,
                            ]);
                        }
                    }
                }

                // Notify Admin Evaluators via email
                try {
                    $evaluators = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                        $q->where('name', 'Training Evaluator');
                    })->get();

                    if ($evaluators->isNotEmpty()) {
                        $ntcReport->loadMissing([
                            'accreditation.user.organizationProfile',
                            'accreditation.user.individualProfile',
                            'trainingType',
                            'trainingMode',
                            'trainingDates',
                            'instructors',
                            'documents.documentType',
                        ]);

                        $evaluatorEmails = $evaluators->pluck('email');
                        Mail::to($evaluatorEmails)->send(new AdminNtcSubmittedEmail($ntcReport));

                        // Send database/in-app portal notifications
                        foreach ($evaluators as $evaluator) {
                            $evaluator->notifications()->create([
                                'id' => \Illuminate\Support\Str::uuid(),
                                'type' => 'App\Notifications\NtcReuploadedNotification',
                                'data' => [
                                    'ntc_report_id' => $ntcReport->id,
                                    'reference_number' => 'NTC-' . str_pad($ntcReport->id, 6, '0', STR_PAD_LEFT),
                                    'message' => 'Report of Changes submitted for NTC-' . str_pad($ntcReport->id, 6, '0', STR_PAD_LEFT) . ' by ' . $user->name . ' and is ready for evaluation.',
                                    'link' => "/admin/hcd/reports/ntc/{$ntcReport->id}"
                                ],
                                'read_at' => null,
                            ]);
                        }
                    }
                } catch (\Exception $mailEx) {
                    Log::warning('Admin Report of Changes submission email/notification failed: ' . $mailEx->getMessage());
                }
            });

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your Report of Changes has been successfully submitted. Admin has been notified.');
        } catch (\Exception $e) {
            Log::error('Report of Changes submission failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while submitting your Report of Changes. Please try again.']);
        }
    }
}

