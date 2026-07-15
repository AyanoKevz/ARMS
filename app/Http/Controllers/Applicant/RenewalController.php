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
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\AdminApplicationSubmittedEmail;
use App\Mail\AdminDocumentsUploadedEmail;
use App\Services\PctService;

class RenewalController extends Controller
{
    // Credential types with their required fields
    private const CREDENTIAL_TYPES = ['EMS', 'TM1', 'NTTC', 'BOSH'];

    // Required document field codes
    private const REQUIRED_DOCUMENT_FIELDS = [
        'LEGAL_01', 'LEGAL_02', 'LEGAL_03', 'LEGAL_04', 'LEGAL_05', 'LEGAL_06',
        'TRAIN_01', 'TRAIN_03',
        'PREM_01', 'PREM_02', 'PREM_03', 'PREM_04', 'PREM_05', 'PREM_06', 'PREM_07',
        'IP_01', 'IP_02',
        'QA_02', 'QA_03', 'QA_04', 'QA_05', 'QA_06', 'QA_07', 'QA_08', 'QA_09',
        'EQUIP_01',
        'IP_DPO_NAME', 'PREM_DATE'
    ];

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
            'instructors.credentials.instructor',
            'userDocuments.documentField.documentType',
        ]);

        // Get latest accreditation (may be active, expired, or revoked)
        $accreditation = $user->accreditations()
            ->orderBy('id', 'desc')
            ->first();

        // Check if user already has an unfinished renewal/reinstatement application.
        // An application is only "finished" when it is Approved (accredited) or deleted (not passed).
        // Every other active status must block a new submission.
        $pendingRenewal = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->whereIn('name', [
                        'Submitted',
                        'Under Evaluation',
                        'For Update',
                        'Scheduled for Interview',
                        'Awaiting Payment',
                        'Payment Verification',
                    ]);
                });
            })
            ->with([
                'documents.documentField.documentType',
                'documents.userDocument',
                'instructors.credentials.instructor',
                'payment',
                'interview',
            ])
            ->first();

        // Check if user has a pending instructor update request
        $pendingInstructorUpdate = $user->instructors()
            ->whereIn('update_request_status', ['admin_requested', 'pending_review'])
            ->first();

        // Get existing user documents grouped by document field code
        $existingDocs = collect();
        if ($accreditation && $accreditation->application) {
            $accreditation->application->load(['documents.userDocument', 'documents.documentField']);
            $existingDocs = $accreditation->application->documents->mapWithKeys(function ($appDoc) {
                if ($appDoc->userDocument && $appDoc->documentField) {
                    return [$appDoc->documentField->code => $appDoc->userDocument];
                }
                return [];
            })->filter();
        } else {
            $existingDocs = $user->userDocuments->keyBy(function ($doc) {
                return $doc->documentField->code ?? null;
            });
        }

        // Get instructors for renewal form
        $instructors = collect();
        if ($accreditation && $accreditation->application && $accreditation->application->instructors()->exists()) {
            $instructors = $accreditation->application->instructors()->with('credentials')->get();
        } else {
            $instructors = $user->instructors()->with('credentials')->get();
        }

        return view('applicant.renewal', compact(
            'user',
            'accreditation',
            'pendingRenewal',
            'pendingInstructorUpdate',
            'existingDocs',
            'instructors'
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

        // ── Server-side guard: block duplicate renewal submission ──
        $alreadyActive = Application::where('user_id', $user->id)
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($q) {
                $q->whereHas('status', function ($q2) {
                    $q2->whereIn('name', [
                        'Submitted',
                        'Under Evaluation',
                        'For Update',
                        'Scheduled for Interview',
                        'Awaiting Payment',
                        'Payment Verification',
                    ]);
                });
            })
            ->exists();

        if ($alreadyActive) {
            return redirect()->route('applicant.renewal.index')
                ->with('error', 'You already have an active renewal or reinstatement application that is still being processed. Please wait for it to be completed before submitting a new one.');
        }

        // ── Server-side guard: block if pending instructor update ──
        $pendingInstructorUpdate = $user->instructors()
            ->whereIn('update_request_status', ['admin_requested', 'pending_review'])
            ->exists();

        if ($pendingInstructorUpdate) {
            return redirect()->route('applicant.renewal.index')
                ->with('error', 'You cannot submit a renewal application while you have a pending instructor update request.');
        }

        // ── Server-side guard: block invalid application type based on accreditation status ──
        $accreditation = $user->accreditations()->orderBy('created_at', 'desc')->first();
        if (!$accreditation) {
            return redirect()->route('applicant.dashboard')
                ->with('error', 'You must have an existing accreditation to apply for renewal or reinstatement.');
        }

        if ($accreditation->status !== 'revoked' && $request->input('application_type') === 'reinstatement') {
            return back()->with('error', 'Reinstatement is only allowed if your current accreditation is revoked. Please submit a renewal application instead.')->withInput();
        }

        if ($accreditation->status === 'revoked' && $request->input('application_type') === 'renewal') {
            return back()->with('error', 'Renewal is not allowed since your current accreditation is revoked. Please submit a reinstatement application instead.')->withInput();
        }

        // ── Dynamic Organization validation rules based on profile ──
        $orgRules = [];
        if ($user->profile_type === 'Organization') {
            $orgRules = [
                'org_name'     => ['required', 'string', 'max:255'],
                'org_address'  => ['required', 'string', 'max:500'],
                'head_name'    => ['required', 'string', 'max:255'],
                'designation'  => ['nullable', 'string', 'max:255'],
                'telephone'    => ['nullable', 'regex:/^\d{10}$/'],
                'fax'          => ['nullable', 'regex:/^\d{10}$/'],
                'org_email'    => ['required', 'email', 'max:255'],
                'rep_full_name'      => ['required', 'string', 'max:255'],
                'rep_position'       => ['required', 'string', 'max:255'],
                'rep_contact_number' => ['required', 'string', 'max:13', 'regex:/^(09|\+639)\d{9}$/'],
                'rep_email'          => ['required', 'email', 'max:255'],
            ];
        } else {
            $orgRules = [
                'org_name'     => ['nullable', 'string', 'max:255'],
                'org_address'  => ['nullable', 'string', 'max:500'],
                'head_name'    => ['nullable', 'string', 'max:255'],
                'designation'  => ['nullable', 'string', 'max:255'],
                'telephone'    => ['nullable', 'regex:/^\d{10}$/'],
                'fax'          => ['nullable', 'regex:/^\d{10}$/'],
                'org_email'    => ['nullable', 'email', 'max:255'],
                'rep_full_name'      => ['nullable', 'string', 'max:255'],
                'rep_position'       => ['nullable', 'string', 'max:255'],
                'rep_contact_number' => ['nullable', 'string', 'max:13', 'regex:/^(09|\+639)\d{9}$/'],
                'rep_email'          => ['nullable', 'email', 'max:255'],
            ];
        }

        // ── Build document field validation rules ──────────────────
        $documentFields = DocumentField::all()->keyBy('code');
        $documentRules = [];
        foreach ($documentFields as $code => $field) {
            $key = "documents.{$code}";
            $existing = UserDocument::where('user_id', $user->id)
                ->where('document_field_id', $field->id)
                ->first();

            $isRequired = in_array($code, self::REQUIRED_DOCUMENT_FIELDS);

            if ($field->input_type === 'file') {
                if ($isRequired && (!$existing || !$existing->file_path)) {
                    $documentRules[$key] = ['required', 'file', 'mimes:pdf', 'max:10240'];
                } else {
                    $documentRules[$key] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
                }
            } elseif ($field->input_type === 'date') {
                if ($isRequired && (!$existing || !$existing->value)) {
                    $documentRules[$key] = ['required', 'date'];
                } else {
                    $documentRules[$key] = ['nullable', 'date'];
                }
            } elseif ($field->input_type === 'text') {
                if ($isRequired && (!$existing || !$existing->value)) {
                    $documentRules[$key] = ['required', 'string', 'max:500'];
                } else {
                    $documentRules[$key] = ['nullable', 'string', 'max:500'];
                }
            }
        }

        // ── Build instructor validation rules ──────────────────────
        $instructorRules = [];
        $instructors = $request->input('instructors', []);

        if (is_array($instructors) && count($instructors) > 0) {
            foreach ($instructors as $i => $inst) {
                $instructorId = $inst['id'] ?? null;
                $existingInst = $instructorId ? Instructor::where('id', $instructorId)->where('user_id', $user->id)->first() : null;

                $instructorRules["instructors.{$i}.first_name"] = ['required', 'string', 'max:255'];
                $instructorRules["instructors.{$i}.middle_name"] = ['nullable', 'string', 'max:255'];
                $instructorRules["instructors.{$i}.last_name"]  = ['required', 'string', 'max:255'];

                // Service agreement is strictly required
                $instructorRules["instructors.{$i}.service_agreement"] = ['required', 'file', 'mimes:pdf', 'max:10240'];

                foreach (self::CREDENTIAL_TYPES as $type) {
                    $base = "instructors.{$i}.credentials.{$type}";
                    $existingCred = $existingInst ? $existingInst->credentials->firstWhere('type', $type) : null;

                    $instructorRules["{$base}.number"]         = ['required', 'string', 'max:255'];
                    if ($type !== 'BOSH') {
                        $instructorRules["{$base}.issued_date"]    = ['required', 'date'];
                    }
                    $instructorRules["{$base}.validity_date"]  = ['required', 'date'];
                    if ($type === 'BOSH') {
                        $instructorRules["{$base}.training_dates"] = ['required', 'string', 'max:500'];
                    }

                    // Certificate PDF is strictly required
                    $instructorRules["{$base}.pdf"] = ['required', 'file', 'mimes:pdf', 'max:10240'];
                }
            }
        } else {
            // Require at least one instructor
            $request->merge(['instructors' => []]);
            $instructorRules['instructors'] = ['required', 'array', 'min:1'];
        }

        // ── Full validation ────────────────────────────────────────
        $request->validate(array_merge([
            'application_type' => ['required', 'in:renewal,reinstatement'],
            'documents'   => ['nullable', 'array'],
            'instructors' => ['nullable', 'array'],
        ], $orgRules, $documentRules, $instructorRules), [
            'telephone.regex'          => 'The telephone number must be a valid 10-digit number (e.g. 0281234567).',
            'fax.regex'                => 'The facsimile number must be a valid 10-digit number (e.g. 0281234567).',
            'rep_contact_number.regex' => 'The representative contact number must be a PH mobile number (e.g. 09171234567 or +639171234567).',
        ]);

        try {
            $application = null;
            DB::transaction(function () use ($request, $user, $documentFields, &$application) {
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
                    $isRequired = in_array($code, self::REQUIRED_DOCUMENT_FIELDS);

                    if ($field->input_type === 'file') {
                        if ($request->hasFile("documents.{$code}")) {
                            $newFile = $request->file("documents.{$code}");
                            $filePath = "{$baseDocPath}/{$code}_{$timestamp}.pdf";
                            $newFile->storeAs($baseDocPath, "{$code}_{$timestamp}.pdf", 'local');
                        } elseif ($isRequired) {
                            // Keep existing file path from the latest document (only if required)
                            $existingDoc = UserDocument::where('user_id', $user->id)
                                ->where('document_field_id', $field->id)
                                ->orderBy('id', 'desc')
                                ->first();
                            $filePath = $existingDoc?->file_path;
                        }
                    } else {
                        $val = $request->input("documents.{$code}");
                        if (!is_null($val) && $val !== '') {
                            $textValue = $val;
                        } elseif ($isRequired) {
                            $existingDoc = UserDocument::where('user_id', $user->id)
                                ->where('document_field_id', $field->id)
                                ->orderBy('id', 'desc')
                                ->first();
                            $textValue = $existingDoc?->value;
                        }
                    }

                    // Only create application_document if user has data for this field
                    if ($filePath || $textValue) {
                        $userDoc = UserDocument::create([
                            'user_id'           => $user->id,
                            'document_field_id' => $field->id,
                            'file_path'         => $filePath,
                            'value'             => $textValue,
                        ]);

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

                // Process instructors input (always create new ones for isolation)
                foreach ($instructorsInput as $i => $instData) {
                    $instructorId = $instData['id'] ?? null;
                    $existingInst = $instructorId ? Instructor::where('id', $instructorId)->where('user_id', $user->id)->first() : null;

                    $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instData['first_name'] ?? ''));
                    $instLast = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instData['last_name'] ?? ''));

                    // Handle service agreement file
                    $saPermanent = $existingInst ? $existingInst->service_agreement_path : null;
                    if ($request->hasFile("instructors.{$i}.service_agreement")) {
                        $saFile = $request->file("instructors.{$i}.service_agreement");
                        $saPermanent = "{$baseCredPath}/sa_{$instFirst}_{$instLast}_{$timestamp}.pdf";
                        $saFile->storeAs($baseCredPath, "sa_{$instFirst}_{$instLast}_{$timestamp}.pdf", 'local');
                    }

                    $instructor = Instructor::create([
                        'user_id'                => $user->id,
                        'application_id'         => $application->id,
                        'first_name'             => $instData['first_name'] ?? '',
                        'middle_name'            => $instData['middle_name'] ?? null,
                        'last_name'              => $instData['last_name'] ?? '',
                        'service_agreement_path' => $saPermanent,
                        'status'                 => 'pending',
                        'remarks'                => null,
                    ]);

                    // Credentials
                    foreach (self::CREDENTIAL_TYPES as $type) {
                        $credData = $instData['credentials'][$type] ?? [];

                        $existingCred = $existingInst ? $existingInst->credentials->firstWhere('type', $type) : null;
                        $credPermanent = $existingCred ? $existingCred->pdf_path : null;

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
                                'status'         => 'pending',
                                'remarks'        => null,
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

            // Notify Admin Evaluators
            try {
                $evaluators = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                    $q->where('name', 'Evaluator');
                })->get();

                if ($evaluators->isNotEmpty() && $application) {
                    $application->load(['user', 'accreditationType']);
                    
                    // Send Email
                    $evaluatorEmails = $evaluators->pluck('email');
                    Mail::to($evaluatorEmails)->send(new AdminApplicationSubmittedEmail($application));

                    // Send database/in-app portal notifications
                    \Illuminate\Support\Facades\Notification::send($evaluators, new \App\Notifications\NewApplicationSubmittedNotification($application));
                }
            } catch (\Exception $mailEx) {
                Log::warning('Admin renewal application submission notification failed: ' . $mailEx->getMessage());
            }

            // Bust listing caches — new application submitted
            CacheService::bustApplicationCaches();

            return redirect()
                ->route('applicant.renewal.index')
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
        return redirect()->route('applicant.renewal.index');
    }

    /**
     * Process the re-upload of rejected documents for renewal/reinstatement.
     * Same overwrite logic as TrackingController@resubmitAll but for authenticated users.
     */
    public function submitReupload(Request $request)
    {
        $user = Auth::user();
        $application = Application::where('id', $request->input('application_id'))
            ->where('user_id', $user->id)
            ->with([
                'accreditationType',
                'documents.documentField',
                'documents.userDocument',
                'instructors.credentials.instructor',
                'latestStatus.status',
            ])
            ->first();

        if (!$application) {
            return back()->with('resubmit_error', 'Application not found.');
        }

        $statusName = $application->latestStatus?->status?->name;
        if ($statusName !== 'For Update') {
            return back()->with('resubmit_error', 'Invalid action. You can only resubmit documents if your application status is "For Update".');
        }

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

        // Strict backend validation: Make sure all rejected/returned items are provided in the upload
        $rejectedDocs = $application->documents->filter(fn($d) => in_array($d->status, ['rejected','returned']));
        $rejectedInstructors = $application->instructors->filter(fn($i) => in_array($i->status, ['rejected','returned']));
        $rejectedCredentials = collect();
        foreach ($application->instructors as $inst) {
            foreach ($inst->credentials as $cred) {
                if (in_array($cred->status, ['rejected','returned'])) {
                    $rejectedCredentials->push($cred);
                }
            }
        }

        $errors = [];
        foreach ($rejectedDocs as $rdoc) {
            if ($rdoc->documentField?->input_type === 'file') {
                if (!$request->hasFile("files.{$rdoc->id}")) {
                    $errors["files.{$rdoc->id}"] = "The replacement file for " . ($rdoc->documentField->name ?? 'Document') . " is required.";
                }
            } else {
                if (!$request->filled("values.{$rdoc->id}")) {
                    $errors["values.{$rdoc->id}"] = "The updated value for " . ($rdoc->documentField->name ?? 'Document') . " is required.";
                }
            }
        }
        foreach ($rejectedInstructors as $rInst) {
            if (!$request->hasFile("instructor_files.{$rInst->id}")) {
                $errors["instructor_files.{$rInst->id}"] = "The replacement Service Agreement for {$rInst->first_name} {$rInst->last_name} is required.";
            }
        }
        foreach ($rejectedCredentials as $rCred) {
            if (!$request->hasFile("credential_files.{$rCred->id}")) {
                $errors["credential_files.{$rCred->id}"] = "The replacement {$rCred->type} Certificate for {$rCred->instructor->first_name} {$rCred->instructor->last_name} is required.";
            }
        }

        if (!empty($errors)) {
            $missing = count($errors);
            return back()->with('resubmit_error',
                "Please upload replacement files for all rejected items. {$missing} item" . ($missing > 1 ? 's are' : ' is') . " still missing a replacement file."
            );
        }

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

            $userDoc = $appDoc->userDocument;

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

            $userDoc = $appDoc->userDocument;

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
            $instructor = $application->instructors->firstWhere('id', $instructorId);
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
            $instModel = null;
            foreach ($application->instructors as $inst) {
                $cred = $inst->credentials->firstWhere('id', $credentialId);
                if ($cred) {
                    $credential = $cred;
                    $instModel = $inst;
                    break;
                }
            }
            if (!$credential || !in_array($credential->status, ['rejected', 'returned'])) continue;

            $typeClean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $credential->type));
            $timestamp = time();
            $instFirst = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instModel->first_name));
            $instLast  = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $instModel->last_name));
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

        // Progress application status back to "Under Evaluation"
        $underEvaluationStatus = ApplicationStatus::where('name', 'Under Evaluation')->first();
        if ($underEvaluationStatus) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $underEvaluationStatus->id,
                'updated_by'     => null,
                'remarks'        => 'Documents resubmitted by applicant. Application is back under evaluation.',
            ]);
        }

        // ── PCT: Resume the paused step (applicant has resubmitted)
        app(PctService::class)->resumeCurrentStep($application);

        // Bust caches — status changed back to Under Evaluation
        CacheService::bustApplicationCaches();

        // Notify Admin Evaluators about resubmitted documents
        try {
            $evaluatorEmails = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                $q->where('name', 'Evaluator');
            })->pluck('email');

            if ($evaluatorEmails->isNotEmpty()) {
                $application->load(['user', 'accreditationType']);
                Mail::to($evaluatorEmails)->send(new AdminDocumentsUploadedEmail($application, $resubmitted));
            }
        } catch (\Exception $mailEx) {
            Log::warning('Admin resubmitted documents notification failed: ' . $mailEx->getMessage());
        }

        return redirect()
            ->route('applicant.renewal.index')
            ->with('success', "{$resubmitted} document" . ($resubmitted > 1 ? 's' : '') . " successfully resubmitted. Your application is now back under review.");
    }

    /**
     * Handle portal submission of payment requirements.
     */
    public function submitPaymentPortal(Request $request)
    {
        $request->validate([
            'application_id'   => ['required', 'exists:applications,id'],
            'proof_of_payment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $user = Auth::user();
        $application = Application::where('id', $request->input('application_id'))
            ->where('user_id', $user->id)
            ->firstOrFail();

        $payment = $application->payment ?? new \App\Models\ApplicationPayment(['application_id' => $application->id]);

        $accreditationType = $application->accreditationType;
        $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
        $fatProName = $user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';
        
        $proofPaymentPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/proof_of_payments";

        $changed = false;

        // Process proof_of_payment
        if ($request->hasFile('proof_of_payment')) {
            if ($payment->proof_of_payment && Storage::disk('local')->exists($payment->proof_of_payment)) {
                Storage::disk('local')->delete($payment->proof_of_payment);
            }
            $ext = $request->file('proof_of_payment')->getClientOriginalExtension();
            $filename = "proof_of_payment_" . time() . ".{$ext}";
            $path = $request->file('proof_of_payment')->storeAs($proofPaymentPath, $filename, 'local');
            $payment->proof_of_payment = $path;
            $payment->proof_of_payment_status = 'pending';
            $payment->proof_of_payment_remarks = null;
            $changed = true;
        }

        if ($changed) {
            $payment->save();

            // ── PCT: Resume the paused Step 7 (payment re-uploaded)
            app(PctService::class)->resumeCurrentStep($application);

            // ── Transition status to 'Payment Verification'
            $paymentVerificationStatus = ApplicationStatus::where('name', 'Payment Verification')->first();
            if ($paymentVerificationStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $paymentVerificationStatus->id,
                    'updated_by'     => null,
                    'remarks'        => 'Proof of payment submitted by applicant. Awaiting verifier review.',
                ]);
            }

            // Notify Verifiers via Notification system (Email + DB)
            try {
                $verifiers = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                    $q->where('name', 'Verifier');
                })->get();

                if ($verifiers->isNotEmpty()) {
                    \Illuminate\Support\Facades\Notification::send($verifiers, new \App\Notifications\PaymentSubmittedNotification($application));
                }
            } catch (\Exception $e) {
                Log::warning('Verifier notification email failed: ' . $e->getMessage());
            }

            // Bust caches — status changed to Payment Verification
            CacheService::bustApplicationCaches();

            return back()->with('success', 'Payment details uploaded successfully. Your submission is now pending verifier evaluation.');
        }

        return back()->with('error', 'No new payment files were selected.');
    }

    /**
     * Stream a document PDF to the browser (auth-guarded).
     */
    public function serveDocument(ApplicationDocument $document)
    {
        $userDoc = $document->userDocument;
        abort_if(!$userDoc || $userDoc->user_id !== auth()->id(), 403);
        abort_if(!$userDoc->file_path || !Storage::disk('local')->exists($userDoc->file_path), 404);

        $fullPath = Storage::disk('local')->path($userDoc->file_path);
        $filename = basename($userDoc->file_path);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Stream a user document PDF to the browser (auth-guarded).
     */
    public function serveUserDocument(UserDocument $userDocument)
    {
        abort_if($userDocument->user_id !== auth()->id(), 403);
        abort_if(!$userDocument->file_path || !Storage::disk('local')->exists($userDocument->file_path), 404);

        $fullPath = Storage::disk('local')->path($userDocument->file_path);
        $filename = basename($userDocument->file_path);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
