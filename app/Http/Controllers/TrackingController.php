<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\UserDocument;
use App\Models\Accreditation;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class TrackingController extends Controller
{
    /**
     * Generate and stream the applicant's own Accreditation Certificate as PDF.
     */
    public function downloadCertificate()
    {
        $accreditation = Accreditation::where('user_id', auth()->id())
            ->where('status', 'active')
            ->with(['user.organizationProfile', 'user.individualProfile', 'accreditationType'])
            ->first();

        if (! $accreditation) {
            abort(404, 'No active accreditation found for your account.');
        }

        $user = $accreditation->user;

        if ($user->profile_type === 'Organization' && $user->organizationProfile) {
            $fatproName = $user->organizationProfile->name ?? $user->name;
        } elseif ($user->individualProfile) {
            $fatproName = $user->individualProfile->full_name ?? $user->name;
        } else {
            $fatproName = $user->name;
        }

        $pdf = Pdf::loadView('certificates.accreditation', [
            'accreditation' => $accreditation,
            'fatproName'    => $fatproName,
        ])->setPaper('a4', 'portrait');

        $filename = 'Accreditation_Certificate_' . $accreditation->accreditation_number . '.pdf';

        return $pdf->stream($filename);
    }

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
                'user',
            ])->where('tracking_number', $request->input('tracking_number'))->first();
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
        $request->validate([
            'application_id' => ['required', 'exists:applications,id'],
            'files'          => ['required', 'array'],
            'files.*'        => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $application = Application::with('user')->findOrFail($request->input('application_id'));
        $userId      = $application->user_id;
        $files       = $request->file('files'); // keyed by application_document.id
        $resubmitted = 0;

        foreach ($files as $appDocId => $file) {

            // ── 1. Load and validate the ApplicationDocument ─────────────────
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;

            // ── 2. Guard: only process rejected / returned docs ──────────────
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type !== 'file') continue;

            // ── 3. Build unique path to prevent browser caching & ensure actual DB update ───
            $code      = $field->code;
            $timestamp = time();
            $filename  = "{$code}_{$timestamp}.pdf";
            $subFolder = "public/documents/{$application->id}";
            $finalPath = "{$subFolder}/{$filename}";

            // ── 4. Find UserDocument by the true unique key ──────────────────
            //    user_documents has UNIQUE(user_id, document_field_id)
            //    so this is always the canonical record for this field+user.
            $userDoc = UserDocument::where('user_id', $userId)
                ->where('document_field_id', $field->id)
                ->first();

            // ── 5. Delete old file if it exists, so we don't stack files ─────
            if ($userDoc && $userDoc->file_path) {
                if (Storage::disk('local')->exists($userDoc->file_path)) {
                    Storage::disk('local')->delete($userDoc->file_path);
                }
            }

            // ── 6. Store the new file ────────────────────────────────────────
            $file->storeAs($subFolder, $filename, 'local');

            // ── 7. Update or create the UserDocument record ──────────────────
            if ($userDoc) {
                $userDoc->update(['file_path' => $finalPath]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $userId,
                    'document_field_id' => $field->id,
                    'file_path'         => $finalPath,
                ]);
            }

            // ── 8. Re-link ApplicationDocument → correct UserDocument ────────
            $appDoc->update([
                'user_document_id' => $userDoc->id,
                'status'           => 'pending',
                'remarks'          => null,
            ]);

            $resubmitted++;
        }

        if ($resubmitted === 0) {
            return back()->with('error', 'No valid documents were resubmitted. Please ensure you are uploading PDF files for rejected items.');
        }

        return back()->with('success',
            "{$resubmitted} document" . ($resubmitted > 1 ? 's' : '') .
            " successfully resubmitted. Your application is now back under review."
        );
    }
}
