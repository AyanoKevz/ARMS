<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Mail\AdminPostTrainingSubmittedEmail;
use App\Models\Accreditation;
use App\Models\Application;
use App\Models\Instructor;
use App\Models\NtcReport;
use App\Models\PostTrainingDraft;
use App\Models\PostTrainingReport;
use App\Models\PtrDocument;
use App\Models\PtrDocumentType;
use App\Models\PtrParticipant;
use App\Models\User;
use App\Support\ApplicantStoragePath;
use App\Support\PhLocations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostTrainingReportController extends Controller
{
    /** Per-file upload ceiling, in kilobytes (25 MB). */
    private const MAX_FILE_KB = 25600;

    /** Upper bound on one encoded Directory of Participants. */
    private const MAX_PARTICIPANTS = 500;

    /**
     * How long an unreferenced staged file is kept.
     *
     * Anything a live draft still points at is exempt, so this only governs
     * leftovers — a picture staged and then abandoned without saving.
     */
    private const STAGING_RETENTION_DAYS = 14;

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

        // What a draft staged earlier, keyed by document type.
        $staged = $this->stagedDocumentsFor($ntcReport, $user);

        // A recording of the training is required, but as a link: a full
        // session runs to gigabytes and no upload here would carry it.
        $rules['training_video_url'] = ['required', 'url', 'max:500'];
        $rules['applicant_remarks']  = ['nullable', 'string', 'max:1000'];

        $messages['training_video_url.required'] = 'Please provide the link to the training video.';
        $messages['training_video_url.url']      = 'The training video link must be a valid URL, starting with http:// or https://.';

        $validated = $request->validate($rules, $messages);

        // Every attachment must come from somewhere — this sitting or a draft.
        foreach ($documentTypes->filter->isFile() as $docType) {
            if ($request->file($docType->inputName()) || isset($staged[$docType->id])) {
                continue;
            }

            throw ValidationException::withMessages([
                $docType->inputName() => "The {$docType->name} is required.",
            ]);
        }

        // The Directory arrives as one JSON field rather than N*19 form inputs,
        // so the row count never has to fit under max_input_vars.
        $participants = $this->validateParticipants($request, $user);

        // Requirement 2 is no longer a PDF: the instructors come over from the
        // NTC pre-selected and the FATPro either proceeds or amends the list.
        $instructorIds = $this->resolveReportInstructors($request, $ntcReport, $user);

        try {
            DB::transaction(function () use ($request, $ntcReport, $user, $documentTypes, $participants, $instructorIds, $validated, $staged) {
                $accreditation = $ntcReport->accreditation()->with('accreditationType')->first();

                $report = PostTrainingReport::create([
                    'ntc_report_id'       => $ntcReport->id,
                    'accreditation_id'    => $accreditation->id,
                    'status'              => 'submitted',
                    'due_date'            => $ntcReport->postTrainingDeadlineDate(),
                    'submitted_at'        => Carbon::now(),
                    'training_video_url'  => $validated['training_video_url'],
                    'applicant_remarks'   => $validated['applicant_remarks'] ?? null,
                ]);

                $basePath = ApplicantStoragePath::postTrainingReports(
                    $accreditation->accreditationType->name ?? null,
                    $user->id
                );

                $report->instructors()->sync($instructorIds);

                foreach ($documentTypes as $docType) {
                    // A type captured in the portal still gets a ptr_documents
                    // row: it is the section the evaluator acts on and where its
                    // single set of remarks lives. It just has no file.
                    if (!$docType->isFile()) {
                        PtrDocument::create([
                            'post_training_report_id' => $report->id,
                            'ptr_document_type_id'    => $docType->id,
                            'file_path'               => null,
                            'original_filename'       => null,
                            'mime_type'               => null,
                            'file_size'               => null,
                            'uploaded_at'             => Carbon::now(),
                            'status'                  => 'pending',
                        ]);
                        continue;
                    }

                    $file = $request->file($docType->inputName());

                    // A file picked now wins over whatever the draft staged;
                    // the FATPro replacing it in this sitting is the later
                    // decision.
                    if ($file) {
                        $attributes = [
                            'file_path'         => $this->storeUpload($file, $basePath, $docType->code),
                            'original_filename' => $file->getClientOriginalName(),
                            'mime_type'         => $file->getMimeType(),
                            'file_size'         => $file->getSize(),
                        ];
                    } elseif (isset($staged[$docType->id])) {
                        $entry  = $staged[$docType->id];
                        $claim  = $this->claimStagedDocument($user, $entry['token'], $basePath, $docType->code);

                        $attributes = $claim + [
                            'original_filename' => $entry['name'] ?? basename($claim['file_path']),
                            'mime_type'         => 'application/pdf',
                        ];
                    } else {
                        continue;
                    }

                    PtrDocument::create($attributes + [
                        'post_training_report_id' => $report->id,
                        'ptr_document_type_id'    => $docType->id,
                        'uploaded_at'             => Carbon::now(),
                        'status'                  => 'pending',
                    ]);
                }

                $this->persistParticipants($report, $participants, $basePath, $user);

                // The work in progress is now a submission; anything it still
                // had staged has either been claimed above or is surplus.
                $this->clearDraft($ntcReport, $user);

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


    /* ── Drafts ─────────────────────────────────────────────────────────────
     *
     * A report asks for seven things at once, which is more than one sitting's
     * work. Everything typed or chosen is kept in post_training_drafts so the
     * dialog can be closed and reopened; files are staged on pick, because a
     * JSON payload cannot hold a PDF.
     */

    /**
     * Record the current state of an unfinished submission.
     *
     * Deliberately forgiving: a draft is not a submission, so nothing here is
     * required and nothing is rejected for being incomplete. The only checks
     * are the ones that protect the record itself — that the NTC belongs to
     * this FATPro and has not already been reported on.
     */
    public function saveDraft(Request $request, NtcReport $ntcReport)
    {
        $user = Auth::user();

        if ($ntcReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        if ($ntcReport->postTrainingReport) {
            return response()->json([
                'ok'      => false,
                'message' => 'This training has already been reported on.',
            ], 409);
        }

        $validated = $request->validate([
            'payload'                     => ['required', 'array'],
            'payload.participants'        => ['nullable', 'array', 'max:' . self::MAX_PARTICIPANTS],
            'payload.instructor_ids'      => ['nullable', 'array'],
            'payload.training_video_url'  => ['nullable', 'string', 'max:500'],
            'payload.applicant_remarks'   => ['nullable', 'string', 'max:1000'],
            'payload.documents'           => ['nullable', 'array'],
            'payload.step'                => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $draft = PostTrainingDraft::updateOrCreate(
            ['ntc_report_id' => $ntcReport->id],
            [
                'user_id'  => $user->id,
                'payload'  => $validated['payload'],
                'saved_at' => Carbon::now(),
            ]
        );

        return response()->json([
            'ok'       => true,
            'saved_at' => $draft->saved_at->toIso8601String(),
        ]);
    }

    /**
     * Throw away an unfinished submission at the FATPro's request.
     */
    public function discardDraft(NtcReport $ntcReport)
    {
        $user = Auth::user();

        if ($ntcReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        $this->clearDraft($ntcReport, $user);

        return response()->json(['ok' => true]);
    }

    /**
     * Stage one attachment ahead of submission.
     *
     * Uploaded the moment it is picked rather than with the form, for two
     * reasons: a draft has to survive the dialog being closed, and four 25 MB
     * PDFs in one request is a fight with post_max_size that there is no need
     * to pick.
     */
    public function stageDocument(Request $request)
    {
        $user = Auth::user();

        if ($reason = $this->accessDenialReason($user, 'submit')) {
            return response()->json(['ok' => false, 'message' => $reason], 403);
        }

        $validated = $request->validate([
            'document'            => ['required', 'file', 'mimes:pdf', 'max:' . self::MAX_FILE_KB],
            'ptr_document_type_id' => ['required', 'exists:ptr_document_types,id'],
        ], [
            'document.mimes' => 'The document must be a PDF file.',
            'document.max'   => 'The document must not exceed 25 MB.',
        ]);

        $file  = $validated['document'];
        $token = Str::uuid() . '.pdf';

        $file->storeAs($this->stagingPath($user), $token, 'local');

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'name'  => $file->getClientOriginalName(),
            'size'  => $file->getSize(),
        ]);
    }

    /**
     * The attachments a draft staged, keyed by document type id.
     *
     * Only entries whose file is actually still on disk are returned, so a
     * token left behind by a prune or a failed upload cannot make store()
     * believe a requirement is satisfied.
     *
     * @return array<int, array{token: string, name: ?string}>
     */
    private function stagedDocumentsFor(NtcReport $ntcReport, User $user): array
    {
        $draft = PostTrainingDraft::where('ntc_report_id', $ntcReport->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$draft) {
            return [];
        }

        $staged = [];

        foreach ($draft->payload['documents'] ?? [] as $typeId => $entry) {
            $token = $entry['token'] ?? null;

            if (!$token || !Storage::disk('local')->exists($this->stagingPath($user) . '/' . $token)) {
                continue;
            }

            $staged[(int) $typeId] = [
                'token' => $token,
                'name'  => $entry['name'] ?? null,
            ];
        }

        return $staged;
    }

    /**
     * Move a staged attachment into the report's own folder.
     */
    private function claimStagedDocument(User $user, string $token, string $basePath, string $docCode): array
    {
        $from = $this->stagingPath($user) . '/' . $token;
        $to   = $basePath . '/' . strtolower($docCode) . '_' . time() . '.pdf';

        Storage::disk('local')->move($from, $to);

        return [
            'file_path' => $to,
            'file_size' => Storage::disk('local')->size($to),
        ];
    }

    /**
     * Drop a draft and the staged files only it was holding.
     */
    private function clearDraft(NtcReport $ntcReport, User $user): void
    {
        $draft = PostTrainingDraft::where('ntc_report_id', $ntcReport->id)->first();

        if (!$draft) {
            return;
        }

        foreach ($draft->stagedTokens() as $token) {
            try {
                Storage::disk('local')->delete($this->stagingPath($user) . '/' . $token);
            } catch (\Exception $e) {
                Log::warning('Draft staged file cleanup failed: ' . $e->getMessage());
            }
        }

        $draft->delete();
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
     * Serve a participant's ID picture (private storage).
     */
    public function serveParticipantPhoto(PtrParticipant $participant)
    {
        $user = Auth::user();

        $latestAccreditation = Accreditation::where('user_id', $user->id)->latest()->first();
        if ($latestAccreditation && $latestAccreditation->status === 'revoked') {
            abort(403, 'Your accreditation has been revoked.');
        }

        if ($participant->postTrainingReport->accreditation->user_id !== $user->id) {
            abort(403);
        }

        if (!$participant->id_picture_path || !Storage::disk('local')->exists($participant->id_picture_path)) {
            abort(404, 'ID picture not found.');
        }

        return Storage::disk('local')->response(
            $participant->id_picture_path,
            $participant->id_picture_filename ?: 'id-picture.jpg'
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
            // The Directory of Participants and the instructor roster are both
            // captured in the portal, so neither has a file to validate.
            if (!$docType->isFile()) {
                continue;
            }

            $field = $docType->inputName();
            $extensions = $docType->acceptedExtensions();

            // A file picked in this sitting OR one staged into a draft
            // earlier satisfies the requirement, so neither is 'required' on
            // its own — store() checks that one of the two is present.
            $rules[$field] = [
                'nullable',
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
     * Put right everything an evaluator sent back, in one go.
     *
     * A report is declined in four different ways — an attachment is wiped and
     * must be re-uploaded, participants are rejected row by row, the instructor
     * list is refused, the video link is unreachable — and any combination can
     * be outstanding at once. The
     * FATPro should not have to find three different places to fix them, so
     * this takes whichever sections are open and applies them together: one
     * transaction, one notification, one thing to do.
     *
     * Only declined sections are touched. Anything already accepted is left
     * exactly as it is, which is why each branch is guarded by its own check
     * rather than by what happens to be in the request.
     */
    public function submitCorrections(Request $request, PostTrainingReport $postTrainingReport)
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

        $declinedDocs     = $postTrainingReport->declinedDocuments();
        $needsDirectory   = $postTrainingReport->hasDirectoryCorrections();
        $needsInstructors = $postTrainingReport->hasInstructorCorrections();
        $needsVideo       = $postTrainingReport->hasVideoCorrections();

        if ($declinedDocs->isEmpty() && !$needsDirectory && !$needsInstructors && !$needsVideo) {
            return back()->withErrors([
                'error' => 'Nothing on this Post Training Report has been sent back for correction.',
            ]);
        }

        // Everything is validated before anything is written, so a report is
        // never left half-corrected by a mistake in one section.
        $files = $declinedDocs->isNotEmpty()
            ? $this->validateCorrectionFiles($request, $declinedDocs)
            : [];

        $participants = $needsDirectory
            ? $this->validateParticipants($request, $user, true)
            : [];

        $instructorIds = $needsInstructors
            ? $this->resolveReportInstructors($request, $postTrainingReport->ntcReport, $user)
            : [];

        $videoUrl = null;

        if ($needsVideo) {
            $videoUrl = $request->validate([
                'training_video_url' => ['required', 'url', 'max:500'],
            ], [
                'training_video_url.required' => 'Please provide the link to the training video.',
                'training_video_url.url'      => 'The training video link must be a valid URL, starting with http:// or https://.',
            ])['training_video_url'];
        }

        try {
            $summary = [];

            DB::transaction(function () use (
                $request, $postTrainingReport, $user, $files, $participants,
                $instructorIds, $needsDirectory, $needsInstructors, $needsVideo,
                $videoUrl, &$summary
            ) {
                $accreditation = $postTrainingReport->accreditation()->with('accreditationType')->first();
                $basePath      = ApplicantStoragePath::postTrainingReports(
                    $accreditation->accreditationType->name ?? null,
                    $user->id
                );

                foreach ($files as $docId => $file) {
                    $document = PtrDocument::with('documentType')->find($docId);

                    // Delete the old file first — no stacking.
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
                        'status'            => 'returned',
                        'remarks'           => null,
                        'evaluated_by'      => null,
                        'evaluated_at'      => null,
                    ]);

                    $summary[] = [
                        'type'     => $document->documentType->name ?? 'Document',
                        'filename' => $file->getClientOriginalName(),
                    ];
                }

                if ($needsDirectory) {
                    $corrected = $this->applyParticipantCorrections(
                        $postTrainingReport,
                        $participants,
                        $basePath,
                        $user
                    );

                    if ($corrected > 0) {
                        $summary[] = [
                            'type'     => 'Directory of Participants',
                            'filename' => $corrected . ' corrected participant ' . Str::plural('row', $corrected),
                        ];
                    }
                }

                if ($needsInstructors) {
                    $postTrainingReport->instructors()->sync($instructorIds);

                    $this->reopenSection($postTrainingReport->instructorsDocument());

                    $summary[] = [
                        'type'     => 'List of Instructors Who Conducted the Training',
                        'filename' => count($instructorIds) . ' ' . Str::plural('instructor', count($instructorIds)),
                    ];
                }

                if ($needsVideo) {
                    $postTrainingReport->update(['training_video_url' => $videoUrl]);

                    $this->reopenSection($postTrainingReport->videoDocument());

                    $summary[] = [
                        'type'     => 'Link to the Training Video',
                        'filename' => $videoUrl,
                    ];
                }

                $postTrainingReport->update(['status' => 'submitted']);
            });

            $this->notifyEvaluators($postTrainingReport, $user, $summary);
        } catch (\Exception $e) {
            Log::error('Post Training Report corrections failed: ' . $e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while submitting your corrections. Please try again.']);
        }

        return redirect()->route('applicant.ntc.index')
            ->with('success', 'Your corrections have been submitted. Admin has been notified for re-evaluation.');
    }

    /**
     * The replacement files for the declined attachments.
     *
     * Every declined document needs one: a correction that leaves a section
     * blank has not corrected it, and submitting partway would re-open the
     * report for review while still missing what was asked for.
     *
     * @return array<int, \Illuminate\Http\UploadedFile>  keyed by document id
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateCorrectionFiles(Request $request, $declinedDocs): array
    {
        $rules    = [];
        $messages = [];

        foreach ($declinedDocs as $document) {
            $field      = "files.{$document->id}";
            $extensions = $document->documentType?->acceptedExtensions() ?: ['pdf'];
            $name       = $document->documentType->name ?? 'document';

            $rules[$field] = ['required', 'file', 'mimes:' . implode(',', $extensions), 'max:' . self::MAX_FILE_KB];

            $messages["{$field}.required"] = "Please re-upload the {$name}.";
            $messages["{$field}.mimes"]    = "The {$name} must be a " . strtoupper(implode(' or ', $extensions)) . ' file.';
            $messages["{$field}.max"]      = "The {$name} must not exceed 25 MB.";
        }

        $request->validate($rules, $messages);

        $files = [];

        foreach ($declinedDocs as $document) {
            $files[$document->id] = $request->file("files.{$document->id}");
        }

        return $files;
    }

    /**
     * Write the corrected participant rows and re-open the Directory.
     *
     * @return int  how many rows were actually corrected
     */
    private function applyParticipantCorrections(
        PostTrainingReport $report,
        array $rows,
        string $basePath,
        User $user
    ): int {
        $editable = $report->participants()
            ->where('status', 'rejected')
            ->get()
            ->keyBy('id');

        $corrected = 0;

        foreach ($rows as $row) {
            $participant = $editable->get($row['id'] ?? null);

            // Only declined rows are editable; approved ones stay put.
            if (!$participant) {
                continue;
            }

            $attributes = $this->participantAttributes($row);

            // A replacement picture is optional on a correction.
            if (!empty($row['photo_token'])) {
                $participant->deleteIdPicture();

                $attributes = array_merge($attributes, $this->claimStagedPhoto(
                    $user,
                    $row['photo_token'],
                    $basePath,
                    $participant->row_no,
                    $row['photo_name'] ?? null
                ));
            }

            $participant->update($attributes + [
                'status'       => 'pending',
                'evaluated_by' => null,
                'evaluated_at' => null,
            ]);

            $corrected++;
        }

        if ($corrected > 0) {
            $this->reopenSection($report->directoryDocument());
        }

        return $corrected;
    }

    /**
     * Put a section back in front of the evaluator, clearing the verdict that
     * sent it back.
     */
    private function reopenSection(?PtrDocument $section): void
    {
        $section?->update([
            'status'       => 'returned',
            'remarks'      => null,
            'evaluated_by' => null,
            'evaluated_at' => null,
        ]);
    }
    /**
     * Amend the instructor roster on a report the evaluator sent back.
     *
     * The roster is corrected by re-choosing, not by re-uploading, so it has
     * its own endpoint rather than riding along with reuploadBatch(). Putting
     * the section back to 'returned' is what tells the evaluator there is
     * something new to look at.
     */
    public function updateInstructors(Request $request, PostTrainingReport $postTrainingReport)
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

        $section = $postTrainingReport->instructorsDocument();

        if (!$section || $section->status !== 'rejected') {
            return back()->withErrors(['error' => 'The list of instructors has not been sent back for correction.']);
        }

        $instructorIds = $this->resolveReportInstructors(
            $request,
            $postTrainingReport->ntcReport,
            $user
        );

        try {
            DB::transaction(function () use ($postTrainingReport, $section, $instructorIds, $user) {
                $postTrainingReport->instructors()->sync($instructorIds);

                $section->update([
                    'status'       => 'returned',
                    'remarks'      => null,
                    'evaluated_by' => null,
                    'evaluated_at' => null,
                ]);

                $this->notifyEvaluators($postTrainingReport, $user);
            });

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your corrected list of instructors has been submitted. Admin has been notified.');
        } catch (\Exception $e) {
            Log::error('Post Training instructor correction failed: ' . $e->getMessage());
            return back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while submitting your corrected list. Please try again.']);
        }
    }

    /**
     * The instructors recorded as having conducted the training.
     *
     * Works in PERSON ids, like the NTC it inherits from, so the record
     * survives the renewal that replaces the underlying `instructors` rows.
     *
     * Two groups may be named. Anyone declared on the parent NTC is allowed
     * through unconditionally — they were vetted when it was filed, and a
     * credential that has lapsed in the weeks since does not unmake the fact
     * that they taught. Anyone NOT declared is a late addition and has to
     * clear the same bar the NTC applied: on this FATPro's roster, and
     * eligible as of the last training day.
     *
     * @return array<int, int>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function resolveReportInstructors(Request $request, NtcReport $ntcReport, User $user): array
    {
        $validated = $request->validate([
            'instructor_ids'   => ['required', 'array', 'min:1'],
            'instructor_ids.*' => ['integer', 'exists:instructor_people,id'],
        ], [
            'instructor_ids.required' => 'List at least one instructor who conducted this training.',
            'instructor_ids.min'      => 'List at least one instructor who conducted this training.',
        ]);

        $declared = $ntcReport->instructors()->pluck('instructor_people.id')->all();
        $roster   = Instructor::accreditedRosterFor($user->id)
            ->filter(fn ($instructor) => $instructor->instructor_person_id)
            ->keyBy('instructor_person_id');
        $lastDay  = $ntcReport->training_end_date;
        $chosen   = [];

        foreach (array_unique($validated['instructor_ids']) as $id) {
            $id = (int) $id;

            if (in_array($id, $declared, true)) {
                $chosen[] = $id;
                continue;
            }

            $instructor = $roster->get($id);

            if (!$instructor) {
                throw ValidationException::withMessages([
                    'instructor_ids' => 'One of the listed instructors is not on your accredited roster.',
                ]);
            }

            if ($reason = $instructor->ineligibilityReason($lastDay)) {
                throw ValidationException::withMessages([
                    'instructor_ids' => $instructor->fullName()
                        . ' was not declared on the Notice to Conduct and cannot be added: ' . $reason,
                ]);
            }

            $chosen[] = (int) $instructor->instructor_person_id;
        }

        return $chosen;
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
     * Stage one participant's ID picture ahead of submission.
     *
     * Pictures upload one at a time instead of riding along with the final form.
     * Posting them together would have to fit the whole batch inside one request:
     * at up to 5 MB each they exhaust post_max_size (295M here) long before a
     * large directory is filed, and a host with PHP's default max_file_uploads
     * of 20 would silently drop the rest — no error, just missing pictures.
     */
    public function stageParticipantPhoto(Request $request)
    {
        $user = Auth::user();

        if ($reason = $this->accessDenialReason($user, 'submit')) {
            return response()->json(['message' => $reason], 403);
        }

        $request->validate([
            'photo' => [
                'required',
                'file',
                'mimes:' . implode(',', PtrParticipant::PHOTO_EXTENSIONS),
                'max:' . PtrParticipant::MAX_PHOTO_KB,
            ],
        ], [
            'photo.required' => 'Please choose an ID picture.',
            'photo.mimes'    => 'The ID picture must be a ' . strtoupper(implode(' or ', PtrParticipant::PHOTO_EXTENSIONS)) . ' image.',
            'photo.max'      => 'Each ID picture must not exceed 5 MB.',
        ]);

        $this->pruneStagedFiles($user);

        $file = $request->file('photo');
        $ext  = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $name = Str::uuid() . '.' . $ext;

        $file->storeAs($this->stagingPath($user), $name, 'local');

        return response()->json([
            'token'    => $name,
            'filename' => $file->getClientOriginalName(),
            'size'     => $file->getSize(),
        ]);
    }

    /**
     * Correct the participants an evaluator turned down. The Directory is fixed
     * by editing rows, not by uploading a replacement file.
     */
    public function updateParticipants(Request $request, PostTrainingReport $postTrainingReport)
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

        $rows = $this->validateParticipants($request, $user, true);

        try {
            DB::transaction(function () use ($rows, $postTrainingReport, $user) {
                $accreditation = $postTrainingReport->accreditation()->with('accreditationType')->first();
                $basePath = ApplicantStoragePath::postTrainingReports(
                    $accreditation->accreditationType->name ?? null,
                    $user->id
                );

                // Shared with submitCorrections, which does the same thing as
                // one part of a larger submission. It picks the editable rows,
                // claims any replacement pictures and re-opens the section.
                $corrected = $this->applyParticipantCorrections(
                    $postTrainingReport,
                    $rows,
                    $basePath,
                    $user
                );

                if ($corrected === 0) {
                    return;
                }

                $postTrainingReport->update(['status' => 'submitted']);

                $this->notifyEvaluators($postTrainingReport, $user, [[
                    'type'     => 'Directory of Participants',
                    'filename' => $corrected . ' corrected participant ' . Str::plural('row', $corrected),
                ]]);
            });

            return redirect()->route('applicant.ntc.index')
                ->with('success', 'Your corrected participants have been submitted. Admin has been notified for re-evaluation.');
        } catch (\Exception $e) {
            Log::error('Post Training Report participant correction failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'An error occurred while saving your participants. Please try again.']);
        }
    }

    // ── Directory of Participants internals ───────────────────────────────────

    /**
     * Validate the encoded Directory.
     *
     * The grid posts one JSON field rather than ~19 inputs per row, so the row
     * count never approaches max_input_vars. This stack raises it to 10000 in
     * .user.ini, but a host running PHP's 1000 default would truncate the POST
     * array at roughly 52 participants without raising an error.
     */
    private function validateParticipants(Request $request, User $user, bool $isCorrection = false): array
    {
        $decoded = json_decode((string) $request->input('participants'), true);

        if (!is_array($decoded) || $decoded === []) {
            throw ValidationException::withMessages([
                'participants' => $isCorrection
                    ? 'There are no corrected participants to submit.'
                    : 'Encode at least one participant in the Directory of Participants.',
            ]);
        }

        $decoded = array_values($decoded);

        $rules = [
            'participants'                      => ['required', 'array', 'min:1', 'max:' . self::MAX_PARTICIPANTS],
            'participants.*.certificate_number' => ['required', 'string', 'max:100'],
            'participants.*.last_name'          => ['required', 'string', 'max:100'],
            'participants.*.first_name'         => ['required', 'string', 'max:100'],
            'participants.*.middle_name'        => ['nullable', 'string', 'max:100'],
            'participants.*.suffix'             => ['nullable', 'string', 'max:20'],
            // Only the two the form offers. 'string|max:20' would have taken
            // anything a crafted post cared to send.
            'participants.*.sex'                => ['required', 'in:Male,Female'],
            'participants.*.age'                => ['required', 'integer', 'min:1', 'max:120'],
            'participants.*.company'            => ['required', 'string', 'max:255'],
            'participants.*.position'           => ['required', 'string', 'max:255'],
            // Region is picked from the PSGC register, not typed. The city is
            // checked against that region's own list in the after() pass below,
            // which needs both values and so cannot be expressed as a rule.
            'participants.*.company_region'     => ['required', 'string', Rule::in(PhLocations::regionCodes())],
            'participants.*.company_city'       => ['required', 'string', 'max:255'],
            'participants.*.industry'           => ['required', 'string', 'max:255'],
            'participants.*.total_workers'      => ['nullable', 'integer', 'min:0'],
            'participants.*.company_email'      => ['nullable', 'email', 'max:255'],
            'participants.*.personal_email'     => ['nullable', 'email', 'max:255'],
            // The shapes RegistrationController applies to the same two kinds
            // of number, so one form cannot accept what the other refuses.
            'participants.*.mobile_no'          => ['required', 'string', 'max:13', 'regex:/^(09|\+639)\d{9}$/'],
            'participants.*.company_landline'   => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'participants.*.mode_of_training'   => ['required', 'string', 'max:100'],
            'participants.*.batch_no'           => ['nullable', 'string', 'max:50'],
        ];

        // Every participant needs a picture to submit. On a correction the row
        // already has one, so a replacement is optional.
        $rules['participants.*.photo_token'] = $isCorrection
            ? ['nullable', 'string', 'max:100']
            : ['required', 'string', 'max:100'];

        if ($isCorrection) {
            $rules['participants.*.id'] = ['required', 'integer'];
        }

        $messages = [
            'participants.max'                           => 'A Directory of Participants cannot exceed ' . self::MAX_PARTICIPANTS . ' participants.',
            'participants.*.photo_token.required'        => 'Every participant needs an ID picture before you can submit.',
            'participants.*.certificate_number.required' => 'Certificate Number is required for every participant.',
            'participants.*.age.integer'                 => 'Age must be a whole number.',
            'participants.*.company_region.in'           => 'Choose the company region from the list.',
            'participants.*.mobile_no.regex'             => 'Mobile numbers must be valid PH mobile numbers (e.g. 09171234567 or +639171234567).',
            'participants.*.mobile_no.max'               => 'Mobile numbers must be valid PH mobile numbers (e.g. 09171234567 or +639171234567).',
            'participants.*.company_landline.regex'      => 'A company landline must be a 10-digit number including the area code (e.g. 0281234567).',
            'participants.*.company_email.email'         => 'Company e-mail addresses must be valid e-mail addresses.',
            'participants.*.personal_email.email'        => 'Personal e-mail addresses must be valid e-mail addresses.',
        ];

        $validator = Validator::make(['participants' => $decoded], $rules, $messages);

        $validator->after(function ($v) use ($decoded, $user) {
            $seen = [];

            foreach ($decoded as $i => $row) {
                // A certificate number may repeat across trainings, never within one.
                $cert = trim((string) ($row['certificate_number'] ?? ''));

                if ($cert !== '') {
                    $key = mb_strtolower($cert);

                    if (isset($seen[$key])) {
                        $v->errors()->add(
                            "participants.{$i}.certificate_number",
                            'Certificate number "' . $cert . '" appears on both row ' . ($seen[$key] + 1) . ' and row ' . ($i + 1) . '.'
                        );
                    } else {
                        $seen[$key] = $i;
                    }
                }

                // A city is only meaningful inside a region. Checking the pair
                // rather than the name alone is what stops Cebu City arriving
                // filed under NCR — the dropdown cannot offer that, but a
                // hand-built post can still send it.
                $region = trim((string) ($row['company_region'] ?? ''));
                $city   = trim((string) ($row['company_city'] ?? ''));

                if ($city !== '' && PhLocations::isRegion($region) && !PhLocations::isCityIn($city, $region)) {
                    $v->errors()->add(
                        "participants.{$i}.company_city",
                        'Row ' . ($i + 1) . ': "' . $city . '" is not a city or municipality of ' . $region . '.'
                    );
                }

                // A token is only meaningful while its staged file is still there.
                $token = $row['photo_token'] ?? null;

                if ($token && !$this->stagedPhotoExists($user, $token)) {
                    $v->errors()->add(
                        "participants.{$i}.photo_token",
                        'The ID picture for row ' . ($i + 1) . ' is no longer available. Please upload it again.'
                    );
                }
            }
        });

        $validator->validate();

        return $decoded;
    }

    /**
     * Write the encoded rows and move each staged picture into the report folder.
     */
    private function persistParticipants(PostTrainingReport $report, array $participants, string $basePath, User $user): void
    {
        foreach ($participants as $index => $row) {
            $rowNo = $index + 1;

            PtrParticipant::create(
                $this->participantAttributes($row)
                + $this->claimStagedPhoto($user, $row['photo_token'], $basePath, $rowNo, $row['photo_name'] ?? null)
                + [
                    'post_training_report_id' => $report->id,
                    'row_no'                  => $rowNo,
                    'status'                  => 'pending',
                ]
            );
        }
    }

    /**
     * Normalise one encoded row into column values. Blank optional fields are
     * stored as null rather than empty strings so the grid and the database agree.
     */
    private function participantAttributes(array $row): array
    {
        $numeric    = ['age', 'total_workers'];
        $attributes = [];

        foreach (PtrParticipant::FIELDS as $field) {
            $value = isset($row[$field]) ? trim((string) $row[$field]) : '';

            if ($value === '') {
                $attributes[$field] = null;
                continue;
            }

            $attributes[$field] = in_array($field, $numeric, true) ? (int) $value : $value;
        }

        return $attributes;
    }

    /**
     * Move a staged picture into the report's participants folder.
     */
    private function claimStagedPhoto(User $user, string $token, string $basePath, int $rowNo, ?string $originalName): array
    {
        $ext  = strtolower(pathinfo($token, PATHINFO_EXTENSION)) ?: 'jpg';
        $from = $this->stagingPath($user) . '/' . $token;
        $to   = $basePath . '/participants/participant_' . $rowNo . '_' . time() . '.' . $ext;

        Storage::disk('local')->move($from, $to);

        return [
            'id_picture_path'     => $to,
            'id_picture_filename' => $originalName ?: basename($to),
            'id_picture_size'     => Storage::disk('local')->size($to),
        ];
    }

    /** Where this FATPro's not-yet-submitted ID pictures wait. */
    private function stagingPath(User $user): string
    {
        return 'ptr_staging/' . $user->id;
    }

    /**
     * A staged token is only valid if it is a bare filename we wrote ourselves
     * and the file is still on disk.
     */
    private function stagedPhotoExists(User $user, string $token): bool
    {
        if ($token !== basename($token) || !preg_match('/^[A-Za-z0-9\-]+\.(jpg|jpeg|png)$/i', $token)) {
            return false;
        }

        return Storage::disk('local')->exists($this->stagingPath($user) . '/' . $token);
    }

    /**
     * Drop staged pictures from abandoned encoding sessions. Runs opportunistically
     * on upload, so no scheduled task is needed to keep the folder from growing.
     */
    private function pruneStagedFiles(User $user): void
    {
        try {
            // A day was fine when staging only had to survive one sitting.
            // A draft is meant to be picked up next week, so anything a live
            // draft still references is kept however old it is, and the rest
            // is given a fortnight before it counts as abandoned.
            $cutoff = Carbon::now()->subDays(self::STAGING_RETENTION_DAYS)->getTimestamp();

            $spokenFor = PostTrainingDraft::where('user_id', $user->id)
                ->get()
                ->flatMap->stagedTokens()
                ->flip();

            foreach (Storage::disk('local')->files($this->stagingPath($user)) as $path) {
                if ($spokenFor->has(basename($path))) {
                    continue;
                }

                if (Storage::disk('local')->lastModified($path) < $cutoff) {
                    Storage::disk('local')->delete($path);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Staged file prune failed: ' . $e->getMessage());
        }
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
