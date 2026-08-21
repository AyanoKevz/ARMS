<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Mail\NtcEvaluationEmail;
use App\Models\NtcDocument;
use App\Models\NtcReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class NtcController extends Controller
{
    /**
     * Helper to allow ONLY Training Evaluator through — NTC/training reports are their exclusive domain.
     */
    private function requireTrainingEvaluatorAccess()
    {
        $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        if (strtolower($isAdminRole) !== 'training evaluator') {
            abort(403, 'Unauthorized action. Only Training Evaluator has access to this page.');
        }
    }

    /**
     * List all NTC submissions across all FATPros.
     */
    public function index()
    {
        $this->requireTrainingEvaluatorAccess();
        $ntcReports = NtcReport::with([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
        ])
            ->where('status', '!=', 'report_changes')
            ->latest()
            ->get();

        return view('admin.hcd.reports.ntc', compact('ntcReports'));
    }

    /**
     * List all Report of Changes submissions across all FATPros.
     */
    public function reportChangesIndex()
    {
        $this->requireTrainingEvaluatorAccess();
        $ntcReports = NtcReport::with([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
        ])
            ->where('status', 'report_changes')
            ->latest()
            ->get();

        return view('admin.hcd.reports.report_changes', compact('ntcReports'));
    }

    /**
     * Show the detail/evaluation page for a single NTC submission.
     */
    public function show(NtcReport $ntcReport)
    {
        $this->requireTrainingEvaluatorAccess();
        $ntcReport->loadMissing([
            'accreditation.user.organizationProfile.authorizedRepresentatives',
            'accreditation.user.individualProfile',
            'accreditation.accreditationType',
            'trainingType',
            'trainingMode',
            'documents.documentType',
            'documents.evaluatedByUser',
            'acknowledgedByUser',
        ]);

        $accreditation = $ntcReport->accreditation;
        $fatproUser    = $accreditation->user ?? null;
        $isOrg         = $fatproUser?->profile_type === 'Organization';
        $org           = $fatproUser?->organizationProfile;
        $ind           = $fatproUser?->individualProfile;
        $reps          = $org?->authorizedRepresentatives ?? collect();

        return view('admin.hcd.reports.ntc_show', compact(
            'ntcReport',
            'accreditation',
            'fatproUser',
            'isOrg',
            'org',
            'ind',
            'reps',
        ));
    }

    /**
     * Evaluate (approve/reject) individual NTC documents and optionally
     * mark the overall NTC report as acknowledged when all docs are approved.
     * A document can also be reverted to 'pending' — clicking an already-active
     * Approve/Reject button again undoes a mistaken click.
     */
    public function evaluateDocument(Request $request, NtcDocument $document)
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
     * Serve an NTC document file to the admin (private storage).
     */
    public function serveDocument(NtcDocument $document)
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
     * Finalize the entire NTC evaluation.
     */
    public function finalizeEvaluation(Request $request, NtcReport $ntcReport)
    {
        $this->requireTrainingEvaluatorAccess();
        $validated = $request->validate([
            'evaluations' => ['required', 'array'],
            'evaluations.*.id' => ['required', 'exists:ntc_documents,id'],
            'evaluations.*.status' => ['required', 'in:approved,rejected,pending,returned'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $evaluations = $request->input('evaluations', []);
        $admin = Auth::user();
        $hasRejections = false;
        $wasReportChanges = $ntcReport->status === 'report_changes';

        // Resolve all submitted documents in one query (still scoped to this
        // report) rather than a SELECT per row inside the loop.
        $docs = NtcDocument::where('ntc_report_id', $ntcReport->id)
            ->whereIn('id', array_column($evaluations, 'id'))
            ->get()
            ->keyBy('id');

        foreach ($evaluations as $eval) {
            $doc = $docs->get($eval['id']);
            if ($doc) {
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
                    // Fold the file_path reset into the same UPDATE instead of
                    // issuing a second one.
                    $attributes['file_path'] = null;
                }

                $doc->update($attributes);
            }
        }

        $ntcReport->load('documents');
        $allApproved = $ntcReport->documents->every(fn ($d) => $d->status === 'approved');

        if ($allApproved && $ntcReport->status !== 'acknowledged') {
            $ntcReport->update([
                'status'          => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $admin->id,
            ]);

            try {
                $fatproEmail = $ntcReport->accreditation->user->email ?? null;
                if ($fatproEmail) {
                    Mail::to($fatproEmail)->send(new \App\Mail\NtcEvaluationEmail($ntcReport, null, $wasReportChanges));
                }
            } catch (\Exception $e) {
                Log::warning('NTC acknowledgment email failed: ' . $e->getMessage());
            }
        }

        if ($hasRejections) {
            try {
                $rejectedDocs = $ntcReport->documents->where('status', 'rejected');
                $fatproEmail  = $ntcReport->accreditation->user->email ?? null;

                if ($fatproEmail) {
                    Mail::to($fatproEmail)
                        ->send(new NtcEvaluationEmail($ntcReport, $rejectedDocs, $wasReportChanges));
                }
            } catch (\Exception $e) {
                Log::warning('NTC document rejection email failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success'          => true,
            'message'          => $hasRejections ? 'Rejection notice sent successfully.' : 'Evaluation saved successfully.',
            'ntc_acknowledged' => $allApproved,
            'has_rejections'   => $hasRejections,
        ]);
    }
}
