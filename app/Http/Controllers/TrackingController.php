<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\UserDocument;
use App\Models\Accreditation;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\AdminDocumentsUploadedEmail;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Services\PctService;

class TrackingController extends Controller
{

    /**
     * Display the tracking page, resolving the application if a tracking number is provided.
     */
    public function index(Request $request)
    {
        $application = null;

        if ($request->has('tracking_number')) {
            $application = Application::with([
                'latestStatus.status',
                'documents.documentField.documentType',
                'documents.userDocument',
                'interview',
                'user.instructors.credentials.instructor',
            ])->where('tracking_number', $request->input('tracking_number'))->first();

            if ($application && strtolower($application->application_type) !== 'new') {
                return redirect()->route('track')->with('error', 'This tracking number belongs to a ' . ucfirst($application->application_type) . ' application. Please log in to your applicant portal to track its status.');
            }
        }

        return view('landing.track', compact('application'));
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
        $application = Application::with(['user.instructors.credentials', 'accreditationType', 'latestStatus.status'])->find($request->input('application_id'));
        if (!$application) {
            return back()->with('error', 'Application not found.');
        }
        $statusName = $application->latestStatus?->status?->name;
        if ($statusName !== 'For Update') {
            return back()->with('error', 'Invalid action. You can only resubmit documents if your application status is "For Update".');
        }

        $request->validate([
            'application_id' => ['required', 'exists:applications,id'],
            'files'          => ['nullable', 'array'],
            'files.*'        => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'values'         => ['nullable', 'array'],
            'values.*'       => ['required', 'string', 'max:500'],
            'instructor_files' => ['nullable', 'array'],
            'instructor_files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'credential_files' => ['nullable', 'array'],
            'credential_files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $userId      = $application->user_id;
        
        $accreditationName = $application->accreditationType ? $application->accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));

        $fatProName = $application->user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';

        $baseDocPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/documents";
        $baseCredPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials";
        
        $files             = $request->file('files') ?? [];
        $values            = $request->input('values') ?? [];
        $instructorFiles   = $request->file('instructor_files') ?? [];
        $credentialFiles   = $request->file('credential_files') ?? [];
        $resubmitted       = 0;

        foreach ($values as $appDocId => $value) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type === 'file') continue;

            $userDoc = UserDocument::where('user_id', $userId)
                ->where('document_field_id', $field->id)
                ->first();

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

        foreach ($files as $appDocId => $file) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type !== 'file') continue;

            $code      = $field->code;
            $timestamp = time();
            $filename  = "{$code}_{$timestamp}.pdf";
            $subFolder = $baseDocPath;
            $finalPath = "{$subFolder}/{$filename}";

            $userDoc = UserDocument::where('user_id', $userId)
                ->where('document_field_id', $field->id)
                ->first();

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
            $instructor = $application->user->instructors->firstWhere('id', $instructorId);
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

        foreach ($credentialFiles as $credentialId => $file) {
            $credential = null;
            $instModel = null;
            foreach ($application->user->instructors as $inst) {
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
        $underEvaluationStatus = ApplicationStatus::where('name', 'Under Evaluation')->first();
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

        // Notify Admin Evaluators about resubmitted documents
        try {
            $evaluatorEmails = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                $q->where('name', 'Evaluator');
            })->pluck('email');

            if ($evaluatorEmails->isNotEmpty()) {
                $application->load(['user', 'accreditationType']);
                Mail::to($evaluatorEmails)->send(new AdminDocumentsUploadedEmail($application, $resubmitted));
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
        $request->validate([
            'application_id'   => ['required', 'exists:applications,id'],
            'proof_of_payment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $application = Application::findOrFail($request->input('application_id'));
        $payment = $application->payment ?? new \App\Models\ApplicationPayment(['application_id' => $application->id]);

        $accreditationType = $application->accreditationType;
        $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
        $fatProName = $application->user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';
        
        $baseDocPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/documents";

        $changed = false;

        // Process proof_of_payment
        if ($request->hasFile('proof_of_payment')) {
            if ($payment->proof_of_payment && Storage::disk('local')->exists($payment->proof_of_payment)) {
                Storage::disk('local')->delete($payment->proof_of_payment);
            }
            $ext = $request->file('proof_of_payment')->getClientOriginalExtension();
            $filename = "proof_of_payment_" . time() . ".{$ext}";
            $path = $request->file('proof_of_payment')->storeAs($baseDocPath, $filename, 'local');
            $payment->proof_of_payment = $path;
            $payment->proof_of_payment_status = 'pending';
            $payment->proof_of_payment_remarks = null;
            $changed = true;
        }

        if ($changed) {
            $payment->save();

            // ── PCT: Resume the paused Step 7 (payment re-uploaded)
            app(PctService::class)->resumeCurrentStep($application);

            // ── Transition status to 'Payment Verification'
            $paymentVerificationStatus = \App\Models\ApplicationStatus::where('name', 'Payment Verification')->first();
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
                $verifiers = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                    $q->where('name', 'Verifier');
                })->get();

                if ($verifiers->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($verifiers, new \App\Notifications\PaymentSubmittedNotification($application));
                }
            } catch (\Exception $e) {
                Log::warning('Verifier notification email failed: ' . $e->getMessage());
            }

            return back()->with('success', 'Payment details uploaded successfully. Your submission is now pending verifier evaluation.');
        }

        return back()->with('error', 'No new payment files were selected.');
    }
}
