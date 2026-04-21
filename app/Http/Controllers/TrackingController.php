<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Support\Facades\Storage;

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
                'user',
            ])->where('tracking_number', $request->input('tracking_number'))->first();
        }

        return view('landing.track', compact('application'));
    }

    /**
     * Handle the resubmission of an application document.
     */
    public function resubmitDocument(Request $request, ApplicationDocument $document)
    {
        $request->validate([
            'replacement_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $application = $document->application;
        $field       = $document->documentField;

        if (! $field || ! $request->hasFile('replacement_file')) {
            return back()->with('error', 'Failed to upload the replacement file.');
        }

        $file      = $request->file('replacement_file');
        $code      = $field->code ?? ('doc_' . uniqid());
        $finalPath = "public/documents/{$application->id}/{$code}.pdf";

        // Remove old file if it exists
        $userDoc = $document->userDocument;
        if ($userDoc && $userDoc->file_path && Storage::disk('local')->exists($userDoc->file_path)) {
            Storage::disk('local')->delete($userDoc->file_path);
        }

        // Store replacement file
        $file->storeAs("public/documents/{$application->id}", "{$code}.pdf", 'local');

        // Update user_document with new path
        if ($userDoc) {
            $userDoc->update(['file_path' => $finalPath]);
        }

        // Reset document tracking status
        $document->update([
            'status'  => 'pending',
            'remarks' => null,
        ]);

        return back()->with('success', "Document '{$field->name}' successfully resubmitted.");
    }
}
