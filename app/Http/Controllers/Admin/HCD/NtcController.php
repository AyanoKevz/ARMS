<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Models\NtcDocument;
use App\Models\NtcReport;
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
     * Serve an NTC document file to the admin (private storage).
     */
    public function serveDocument(NtcDocument $document)
    {
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
