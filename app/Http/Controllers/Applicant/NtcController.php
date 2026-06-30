<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Mail\AdminNtcSubmittedEmail;
use App\Models\Accreditation;
use App\Models\Application;
use App\Models\NtcDocument;
use App\Models\NtcDocumentType;
use App\Models\NtcReport;
use App\Models\NtcTrainingMode;
use App\Models\NtcTrainingType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class NtcController extends Controller
{
    /**
     * Show the NTC report list / creation page.
     */
    public function index()
    {
        $user = Auth::user();

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
                ->with('error', 'You cannot access or submit a Notice to Conduct while you have an ongoing renewal or reinstatement application.');
        }

        // Only active accreditations can submit NTC
        $accreditation = Accreditation::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['user.organizationProfile', 'user.individualProfile'])
            ->latest()
            ->first();

        // All NTC reports for this user (via their accreditations)
        $ntcReports = NtcReport::whereHas('accreditation', fn($q) => $q->where('user_id', $user->id))
            ->with(['trainingType', 'trainingMode', 'documents.documentType'])
            ->latest()
            ->get();

        $trainingTypes  = NtcTrainingType::all();
        $trainingModes  = NtcTrainingMode::all();
        $documentTypes  = NtcDocumentType::all();

        // Earliest allowed training start date (10 working days from today)
        $earliestStartDate = NtcReport::earliestAllowedStartDate()->format('Y-m-d');

        return view('applicant.ntc', compact(
            'accreditation',
            'ntcReports',
            'trainingTypes',
            'trainingModes',
            'documentTypes',
            'earliestStartDate',
        ));
    }

    /**
     * Store a new NTC report submission.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

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
                ->with('error', 'You cannot submit a Notice to Conduct while you have an ongoing renewal or reinstatement application.');
        }

        // Verify active accreditation
        $accreditation = Accreditation::where('user_id', $user->id)
            ->where('status', 'active')
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
            'training_start_date'  => ['required', 'date', 'after_or_equal:' . $earliestDate],
            'training_end_date'    => ['required', 'date', 'after_or_equal:training_start_date'],
            'file_rtcman'          => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
            'file_prog'            => ['required', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ], [
            'training_start_date.after_or_equal' =>
                "The training start date must be at least 10 working days from today (on or after {$earliestDate}).",
            'training_end_date.after_or_equal' =>
                'The training end date must be on or after the start date.',
            'file_rtcman.required' => 'The DOLE-OSHC-STO-RTCMan Form is required.',
            'file_prog.required'   => 'The DOLE-OSHC-STO-PROG Form is required.',
            'file_rtcman.max'      => 'The RTCMan Form must not exceed 100 MB.',
            'file_prog.max'        => 'The PROG Form must not exceed 100 MB.',
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $accreditation, $user) {
                // Create the NTC report
                $ntcReport = NtcReport::create([
                    'accreditation_id'     => $accreditation->id,
                    'ntc_training_type_id' => $validated['ntc_training_type_id'],
                    'ntc_training_mode_id' => $validated['ntc_training_mode_id'],
                    'training_start_date'  => $validated['training_start_date'],
                    'training_end_date'    => $validated['training_end_date'],
                    'status'               => 'submitted',
                    'submitted_at'         => Carbon::now(),
                ]);

                // Store file uploads
                $fileFields = [
                    'file_rtcman' => 'RTCMAN',
                    'file_prog'   => 'PROG',
                ];

                foreach ($fileFields as $inputName => $docCode) {
                    if ($request->hasFile($inputName)) {
                        $file = $request->file($inputName);
                        $docType = NtcDocumentType::where('code', $docCode)->first();

                        // Build folder: first_aid_training_providers/{fatpro_name}/reports/ntc/{report_id}/
                        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $user->name)) ?: 'unknown';
                        $storagePath = "first_aid_training_providers/{$sanitizedFatPro}/reports/ntc/{$ntcReport->id}";
                        $path = $file->store($storagePath, 'local');

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
                        $q->where('name', 'Evaluator');
                    })->get();

                    if ($evaluators->isNotEmpty()) {
                        $ntcReport->loadMissing([
                            'accreditation.user.organizationProfile',
                            'accreditation.user.individualProfile',
                            'trainingType',
                            'trainingMode',
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

        // Ensure the document belongs to this user's NTC report
        $belongsToUser = $document->ntcReport->accreditation->user_id === $user->id;
        if (!$belongsToUser) {
            abort(403);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }
}
