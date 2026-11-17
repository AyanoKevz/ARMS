<?php

namespace App\Http\Controllers\Admin\Accreditation;

use App\Http\Controllers\Controller;
use App\Mail\ApplicationEvaluationStartedEmail;
use App\Mail\ApplicationNoticeEmail;
use App\Mail\ApplicationResultEmail;
use App\Models\Accreditation;
use App\Mail\DocumentRejectionEmail;
use App\Mail\InterviewScheduleEmail;
use App\Mail\InterviewSlotSelectionEmail;
use App\Models\InterviewSlot;
use App\Mail\PractitionerInterviewPassedEmail;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationPayment;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\PctEntry;
use App\Services\CacheService;
use Carbon\Carbon;
use App\Services\PctService;
use App\Services\PractitionerApplicationService;
use App\Services\PractitionerArchiveService;
use App\Support\ApplicantStoragePath;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * OSH Practitioner — new applications, handled by the Accreditation division.
 *
 * The same evaluation HCD runs for FATPro (see Admin\HCD\ApplicationController):
 *   Submitted ──(Start Evaluation)──▶ Under Evaluation ──▶ For Update ⇄ Under Evaluation
 *   Under Evaluation ──(Save Approvals)──▶ Scheduled for Interview
 *
 * The whole route group sits behind admin.division:Accreditation, and every
 * admin in the division may start and evaluate any application. Whoever
 * starts the evaluation becomes the application's in-charge.
 *
 * The AF-PCN-A1 form is filed as document PRAC_00, so it is approved or
 * rejected together with the uploads. Rejected items must be re-uploaded
 * within 5 working days or the application is archived (1 month before the
 * applicant may apply again — PractitionerArchiveService).
 *
 * Once approved, the applicant books an open interview slot from the
 * tracking page (InterviewSlotController, TrackingController::bookInterview).
 * The panel interview then runs as FATPro's does — it starts on its own at
 * the slot's time or with Start Interview (opening the meeting link when
 * online), runs at most 2 hours, is stopped (PCT Step 6, Interview Result and
 * Recommendation, starts), and its result recorded:
 *   Passed      ──▶ For Recommendation; the applicant is congratulated
 *   Not Passed  ──▶ Rejected / archived; may apply again after 6 months
 *
 * Then FATPro's recommendation and payment stage: the recommendation form
 * is generated from FATPro's template, its signed scan uploaded (Awaiting
 * Payment, payment instructions emailed, PCT Step 7 paused), the applicant
 * uploads the proof of payment on the tracking page (Payment Verification,
 * Step 7 runs) and an admin validates it: the accreditation number is issued
 * (Approved, Step 8). The certificate is generated from FATPro's template;
 * its signed scan and the ID card are uploaded (Step 9) and released to the
 * applicant by email (the end of the cycle).
 */
class PractitionerController extends Controller
{
    private const PENDING_STATUSES = ['Submitted'];
    private const REVIEW_STATUSES  = ['Under Evaluation', 'For Update'];

    public function __construct(
        private readonly PctService $pct,
        private readonly PractitionerApplicationService $practitioners,
        private readonly PractitionerArchiveService $archives,
    ) {
    }

    /** New applications waiting for their evaluation to start. */
    public function pending()
    {
        return $this->listView('pending', 'practitioner', self::PENDING_STATUSES, 'created_at');
    }

    /** New applications being evaluated or waiting on the applicant. */
    public function underReview()
    {
        return $this->listView('under_review', 'practitioner', self::REVIEW_STATUSES, 'updated_at');
    }

    /** OSH Consultant's Pending list — same process, its own list. */
    public function consultantPending()
    {
        return $this->listView('pending', 'consultant', self::PENDING_STATUSES, 'created_at');
    }

    /** OSH Consultant's Under Review list. */
    public function consultantUnderReview()
    {
        return $this->listView('under_review', 'consultant', self::REVIEW_STATUSES, 'updated_at');
    }

