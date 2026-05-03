<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Accreditation;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\DocumentField;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RenewalController extends Controller
{
    // Credential types with their required fields
    private const CREDENTIAL_TYPES = ['EMS', 'TM1', 'NTTC', 'BOSH'];

    /**
     * Show the renewal / reinstatement form.
     * Pre-fills existing accreditation details, profile, instructors, and documents.
     */
    public function index()
    {
        $user = Auth::user();
        $user->load([
            'accreditations.accreditationType',
            'organizationProfile.authorizedRepresentatives',
            'individualProfile',
            'instructors.credentials',
            'userDocuments.documentField.documentType',
        ]);

        // Get latest accreditation (may be active, expired, or revoked)
        $accreditation = $user->accreditations()
            ->orderBy('created_at', 'desc')
            ->first();

        // Check if user already has a pending renewal/reinstatement application
        $pendingRenewal = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->whereIn('name', ['Submitted', 'Under Evaluation', 'For Update']);
                });
            })
            ->first();

        // Get existing user documents grouped by document field code
        $existingDocs = $user->userDocuments->keyBy(function ($doc) {
            return $doc->documentField->code ?? null;
        });

        return view('applicant.renewal', compact(
            'user',
            'accreditation',
            'pendingRenewal',
            'existingDocs'
        ));
    }

    /**
     * Process the renewal / reinstatement submission.
     * Creates a new application, overwrites old files, updates profile info.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $user->load(['organizationProfile.authorizedRepresentatives', 'instructors.credentials']);

        // ── Build document field validation rules ──────────────────
        $documentFields = DocumentField::all()->keyBy('code');
        $documentRules = [];
        foreach ($documentFields as $code => $field) {
            $key = "documents.{$code}";
            if ($field->input_type === 'file') {
                $documentRules[$key] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
            } elseif ($field->input_type === 'date') {
                $documentRules[$key] = ['nullable', 'date'];
            } elseif ($field->input_type === 'text') {
                $documentRules[$key] = ['nullable', 'string', 'max:500'];
            }
        }

        // ── Build instructor validation rules ──────────────────────
        $instructorRules = [];
        $instructors = $request->input('instructors', []);

        if (is_array($instructors) && count($instructors) > 0) {
            foreach ($instructors as $i => $inst) {
                $instructorRules["instructors.{$i}.first_name"] = ['required', 'string', 'max:255'];
                $instructorRules["instructors.{$i}.middle_name"] = ['nullable', 'string', 'max:255'];
                $instructorRules["instructors.{$i}.last_name"]  = ['required', 'string', 'max:255'];
                $instructorRules["instructors.{$i}.service_agreement"] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];

                foreach (self::CREDENTIAL_TYPES as $type) {
                    $base = "instructors.{$i}.credentials.{$type}";
                    $instructorRules["{$base}.number"]         = ['nullable', 'string', 'max:255'];
                    $instructorRules["{$base}.issued_date"]    = ['nullable', 'date'];
                    $instructorRules["{$base}.validity_date"]  = ['nullable', 'date'];
                    $instructorRules["{$base}.pdf"]            = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
                    if ($type === 'BOSH') {
                        $instructorRules["{$base}.training_dates"] = ['nullable', 'string', 'max:500'];
                    }
                }
            }
        }

        // ── Full validation ────────────────────────────────────────
        $request->validate(array_merge([
            'application_type' => ['required', 'in:renewal,reinstatement'],

            // Organization fields (optional for renewal — only update if provided)
            'org_name'     => ['nullable', 'string', 'max:255'],
            'org_address'  => ['nullable', 'string', 'max:500'],
            'head_name'    => ['nullable', 'string', 'max:255'],
            'designation'  => ['nullable', 'string', 'max:255'],
            'telephone'    => ['nullable', 'string', 'max:50'],
            'fax'          => ['nullable', 'string', 'max:50'],
            'org_email'    => ['nullable', 'email', 'max:255'],

            // Representative fields
            'rep_full_name'      => ['nullable', 'string', 'max:255'],
            'rep_position'       => ['nullable', 'string', 'max:255'],
            'rep_contact_number' => ['nullable', 'string', 'max:11'],
            'rep_email'          => ['nullable', 'email', 'max:255'],

            'documents'   => ['nullable', 'array'],
            'instructors' => ['nullable', 'array'],
        ], $documentRules, $instructorRules));

        try {
            DB::transaction(function () use ($request, $user, $documentFields) {
                $timestamp = time();
                $isOrg = $user->profile_type === 'Organization';

                // ── Determine storage paths ───────────────────────
                $accreditationType = $user->accreditations()->latest()->first()?->accreditationType;
                $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
                $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));

                $fatProName = $user->name;
                $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';

                $baseDocPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/documents";
                $baseCredPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials";

                // ── 1. Update Organization Profile ────────────────
                if ($isOrg && $user->organizationProfile) {
                    $orgFields = array_filter([
                        'name'        => $request->input('org_name'),
                        'address'     => $request->input('org_address'),
                        'head_name'   => $request->input('head_name'),
                        'designation' => $request->input('designation'),
                        'telephone'   => $request->input('telephone'),
                        'fax'         => $request->input('fax'),
                        'email'       => $request->input('org_email'),
                    ], fn($v) => !is_null($v) && $v !== '');

                    if (!empty($orgFields)) {
                        $user->organizationProfile->update($orgFields);
                    }

                    // Update authorized representative
                    $rep = $user->organizationProfile->authorizedRepresentatives()->first();
                    $repFields = array_filter([
                        'full_name'      => $request->input('rep_full_name'),
                        'position'       => $request->input('rep_position'),
                        'contact_number' => $request->input('rep_contact_number'),
                        'email'          => $request->input('rep_email'),
                    ], fn($v) => !is_null($v) && $v !== '');

                    if (!empty($repFields) && $rep) {
                        $rep->update($repFields);
                    }
                }

                // ── 2. Generate tracking number ───────────────────
                $year     = now()->format('Y');
                $sequence = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $trackingNumber = "ARMS{$year}-{$sequence}";

                // ── 3. Create Application ─────────────────────────
                $application = Application::create([
                    'user_id'               => $user->id,
                    'accreditation_type_id' => $user->accreditations()->latest()->first()?->accreditation_type_id ?? 7,
                    'application_type'      => $request->input('application_type'),
                    'tracking_number'       => $trackingNumber,
                    'submitted_at'          => now(),
                ]);

                // ── 4. Persist documents (overwrite old files) ────
                $allFields = DocumentField::all()->keyBy('code');

                foreach ($allFields as $code => $field) {
                    $filePath  = null;
                    $textValue = null;
                    $hasNew    = false;

                    if ($field->input_type === 'file') {
                        if ($request->hasFile("documents.{$code}")) {
                            $hasNew  = true;
                            $newFile = $request->file("documents.{$code}");

                            // Delete old file first
                            $existingDoc = UserDocument::where('user_id', $user->id)
                                ->where('document_field_id', $field->id)
                                ->first();

                            if ($existingDoc && $existingDoc->file_path) {
                                if (Storage::disk('local')->exists($existingDoc->file_path)) {
                                    Storage::disk('local')->delete($existingDoc->file_path);
                                }
                            }

                            $filePath = "{$baseDocPath}/{$code}_{$timestamp}.pdf";
                            $newFile->storeAs($baseDocPath, "{$code}_{$timestamp}.pdf", 'local');
                        } else {
                            // Keep existing file
                            $existingDoc = UserDocument::where('user_id', $user->id)
                                ->where('document_field_id', $field->id)
                                ->first();
                            $filePath = $existingDoc?->file_path;
                        }
                    } else {
                        $val = $request->input("documents.{$code}");
                        if (!is_null($val) && $val !== '') {
                            $textValue = $val;
                            $hasNew = true;
                        } else {
                            $existingDoc = UserDocument::where('user_id', $user->id)
                                ->where('document_field_id', $field->id)
                                ->first();
                            $textValue = $existingDoc?->value;
                        }
                    }

                    // Only create application_document if user has data for this field
                    if ($filePath || $textValue) {
                        $userDoc = UserDocument::updateOrCreate(
                            ['user_id' => $user->id, 'document_field_id' => $field->id],
                            ['file_path' => $filePath, 'value' => $textValue]
                        );

                        ApplicationDocument::create([
                            'application_id'    => $application->id,
                            'document_field_id' => $field->id,
                            'user_document_id'  => $userDoc->id,
                            'status'            => 'pending',
                        ]);
                    }
                }

                // ── 5. Handle instructors & credentials ───────────
                $instructorsInput = $request->input('instructors', []);
                $existingInstructors = $user->instructors()->with('credentials')->get();

                // Delete old instructors and their credentials (overwrite)
                foreach ($existingInstructors as $oldInst) {
                    // Delete old credential files
                    foreach ($oldInst->credentials as $cred) {
                        if ($cred->pdf_path && Storage::disk('local')->exists($cred->pdf_path)) {
                            Storage::disk('local')->delete($cred->pdf_path);
                        }
                        $cred->delete();
                    }
                    // Delete old service agreement
                    if ($oldInst->service_agreement_path && Storage::disk('local')->exists($oldInst->service_agreement_path)) {
                        Storage::disk('local')->delete($oldInst->service_agreement_path);
                    }
                    $oldInst->delete();
                }

                // Create new instructors
                foreach ($instructorsInput as $i => $instData) {
                    $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instData['first_name'] ?? ''));
                    $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instData['last_name'] ?? ''));

                    // Service agreement
                    $saPermanent = null;
                    if ($request->hasFile("instructors.{$i}.service_agreement")) {
                        $saFile = $request->file("instructors.{$i}.service_agreement");
                        $saPermanent = "{$baseCredPath}/sa_{$instFirst}_{$instLast}_{$timestamp}.pdf";
                        $saFile->storeAs($baseCredPath, "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf", 'local');
                    }

                    $instructor = Instructor::create([
                        'user_id'                => $user->id,
                        'first_name'             => $instData['first_name'] ?? '',
                        'middle_name'            => $instData['middle_name'] ?? null,
                        'last_name'              => $instData['last_name'] ?? '',
                        'service_agreement_path' => $saPermanent,
                    ]);

                    // Credentials
                    foreach (self::CREDENTIAL_TYPES as $type) {
                        $credData = $instData['credentials'][$type] ?? [];

                        $credPermanent = null;
                        if ($request->hasFile("instructors.{$i}.credentials.{$type}.pdf")) {
                            $credFile = $request->file("instructors.{$i}.credentials.{$type}.pdf");
                            $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $type));
                            $credPermanent = "{$baseCredPath}/{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}.pdf";
                            $credFile->storeAs($baseCredPath, "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}.pdf", 'local');
                        }

                        $hasData = (($credData['number'] ?? null)
                            || ($credData['issued_date'] ?? null)
                            || ($credData['validity_date'] ?? null)
                            || ($credData['training_dates'] ?? null)
                            || $credPermanent);

                        if ($hasData) {
                            InstructorCredential::create([
                                'instructor_id'  => $instructor->id,
                                'type'           => $type,
                                'number'         => $credData['number'] ?? null,
                                'issued_date'    => $credData['issued_date'] ?? null,
                                'validity_date'  => $credData['validity_date'] ?? null,
                                'training_dates' => $credData['training_dates'] ?? null,
                                'pdf_path'       => $credPermanent,
                            ]);
                        }
                    }
                }

                // ── 6. Create initial status log ──────────────────
                $submittedStatus = ApplicationStatus::where('name', 'Submitted')->first();
                if ($submittedStatus) {
                    ApplicationStatusLog::create([
                        'application_id' => $application->id,
                        'status_id'      => $submittedStatus->id,
                        'updated_by'     => null,
                        'remarks'        => ucfirst($request->input('application_type')) . ' application submitted by applicant.',
                    ]);
                }
            });

            return redirect()
                ->route('applicant.dashboard')
                ->with('success', 'Your ' . $request->input('application_type') . ' application has been submitted successfully!');

        } catch (\Throwable $e) {
            Log::error('Renewal submission failed: ' . $e->getMessage(), ['exception' => $e]);
            return back()->with('error', 'Something went wrong while submitting your application. Please try again.')->withInput();
        }
    }

    /**
     * Show the re-upload form for rejected documents (renewal/reinstatement apps only).
     * This is the portal-based re-upload, not the public tracking page.
     */
    public function reupload(Request $request)
    {
        $user = Auth::user();

        // Find the latest renewal/reinstatement application that is "For Update"
        $application = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->where('name', 'For Update');
                });
            })
            ->with([
                'documents.documentField.documentType',
                'documents.userDocument',
                'user.instructors.credentials',
                'accreditationType',
                'latestStatus.status',
            ])
            ->latest()
            ->first();

        if (!$application) {
            return redirect()->route('applicant.dashboard')->with('error', 'No pending re-upload found.');
        }

        // Get rejected items
        $rejectedDocs = $application->documents()->where('status', 'rejected')->with(['documentField.documentType', 'userDocument'])->get();
        $rejectedInstructors = $user->instructors()->where('status', 'rejected')->get();
        $rejectedCredentials = InstructorCredential::whereIn('instructor_id', $user->instructors->pluck('id'))
            ->where('status', 'rejected')
            ->with('instructor')
            ->get();

        return view('applicant.renewal_reupload', compact(
            'application',
            'rejectedDocs',
            'rejectedInstructors',
            'rejectedCredentials'
        ));
    }

    /**
     * Process the re-upload of rejected documents for renewal/reinstatement.
     * Same overwrite logic as TrackingController@resubmitAll but for authenticated users.
     */
    public function submitReupload(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'application_id'     => ['required', 'exists:applications,id'],
            'files'              => ['nullable', 'array'],
            'files.*'            => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'values'             => ['nullable', 'array'],
            'values.*'           => ['required', 'string', 'max:500'],
            'instructor_files'   => ['nullable', 'array'],
            'instructor_files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'credential_files'   => ['nullable', 'array'],
            'credential_files.*' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $application = Application::where('id', $request->input('application_id'))
            ->where('user_id', $user->id)
            ->with(['user.instructors.credentials', 'accreditationType'])
            ->firstOrFail();

        $accreditationName = $application->accreditationType ? $application->accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));

        $fatProName = $user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';

        $baseDocPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/documents";
        $baseCredPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/instructor_credentials";

        $files             = $request->file('files') ?? [];
        $values            = $request->input('values') ?? [];
        $instructorFiles   = $request->file('instructor_files') ?? [];
        $credentialFiles   = $request->file('credential_files') ?? [];
        $resubmitted       = 0;

        // Text/date values
        foreach ($values as $appDocId => $value) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (!$appDoc || !in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (!$field || $field->input_type === 'file') continue;

            $userDoc = UserDocument::where('user_id', $user->id)
                ->where('document_field_id', $field->id)
                ->first();

            if ($userDoc) {
                $userDoc->update(['value' => $value]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $user->id,
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

        // File documents
        foreach ($files as $appDocId => $file) {
            $appDoc = ApplicationDocument::with(['documentField', 'userDocument'])
                ->where('id', $appDocId)
                ->where('application_id', $application->id)
                ->first();

            if (!$appDoc || !in_array($appDoc->status, ['rejected', 'returned'])) continue;

            $field = $appDoc->documentField;
            if (!$field || $field->input_type !== 'file') continue;

            $code      = $field->code;
            $timestamp = time();
            $filename  = "{$code}_{$timestamp}.pdf";
            $finalPath = "{$baseDocPath}/{$filename}";

            $userDoc = UserDocument::where('user_id', $user->id)
                ->where('document_field_id', $field->id)
                ->first();

            if ($userDoc && $userDoc->file_path) {
                if (Storage::disk('local')->exists($userDoc->file_path)) {
                    Storage::disk('local')->delete($userDoc->file_path);
                }
            }

            $file->storeAs($baseDocPath, $filename, 'local');

            if ($userDoc) {
                $userDoc->update(['file_path' => $finalPath]);
            } else {
                $userDoc = UserDocument::create([
                    'user_id'           => $user->id,
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

        // Instructor service agreements
        foreach ($instructorFiles as $instructorId => $file) {
            $instructor = $user->instructors->firstWhere('id', $instructorId);
            if (!$instructor || !in_array($instructor->status, ['rejected', 'returned'])) continue;

            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->first_name));
            $instLast  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instructor->last_name));
            $filename  = "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $finalPath = "{$baseCredPath}/{$filename}";

            if ($instructor->service_agreement_path && Storage::disk('local')->exists($instructor->service_agreement_path)) {
                Storage::disk('local')->delete($instructor->service_agreement_path);
            }

            $file->storeAs($baseCredPath, $filename, 'local');

            $instructor->update([
                'service_agreement_path' => $finalPath,
                'status'                 => 'pending',
                'remarks'                => null,
            ]);

            $resubmitted++;
        }

        // Credential files
        foreach ($credentialFiles as $credentialId => $file) {
            $credential = null;
            foreach ($user->instructors as $inst) {
                $cred = $inst->credentials->firstWhere('id', $credentialId);
                if ($cred) {
                    $credential = $cred;
                    break;
                }
            }
            if (!$credential || !in_array($credential->status, ['rejected', 'returned'])) continue;

            $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->type));
            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->instructor->first_name));
            $instLast  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->instructor->last_name));
            $filename  = "{$typeClean}_{$instFirst}_{$instLast}_{$timestamp}.pdf";
            $finalPath = "{$baseCredPath}/{$filename}";

            if ($credential->pdf_path && Storage::disk('local')->exists($credential->pdf_path)) {
                Storage::disk('local')->delete($credential->pdf_path);
            }

            $file->storeAs($baseCredPath, $filename, 'local');

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

        return redirect()
            ->route('applicant.dashboard')
            ->with('success', "{$resubmitted} document" . ($resubmitted > 1 ? 's' : '') . " successfully resubmitted. Your application is now back under review.");
    }
}
