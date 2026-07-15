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

                // Build base path using the same convention as application documents:
                // public/{accreditation_type}/{fatpro_name}/reports/ntc/
                $accreditationType   = $accreditation->accreditationType;
                $accreditationName   = $accreditationType ? $accreditationType->name : 'Unknown';
                $sanitizedAccType    = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
                $sanitizedFatPro     = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $user->name)) ?: 'unknown';
                $ntcBasePath         = "public/{$sanitizedAccType}/{$sanitizedFatPro}/reports/ntc";

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
            $accreditationName   = $accreditationType ? $accreditationType->name : 'Unknown';
            $sanitizedAccType    = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
            $sanitizedFatPro     = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $user->name)) ?: 'unknown';
            $ntcBasePath         = "public/{$sanitizedAccType}/{$sanitizedFatPro}/reports/ntc";

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
            $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
            $sanitizedAccType  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
            $sanitizedFatPro   = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $user->name)) ?: 'unknown';
            $ntcBasePath       = "public/{$sanitizedAccType}/{$sanitizedFatPro}/reports/ntc";

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
                    $q->where('name', 'Evaluator');
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
            'training_start_date'  => ['required', 'date', 'after_or_equal:' . $earliestDate],
            'training_end_date'    => ['required', 'date', 'after_or_equal:training_start_date'],
            'file_rtcman'          => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
            'file_prog'            => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:102400'],
        ], [
            'training_start_date.after_or_equal' =>
                "The training start date must be at least 10 working days from today (on or after {$earliestDate}).",
            'training_end_date.after_or_equal' =>
                'The training end date must be on or after the start date.',
            'file_rtcman.max'      => 'The RTCMan Form must not exceed 100 MB.',
            'file_prog.max'        => 'The PROG Form must not exceed 100 MB.',
        ]);

        try {
            DB::transaction(function () use ($validated, $request, $ntcReport, $user) {
                // Update NTC Report details
                $ntcReport->update([
                    'ntc_training_type_id' => $validated['ntc_training_type_id'],
                    'ntc_training_mode_id' => $validated['ntc_training_mode_id'],
                    'training_start_date'  => $validated['training_start_date'],
                    'training_end_date'    => $validated['training_end_date'],
                    'status'               => 'report_changes',
                    'submitted_at'         => Carbon::now(),
                    'acknowledged_at'      => null,
                    'acknowledged_by'      => null,
                ]);

                $fileFields = [
                    'file_rtcman' => 'RTCMAN',
                    'file_prog'   => 'PROG',
                ];

                $accreditation = $ntcReport->accreditation;
                $accreditationType = $accreditation->accreditationType;
                $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
                $sanitizedAccType  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
                $sanitizedFatPro   = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $user->name)) ?: 'unknown';
                $ntcBasePath       = "public/{$sanitizedAccType}/{$sanitizedFatPro}/reports/ntc";

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