    public function show(Application $application)
    {
        $this->ensurePractitioner($application);

        // The form is evaluated as a document, so make sure it is on file.
        try {
            $this->practitioners->ensureApplicationFormDocument($application);
        } catch (\Throwable $e) {
            Log::error('Could not attach the AF-PCN-A1 form to application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        $application->load([
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'documents.documentField.documentType',
            'documents.userDocument',
            'practitionerProfile',
            'pctEntries',
            'assignedEvaluator.adminProfile',
            'interviewSlot',
            'interview',
            'payment',
            'accreditation',
        ]);

        $this->pct->initializeMissingEntries($application);
        $application->load('pctEntries');

        $status = $application->latestStatus?->status?->name;

        // Also starts the interview once its time has come, and stops it
        // after 2 hours — so read the PCT state after it.
        $pctSummary = $this->pct->getSummary($application);
        $application->load('pctEntries');
        $activePct  = $application->pctEntries->first(fn ($e) => $e->is_active && ! $e->completed_at);

        $interview     = $application->interview;
        $interviewStep = $application->pctEntries->firstWhere('step_number', 5);
        $interviewState = match (true) {
            $status !== 'Scheduled for Interview' || ! $interview || ! $interviewStep => null,
            $interviewStep->completed_at !== null                                  => 'result',
            $interviewStep->stepStatus() === 'paused'                              => 'waiting',
            default                                                                => 'running',
        };

        $documents = $this->evaluatedDocuments($application->documents);

        $evaluationComplete = $application->pctEntries
            ->firstWhere('step_number', 3)?->completed_at !== null;

        // Approve / Reject is open while Under Evaluation and until the
        // approvals are saved; For Update shows the rejected rows read-only.
        $canEvaluate = $status === 'Under Evaluation' && ! $evaluationComplete;

        return view('admin.accreditation.practitioner.show', [
            'application'        => $application,
            'status'             => $status,
            'pctSummary'         => $pctSummary,
            'activeStep'         => $activePct?->step_number ?? 0,
            'pctStatus'          => $activePct?->stepStatus() ?? '',
            'documents'          => $documents,
            'canEvaluate'        => $canEvaluate,
            'evaluationComplete' => $evaluationComplete,
            'holidays'           => PctService::getHolidays(now()->year - 1, now()->year + 1),
            'interview'          => $interview,
            'interviewState'     => $interviewState,
            'interviewLink'      => $interview?->mode === 'online' ? $this->meetingUrl($interview->venue) : '',
            'interviewStartsAt'  => $interview
                ? Carbon::parse($interview->interview_date->format('Y-m-d') . ' ' . $interview->interview_time)
                : null,
            'maxInterviewSeconds' => $this->pct->maxInterviewSeconds($application),
            'archiveReason'      => $this->archives->reasonLabel($application->archive_reason),
            'payment'            => $application->payment,
            // "Update Schedule": the other open slots, while the interview waits.
            'openSlots'          => $interviewState === 'waiting'
                ? InterviewSlot::open()->whereKeyNot($application->interviewSlot?->id ?? 0)->chronological()->get()
                    ->groupBy(fn ($slot) => $slot->slot_date->format('Y-m-d'))
                : collect(),
        ]);
    }

    /**
     * Start the panel interview (PCT Step 5 resumes), as FATPro's Start
     * Interview does. The page opens the meeting link for an online one.
     */
    public function startInterview(Application $application)
    {
        $this->ensurePractitioner($application);

        $step = $this->interviewStep($application);
        if (! $step) {
            return back()->with('error', 'This application has no scheduled interview to start.');
        }

        if ($step->completed_at) {
            return back()->with('error', 'This interview has already been stopped. Record its result.');
        }

        if ($step->stepStatus() === 'paused') {
            $this->pct->resumeCurrentStep($application);
            CacheService::bustApplicationCaches();
        }

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Interview started. Stop it once the panel is done; it stops on its own after 2 hours.');
    }

