<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Mail\PostTrainingEvaluationEmail;
use App\Models\PostTrainingReport;
use App\Models\PtrDocument;
use App\Models\PtrParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class PostTrainingReportController extends Controller
{
    /**
     * Post training reports are the Training Evaluator's exclusive domain,
     * exactly like NTC and Report of Changes.
     */
    private function requireTrainingEvaluatorAccess(): void
    {
        $adminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        if (strtolower($adminRole) !== 'training evaluator') {
            abort(403, 'Unauthorized action. Only Training Evaluator has access to this page.');
        }
    }

    /**
     * List all post training report submissions across all FATPros.
     */
    public function index()
    {
        $this->requireTrainingEvaluatorAccess();

        $reports = PostTrainingReport::with([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'ntcReport.trainingType',
            'ntcReport.trainingMode',
            'documents.documentType',
        ])
            ->latest()
            ->get();

        return view('admin.hcd.reports.post_training', compact('reports'));
    }

    /**
     * Show the detail/evaluation page for a single post training report.
     */
    public function show(PostTrainingReport $postTrainingReport)
    {
        $this->requireTrainingEvaluatorAccess();

        $postTrainingReport->loadMissing([
            'accreditation.user.organizationProfile.authorizedRepresentatives',
            'accreditation.user.individualProfile',
            'accreditation.accreditationType',
            'ntcReport.trainingType',
            'ntcReport.trainingMode',
            'ntcReport.trainingDates',
            // Both lists are shown side by side on the roster section, so the
            // evaluator can see who was added after the NTC was acknowledged.
            'ntcReport.instructors',
            'instructors.records.credentials',
            'documents.documentType',
            'documents.evaluatedByUser',
            'participants.evaluatedByUser',
            'acceptedByUser',
        ]);

        $accreditation = $postTrainingReport->accreditation;
        $fatproUser    = $accreditation->user ?? null;
        $isOrg         = $fatproUser?->profile_type === 'Organization';
        $org           = $fatproUser?->organizationProfile;
        $ind           = $fatproUser?->individualProfile;
        $reps          = $org?->authorizedRepresentatives ?? collect();

        return view('admin.hcd.reports.post_training_show', compact(
            'postTrainingReport',
            'accreditation',
            'fatproUser',
            'isOrg',
            'org',
            'ind',
            'reps',
        ));
    }

    /**
     * Accept/decline a single document. Clicking an already-active button
     * reverts it to pending, so a mis-click can be undone.
     */
    public function evaluateDocument(Request $request, PtrDocument $document)
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
     * Serve a post training document file to the admin (private storage).
     */
    public function serveDocument(PtrDocument $document)
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
     * Finalize the whole evaluation: accept the report when every document is
     * approved, or mail the FATPro the list of declined documents.
     */
    /**
     * Accept/decline a single participant. Clicking an already-active button
     * reverts it to pending, mirroring how documents behave.
     *
     * Declining a participant never deletes their ID picture: the FATPro
     * corrects the row in place rather than re-filing the whole Directory.
     */
    public function evaluateParticipant(Request $request, PtrParticipant $participant)
    {
        $this->requireTrainingEvaluatorAccess();

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $admin = Auth::user();

        $participant->update([
            'status'       => $validated['status'],
            'evaluated_by' => $validated['status'] === 'pending' ? null : $admin->id,
            'evaluated_at' => $validated['status'] === 'pending' ? null : now(),
        ]);

        return response()->json([
            'success' => true,
            'status'  => $participant->status,
        ]);
    }

    /**
     * Serve a participant's ID picture to the admin (private storage).
     */
    public function serveParticipantPhoto(PtrParticipant $participant)
    {
        $this->requireTrainingEvaluatorAccess();

        if (!$participant->id_picture_path || !Storage::disk('local')->exists($participant->id_picture_path)) {
            abort(404, 'ID picture not found.');
        }

        return Storage::disk('local')->response(
            $participant->id_picture_path,
            $participant->id_picture_filename ?: 'id-picture.jpg'
        );
    }

    /**
     * Save the per-participant verdicts submitted alongside the document ones.
     */
    private function applyParticipantEvaluations(Request $request, PostTrainingReport $report, $admin): void
    {
        $evaluations = $request->input('participants', []);

        if (!is_array($evaluations) || $evaluations === []) {
            return;
        }

        $rows = $report->participants()
            ->whereIn('id', array_column($evaluations, 'id'))
            ->get()
            ->keyBy('id');

        foreach ($evaluations as $eval) {
            $row = $rows->get($eval['id'] ?? null);

            if (!$row) {
                continue;
            }

            $status = $eval['status'] ?? 'pending';

            $row->update([
                'status'       => $status,
                'evaluated_by' => $status === 'pending' ? null : $admin->id,
                'evaluated_at' => $status === 'pending' ? null : now(),
            ]);
        }
    }

    /**
     * Give the Directory's section row the verdict its participants imply.
     *
     * The Directory holds no file, so its status is not something the evaluator
     * sets directly — one declined participant declines the section. Remarks are
     * section-wide by design: there is a single explanation for the whole
     * Directory rather than one per participant.
     *
     * Returns true when the section came out declined.
     */
    private function syncDirectoryStatus(Request $request, PostTrainingReport $report, $admin): bool
    {
        $report->load(['documents.documentType', 'participants']);

        $directory = $report->documents->first(fn ($d) => $d->documentType?->isEncoded());

        if (!$directory || $report->participants->isEmpty()) {
            return false;
        }

        $anyRejected = $report->participants->contains(fn ($p) => $p->status === 'rejected');
        $allApproved = $report->participants->every(fn ($p) => $p->status === 'approved');

        $status = match (true) {
            $anyRejected => 'rejected',
            $allApproved => 'approved',
            default      => 'pending',
        };

        $directory->update([
            'status'       => $status,
            'remarks'      => $status === 'rejected' ? $request->input('directory_remarks') : null,
            'evaluated_by' => $status === 'pending' ? null : $admin->id,
            'evaluated_at' => $status === 'pending' ? null : now(),
        ]);

        return $anyRejected;
    }

    public function finalizeEvaluation(Request $request, PostTrainingReport $postTrainingReport)
    {
        $this->requireTrainingEvaluatorAccess();

        $request->validate([
            'evaluations'           => ['required', 'array'],
            'evaluations.*.id'      => ['required', 'exists:ptr_documents,id'],
            'evaluations.*.status'  => ['required', 'in:approved,rejected,pending,returned'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
            // The Directory is judged row by row, under a single remark.
            'participants'           => ['nullable', 'array'],
            'participants.*.id'      => ['required', 'exists:ptr_participants,id'],
            'participants.*.status'  => ['required', 'in:approved,rejected,pending'],
            'directory_remarks'      => ['nullable', 'string', 'max:1000'],
        ]);

        $evaluations   = $request->input('evaluations', []);
        $admin         = Auth::user();
        $hasRejections = false;

        $docs = PtrDocument::where('post_training_report_id', $postTrainingReport->id)
            ->whereIn('id', array_column($evaluations, 'id'))
            ->get()
            ->keyBy('id');

        foreach ($evaluations as $eval) {
            $doc = $docs->get($eval['id']);
            if (!$doc) {
                continue;
            }

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
                // Folded into the same UPDATE rather than a second one.
                $attributes['file_path'] = null;
            }

            $doc->update($attributes);
        }

        $this->applyParticipantEvaluations($request, $postTrainingReport, $admin);

        if ($this->syncDirectoryStatus($request, $postTrainingReport, $admin)) {
            $hasRejections = true;
        }

        $postTrainingReport->load('documents');
        $allApproved = $postTrainingReport->documents->isNotEmpty()
            && $postTrainingReport->documents->every(fn ($d) => $d->status === 'approved');

        if ($allApproved && $postTrainingReport->status !== 'accepted') {
            $postTrainingReport->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
                'accepted_by' => $admin->id,
            ]);

            try {
                $fatproEmail = $postTrainingReport->accreditation->user->email ?? null;
                if ($fatproEmail) {
                    Mail::to($fatproEmail)->send(new PostTrainingEvaluationEmail($postTrainingReport));
                }
            } catch (\Exception $e) {
                Log::warning('Post Training Report acceptance email failed: ' . $e->getMessage());
            }
        }

        if ($hasRejections) {
            $postTrainingReport->update(['status' => 'declined']);

            try {
                $declinedDocs = $postTrainingReport->documents->where('status', 'rejected');
                $fatproEmail  = $postTrainingReport->accreditation->user->email ?? null;

                if ($fatproEmail) {
                    Mail::to($fatproEmail)->send(new PostTrainingEvaluationEmail($postTrainingReport, $declinedDocs));
                }
            } catch (\Exception $e) {
                Log::warning('Post Training Report decline email failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success'        => true,
            'message'        => $hasRejections ? 'Decline notice sent successfully.' : 'Evaluation saved successfully.',
            'ntc_acknowledged' => $allApproved,
            'has_rejections' => $hasRejections,
        ]);
    }
}
