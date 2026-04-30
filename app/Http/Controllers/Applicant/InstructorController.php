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

        return view('applicant.instructor_show', compact('instructor'));
    }

    /**
     * Update a specific credential's text fields and/or PDF file.
     * Resets the credential's status to 'pending' so the admin must re-review.
     */
    public function updateCredential(Request $request, Instructor $instructor, InstructorCredential $credential)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);
        abort_if($credential->instructor_id !== $instructor->id, 403);

        $rules = [
            'number'         => 'nullable|string|max:255',
            'issued_date'    => 'nullable|date',
            'validity_date'  => 'nullable|date',
            'training_dates' => 'nullable|string|max:500',
            'pdf_file'       => 'nullable|file|mimes:pdf|max:10240',
        ];

        $validated = $request->validate($rules);

        $data = [
            'number'         => $validated['number'] ?? $credential->number,
            'issued_date'    => $validated['issued_date'] ?? $credential->issued_date,
            'validity_date'  => $validated['validity_date'] ?? $credential->validity_date,
            'training_dates' => $validated['training_dates'] ?? $credential->training_dates,
            'status'         => 'pending', // Reset for admin re-review
            'remarks'        => null,
        ];

        // Handle new PDF upload
        if ($request->hasFile('pdf_file')) {
            // Delete old file
            if ($credential->pdf_path && Storage::disk('local')->exists($credential->pdf_path)) {
                Storage::disk('local')->delete($credential->pdf_path);
            }

            $path = $request->file('pdf_file')->store(
                'public/instructors/' . auth()->id() . '/' . $instructor->id . '/credentials',
                'local'
            );
            $data['pdf_path'] = $path;
        }

        $credential->update($data);

        return redirect()->route('applicant.instructors.show', $instructor->id)
            ->with('success', 'Credential updated successfully. It has been submitted for admin review.');
    }

    /**
     * Replace the service agreement PDF for an instructor.
     * Resets the instructor's status to 'pending'.
     */
    public function updateServiceAgreement(Request $request, Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);

        $request->validate([
            'service_agreement' => 'required|file|mimes:pdf|max:10240',
        ]);

        // Delete old file
        if ($instructor->service_agreement_path && Storage::disk('local')->exists($instructor->service_agreement_path)) {
            Storage::disk('local')->delete($instructor->service_agreement_path);
        }

        $path = $request->file('service_agreement')->store(
            'public/instructors/' . auth()->id() . '/' . $instructor->id,
            'local'
        );

        $instructor->update([
            'service_agreement_path' => $path,
            'status'                 => 'pending',
            'remarks'                => null,
        ]);

        return redirect()->route('applicant.instructors.show', $instructor->id)
            ->with('success', 'Service Agreement updated. It has been submitted for admin review.');
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
