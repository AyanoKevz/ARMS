<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Mail\PostTrainingEvaluationEmail;
use App\Models\PostTrainingReport;
use App\Models\PtrDocument;
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
            'documents.documentType',
            'documents.evaluatedByUser',
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
    public function finalizeEvaluation(Request $request, PostTrainingReport $postTrainingReport)
    {
        $this->requireTrainingEvaluatorAccess();

        $request->validate([
            'evaluations'           => ['required', 'array'],
            'evaluations.*.id'      => ['required', 'exists:ptr_documents,id'],
            'evaluations.*.status'  => ['required', 'in:approved,rejected,pending,returned'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
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
