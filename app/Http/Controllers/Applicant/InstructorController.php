<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RegistrationController;
use App\Models\Application;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InstructorController extends Controller
{
    /**
     * List all instructors belonging to the authenticated FATPro applicant.
     */
    public function index()
    {
        // Shared with the applicant dashboard so both pages show the same roster.
        $instructors = Instructor::accreditedRosterFor(auth()->id());

        // Ids the minimum-roster rule allows removing, resolved here so the view
        // stays free of the branching.
        $deletableIds = $instructors
            ->filter(fn ($instructor) => $this->isDeletable($instructor, $instructors))
            ->pluck('id')
            ->all();

        $credentialTypes = RegistrationController::CREDENTIAL_TYPES;

        return view('applicant.instructor_list', compact('instructors', 'deletableIds', 'credentialTypes'));
    }

    /**
     * Add a new instructor to the roster from the FATPRO portal.
     *
     * Allowed at any time, including while an accreditation or renewal is still
     * being evaluated: the instructor is filed against the application currently
     * under evaluation and follows the same review path as one submitted with a
     * new or renewal application.
     */
    public function store(Request $request)
    {
        $userId = auth()->id();
        $credentialTypes = RegistrationController::CREDENTIAL_TYPES;

        $rules = [
            'first_name'        => ['required', 'string', 'max:255'],
            'middle_name'       => ['nullable', 'string', 'max:255'],
            'last_name'         => ['required', 'string', 'max:255'],
            'ins_sex'           => ['required', 'in:Male,Female'],
            // 15 MB per file, the same ceiling the new and renewal applications
            // use for instructor uploads. PDF is enforced twice: by extension and
            // by the file's real MIME type.
            'service_agreement' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:15360'],
            'cv'                => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:15360'],
        ];

        foreach ($credentialTypes as $type) {
            $rules["credentials.{$type}.number"]        = ['required', 'string', 'max:255'];
            $rules["credentials.{$type}.issued_date"]   = ['required', 'date'];
            $rules["credentials.{$type}.validity_date"] = ['required', 'date'];
            $rules["credentials.{$type}.pdf"]           = ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:15360'];
        }

        $validated = $request->validate($rules);

        $application = Application::with('accreditationType')
            ->where('user_id', $userId)
            ->latest()
            ->first();

        if (!$application) {
            return redirect()->route('applicant.instructors.index')
                ->with('error', 'You have no application on record yet, so an instructor cannot be added.');
        }

        [$baseCredPath, $timestamp] = $this->credentialStoragePath($application);
        $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $validated['first_name']));
        $instLast  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $validated['last_name']));

        $instructor = DB::transaction(function () use ($request, $validated, $application, $userId, $credentialTypes, $baseCredPath, $timestamp, $instFirst, $instLast) {
            $saPath = $request->file('service_agreement')
                ->storeAs($baseCredPath, "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf", 'local');
            $cvPath = $request->file('cv')
                ->storeAs($baseCredPath, "cv_{$instFirst}_{$instLast}_{$timestamp}.pdf", 'local');

            $instructor = Instructor::create([
                'user_id'                => $userId,
                'application_id'         => $application->id,
                'first_name'             => $validated['first_name'],
                'middle_name'            => $validated['middle_name'] ?? null,
                'last_name'              => $validated['last_name'],
                'ins_sex'                => $validated['ins_sex'],
                'service_agreement_path' => $saPath,
                'cv_path'                => $cvPath,
                'status'                 => 'pending',
                'update_request_status'  => 'pending_review',
                'update_request_fields'  => array_merge(['service_agreement', 'cv'], $credentialTypes),
            ]);

            foreach ($credentialTypes as $type) {
                $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $type));
                $credPath = $request->file("credentials.{$type}.pdf")->storeAs(
                    $baseCredPath,
                    "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}.pdf",
                    'local'
                );

                InstructorCredential::create([
                    'instructor_id' => $instructor->id,
                    'type'          => $type,
                    'number'        => $validated['credentials'][$type]['number'],
                    'issued_date'   => $validated['credentials'][$type]['issued_date'],
                    'validity_date' => $validated['credentials'][$type]['validity_date'],
                    'pdf_path'      => $credPath,
                    'status'        => 'pending',
                ]);
            }

            return $instructor;
        });

        // Same notification the instructor update flow sends: the assigned
        // evaluator is the one who reviews the submitted files.
        $this->notifyAssignedEvaluator(
            $application,
            fn () => new \App\Mail\AdminDocumentsUploadedEmail(
                $application,
                count($credentialTypes) + 2,
                true,
                $instructor
            ),
            'New instructor notification'
        );

        return redirect()->route('applicant.instructors.index')
            ->with('success', 'Instructor added and submitted for admin review.');
    }

    /**
     * Remove an instructor, their credentials and every uploaded file.
     *
     * The files are deleted outright rather than left orphaned on disk, since an
     * abandoned roster entry keeps its PDFs on the host forever otherwise.
     */
    public function destroy(Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);

        $roster = Instructor::accreditedRosterFor(auth()->id());

        if (!$this->isDeletable($instructor, $roster)) {
            return redirect()->route('applicant.instructors.index')
                ->with('error', 'You must keep at least one instructor on your roster, so this instructor cannot be removed.');
        }

        $instructor->load('credentials');

        $application = Application::with('accreditationType')
            ->where('user_id', auth()->id())
            ->latest()
            ->first();

        $removedName = trim("{$instructor->first_name} {$instructor->middle_name} {$instructor->last_name}");
        $wasApproved = $instructor->status === 'approved';

        $filePaths = $instructor->credentials->pluck('pdf_path')
            ->push($instructor->service_agreement_path)
            ->push($instructor->cv_path)
            ->filter()
            ->all();

        DB::transaction(function () use ($instructor) {
            $instructor->credentials()->delete();
            $instructor->delete();
        });

        // Files go after the rows commit: a missing file must not roll back or
        // fail a removal that has already been agreed to.
        foreach ($filePaths as $path) {
            try {
                if (Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            } catch (\Exception $e) {
                Log::warning("Instructor file cleanup failed for {$path}: " . $e->getMessage());
            }
        }

        if ($application) {
            $this->notifyAssignedEvaluator(
                $application,
                fn () => new \App\Mail\InstructorRemovedEmail($application, $removedName, $wasApproved),
                'Instructor removal notification'
            );
        }

        return redirect()->route('applicant.instructors.index')
            ->with('success', "{$removedName} has been removed from your instructor roster.");
    }

    /**
     * Whether the minimum-roster rule allows removing this instructor.
     *
     * A FATPro must always keep one instructor, and only an approved one counts
     * toward that minimum — a pending submission is not yet a roster member.
     * When nothing is approved yet (a first accreditation still under review),
     * the rule falls back to "keep one row", otherwise an instructor added by
     * mistake could never be taken back out.
     */
    private function isDeletable(Instructor $instructor, $roster): bool
    {
        $approvedCount = $roster->where('status', 'approved')->count();

        if ($approvedCount === 0) {
            return $roster->count() > 1;
        }

        return !($instructor->status === 'approved' && $approvedCount === 1);
    }

    /**
     * Storage folder for this FATPro's instructor files, matching the layout
     * registration and the instructor update flow already write to.
     *
     * @return array{0: string, 1: int}
     */
    private function credentialStoragePath(Application $application): array
    {
        $accreditationName = $application->accreditationType->name ?? 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', auth()->user()->name)) ?: 'unknown';

        return ["public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials", time()];
    }

    /**
     * Mail the evaluator assigned to this application, logging (never throwing)
     * when there is no evaluator or the send fails.
     */
    private function notifyAssignedEvaluator(Application $application, callable $mailableFactory, string $context): void
    {
        try {
            $application->loadMissing(['assignedEvaluator', 'user', 'accreditationType']);
            $assignedEvaluatorEmail = $application->assignedEvaluator?->email;

            if ($assignedEvaluatorEmail) {
                Mail::to($assignedEvaluatorEmail)->send($mailableFactory());
            } else {
                Log::warning("{$context} skipped: application {$application->tracking_number} has no assigned evaluator.");
            }
        } catch (\Exception $mailEx) {
            Log::warning("{$context} email failed: " . $mailEx->getMessage());
        }
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
            'service_agreement' => 'nullable|file|mimes:pdf|max:15360',
            'cv' => 'nullable|file|mimes:pdf|max:15360',
            'credentials.*.number' => 'nullable|string|max:255',
            'credentials.*.issued_date' => 'nullable|date',
            'credentials.*.validity_date' => 'nullable|date',
            'credentials.*.pdf_file' => 'nullable|file|mimes:pdf|max:15360',
        ];

        $request->validate($rules);

        // Guard 2: Require at least one uploaded file or modified credential field
        $hasAnyFile = $request->hasFile('service_agreement') || $request->hasFile('cv');
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

        // 1b. Handle CV / Resume Update
        if ($request->hasFile('cv')) {
            if ($instructor->cv_path && Storage::disk('local')->exists($instructor->cv_path)) {
                Storage::disk('local')->delete($instructor->cv_path);
            }
            $filename = "cv_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $path = $request->file('cv')->storeAs($baseCredPath, $filename, 'local');

            // The CV has its own status pair. Resetting the shared status/remarks
            // here re-opened the *service agreement* instead, leaving a replaced CV
            // still marked rejected.
            $instructor->update([
                'cv_path'    => $path,
                'cv_status'  => 'pending',
                'cv_remarks' => null,
            ]);

            if (!in_array('cv', $updatedFields)) {
                $updatedFields[] = 'cv';
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

                $isCredUpdated = $hasFile || $numberChanged || $issuedChanged || $validChanged;

                // Skip unchanged credentials so their status remains intact (e.g. approved)
                if (!$isCredUpdated) {
                    continue;
                }

                $data = [
                    'number'         => $credData['number'] ?? $credential->number,
                    'issued_date'    => $credData['issued_date'] ?? $credential->issued_date,
                    'validity_date'  => $credData['validity_date'] ?? $credential->validity_date,
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

    /**
     * Serve this applicant's own instructor CV / resume PDF.
     */
    public function serveCv(Instructor $instructor)
    {
        abort_if($instructor->user_id !== auth()->id(), 403);
        abort_if(!$instructor->cv_path || !Storage::disk('local')->exists($instructor->cv_path), 404);

        return response()->file(Storage::disk('local')->path($instructor->cv_path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($instructor->cv_path) . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
