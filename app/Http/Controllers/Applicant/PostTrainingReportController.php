<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Mail\AdminPostTrainingSubmittedEmail;
use App\Models\Accreditation;
use App\Models\Application;
use App\Models\NtcReport;
use App\Models\PostTrainingReport;
use App\Models\PtrDocument;
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

class PostTrainingReportController extends Controller
{
    /** Per-file upload ceiling, in kilobytes (25 MB). */
    private const MAX_FILE_KB = 25600;

    /**
     * The same gate the NTC portal uses: a revoked accreditation or an ongoing
     * renewal/reinstatement locks the whole Submission report area.
     */
    private function accessDenialReason(User $user, string $action = 'access or submit'): ?string
    {
        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            return "Your accreditation has been revoked. You cannot {$action} a Post Training Report.";
        }

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
            return "You cannot {$action} a Post Training Report while you have an ongoing renewal or reinstatement application.";
        }

        return null;
    }

    /**
     * File a post training report against an acknowledged, concluded NTC.
     */
    public function store(Request $request, NtcReport $ntcReport)
    {
        $user = Auth::user();

        if ($reason = $this->accessDenialReason($user, 'submit')) {
            return redirect()->route('applicant.dashboard')->with('error', $reason);
        }

        // Security: the NTC must belong to this user
        if ($ntcReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        if ($ntcReport->status !== 'acknowledged') {
            return back()->withErrors(['error' => 'Only an acknowledged Notice to Conduct can have a Post Training Report.']);
        }

        if (!$ntcReport->hasTrainingConcluded()) {
            return back()->withErrors(['error' => 'You can only submit a Post Training Report after the training has concluded.']);
        }

        if ($ntcReport->postTrainingReport) {
            return back()->withErrors(['error' => 'A Post Training Report has already been submitted for this training.']);
        }

        $documentTypes = PtrDocumentType::orderBy('sort_order')->get();
        [$rules, $messages] = $this->uploadRules($documentTypes);
        $request->validate($rules, $messages);

        try {
            DB::transaction(function () use ($request, $ntcReport, $user, $documentTypes) {
                $accreditation = $ntcReport->accreditation()->with('accreditationType')->first();

                $report = PostTrainingReport::create([
                    'ntc_report_id'    => $ntcReport->id,
                    'accreditation_id' => $accreditation->id,
                    'status'           => 'submitted',
                    'due_date'         => $ntcReport->postTrainingDeadlineDate(),
                    'submitted_at'     => Carbon::now(),
                ]);

                $basePath = ApplicantStoragePath::postTrainingReports(
                    $accreditation->accreditationType->name ?? null,
                    $user->id
                );

                foreach ($documentTypes as $docType) {
                    $file = $request->file($docType->inputName());
                    if (!$file) {
                        continue;
                    }

                    $path = $this->storeUpload($file, $basePath, $docType->code);

                    PtrDocument::create([
                        'post_training_report_id' => $report->id,
                        'ptr_document_type_id'    => $docType->id,
                        'file_path'               => $path,
                        'original_filename'       => $file->getClientOriginalName(),
                        'mime_type'               => $file->getMimeType(),
                        'file_size'               => $file->getSize(),
                        'uploaded_at'             => Carbon::now(),
                        'status'                  => 'pending',
                    ]);
                }

                $this->notifyEvaluators($report, $user);
            });

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your Post Training Report has been successfully submitted. Admin has been notified.');
        } catch (\Exception $e) {
            Log::error('Post Training Report submission failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while submitting your Post Training Report. Please try again.']);
        }
    }

    /**
     * Serve a post training document file (private storage).
     */
    public function serveDocument(PtrDocument $document)
    {
        $user = Auth::user();

        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            abort(403, 'Your accreditation has been revoked.');
        }

        if ($document->postTrainingReport->accreditation->user_id !== $user->id) {
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
     * Re-upload the documents an evaluator declined.
     */
    public function reuploadBatch(Request $request, PostTrainingReport $postTrainingReport)
    {
        $user = Auth::user();

        if ($reason = $this->accessDenialReason($user, 'submit')) {
            return redirect()->route('applicant.dashboard')->with('error', $reason);
        }

        if ($postTrainingReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        if ($postTrainingReport->isAccepted()) {
            return back()->withErrors(['error' => 'This Post Training Report has already been accepted.']);
        }

        $request->validate([
            'files'   => ['required', 'array'],
            'files.*' => ['required', 'file', 'max:' . self::MAX_FILE_KB],
        ], [
            'files.required'   => 'Please select at least one file to upload.',
            'files.*.required' => 'Please select a file to upload.',
            'files.*.max'      => 'Each file must not exceed 25 MB.',
        ]);

        try {
            $accreditation = $postTrainingReport->accreditation()->with('accreditationType')->first();
            $basePath = ApplicantStoragePath::postTrainingReports(
                $accreditation->accreditationType->name ?? null,
                $user->id
            );

            $reuploadedDocsInfo = [];

            foreach ($request->file('files') as $docId => $file) {
                $document = PtrDocument::where('post_training_report_id', $postTrainingReport->id)
                    ->with('documentType')
                    ->find($docId);

                if (!$document || !in_array($document->status, ['rejected', 'returned'])) {
                    continue;
                }

                // Each declined document still only accepts its own format.
                $allowed = $document->documentType?->acceptedExtensions() ?? ['pdf'];
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, $allowed, true)) {
                    return back()->withErrors([
                        'error' => ($document->documentType->name ?? 'A document')
                            . ' must be uploaded as ' . strtoupper(implode(' or ', $allowed)) . '.',
                    ]);
                }

                // Delete old file — no stacking
                if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                    Storage::disk('local')->delete($document->file_path);
                }

                $path = $this->storeUpload($file, $basePath, $document->documentType->code ?? 'doc', $docId);

                $document->update([
                    'file_path'         => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type'         => $file->getMimeType(),
                    'file_size'         => $file->getSize(),
                    'uploaded_at'       => Carbon::now(),
                    'status'            => 'returned', // awaiting re-evaluation
                    'remarks'           => null,
                    'evaluated_by'      => null,
                    'evaluated_at'      => null,
                ]);

                $reuploadedDocsInfo[] = [
                    'type'     => $document->documentType->name ?? 'Document',
                    'filename' => $file->getClientOriginalName(),
                ];
            }

            if (!empty($reuploadedDocsInfo)) {
                $postTrainingReport->update(['status' => 'submitted']);
                $this->notifyEvaluators($postTrainingReport, $user, $reuploadedDocsInfo);
            }

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your documents have been re-uploaded successfully. Admin has been notified for re-evaluation.');
        } catch (\Exception $e) {
            Log::error('Post Training Report re-upload failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while uploading your documents. Please try again.']);
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Validation rules for a full six-document submission. Every type is
     * required and carries its own extension whitelist.
     */
    private function uploadRules($documentTypes): array
    {
        $rules = [];
        $messages = [];

        foreach ($documentTypes as $docType) {
            $field = $docType->inputName();
            $extensions = $docType->acceptedExtensions();

            $rules[$field] = [
                'required',
                'file',
                'mimes:' . implode(',', $extensions),
                'max:' . self::MAX_FILE_KB,
            ];

            $messages["{$field}.required"] = "The {$docType->name} is required.";
            $messages["{$field}.mimes"]    = "The {$docType->name} must be a " . strtoupper(implode(' or ', $extensions)) . ' file.';
            $messages["{$field}.max"]      = "The {$docType->name} must not exceed 25 MB.";
        }

        return [$rules, $messages];
    }

    /**
     * Store one upload under the FATPro's post training folder.
     */
    private function storeUpload($file, string $basePath, string $docCode, $suffix = null): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'pdf';
        $filename = strtolower($docCode) . '_' . time() . ($suffix !== null ? '_' . $suffix : '') . '.' . $ext;

        return $file->storeAs($basePath, $filename, 'local');
    }

    /**
     * Email + in-app notice to every Training Evaluator. Never fatal: a mail
     * outage must not roll back a submission the FATPro already made.
     */
    private function notifyEvaluators(PostTrainingReport $report, User $user, array $reuploadedDocsInfo = []): void
    {
        $isReupload = !empty($reuploadedDocsInfo);

        try {
            $evaluators = User::whereHas('adminProfile.adminRole', function ($q) {
                $q->where('name', 'Training Evaluator');
            })->get();

            if ($evaluators->isEmpty()) {
                return;
            }

            $report->loadMissing([
                'accreditation.user.organizationProfile',
                'accreditation.user.individualProfile',
                'ntcReport.trainingType',
                'ntcReport.trainingMode',
                'documents.documentType',
            ]);

            Mail::to($evaluators->pluck('email'))
                ->send(new AdminPostTrainingSubmittedEmail($report, $isReupload, $reuploadedDocsInfo));

            $message = $isReupload
                ? "Post Training Report {$report->reference_number} has been updated with re-uploaded documents by {$user->name} and is ready for re-evaluation."
                : "Post Training Report {$report->reference_number} has been submitted by {$user->name} and is ready for evaluation.";

            foreach ($evaluators as $evaluator) {
                $evaluator->notifications()->create([
                    'id'   => Str::uuid(),
                    'type' => 'App\Notifications\PostTrainingReportSubmittedNotification',
                    'data' => [
                        'post_training_report_id' => $report->id,
                        'reference_number'        => $report->reference_number,
                        'message'                 => $message,
                        'link'                    => "/admin/hcd/reports/post-training/{$report->id}",
                    ],
                    'read_at' => null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Admin Post Training Report notification failed: ' . $e->getMessage());
        }
    }
}
