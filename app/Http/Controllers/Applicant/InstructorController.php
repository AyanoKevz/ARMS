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
        // Get all instructors belonging to the user, ordered by ID desc so the latest version is first.
        // Then filter duplicates in memory keeping only the latest version, and sort alphabetically by last_name.
        //
        // Instructors still awaiting evaluation are excluded. Submitting a renewal creates a
        // fresh set of instructor rows (status 'pending' by default) alongside the already
        // accredited ones, and because those rows have higher IDs the de-duplication below
        // would otherwise surface the in-flight renewal copy instead of the approved record.
        // This list is meant to show the FATPro's accredited instructors, not the contents of
        // an application that is still under review.
        $instructors = Instructor::where('user_id', auth()->id())
            ->where('status', '!=', 'pending')
            ->whereDoesntHave('credentials', fn ($q) => $q->where('status', 'pending'))
            ->with('credentials')
            ->orderBy('id', 'desc')
            ->get()
            ->unique(function ($item) {
                return strtolower(trim($item->first_name) . '|' . trim($item->middle_name) . '|' . trim($item->last_name));
            })
            ->sortBy('last_name')
            ->values();

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

        $accreditation = auth()->user()->accreditations()
            ->orderBy('id', 'desc')
            ->first();

        return view('applicant.instructor_show', compact('instructor', 'isAccredited', 'accreditation'));
    }

    /**
     * Update the instructor's name.
     */
    public function updateName(Request $request, Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);

        $request->validate([
            'first_name'  => 'required|string|max:255',
            'last_name'   => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
        ]);

        $instructor->update([
            'first_name'  => $request->input('first_name'),
            'last_name'   => $request->input('last_name'),
            'middle_name' => $request->input('middle_name'),
        ]);

        return redirect()->route('applicant.instructors.show', $instructor->id)
            ->with('success', 'Instructor name updated successfully.');
    }

    /**
     * Batch update for instructor's service agreement and credentials.
     * Processes all submitted files/fields at once and sets the instructor's status to 'pending_review'.
     */
    public function batchUpdate(Request $request, Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);

        // Guard 1: Prevent submitting again while already under admin review
        if ($instructor->update_request_status === 'pending_review') {
            return redirect()->back()->with('error', 'Your submitted updates are currently under admin review. You cannot submit new changes until the review is completed.');
        }

        $rules = [
            'service_agreement' => 'nullable|file|mimes:pdf|max:10240',
            'credentials.*.number' => 'nullable|string|max:255',
            'credentials.*.issued_date' => 'nullable|date',
            'credentials.*.validity_date' => 'nullable|date',
            'credentials.*.training_dates' => 'nullable|string|max:500',
            'credentials.*.pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        ];

        $request->validate($rules);

        // Guard 2: Require at least one uploaded file or modified credential field
        $hasAnyFile = $request->hasFile('service_agreement');
        $hasAnyFieldChange = false;

        if ($request->has('credentials')) {
            foreach ($request->input('credentials') as $credId => $credData) {
                if ($request->hasFile("credentials.{$credId}.pdf_file")) {
                    $hasAnyFile = true;
                }
                $credential = $instructor->credentials()->find($credId);
                if ($credential) {
                    if (isset($credData['number']) && trim((string)$credData['number']) !== trim((string)$credential->number)) {
                        $hasAnyFieldChange = true;
                    }
                    $existingIssued = $credential->issued_date ? $credential->issued_date->format('Y-m-d') : '';
                    if (isset($credData['issued_date']) && trim((string)$credData['issued_date']) !== $existingIssued) {
                        $hasAnyFieldChange = true;
                    }
                    $existingValid = $credential->validity_date ? $credential->validity_date->format('Y-m-d') : '';
                    if (isset($credData['validity_date']) && trim((string)$credData['validity_date']) !== $existingValid) {
                        $hasAnyFieldChange = true;
                    }
                    if (isset($credData['training_dates']) && trim((string)$credData['training_dates']) !== trim((string)$credential->training_dates)) {
                        $hasAnyFieldChange = true;
                    }
                }
            }
        }

        if (!$hasAnyFile && !$hasAnyFieldChange) {
            return redirect()->back()->with('error', 'Please upload at least one document file or update a credential field before submitting.');
        }

        $application = \App\Models\Application::with('accreditationType')->where('user_id', auth()->id())->latest()->first();
        $accreditationName = $application && $application->accreditationType ? $application->accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));

        $fatProName = auth()->user()->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';

        $baseCredPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials";
        $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->first_name));
        $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->last_name));
        $timestamp = time();

        $updatedFields = $instructor->update_request_fields ?? [];
        if (!is_array($updatedFields)) {
            $updatedFields = [];
        }

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

            if (!in_array('service_agreement', $updatedFields)) {
                $updatedFields[] = 'service_agreement';
            }
        }

        // 2. Handle Credentials Update
        if ($request->has('credentials')) {
            foreach ($request->input('credentials') as $credId => $credData) {
                $credential = $instructor->credentials()->find($credId);
                if (!$credential) continue;

                $hasFile = $request->hasFile("credentials.{$credId}.pdf_file");
                
                $numberChanged = isset($credData['number']) && trim((string)$credData['number']) !== trim((string)($credential->number ?? ''));

                $issuedChanged = false;
                if (isset($credData['issued_date'])) {
                    $existingIssued = $credential->issued_date ? $credential->issued_date->format('Y-m-d') : '';
                    $newIssued = trim((string)$credData['issued_date']);
                    $issuedChanged = ($newIssued !== $existingIssued);
                }

                $validChanged = false;
                if (isset($credData['validity_date'])) {
                    $existingValid = $credential->validity_date ? $credential->validity_date->format('Y-m-d') : '';
                    $newValid = trim((string)$credData['validity_date']);
                    $validChanged = ($newValid !== $existingValid);
                }

                $trainingChanged = false;
                if (isset($credData['training_dates'])) {
                    $existingTraining = trim((string)($credential->training_dates ?? ''));
                    $newTraining = trim((string)$credData['training_dates']);
                    $trainingChanged = ($newTraining !== $existingTraining);
                }

                $isCredUpdated = $hasFile || $numberChanged || $issuedChanged || $validChanged || $trainingChanged;

                // Skip unchanged credentials so their status remains intact (e.g. approved)
                if (!$isCredUpdated) {
                    continue;
                }

                $data = [
                    'number'         => $credData['number'] ?? $credential->number,
                    'issued_date'    => $credData['issued_date'] ?? $credential->issued_date,
                    'validity_date'  => $credData['validity_date'] ?? $credential->validity_date,
                    'training_dates' => $credData['training_dates'] ?? $credential->training_dates,
                    'status'         => 'pending', // Reset for admin re-review ONLY if updated
                    'remarks'        => null,
                ];

                if ($hasFile) {
                    if ($credential->pdf_path && Storage::disk('local')->exists($credential->pdf_path)) {
                        Storage::disk('local')->delete($credential->pdf_path);
                    }
                    $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->type));
                    $filename  = "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}_{$credId}.pdf";
                    $path = $request->file("credentials.{$credId}.pdf_file")->storeAs($baseCredPath, $filename, 'local');
                    $data['pdf_path'] = $path;
                }

                $credential->update($data);

                if (!in_array($credential->type, $updatedFields)) {
                    $updatedFields[] = $credential->type;
                }
            }
        }

        // Set status to pending_review and record updated fields
        $instructor->update([
            'update_request_status' => 'pending_review',
            'update_request_fields' => array_values(array_unique($updatedFields)),
        ]);

        // Send email notification to the assigned Evaluator only
        if ($application) {
            try {
                $application->loadMissing('assignedEvaluator');
                $assignedEvaluatorEmail = $application->assignedEvaluator?->email;

                if ($assignedEvaluatorEmail) {
                    $count = count($updatedFields) ?: 1;
                    $application->loadMissing(['user', 'accreditationType']);
                    \Illuminate\Support\Facades\Mail::to($assignedEvaluatorEmail)->send(new \App\Mail\AdminDocumentsUploadedEmail($application, $count, true, $instructor));
                } else {
                    \Illuminate\Support\Facades\Log::warning('Instructor update notification skipped: application ' . $application->tracking_number . ' has no assigned evaluator.');
                }
            } catch (\Exception $mailEx) {
                \Illuminate\Support\Facades\Log::warning('Admin instructor update notification email failed: ' . $mailEx->getMessage());
            }
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
