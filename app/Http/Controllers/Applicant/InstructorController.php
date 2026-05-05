<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstructorController extends Controller
{
    /**
     * List all instructors belonging to the authenticated FATPro applicant.
     */
    public function index()
    {
        $instructors = Instructor::where('user_id', auth()->id())
            ->with('credentials')
            ->orderBy('last_name')
            ->get();

        return view('applicant.instructor_list', compact('instructors'));
    }

    /**
     * Show full details + credentials for one instructor.
     * Ensures the instructor belongs to the logged-in user.
     */
    public function show(Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);

        $instructor->load('credentials');

        $isAccredited = auth()->user()->accreditations()->where('status', 'active')->exists();

        return view('applicant.instructor_show', compact('instructor', 'isAccredited'));
    }

    /**
     * Batch update for instructor's service agreement and credentials.
     * Processes all submitted files/fields at once and sets the instructor's status to 'pending_review'.
     */
    public function batchUpdate(Request $request, Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);

        $rules = [
            'service_agreement' => 'nullable|file|mimes:pdf|max:10240',
            'credentials.*.number' => 'nullable|string|max:255',
            'credentials.*.issued_date' => 'nullable|date',
            'credentials.*.validity_date' => 'nullable|date',
            'credentials.*.training_dates' => 'nullable|string|max:500',
            'credentials.*.pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        ];

        $request->validate($rules);

        $application = \App\Models\Application::with('accreditationType')->where('user_id', auth()->id())->latest()->first();
        $accreditationName = $application && $application->accreditationType ? $application->accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));

        $fatProName = auth()->user()->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';

        $baseCredPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials";
        $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->first_name));
        $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->last_name));
        $timestamp = time();

        // 1. Handle Service Agreement Update
        if ($request->hasFile('service_agreement')) {
            if ($instructor->service_agreement_path && Storage::disk('local')->exists($instructor->service_agreement_path)) {
                Storage::disk('local')->delete($instructor->service_agreement_path);
            }
            $filename  = "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $path = $request->file('service_agreement')->storeAs($baseCredPath, $filename, 'local');

            $instructor->update([
                'service_agreement_path' => $path,
                'status'                 => 'pending',
                'remarks'                => null,
            ]);
        }

        // 2. Handle Credentials Update
        if ($request->has('credentials')) {
            foreach ($request->input('credentials') as $credId => $credData) {
                $credential = $instructor->credentials()->find($credId);
                if (!$credential) continue;

                $data = [
                    'number'         => $credData['number'] ?? $credential->number,
                    'issued_date'    => $credData['issued_date'] ?? $credential->issued_date,
                    'validity_date'  => $credData['validity_date'] ?? $credential->validity_date,
                    'training_dates' => $credData['training_dates'] ?? $credential->training_dates,
                    'status'         => 'pending', // Reset for admin re-review
                    'remarks'        => null,
                ];

                if ($request->hasFile("credentials.{$credId}.pdf_file")) {
                    if ($credential->pdf_path && Storage::disk('local')->exists($credential->pdf_path)) {
                        Storage::disk('local')->delete($credential->pdf_path);
                    }
                    $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->type));
                    $filename  = "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}_{$credId}.pdf";
                    $path = $request->file("credentials.{$credId}.pdf_file")->storeAs($baseCredPath, $filename, 'local');
                    $data['pdf_path'] = $path;
                }

                $credential->update($data);
            }
        }

        // If this update was admin-requested, signal it's ready for re-evaluation
        if ($instructor->update_request_status === 'admin_requested') {
            $instructor->update(['update_request_status' => 'pending_review']);
        }

        return redirect()->route('applicant.instructors.show', $instructor->id)
            ->with('success', 'Updates submitted successfully for admin review.');
    }

    /**
     * Stream a credential PDF to the browser (auth-guarded).
     */
    public function serveCredential(InstructorCredential $credential)
    {
        $instructor = $credential->instructor;
        abort_if($instructor->user_id !== auth()->id(), 403);
        abort_if(!$credential->pdf_path || !Storage::disk('local')->exists($credential->pdf_path), 404);

        return response()->file(Storage::disk('local')->path($credential->pdf_path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($credential->pdf_path) . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Stream a service agreement PDF to the browser (auth-guarded).
     */
    public function serveServiceAgreement(Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);
        abort_if(!$instructor->service_agreement_path || !Storage::disk('local')->exists($instructor->service_agreement_path), 404);

        return response()->file(Storage::disk('local')->path($instructor->service_agreement_path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($instructor->service_agreement_path) . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
