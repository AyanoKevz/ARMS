<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Mail\NtcDocumentRejectionEmail;
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
     * List all NTC submissions across all FATPros.
     */
    public function index()
    {
        $ntcReports = NtcReport::with([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
        ])
            ->latest()
            ->get();

        return view('admin.hcd.reports.ntc', compact('ntcReports'));
    }

    /**
     * Show the detail/evaluation page for a single NTC submission.
     */
    public function show(NtcReport $ntcReport)
    {
        $ntcReport->loadMissing([
            'accreditation.user.organizationProfile',
            'accreditation.user.individualProfile',
            'trainingType',
            'trainingMode',
            'documents.documentType',
            'documents.evaluatedByUser',
            'acknowledgedByUser',
        ]);

        return view('admin.hcd.reports.ntc_show', compact('ntcReport'));
    }

    /**
     * Evaluate (approve/reject) individual NTC documents and optionally
     * mark the overall NTC report as acknowledged when all docs are approved.
     */
    public function evaluateDocument(Request $request, NtcDocument $document)
    {
        $validated = $request->validate([
            'status'  => ['required', 'in:approved,rejected'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $admin = Auth::user();

        $document->update([
            'status'       => $validated['status'],
            'remarks'      => $validated['remarks'] ?? null,
            'evaluated_by' => $admin->id,
            'evaluated_at' => now(),
        ]);

        // When rejected: delete the file immediately so there's no stacking.
        // The applicant will re-upload a fresh file.
        if ($validated['status'] === 'rejected') {
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
            // Clear the file_path so the view knows the file is gone
            $document->update(['file_path' => null]);
        }

        // Reload the report with all documents
        $ntcReport = $document->ntcReport->loadMissing([
            'documents',
            'accreditation.user',
        ]);

        // If all documents are now approved → acknowledge the NTC report
        $allApproved = $ntcReport->documents->every(fn ($d) => $d->status === 'approved');
        if ($allApproved && $ntcReport->status !== 'acknowledged') {
            $ntcReport->update([
                'status'          => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $admin->id,
            ]);
        }

        // If at least one document is newly rejected → notify the FATPro
        if ($validated['status'] === 'rejected') {
            try {
                $rejectedDocs = $ntcReport->documents->where('status', 'rejected');
                $fatproEmail  = $ntcReport->accreditation->user->email ?? null;

                if ($fatproEmail) {
                    Mail::to($fatproEmail)
                        ->send(new NtcDocumentRejectionEmail($ntcReport, $rejectedDocs));
                }
            } catch (\Exception $e) {
                Log::warning('NTC document rejection email failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success'         => true,
            'status'          => $document->status,
            'ntc_acknowledged'=> $allApproved,
        ]);
    }

    /**
     * Serve an NTC document file to the admin (private storage).
     */
    public function serveDocument(NtcDocument $document)
    {
        if (!$document->file_path || !Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type]
        );
    }
}
