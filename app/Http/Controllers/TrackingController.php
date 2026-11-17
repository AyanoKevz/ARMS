<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\UserDocument;
use App\Models\Accreditation;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\DocumentField;
use App\Services\CacheService;
use App\Support\ApplicantStoragePath;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\AdminDocumentsUploadedEmail;
use App\Mail\AdminInterviewSlotBookedEmail;
use App\Mail\InterviewScheduleEmail;
use App\Models\Interview;
use App\Models\InterviewSlot;
use App\Services\PctService;
use App\Services\PractitionerApplicationService;
use App\Services\PractitionerArchiveService;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    /**
     * The application a tracking-page form acts on, found by the tracking
     * number the applicant looked up.
     *
     * Never by database id: ids are sequential, so a hidden application_id
     * field could be edited to a neighbouring number to upload files into a
     * stranger's application without signing in. Only 'new' applications are
     * tracked publicly — renewals go through the portal — matching index().
     */
    private function trackedApplication(Request $request, array $with = []): ?Application
    {
        $trackingNumber = strtoupper(trim((string) $request->input('tracking_number')));
        if ($trackingNumber === '' || strlen($trackingNumber) > 32) {
            return null;
        }

        $application = Application::with($with)->where('tracking_number', $trackingNumber)->first();

        if (!$application || strtolower($application->application_type) !== 'new') {
            return null;
        }

        return $application;
    }

    /**
     * Display the tracking page, resolving the application if a tracking number is provided.
     */
    public function index(Request $request)
    {
        $application = null;

        if ($request->has('tracking_number')) {
            $trackingNumber = strtoupper(trim((string) $request->input('tracking_number')));

            // The cache key is derived from this value, so an unvalidated string
            // let anyone mint unlimited cache entries (and rows in the file
            // cache) by varying the query string. Numbers are generated as
            // ARMS{year}-{2 letters}{4 digits} in RegistrationController; a
            // value that cannot be one is not worth a database round trip.
            if (! preg_match('/^ARMS\d{4}-[A-Z]{2}\d{4}$/', $trackingNumber)) {
                return view('landing.track', ['application' => null]);
            }

            $application = CacheService::remember(
                CacheService::trackingKey($trackingNumber),
                CacheService::TTL_TRACKING,
                fn () => Application::with([
                    'latestStatus.status',
                    'documents.documentField.documentType',
                    'documents.userDocument',
                    'interview',
                    'instructors.credentials',
                ])->where('tracking_number', $trackingNumber)->first()
            );

            if ($application && strtolower($application->application_type) !== 'new') {
                return redirect()->route('track')->with('error', 'This tracking number belongs to a ' . ucfirst($application->application_type) . ' application. Please log in to your applicant portal to track its status.');
            }
        }

        // Upcoming interview slots (taken ones show as Fully Booked), read
        // fresh rather than from the cached application — another applicant
        // may have just taken one.
        $interviewSlots = $application && $this->choosesInterviewSlot($application)
            ? InterviewSlot::upcoming()->chronological()->get()->groupBy(fn ($slot) => $slot->slot_date->format('Y-m-d'))
            : collect();

        // A rejected AF-PCN-A1 comes back as the form itself, filled in.
        $pracValues = $application && $this->applicationFormToCorrect($application)
            ? app(PractitionerApplicationService::class)->correctionValues($application, $request->session()->getOldInput())
            : null;

        return view('landing.track', compact('application', 'interviewSlots', 'pracValues'));
    }

    /**
     * The practitioner's rejected AF-PCN-A1, while the application waits for
     * the applicant's corrections; null otherwise.
     */
    private function applicationFormToCorrect(Application $application): ?ApplicationDocument
    {
        if (! $application->isPractitioner()
            || $application->latestStatus?->status?->name !== 'For Update') {
            return null;
        }

        $application->loadMissing('documents.documentField');

        return $application->documents->first(fn ($d) =>
            PractitionerApplicationService::isApplicationFormCode($d->documentField?->code)
            && in_array($d->status, ['rejected', 'returned'], true));
    }

    /**
     * The applicant books one of the Accreditation division's open interview
     * slots. The slot is claimed atomically (only while still unbooked), the
     * application's interview record is written from it, the applicant gets
     * the confirmation email and every Accreditation admin is emailed what was
     * chosen.
     */
    public function bookInterview(Request $request)
    {
        $request->validate([
            'slot_id' => ['required', 'integer'],
        ], [
            'slot_id.required' => 'Please choose an interview date and time.',
        ]);

        $application = $this->trackedApplication($request, ['latestStatus.status', 'interview', 'user']);
        if (! $application) {
            return back()->with('error', 'Application not found.');
        }

        if (! $this->choosesInterviewSlot($application)) {
            return back()->with('error', $application->interview
                ? 'Your interview schedule has already been set.'
                : 'Your application is not yet open for interview scheduling.');
        }

        $slot = InterviewSlot::open()->find($request->integer('slot_id'));
        if (! $slot) {
            return back()->with('error', 'That interview slot is no longer available. Please choose another one.');
        }

        try {
            $booked = DB::transaction(function () use ($application, $slot) {
                // Claim it only if nobody else did in the meantime.
                $claimed = InterviewSlot::whereKey($slot->id)
                    ->whereNull('application_id')
                    ->update(['application_id' => $application->id, 'booked_at' => now()]);

                if ($claimed !== 1) {
                    return null;
                }

                return Interview::create([
                    'application_id' => $application->id,
                    'interview_date' => $slot->slot_date->format('Y-m-d'),
                    'interview_time' => $slot->start_time,
                    'mode'           => $slot->mode,
                    'venue'          => $slot->venue,
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // The unique application_id on interview_slots: a double submit.
            Log::warning('Interview slot booking conflict for ' . $application->tracking_number . ': ' . $e->getMessage());
            $booked = null;
        }

        if (! $booked) {
            return back()->with('error', 'That interview slot was just taken by another applicant. Please choose another one.');
        }

        $slot->refresh();

        // PCT Step 5 (Panel Interview) waits, paused, until the slot starts.
        try {
            app(PctService::class)->schedulePractitionerInterview($application);
        } catch (\Throwable $e) {
            Log::error('Interview PCT step failed for ' . $application->tracking_number . ': ' . $e->getMessage());
        }

        CacheService::bustTrackingCache($application->tracking_number);
        CacheService::bustApplicationCaches();

        try {
            if ($application->user?->email) {
                Mail::to($application->user->email)->send(new InterviewScheduleEmail($application, $booked));
            }
        } catch (\Throwable $e) {
            Log::warning('Interview confirmation email failed for ' . $application->tracking_number . ': ' . $e->getMessage());
        }

        // Every Accreditation admin hears about it, as with a new application.
        $slot->refresh();
        foreach (AdminAccess::membersOf(AdminAccess::ACCREDITATION)->get() as $admin) {
            if (! $admin->email) {
                continue;
            }

            try {
                Mail::to($admin->email)->send(new AdminInterviewSlotBookedEmail($application, $slot));
            } catch (\Throwable $e) {
                Log::warning('Interview slot notification to ' . $admin->email . ' failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Your interview is set for ' . $slot->slot_date->format('l, F j, Y') . ', ' . $slot->timeLabel() . '. A confirmation has been emailed to you.');
    }

    /**
     * Approved Accreditation division (Practitioner / Consultant) applicants
     * choose their own interview slot; FATPro interviews are set by HCD.
     */
    private function choosesInterviewSlot(Application $application): bool
    {
        return AdminAccess::isAccreditationDivisionType($application->accreditation_type_id)
            && $application->latestStatus?->status?->name === 'Scheduled for Interview'
            && ! $application->interview;
    }

    /**
     * Handle batch resubmission of all rejected documents at once.
     *
     * Files are keyed by application_document.id in the request (files[{id}]).
     *
     * Fix 1 – No stacking: Deletes both the previously recorded file path AND
     *          any file already at the canonical target path before storing.
     *
     * Fix 2 – No mismatch: Resolves UserDocument via the unique key
     *          (user_id + document_field_id) rather than trusting the FK on
     *          application_documents.user_document_id, which can be stale.
     *          Also re-links application_document.user_document_id after saving.
     */
    public function resubmitAll(Request $request)
    {
        $application = $this->trackedApplication($request, ['instructors.credentials', 'accreditationType', 'latestStatus.status']);
        if (!$application) {
            return back()->with('error', 'Application not found.');
        }
        $statusName = $application->latestStatus?->status?->name;
        if ($statusName !== 'For Update') {
            return back()->with('error', 'Invalid action. You can only resubmit documents if your application status is "For Update".');
        }

        // Accreditation division: the 5-working-day re-upload window has
        // closed (the daily run archives it; this catches the gap before it).
        $dueDate = $application->compliance_due_date;
        if ($dueDate && $dueDate->lt(now()->startOfDay())) {
            app(PractitionerArchiveService::class)->archive($application, PractitionerArchiveService::NON_COMPLIANCE);

            return back()->with('error', 'The deadline to re-upload your documents was ' . $dueDate->format('F j, Y') . '. Your application has been archived.');
        }

        // A rejected AF-PCN-A1 is corrected on the page (Steps 3–13 again,
        // pre-filled) rather than re-uploaded, and generated anew from the answers.
        $practitioners = app(PractitionerApplicationService::class);
        $formDoc       = $this->applicationFormToCorrect($application);

        $request->validate(array_merge([
            'files'          => ['nullable', 'array'],
            'files.*'        => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'values'         => ['nullable', 'array'],
            'values.*'       => ['required', 'string', 'max:500'],
            'instructor_files' => ['nullable', 'array'],
            'instructor_files.*' => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'cv_files' => ['nullable', 'array'],
            'cv_files.*' => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'legal03_file' => ['nullable', 'file', 'mimes:pdf', 'max:15360'],            'credential_files' => ['nullable', 'array'],
            'credential_files.*' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ], $formDoc ? $practitioners->correctionRules($request) : []), [], $formDoc ? $practitioners->attributes() : []);

        $userId      = $application->user_id;

        $accreditationName = $application->accreditationType ? $application->accreditationType->name : null;

        $baseDocPath = ApplicantStoragePath::documents($accreditationName, $userId);
        $baseCredPath = ApplicantStoragePath::credentials($accreditationName, $userId);
        $resubmitted = 0;

        // First, so a failure here leaves nothing else half-replaced.
        if ($formDoc) {
            try {
                $practitioners->correct(
                    $application,
                    (array) $request->input('person', []),
                    (array) $request->input('practitioner', []),
                    $request->file('practitioner.profile.photo'),
                    $baseDocPath
                );
            } catch (\Throwable $e) {
                Log::error('Application form correction failed for ' . $application->tracking_number . ': ' . $e->getMessage(), ['exception' => $e]);

                return back()->withInput()->with('error', 'Your application form could not be saved. Please try again.');
            }

            $formDoc->update(['status' => 'pending', 'remarks' => null]);
            $resubmitted++;
        }

        $files             = $request->file('files') ?? [];
        $values            = $request->input('values') ?? [];
        $instructorFiles   = $request->file('instructor_files') ?? [];
        $cvFiles           = $request->file('cv_files') ?? [];
        $credentialFiles   = $request->file('credential_files') ?? [];

        foreach ($values as $appDocId => $value) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type === 'file') continue;

            // Fields with a fixed choice list are rendered as a <select>, but the
            // resubmit endpoints accept a raw POST — so re-check the value here
            // rather than trusting the form.
            $allowed = $field->selectOptions();
            if ($allowed !== null && ! array_key_exists($value, $allowed)) {
                continue;
            }

            $userDoc = $appDoc->userDocument;

            if ($userDoc) {
                $userDoc->update(['value' => $value]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $userId,
                    'document_field_id' => $field->id,
                    'value'             => $value,
                ]);
            }

            $appDoc->update([
                'user_document_id' => $userDoc->id,
                'status'           => 'pending',
                'remarks'          => null,
            ]);

            $resubmitted++;
        }

        // ── SEC-only: Articles of Incorporation ─────────────────────────────
        // The registering authority may have just changed to SEC, in which case
        // this document has never been uploaded and has no rejected row of its
        // own. Resolve the authority AFTER the value loop above so a change made
        // in this same submission counts.
        $legal03File = $request->file('legal03_file');
        if ($legal03File) {
            $authority = ApplicationDocument::with('userDocument')
                ->where('application_id', $application->id)
                ->whereHas('documentField', fn ($q) => $q->where('code', 'LEGAL_02_TYPE'))
                ->first()?->userDocument?->value;

            if ($authority === 'SEC') {
                $legal03Field = DocumentField::where('code', 'LEGAL_03')->first();

                if ($legal03Field) {
                    $timestamp = time();
                    $filename  = "LEGAL_03_{$timestamp}.pdf";
                    $finalPath = "{$baseDocPath}/{$filename}";

                    $legal03Doc = ApplicationDocument::with('userDocument')
                        ->where('application_id', $application->id)
                        ->where('document_field_id', $legal03Field->id)
                        ->first();

                    $userDoc = $legal03Doc?->userDocument
                        ?? UserDocument::where('user_id', $userId)
                            ->where('document_field_id', $legal03Field->id)
                            ->first();

                    if ($userDoc && $userDoc->file_path && Storage::disk('local')->exists($userDoc->file_path)) {
                        Storage::disk('local')->delete($userDoc->file_path);
                    }

                    $legal03File->storeAs($baseDocPath, $filename, 'local');

                    if ($userDoc) {
                        $userDoc->update(['file_path' => $finalPath]);
                    } else {
                        $userDoc = UserDocument::create([
                            'user_id'           => $userId,
                            'document_field_id' => $legal03Field->id,
                            'file_path'         => $finalPath,
                        ]);
                    }

                    if ($legal03Doc) {
                        $legal03Doc->update([
                            'user_document_id' => $userDoc->id,
                            'status'           => 'pending',
                            'remarks'          => null,
                        ]);
                    } else {
                        ApplicationDocument::create([
                            'application_id'    => $application->id,
                            'document_field_id' => $legal03Field->id,
                            'user_document_id'  => $userDoc->id,
                            'status'            => 'pending',
                        ]);
                    }

                    $resubmitted++;
                }
            }
        }
        foreach ($files as $appDocId => $file) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type !== 'file') continue;
            // Generated from the corrected answers above, never uploaded.
            if (PractitionerApplicationService::isApplicationFormCode($field->code)) continue;

            $code      = $field->code;
            $timestamp = time();
            $filename  = "{$code}_{$timestamp}.pdf";
            $subFolder = $baseDocPath;
            $finalPath = "{$subFolder}/{$filename}";

            $userDoc = $appDoc->userDocument;

            if ($userDoc && $userDoc->file_path) {
                if (Storage::disk('local')->exists($userDoc->file_path)) {
                    Storage::disk('local')->delete($userDoc->file_path);
                }
            }

            $file->storeAs($subFolder, $filename, 'local');

            if ($userDoc) {
                $userDoc->update(['file_path' => $finalPath]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $userId,
                    'document_field_id' => $field->id,
                    'file_path'         => $finalPath,
                ]);
            }

            $appDoc->update([
                'user_document_id' => $userDoc->id,
                'status'           => 'pending',
                'remarks'          => null,
            ]);

            $resubmitted++;
        }

        foreach ($instructorFiles as $instructorId => $file) {
            $instructor = $application->instructors->firstWhere('id', $instructorId);
            if (! $instructor || ! in_array($instructor->status, ['rejected', 'returned'])) continue;

            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->first_name));
            $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->last_name));
            $filename  = "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $subFolder = $baseCredPath;
            $finalPath = "{$subFolder}/{$filename}";

            if ($instructor->service_agreement_path && Storage::disk('local')->exists($instructor->service_agreement_path)) {
                Storage::disk('local')->delete($instructor->service_agreement_path);
            }

            $file->storeAs($subFolder, $filename, 'local');

            $instructor->update([
                'service_agreement_path' => $finalPath,
                'status'                 => 'pending',
                'remarks'                => null,
            ]);

            $resubmitted++;
        }

        // Instructor CVs. Keyed by instructor id like the service agreements, but
        // gated on cv_status so rejecting one never forces a re-upload of the other.
        foreach ($cvFiles as $instructorId => $file) {
            $instructor = $application->instructors->firstWhere('id', $instructorId);
            if (! $instructor || ! in_array($instructor->cv_status, ['rejected', 'returned'])) continue;

            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->first_name));
            $instLast  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->last_name));
            $filename  = "cv_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $finalPath = "{$baseCredPath}/{$filename}";

            if ($instructor->cv_path && Storage::disk('local')->exists($instructor->cv_path)) {
                Storage::disk('local')->delete($instructor->cv_path);
            }

            $file->storeAs($baseCredPath, $filename, 'local');

            $instructor->update([
                'cv_path'    => $finalPath,
                'cv_status'  => 'pending',
                'cv_remarks' => null,
            ]);

            $resubmitted++;
        }

        foreach ($credentialFiles as $credentialId => $file) {
            $credential = null;
            $instModel = null;
            foreach ($application->instructors as $inst) {
                $cred = $inst->credentials->firstWhere('id', $credentialId);
                if ($cred) {
                    $credential = $cred;
                    $instModel = $inst;
                    break;
                }
            }
            if (! $credential || ! in_array($credential->status, ['rejected', 'returned'])) continue;

            $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->type));
            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instModel->first_name));
            $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instModel->last_name));
            $filename  = "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $subFolder = $baseCredPath;
            $finalPath = "{$subFolder}/{$filename}";

            if ($credential->pdf_path && Storage::disk('local')->exists($credential->pdf_path)) {
                Storage::disk('local')->delete($credential->pdf_path);
            }

            $file->storeAs($subFolder, $filename, 'local');

            $credential->update([
                'pdf_path' => $finalPath,
                'status'   => 'pending',
                'remarks'  => null,
            ]);

            $resubmitted++;
        }

        if ($resubmitted === 0) {
            return back()->with('error', 'No valid documents were resubmitted. Please ensure you are uploading PDF files for rejected items.');
        }

        // Progress application status back to "Under Evaluation"
        $underEvaluationStatus = ApplicationStatus::findByName('Under Evaluation');
        if ($underEvaluationStatus) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $underEvaluationStatus->id,
                'updated_by'     => null,
                'remarks'        => 'Documents resubmitted by applicant. Application is back under evaluation.',
            ]);
        }

        // ── PCT: Resume the paused step (applicant has resubmitted)
        app(PctService::class)->resumeCurrentStep($application);

        // Re-uploaded in time: the archive deadline no longer applies.
        if ($application->compliance_due_date) {
            $application->forceFill(['compliance_due_date' => null])->save();
        }

        // Bust tracking cache and listing caches
        CacheService::bustTrackingCache($application->tracking_number);
        CacheService::bustApplicationCaches();

        // Notify the assigned Evaluator only about resubmitted documents
        try {
            $application->loadMissing('assignedEvaluator');
            $assignedEvaluatorEmail = $application->assignedEvaluator?->email;

            if ($assignedEvaluatorEmail) {
                $application->load(['user', 'accreditationType']);
                Mail::to($assignedEvaluatorEmail)->send(new AdminDocumentsUploadedEmail($application, $resubmitted));
            } else {
                Log::warning('Resubmitted documents notification skipped: application ' . $application->tracking_number . ' has no assigned evaluator.');
            }
        } catch (\Exception $mailEx) {
            Log::warning('Admin resubmitted documents notification failed: ' . $mailEx->getMessage());
        }

        return back()->with('success',
            "{$resubmitted} document" . ($resubmitted > 1 ? 's' : '') .
            " successfully resubmitted. Your application is now back under review."
        );
    }

    /**
     * Handle public submission of payment requirements.
     */
    public function submitPaymentPublic(Request $request)
    {
        $application = $this->trackedApplication($request, ['latestStatus.status', 'payment', 'accreditationType']);
        if (!$application) {
            return back()->with('error', 'Application not found.');
        }

        // Same condition the tracking page uses to show the upload form: a
        // hand-made POST must not replace a proof of payment that is already
        // under review or approved.
        $statusName = $application->latestStatus?->status?->name;
        $paymentRejected = $application->payment?->proof_of_payment_status === 'rejected';
        if ($statusName !== 'Awaiting Payment' && !($statusName === 'Payment Verification' && $paymentRejected)) {
            return back()->with('error', 'Invalid action. Proof of payment can only be uploaded while your application is awaiting payment.');
        }

        // Accreditation division: a rejected proof of payment must be
        // re-uploaded within 5 working days (the daily run archives it;
        // this catches the gap before it).
        $dueDate = $application->compliance_due_date;
        if ($dueDate && $dueDate->lt(now()->startOfDay())) {
            app(PractitionerArchiveService::class)->archive($application, PractitionerArchiveService::PAYMENT_NON_COMPLIANCE);

            return back()->with('error', 'The deadline to re-upload your proof of payment was ' . $dueDate->format('F j, Y') . '. Your application has been archived.');
        }

        $request->validate([
            'proof_of_payment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:15360'],
        ]);

        $payment = $application->payment ?? new \App\Models\ApplicationPayment(['application_id' => $application->id]);

        $accreditationType = $application->accreditationType;
        $accreditationName = $accreditationType ? $accreditationType->name : null;
        $proofPaymentPath = ApplicantStoragePath::proofOfPayments($accreditationName, $application->user_id);

        $changed = false;

        // Process proof_of_payment
        if ($request->hasFile('proof_of_payment')) {
            if ($payment->proof_of_payment && Storage::disk('local')->exists($payment->proof_of_payment)) {
                Storage::disk('local')->delete($payment->proof_of_payment);
            }
            $ext = $request->file('proof_of_payment')->getClientOriginalExtension();
            $filename = "proof_of_payment_" . time() . ".{$ext}";
            $path = $request->file('proof_of_payment')->storeAs($proofPaymentPath, $filename, 'local');
            $payment->proof_of_payment = $path;
            $payment->proof_of_payment_status = 'pending';
            $payment->proof_of_payment_remarks = null;
            $changed = true;
        }

        if ($changed) {
            $payment->save();

            // ── PCT: Resume the paused Step 7 (payment re-uploaded)
            app(PctService::class)->resumeCurrentStep($application);

            // Re-uploaded in time: the archive deadline no longer applies.
            if ($application->compliance_due_date) {
                $application->forceFill(['compliance_due_date' => null])->save();
            }

            // ── Transition status to 'Payment Verification'
            $paymentVerificationStatus = ApplicationStatus::findByName('Payment Verification');
            if ($paymentVerificationStatus) {
                \App\Models\ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $paymentVerificationStatus->id,
                    'updated_by'     => null,
                    'remarks'        => 'Proof of payment submitted by applicant. Awaiting verifier review.',
                ]);
            }

            // Notify Verifiers via Notification system (Email + DB)
            try {
                // Every Accreditation admin (Verifiers and their Team Lead)
                // handles FATPro payment verification.
                $verifiers = \App\Support\AdminAccess::membersOf(\App\Support\AdminAccess::ACCREDITATION)->get();

                if ($verifiers->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($verifiers, new \App\Notifications\PaymentSubmittedNotification($application));
                }
            } catch (\Exception $e) {
                Log::warning('Verifier notification email failed: ' . $e->getMessage());
            }

            // Bust tracking and listing caches
            CacheService::bustTrackingCache($application->tracking_number);
            CacheService::bustApplicationCaches();

            return back()->with('success', 'Payment details uploaded successfully. Your submission is now pending verifier evaluation.');
        }

        return back()->with('error', 'No new payment files were selected.');
    }
}
