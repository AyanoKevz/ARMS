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
            'files'          => ['nullable', 'array'],
            'files.*'        => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'values'         => ['nullable', 'array'],
            'values.*'       => ['required', 'string', 'max:500'],
            'instructor_files' => ['nullable', 'array'],
            'instructor_files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'credential_files' => ['nullable', 'array'],
            'credential_files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $application = Application::with(['user.instructors.credentials', 'accreditationType'])->findOrFail($request->input('application_id'));
        $userId      = $application->user_id;
        
        $accreditationName = $application->accreditationType ? $application->accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));

        $fatProName = $application->user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';

        $baseDocPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/documents";
        $baseCredPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials";
        
        $files             = $request->file('files') ?? [];
        $values            = $request->input('values') ?? [];
        $instructorFiles   = $request->file('instructor_files') ?? [];
        $credentialFiles   = $request->file('credential_files') ?? [];
        $resubmitted       = 0;

        foreach ($values as $appDocId => $value) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type === 'file') continue;

            $userDoc = UserDocument::where('user_id', $userId)
                ->where('document_field_id', $field->id)
                ->first();

            if ($userDoc) {
                $userDoc->update(['value' => $value]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $userId,
                    'document_field_id' => $field->id,
                    'value'             => $value,
                ]);
            }

            $appDoc->update([
                'user_document_id' => $userDoc->id,
                'status'           => 'pending',
                'remarks'          => null,
            ]);

            $resubmitted++;
        }

        foreach ($files as $appDocId => $file) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (! $appDoc) continue;
            if (! in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (! $field || $field->input_type !== 'file') continue;

            $code      = $field->code;
            $timestamp = time();
            $filename  = "{$code}_{$timestamp}.pdf";
            $subFolder = $baseDocPath;
            $finalPath = "{$subFolder}/{$filename}";

            $userDoc = UserDocument::where('user_id', $userId)
                ->where('document_field_id', $field->id)
                ->first();

            if ($userDoc && $userDoc->file_path) {
                if (Storage::disk('local')->exists($userDoc->file_path)) {
                    Storage::disk('local')->delete($userDoc->file_path);
                }
            }

            $file->storeAs($subFolder, $filename, 'local');

            if ($userDoc) {
                $userDoc->update(['file_path' => $finalPath]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $userId,
                    'document_field_id' => $field->id,
                    'file_path'         => $finalPath,
                ]);
            }

            $appDoc->update([
                'user_document_id' => $userDoc->id,
                'status'           => 'pending',
                'remarks'          => null,
            ]);

            $resubmitted++;
        }

        foreach ($instructorFiles as $instructorId => $file) {
            $instructor = $application->user->instructors->firstWhere('id', $instructorId);
            if (! $instructor || ! in_array($instructor->status, ['rejected', 'returned'])) continue;

            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->first_name));
            $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->last_name));
            $filename  = "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $subFolder = $baseCredPath;
            $finalPath = "{$subFolder}/{$filename}";

            if ($instructor->service_agreement_path && Storage::disk('local')->exists($instructor->service_agreement_path)) {
                Storage::disk('local')->delete($instructor->service_agreement_path);
            }

            $file->storeAs($subFolder, $filename, 'local');

            $instructor->update([
                'service_agreement_path' => $finalPath,
                'status'                 => 'pending',
                'remarks'                => null,
            ]);

            $resubmitted++;
        }

        foreach ($credentialFiles as $credentialId => $file) {
            $credential = null;
            foreach ($application->user->instructors as $inst) {
                $cred = $inst->credentials->firstWhere('id', $credentialId);
                if ($cred) {
                    $credential = $cred;
                    break;
                }
            }
            if (! $credential || ! in_array($credential->status, ['rejected', 'returned'])) continue;

            $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->type));
            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->instructor->first_name));
            $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->instructor->last_name));
            $filename  = "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $subFolder = $baseCredPath;
            $finalPath = "{$subFolder}/{$filename}";

            if ($credential->pdf_path && Storage::disk('local')->exists($credential->pdf_path)) {
                Storage::disk('local')->delete($credential->pdf_path);
            }

            $file->storeAs($subFolder, $filename, 'local');

            $credential->update([
                'pdf_path' => $finalPath,
                'status'   => 'pending',
                'remarks'  => null,
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