    /**
     * "Update Schedule" — FATPro's, but onto another open interview slot: the
     * booked slot is freed, the chosen one claimed (only if still open), the
     * interview record moved to it, and the applicant emailed FATPro's
     * schedule-updated notice. Only before the interview has started.
     */
    public function rescheduleInterview(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $request->validate(['slot_id' => ['required', 'integer']], ['slot_id.required' => 'Choose the new interview date and time.']);

        $step      = $this->interviewStep($application);
        $interview = $application->interview;
        if (! $step || ! $interview || $step->stepStatus() !== 'paused') {
            return back()->with('error', 'The schedule can only be changed before the interview starts.');
        }

        $current = $application->interviewSlot;
        $slot    = InterviewSlot::open()->whereKeyNot($current?->id ?? 0)->find($request->integer('slot_id'));
        if (! $slot) {
            return back()->with('error', 'That interview slot is no longer available. Please choose another one.');
        }

        try {
            DB::transaction(function () use ($application, $interview, $current, $slot) {
                // Free the old slot first: application_id is unique on the slots.
                $current?->update(['application_id' => null, 'booked_at' => null]);

                $claimed = InterviewSlot::whereKey($slot->id)
                    ->whereNull('application_id')
                    ->update(['application_id' => $application->id, 'booked_at' => now()]);

                // Taken in the meantime: undo, keeping the old slot.
                if ($claimed !== 1) {
                    throw new \RuntimeException('slot taken');
                }

                $interview->update([
                    'interview_date' => $slot->slot_date->format('Y-m-d'),
                    'interview_time' => $slot->start_time,
                    'mode'           => $slot->mode,
                    'venue'          => $slot->venue,
                ]);
            });
        } catch (\RuntimeException|\Illuminate\Database\QueryException) {
            return back()->with('error', 'That interview slot was just taken. Please choose another one.');
        }

        $slot->refresh();

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $application->latestStatus->status_id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'Interview rescheduled to ' . $slot->slot_date->format('F j, Y') . ', ' . $slot->timeLabel() . '. Applicant notified.',
        ]);

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        $this->mailQuietly($application->user?->email, new InterviewScheduleEmail($application, $interview->fresh(), true), 'interview rescheduled');

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Interview updated successfully. The applicant has been notified via email.');
    }

    /**
     * Stop the panel interview: PCT Step 5 completes and Step 6 (Interview
     * Result and Recommendation) starts, as FATPro's Stop Interview does.
     */
    public function stopInterview(Application $application)
    {
        $this->ensurePractitioner($application);

        $step = $this->interviewStep($application);
        if (! $step) {
            return back()->with('error', 'This application has no scheduled interview.');
        }

        if (! $step->completed_at) {
            if ($step->stepStatus() !== 'active') {
                return back()->with('error', 'Start the interview before stopping it.');
            }

            $this->pct->startPractitionerInterviewResult($application);
            CacheService::bustApplicationCaches();
        }

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Interview stopped. You can now record the result.');
    }

    /**
     * Record the panel interview result.
     *
     * Passed: For Recommendation and the applicant is congratulated, as
     * FATPro's pass is. PCT Step 6 keeps running through the recommendation
     * form until its signed scan is uploaded. The division is not notified —
     * its own admins record the result.
     * Not passed: Rejected and archived; the applicant is emailed and may
     * apply again after 6 months, when the archived record is deleted.
     */
    public function recordInterviewResult(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $request->validate([
            'result' => ['required', 'in:passed,not_passed'],
        ]);

        $step = $this->interviewStep($application);
        if (! $step) {
            return back()->with('error', 'This application has no scheduled interview.');
        }

        if (! $step->completed_at) {
            return back()->with('error', 'Stop the interview before recording its result.');
        }

        if ($request->input('result') === 'not_passed') {
            $this->archives->archive($application, PractitionerArchiveService::INTERVIEW_FAILED, auth()->id());

            return redirect()
                ->route('admin.accreditation.division.applications.archived')
                ->with('success', 'Application ' . $application->tracking_number . ' did not pass the interview and has been archived. The applicant has been notified.');
        }

        // Seeded by ApplicationStatusSeeder; created here if a database
        // predates it.
        $forRecommendation = ApplicationStatus::findByName('For Recommendation');
        if (! $forRecommendation) {
            $forRecommendation = ApplicationStatus::firstOrCreate(['name' => 'For Recommendation']);
            CacheService::bustReferenceCaches();
        }

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $forRecommendation->id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'Passed the panel interview. For recommendation.',
        ]);

        // Step 6 normally started with the stop; this only covers an
        // interview stopped before Step 6 existed.
        try {
            $this->pct->startPractitionerInterviewResult($application);
        } catch (\Throwable $e) {
            Log::error('Practitioner PCT Step 6 start failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        $this->mailQuietly($application->user?->email, new PractitionerInterviewPassedEmail($application), 'interview passed');

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Interview passed. Application ' . $application->tracking_number . ' is now For Recommendation. The applicant has been notified.');
    }

    /**
     * Generate the recommendation form — FATPro's template and flow
     * (HCD ApplicationController::generateRecommendationPDF): the details are
     * posted, kept in the session, and the PDF streams in a new tab.
     */
    public function generateRecommendation(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        if ($request->isMethod('get')) {
            $data = session()->get("rec_pdf_{$application->id}");
            if (! $data) {
                return redirect()->route('admin.accreditation.practitioner.show', $application->id)
                    ->with('error', 'Unable to retrieve recommendation form data. Please generate the form again.');
            }
        } else {
            if (! in_array($application->latestStatus?->status?->name, ['For Recommendation', 'Awaiting Payment', 'Payment Verification'], true)) {
                return back()->with('error', 'The recommendation form is generated once the applicant has passed the interview.');
            }

            $data = $request->validate([
                'date'           => 'required|date',
                'from'           => 'required|string|max:255',
                'to'             => 'required|string|max:255',
                'specialization' => 'nullable|string|max:255',
                'evaluator'      => 'required|string|max:255',
                'interviewers'   => 'nullable|string|max:2000',
                'recommended_by' => 'required|string|max:255',
                'approved_by'    => 'required|string|max:255',
            ]);

            session()->put("rec_pdf_{$application->id}", $data);

            return redirect()->route('admin.accreditation.practitioner.generate_recommendation', $application->id);
        }

        $application->load(['user.individualProfile', 'accreditationType', 'interview']);

        $user          = $application->user;
        $applicantName = $user?->individualProfile?->full_name ?? $user?->name ?? '';
        $interviewers  = array_filter(array_map('trim', explode("\n", (string) ($data['interviewers'] ?? ''))));

        $pdf = Pdf::loadView('admin.hcd.recommendation_pdf', [
            'application'    => $application,
            'applicantName'  => $applicantName,
            'date'           => $data['date'],
            'from'           => $data['from'],
            'to'             => $data['to'],
            'specialization' => $data['specialization'] ?? null,
            'evaluator'      => $data['evaluator'],
            'interviewers'   => $interviewers,
            'recommended_by' => $data['recommended_by'],
            'approved_by'    => $data['approved_by'],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('Recommendation_Form_' . $application->tracking_number . '.pdf');
    }

    /**
     * Upload the scanned, signed recommendation form. PCT Step 6 completes,
     * Step 7 (Payment) opens paused until the applicant pays, the status
     * moves to Awaiting Payment and the applicant is emailed FATPro's payment
     * instructions with the practitioner fee.
     */
    public function uploadRecommendation(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        if ($application->latestStatus?->status?->name !== 'For Recommendation') {
            return back()->with('error', 'The signed recommendation form is uploaded while the application is For Recommendation.');
        }

        $request->validate([
            'signed_recommendation_letter' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'signed_recommendation_letter.required' => 'Choose the scanned signed recommendation form (PDF).',
        ]);

        $payment = $application->payment ?? new ApplicationPayment(['application_id' => $application->id]);
        $disk    = Storage::disk('local');

        if ($payment->signed_recommendation_letter && $disk->exists($payment->signed_recommendation_letter)) {
            $disk->delete($payment->signed_recommendation_letter);
        }

        $payment->signed_recommendation_letter = $request->file('signed_recommendation_letter')->storeAs(
            ApplicantStoragePath::recommendationLetter($application->accreditationType?->name, $application->user_id),
            'signed_recommendation_' . time() . '.pdf',
            'local'
        );
        $payment->save();

        $awaitingPayment = ApplicationStatus::findByName('Awaiting Payment');
        if ($awaitingPayment) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $awaitingPayment->id,
                'updated_by'     => auth()->id(),
                'remarks'        => 'Signed recommendation form uploaded. Payment instructions emailed to the applicant.',
            ]);
        }

        try {
            $this->pct->startPractitionerPayment($application);
        } catch (\Throwable $e) {
            Log::error('Practitioner PCT Step 7 start failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        $this->mailQuietly($application->user?->email, new ApplicationNoticeEmail(
            $application,
            'emails.payment_instructions',
            'Action Required: Submit Payment - ' . $application->tracking_number,
        ), 'payment instructions');

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Signed recommendation form uploaded. The applicant has been emailed the payment instructions.');
    }

    /**
     * Validate the applicant's proof of payment, as FATPro's verifier does.
     *
     * Approved: the accreditation is issued (number, Approved, approval
     * email) and PCT moves from Step 7 to Step 8 (certificate and ID card).
     * Rejected: Step 7 pauses until the applicant re-uploads, and they are
     * emailed FATPro's payment rejection notice with the deadline: 5 working
     * days, after which the application is archived (1 month before they may
     * apply again — PractitionerArchiveService).
     */
    public function evaluatePayment(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $request->validate([
            'proof_of_payment_status'  => ['required', 'in:approved,rejected'],
            'proof_of_payment_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $application->payment;
        if ($application->latestStatus?->status?->name !== 'Payment Verification' || ! $payment?->proof_of_payment || $payment->proof_of_payment_status !== 'pending') {
            return back()->with('error', 'There is no proof of payment waiting to be validated.');
        }

        $approved = $request->input('proof_of_payment_status') === 'approved';

        $payment->update([
            'proof_of_payment_status'  => $approved ? 'approved' : 'rejected',
            'proof_of_payment_remarks' => $approved ? null : $request->input('proof_of_payment_remarks'),
        ]);

        if ($approved) {
            $accreditation = $this->issueAccreditation($application);
        } else {
            $this->pct->pauseCurrentStep($application);

            // 5 working days to re-upload, or the application is archived.
            $dueDate = $this->archives->complianceDueDate();
            $application->forceFill(['compliance_due_date' => $dueDate])->save();

            $awaitingPayment = ApplicationStatus::findByName('Awaiting Payment');
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $awaitingPayment?->id ?? $application->latestStatus->status_id,
                'updated_by'     => auth()->id(),
                'remarks'        => 'Proof of payment was rejected, and applicant has been requested to resubmit by ' . $dueDate->format('F j, Y') . '.',
            ]);

            $this->mailQuietly($application->user?->email, new ApplicationNoticeEmail(
                $application,
                'emails.payment_rejection',
                'Action Required: Correct Your Payment Requirements - ' . $application->tracking_number,
                $payment,
            ), 'payment rejection');
        }

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', $approved
                ? 'Payment validated. Application ' . $application->tracking_number . ' is approved and accredited. Number: ' . $accreditation->accreditation_number
                : 'Proof of payment rejected. The applicant has been asked to upload it again.');
    }

    /**
     * The Certificate of Accreditation on 8.5" x 13", front and back, from the
     * OSHC templates — OSH Practitioner (certificate_pdf) or OSH Consultant
     * (certificate_consultant_pdf). The Executive Director on the front and
     * the OIC-Deputy Executive Director on the back come from the page's
     * Generate Certificate prompt. The back's certification reads "for the
     * renewal of accreditation" (and adds the three-year line) when this cycle
     * came from a renewal.
     */
    public function certificate(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $accreditation = $application->accreditation;
        if (! $accreditation) {
            abort(404, 'This application has not been accredited yet.');
        }

        $accreditation->load(['user.individualProfile', 'accreditationType']);
        $text    = config('accreditation.certificate_text.' . (int) $application->accreditation_type_id, []);
        $profile = $accreditation->user?->individualProfile;
        $back    = config('accreditation.certificate_back_signatory', []);

        $view = $application->isConsultant()
            ? 'admin.accreditation.practitioner.certificate_consultant_pdf'
            : 'admin.accreditation.practitioner.certificate_pdf';

        $pdf = Pdf::loadView($view, [
            'accreditation'      => $accreditation,
            'practitionerName'   => $this->certificateName($profile) ?? $accreditation->user?->name,
            'honorific'          => ['Male' => 'Mr.', 'Female' => 'Ms.'][$profile?->sex] ?? null,
            // "in the field of …" — the practitioner certificate only.
            'field'              => $profile?->type_of_industry === 'Other'
                ? $profile?->type_of_industry_other
                : $profile?->type_of_industry,
            'executiveDirector'  => $request->query('executive_director', 'JOSE MARIA S. BATINO'),
            'certType'           => $text['type'] ?? $application->accreditationTitle(),
            'bodyCopy'           => $text['body'] ?? '',
            'footerNote'         => $text['footer'] ?? '',
            'isRenewal'          => in_array($application->application_type, ['renewal', 'reinstatement'], true),
            // Typed in the pop-up every time (no default: the officer changes).
            'backSignatory'      => trim((string) $request->query('back_signatory', '')),
            'backSignatoryTitle' => trim((string) $request->query('back_signatory_title', '')) ?: ($back['title'] ?? ''),
        ])->setPaper('folio', 'portrait')
            // Embed only the characters used: whole certificate fonts would make it ~4 MB.
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->stream('Accreditation_Certificate_' . $accreditation->accreditation_number . '.pdf');
    }

    /**
     * The certificate's name: first name, middle initial, last name and the
     * suffix after a comma ("Romeo A. Espino, Jr.").
     */
    private function certificateName(?\App\Models\IndividualProfile $profile): ?string
    {
        if (! $profile || trim((string) $profile->first_name . $profile->last_name) === '') {
            return null;
        }

        $middle = trim((string) $profile->middle_name);
        $suffix = trim((string) $profile->suffix);

        return collect([
            trim((string) $profile->first_name),
            $middle !== '' ? mb_strtoupper(mb_substr($middle, 0, 1)) . '.' : null,
            trim((string) $profile->last_name),
        ])->filter()->implode(' ') . ($suffix !== '' ? ', ' . $suffix : '');
    }

    /**
     * Upload the signed certificate (as FATPro's scanned certificate) and the
     * digital or scanned ID card, together or one at a time. Once both are
     * in, PCT Step 8 completes and Step 9 (Release) starts.
     *
     * Until they are released either file can be replaced: the new file is
     * stored and saved first and the old one deleted only after that, so a
     * failed upload leaves the current file in place.
     */
    public function uploadCertificateFiles(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $accreditation = $application->accreditation;
        if (! $accreditation || $application->latestStatus?->status?->name !== 'Approved') {
            return back()->with('error', 'The certificate is uploaded once the application is approved and accredited.');
        }
        if ($accreditation->released_at) {
            return back()->with('error', 'The certificate and ID card have already been released.');
        }

        $request->validate([
            'scanned_certificate' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'id_card'             => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if (! $request->hasFile('scanned_certificate') && ! $request->hasFile('id_card')) {
            return back()->with('error', 'Choose the signed certificate or the ID card to upload.');
        }

        $disk   = Storage::disk('local');
        $folder = ApplicantStoragePath::certificate($application->accreditationType?->name, $application->user_id);

        $stored   = [];
        $replaced = []; // label => the old file's path

        try {
            foreach (['scanned_certificate' => 'Signed certificate', 'id_card' => 'ID card'] as $column => $label) {
                if (! $request->hasFile($column)) {
                    continue;
                }

                $file = $request->file($column);
                // Unique, so a new file can never land on the old one's path.
                $path = $file->storeAs($folder, $column . '_' . time() . '_' . Str::random(6) . '.' . strtolower($file->getClientOriginalExtension() ?: 'pdf'), 'local');
                if (! $path) {
                    throw new \RuntimeException("Could not store the {$label}.");
                }

                $stored[] = $path;
                if ($accreditation->{$column}) {
                    $replaced[$label] = $accreditation->{$column};
                }
                $accreditation->{$column} = $path;
            }
            $accreditation->save();
        } catch (\Throwable $e) {
            $disk->delete($stored);
            Log::error('Practitioner certificate upload failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);

            return back()->with('error', 'The upload failed, so the current files were kept. Please try again.');
        }

        $disk->delete(array_values($replaced));

        $complete     = $accreditation->scanned_certificate && $accreditation->id_card;
        $replacedText = implode(' and ', array_keys($replaced)) . ' replaced.';

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $application->latestStatus->status_id,
            'updated_by'     => auth()->id(),
            'remarks'        => match (true) {
                $replaced !== [] => $replacedText . ($complete ? ' Ready for release.' : ''),
                (bool) $complete => 'Signed certificate and ID card uploaded. Ready for release.',
                default          => $request->hasFile('scanned_certificate') ? 'Signed certificate uploaded.' : 'ID card uploaded.',
            },
        ]);

        if ($complete) {
            $this->pct->startPractitionerRelease($application);
        }

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', ($replaced !== [] ? $replacedText : ($complete ? 'Signed certificate and ID card uploaded.' : 'Uploaded.'))
                . ($complete
                    ? ' Release them to notify the applicant.'
                    : ' Add the ' . ($accreditation->scanned_certificate ? 'ID card' : 'signed certificate') . ' to release them.'));
    }

    /** View the uploaded signed certificate or ID card. */
    public function certificateFile(Application $application, string $file)
    {
        $this->ensurePractitioner($application);

        $column = ['certificate' => 'scanned_certificate', 'id-card' => 'id_card'][$file] ?? abort(404);
        $path   = $application->accreditation?->{$column};

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Release the certificate and ID card: the applicant is emailed that
     * they are ready for pickup (FATPro's certificate-ready email) and PCT
     * Step 9 completes.
     */
    public function releaseCertificate(Application $application)
    {
        $this->ensurePractitioner($application);

        $accreditation = $application->accreditation;
        if (! $accreditation?->scanned_certificate || ! $accreditation->id_card) {
            return back()->with('error', 'Upload both the signed certificate and the ID card before releasing them.');
        }
        if ($accreditation->released_at) {
            return back()->with('error', 'The certificate and ID card have already been released.');
        }

        $accreditation->forceFill(['released_at' => now()])->save();

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $application->latestStatus->status_id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'Certificate and ID card released. Applicant has been emailed that they are ready for pickup.',
        ]);

        $this->pct->completePractitionerRelease($application);

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        $this->mailQuietly($application->user?->email, new ApplicationNoticeEmail(
            $application,
            'emails.certificate_ready',
            'Ready for Pickup: Your Accreditation Certificate and ID - ' . $application->tracking_number,
        ), 'certificate ready');

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Certificate and ID card released. The applicant has been notified by email.');
    }

    /**
     * "Archive Application" — FATPro's archiveFromPayment: an application that
     * will not proceed from the recommendation / payment stage is marked
     * Rejected and archived, and the applicant gets FATPro's "not passed"
     * result. No waiting period, and the record is kept.
     */
    public function archiveApplication(Application $application)
    {
        $this->ensurePractitioner($application);

        if (! in_array($application->latestStatus?->status?->name, ['For Recommendation', 'Awaiting Payment', 'Payment Verification'], true)) {
            return back()->with('error', 'Only an application at the recommendation / payment stage can be archived here.');
        }

        $this->archives->archive($application, PractitionerArchiveService::MANUAL, auth()->id());

        return redirect()
            ->route('admin.accreditation.division.applications.archived')
            ->with('success', 'Application ' . $application->tracking_number . ' has been successfully archived/rejected.');
    }

    /**
     * "Move to Archived" — FATPro's archiveAccreditation: the accreditation is
     * archived (the applicant can no longer log in); the application keeps
     * its Approved status.
     */
    public function archiveAccreditation(Application $application)
    {
        $this->ensurePractitioner($application);

        $accreditation = $application->accreditation;
        if (! $accreditation || $accreditation->status === 'archived') {
            return back()->with('error', 'There is no accreditation to archive.');
        }

        $accreditation->update(['status' => 'archived']);

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $application->latestStatus->status_id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'Accreditation ' . $accreditation->accreditation_number . ' moved to the Archived list.',
        ]);

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        return back()->with('success', $application->accreditationTitle() . ' accreditation #' . $accreditation->accreditation_number . ' has been moved to the Archived list.');
    }

    /** Restore an archived accreditation to active, or expired when its validity has passed. */
    public function unarchiveAccreditation(Application $application)
    {
        $this->ensurePractitioner($application);

        $accreditation = $application->accreditation;
        if ($accreditation?->status !== 'archived') {
            return back()->with('error', 'This accreditation is not archived.');
        }

        $accreditation->update(['status' => $accreditation->validity_date?->isPast() ? 'expired' : 'active']);

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $application->latestStatus->status_id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'Accreditation ' . $accreditation->accreditation_number . ' unarchived and restored to ' . ucfirst($accreditation->status) . '.',
        ]);

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        return back()->with('success', $application->accreditationTitle() . ' accreditation #' . $accreditation->accreditation_number . ' has been unarchived and restored to ' . ucfirst($accreditation->status) . '.');
    }

    /**
     * Payment validated: issue the accreditation, as FATPro's evaluatePayment
     * does — the number, the Approved status, PCT Step 7 → 8, and the
     * applicant's approval email with the accreditation details.
     */
    private function issueAccreditation(Application $application): Accreditation
    {
        $accreditation = DB::transaction(function () use ($application) {
            $number = $this->nextAccreditationNumber((int) $application->accreditation_type_id);

            $accreditation = Accreditation::create([
                'user_id'               => $application->user_id,
                'application_id'        => $application->id,
                'accreditation_type_id' => $application->accreditation_type_id,
                'accreditation_number'  => $number,
                'date_of_accreditation' => now()->toDateString(),
                'validity_date'         => now()->addYears((int) config('accreditation.accreditation_validity_years', 3))->toDateString(),
                'status'                => 'active',
            ]);

            $approvedStatus = ApplicationStatus::findByName('Approved');
            if ($approvedStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $approvedStatus->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Payment validated and approved. Accreditation number ' . $number . ' issued.',
                ]);
            }

            return $accreditation;
        });

        try {
            $this->pct->startPractitionerCertificate($application);
        } catch (\Throwable $e) {
            Log::error('Practitioner PCT Step 8 start failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        $application->load('accreditationType');
        $this->mailQuietly($application->user?->email, new ApplicationResultEmail($application, 'passed', $accreditation), 'accreditation approved');

        return $accreditation;
    }

    /**
     * {prefix}-{date}[-{infix}]-{sequence} with this type's own sequence: it
     * carries on from the last number issued before ARMS (config
     * accreditation.accreditation_numbers) — 1033-YYMMDD-1951 for
     * practitioners, 1030-MMDDYY-OSHC-0519 for consultants.
     */
    private function nextAccreditationNumber(int $typeId): string
    {
        $settings = config("accreditation.accreditation_numbers.{$typeId}", []);
        $prefix   = $settings['prefix'] ?? '235';
        $date     = now()->format($settings['date_format'] ?? 'ymd');
        $infix    = $settings['infix'] ?? null;
        $pad      = (int) ($settings['pad'] ?? 3);

        // The sequence is always the last segment, whatever the format.
        $highest = Accreditation::where('accreditation_type_id', $typeId)
            ->pluck('accreditation_number')
            ->map(fn ($number) => (int) last(explode('-', (string) $number)))
            ->max() ?? 0;

        $sequence = max((int) ($settings['last_issued'] ?? 0), $highest);

        do {
            $sequence++;
            $number = implode('-', array_filter([
                $prefix, $date, $infix, str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT),
            ]));
        } while (Accreditation::where('accreditation_number', $number)->exists());

        return $number;
    }

    /**
     * Stream the AF-PCN-A1 form: the PRAC_00 document when there is one (it
     * may be the applicant's re-upload), else the generated copy, generating
     * it first if submission-time generation never happened.
     */
    public function applicationForm(Application $application)
    {
        $this->ensurePractitioner($application);

        $path = $this->practitioners->applicationFormDocument($application)?->userDocument?->file_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            $path = $application->practitionerProfile?->application_form_path;
        }

        if (! $path || ! Storage::disk('local')->exists($path)) {
            if (! $application->practitionerProfile) {
                abort(404, 'This application has no practitioner record.');
            }

            $path = $this->practitioners->generateApplicationForm($application);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="AF-PCN-A1_' . $application->tracking_number . '.pdf"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /** The applicant's 1"x1" ID photo. */
    public function photo(Application $application)
    {
        $this->ensurePractitioner($application);

        $path = $application->practitionerProfile?->photo_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Start the evaluation, as FATPro's "Start Evaluation" did before
     * evaluators were assigned: the admin who starts it is in charge.
     *
     * Completes PCT Step 2 (Acknowledge the advice from the OED Accreditation
     * Focal), starts Step 3 (Evaluation) and tells the applicant their
     * application was received and is being evaluated.
     */
    public function start(Application $application)
    {
        $this->ensurePractitioner($application);

        $status = $application->latestStatus?->status?->name;
        if ($status !== 'Submitted') {
            CacheService::bustApplicationCaches();

            return back()->with('error', 'Application ' . $application->tracking_number . ' is already ' . ($status ?? 'in progress') . '.');
        }

        $underEvaluation = ApplicationStatus::findByName('Under Evaluation');
        if (! $underEvaluation) {
            return back()->with('error', 'Configuration error: "Under Evaluation" status not found.');
        }

        try {
            DB::transaction(function () use ($application, $underEvaluation) {
                $application->assigned_evaluator_id = auth()->id();
                $application->save();

                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $underEvaluation->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Application is now being evaluated.',
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Practitioner evaluation start failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);

            return back()->with('error', 'Could not start the evaluation right now. Please try again.');
        }

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        // SLA tracking must never block the evaluation itself.
        try {
            $this->pct->startPractitionerEvaluation($application);
        } catch (\Throwable $e) {
            Log::error('Practitioner PCT transition failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        $this->mailQuietly($application->user?->email, new ApplicationEvaluationStartedEmail($application), 'evaluation started');

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with('success', 'Application ' . $application->tracking_number . ' is now Under Evaluation. The applicant has been notified.');
    }

    /**
     * Save one document's Approve / Reject as it is clicked (and its remarks
     * as they are typed) — the endpoint evaluation.js auto-saves to.
     */
    public function evaluateItem(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $request->validate([
            'item_type' => ['required', 'in:document'],
            'item_id'   => ['required', 'integer'],
            'status'    => ['required', 'in:approved,rejected,pending'],
            'remarks'   => ['nullable', 'string', 'max:1000'],
        ]);

        if ($application->latestStatus?->status?->name !== 'Under Evaluation' || $this->evaluationComplete($application)) {
            return response()->json([
                'success' => false,
                'message' => 'Documents can only be evaluated while the application is Under Evaluation.',
            ], 422);
        }

        $document = ApplicationDocument::where('application_id', $application->id)->findOrFail($request->integer('item_id'));
        $status   = $request->input('status');

        $document->update([
            'status'  => $status,
            'remarks' => $status === 'rejected' ? $request->input('remarks') : null,
        ]);

        return response()->json(['success' => true, 'message' => 'Saved']);
    }

    /**
     * Close the evaluation round ("Send Rejection Email" / "Save Approvals").
     *
     * Any rejected document sends the application back to the applicant
     * (For Update) with the rejection email, and pauses the PCT clock until
     * they re-upload through the tracking page — the same as FATPro. When
     * every document is approved the application is for interview: PCT Step 3
     * completes, Step 4 is recorded, and the applicant is emailed to choose
     * an open interview slot.
     */
    public function finalizeEvaluation(Request $request, Application $application)
    {
        $this->ensurePractitioner($application);

        $request->validate([
            'evaluations'           => ['nullable', 'array'],
            'evaluations.*.id'      => ['required', 'integer'],
            'evaluations.*.status'  => ['required', 'in:approved,rejected,pending,returned'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($application->latestStatus?->status?->name !== 'Under Evaluation' || $this->evaluationComplete($application)) {
            return $this->finalizeResponse($request, $application, false, 'Only an application Under Evaluation can be finalized.', status: 422);
        }

        $evaluations = collect($request->input('evaluations', []))
            ->map(fn ($row) => ['id' => (int) $row['id']] + $row);

        $models = ApplicationDocument::where('application_id', $application->id)
            ->whereIn('id', $evaluations->pluck('id'))
            ->get()
            ->keyBy('id');

        foreach ($evaluations->values() as $i => $row) {
            if (! $models->has($row['id'])) {
                throw ValidationException::withMessages(["evaluations.{$i}.id" => 'The selected item is invalid.']);
            }
        }

        // An approved document stays approved unless it is explicitly
        // rejected — the same guard FATPro's evaluation uses.
        foreach ($evaluations as $row) {
            $model     = $models->get($row['id']);
            $newStatus = $row['status'];

            if ($model->status === 'approved' && ! in_array($newStatus, ['rejected', 'returned'], true)) {
                continue;
            }

            $remarks = $newStatus === 'rejected' ? ($row['remarks'] ?? null) : null;
            if ($model->status !== $newStatus || $model->remarks !== $remarks) {
                $model->update(['status' => $newStatus, 'remarks' => $remarks]);
            }
        }

        $documents = $this->evaluatedDocuments(
            $application->documents()->with(['documentField', 'userDocument'])->get()
        );

        $rejected = $documents->whereIn('status', ['rejected', 'returned'])->values();

        if ($rejected->isNotEmpty()) {
            $forUpdate = ApplicationStatus::findByName('For Update');
            if ($forUpdate) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $forUpdate->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Application has rejected documents. Notification sent to applicant.',
                ]);
            }

            // PCT: pause Step 3 while waiting for the applicant's re-upload.
            $this->pct->pauseCurrentStep($application);

            // 5 working days to re-upload, or the application is archived.
            $dueDate = $this->archives->complianceDueDate();
            $application->forceFill(['compliance_due_date' => $dueDate])->save();

            CacheService::bustApplicationCaches();
            CacheService::bustTrackingCache($application->tracking_number);

            $this->mailQuietly(
                $application->user?->email,
                (new DocumentRejectionEmail($application, $rejected))->withComplianceDeadline(
                    $dueDate,
                    PractitionerArchiveService::REAPPLY_MONTHS[PractitionerArchiveService::NON_COMPLIANCE]
                ),
                'document rejection'
            );

            return $this->finalizeResponse($request, $application, true, 'Rejection notice sent successfully.', [
                'action'     => 'rejection_sent',
                'new_status' => 'For Update',
            ]);
        }

        if ($documents->contains(fn ($doc) => $doc->status !== 'approved')) {
            return $this->finalizeResponse($request, $application, false, 'Approve or reject every document before saving the evaluation.', status: 422);
        }

        // All approved: for interview, as FATPro. The applicant books one of
        // the open interview slots from the tracking page.
        $forInterview = ApplicationStatus::findByName('Scheduled for Interview');
        if (! $forInterview) {
            return $this->finalizeResponse($request, $application, false, 'Configuration error: "Scheduled for Interview" status not found.', status: 500);
        }

        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $forInterview->id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'All documents approved. Applicant asked to choose an interview schedule.',
        ]);

        // PCT: Step 3 done; Step 4 (Set up Interview Schedule) is the
        // applicant's, so it is recorded as done with no time counted.
        try {
            $this->pct->completePractitionerEvaluation($application);
        } catch (\Throwable $e) {
            Log::error('Practitioner PCT completion failed for application ' . $application->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        CacheService::bustApplicationCaches();
        CacheService::bustTrackingCache($application->tracking_number);

        $this->mailQuietly($application->user?->email, new InterviewSlotSelectionEmail($application), 'interview slot selection');

        return $this->finalizeResponse($request, $application, true, 'All documents approved. The applicant has been emailed to choose an interview schedule.', [
            'action'     => 'proceed_to_interview',
            'new_status' => 'Scheduled for Interview',
        ]);
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    private function ensurePractitioner(?Application $application): void
    {
        if (! $application || ! $application->isPractitioner() || $application->application_type !== 'new') {
            abort(404);
        }
    }

    /**
     * PCT Step 5 of an application that is for interview and has booked one,
     * or null.
     */
    private function interviewStep(Application $application): ?PctEntry
    {
        if ($application->latestStatus?->status?->name !== 'Scheduled for Interview' || ! $application->interview()->exists()) {
            return null;
        }

        return $application->pctEntries()->where('step_number', 5)->first();
    }

    /** The meeting link as a URL the browser can open. */
    private function meetingUrl(?string $link): string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return '';
        }

        return preg_match('/^https?:\/\//i', $link) ? $link : 'https://' . $link;
    }

    private function evaluationComplete(Application $application): bool
    {
        return $application->pctEntries()->where('step_number', 3)->whereNotNull('completed_at')->exists();
    }

    /**
     * Documents with a file on record, the AF-PCN-A1 (PRAC_00) first and the
     * uploads after it in code order.
     */
    private function evaluatedDocuments(Collection $documents): Collection
    {
        return $documents
            ->filter(fn ($doc) => $doc->userDocument?->file_path)
            ->sortBy(fn ($doc) => $doc->documentField?->code)
            ->values();
    }

    /** A Pending / Under Review list of one type ("practitioner" / "consultant"). */
    private function listView(string $view, string $type, array $statuses, string $orderBy)
    {
        $typeId = (int) config("accreditation.types.{$type}");

        return view("admin.accreditation.practitioner.{$view}", [
            'applications' => $this->listing($typeId, $statuses, $orderBy),
            'typeLabel'    => config("accreditation.accreditation_division_types.{$typeId}", 'OSH Practitioner'),
        ]);
    }

    private function listing(int $typeId, array $statuses, string $orderBy): Collection
    {
        return Application::with([
            'user.individualProfile',
            'latestStatus.status',
            'assignedEvaluator.adminProfile',
        ])
            ->where('accreditation_type_id', $typeId)
            ->where('application_type', 'new')
            ->whereHas('latestStatus.status', fn ($q) => $q->whereIn('name', $statuses))
            ->orderByDesc($orderBy)
            ->get();
    }

    private function finalizeResponse(Request $request, Application $application, bool $ok, string $message, array $extra = [], int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $ok, 'message' => $message] + $extra, $status);
        }

        return redirect()
            ->route('admin.accreditation.practitioner.show', $application->id)
            ->with($ok ? 'success' : 'error', $message);
    }

    private function mailQuietly(?string $to, $mailable, string $what): void
    {
        if (! $to) {
            return;
        }

        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::error("Failed to send practitioner {$what} email: " . $e->getMessage());
        }
    }
}
