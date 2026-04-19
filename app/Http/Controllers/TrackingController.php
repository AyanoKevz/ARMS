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
                'documents.documentType', 
                'user'
            ])->where('tracking_number', $request->input('tracking_number'))->first();
            
            // Note: If no application is found, we pass null to the view so it can display "Not Found" message.
        }

        return view('LandingPage.track', compact('application'));
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

        if ($request->hasFile('replacement_file')) {
            $file = $request->file('replacement_file');
            
            // Use the document code or generate a name. Document pattern: public/documents/{application_id}/{code}.pdf
            $documentType = $document->documentType;
            $code = $documentType->code ?? ('doc_' . uniqid());
            
            // Remove the old file if exists
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            // Store new file
            $finalPath = "public/documents/{$application->id}/{$code}.pdf";
            
            // Move via storeAs directly into the final path equivalent directory structure
            $path = $file->storeAs(
                "public/documents/{$application->id}", 
                "{$code}.pdf", 
                'local'
            );

            // Update document status back to pending, clear remarks
            $document->update([
                'file_path' => $path,
                'status'    => 'pending',
                'remarks'   => null,
            ]);

            return back()->with('success', "Document {$documentType->name} successfully resubmitted.");
        }

        return back()->with('error', 'Failed to upload the replacement file.');
    }
}
