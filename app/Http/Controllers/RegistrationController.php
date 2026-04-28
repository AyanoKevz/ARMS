<?php

namespace App\Http\Controllers;

use App\Mail\VerifyRegistrationEmail;
use App\Mail\ApplicationSubmittedEmail;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\AuthorizedRepresentative;
use App\Models\DocumentField;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\OrganizationProfile;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    // Credential types with their required fields
    private const CREDENTIAL_TYPES = ['EMS', 'TM1', 'NTTC', 'BOSH'];

    // ─────────────────────────────────────────────────────────────
    //  POST /register  — Save pending data, send verification email
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        // ── Guard: PHP silently drops files beyond max_file_uploads ──
        if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
            $maxUploads = (int) ini_get('max_file_uploads');
            if (count($_FILES) >= $maxUploads) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Too many file uploads. Please contact support or try reducing the number of files.',
                ], 422);
            }
        }

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

                // Service agreement PDF (nullable)
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
            'accreditation_type_id' => ['required', 'integer', 'exists:accreditation_types,id'],
            'profile_type'          => ['required', 'in:Individual,Organization'],
            'email'                 => ['required', 'email', 'unique:users,email', 'unique:pending_registrations,email'],
            'password'              => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // Organization fields
            'org_name'     => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:255'],
            'org_address'  => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:500'],
            'head_name'    => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:255'],
            'designation'  => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:255'],
            'telephone'    => ['nullable', 'string', 'max:50'],
            'fax'          => ['nullable', 'string', 'max:50'],
            'org_email'    => ['required_if:profile_type,Organization', 'nullable', 'email', 'max:255'],

            // Representative fields
            'rep_full_name'      => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:255'],
            'rep_position'       => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:255'],
            'rep_contact_number' => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:11'],
            'rep_email'          => ['required_if:profile_type,Organization', 'nullable', 'email', 'max:255'],

            'documents'   => ['nullable', 'array'],
            'instructors' => ['nullable', 'array'],
        ], $documentRules, $instructorRules));

        // ── Generate token ─────────────────────────────────────────
        $token = Str::random(64);

        // ── Store regular document fields ──────────────────────────
        $documentsData    = [];
        $allDocumentFields = DocumentField::all()->keyBy('code');

        foreach ($allDocumentFields as $code => $field) {
            if ($field->input_type === 'file') {
                if ($request->hasFile("documents.{$code}")) {
                    $file     = $request->file("documents.{$code}");
                    $tempPath = $file->storeAs("pending/{$token}", "{$code}.pdf", 'local');
                    $documentsData[$code] = ['input_type' => 'file', 'value' => $tempPath];
                }
            } else {
                $val = $request->input("documents.{$code}");
                if (! is_null($val) && $val !== '') {
                    $documentsData[$code] = ['input_type' => $field->input_type, 'value' => $val];
                }
            }
        }

        // ── Store instructor data ──────────────────────────────────
        $instructorsData = [];

        foreach ($instructors as $i => $inst) {
            $entry = [
                'first_name'  => $inst['first_name']  ?? '',
                'middle_name' => $inst['middle_name'] ?? null,
                'last_name'   => $inst['last_name']   ?? '',
                'service_agreement_path' => null,
                'credentials' => [],
            ];

            // Service agreement PDF
            if ($request->hasFile("instructors.{$i}.service_agreement")) {
                $file = $request->file("instructors.{$i}.service_agreement");
                $entry['service_agreement_path'] = $file->storeAs(
                    "pending/{$token}/instructors/{$i}",
                    'service_agreement.pdf',
                    'local'
                );
            }

            // Credentials
            foreach (self::CREDENTIAL_TYPES as $type) {
                $cred = $inst['credentials'][$type] ?? [];
                $credEntry = [
                    'number'         => $cred['number']         ?? null,
                    'issued_date'    => $cred['issued_date']    ?? null,
                    'validity_date'  => $cred['validity_date']  ?? null,
                    'training_dates' => $cred['training_dates'] ?? null,
                    'pdf_path'       => null,
                ];

                // Credential PDF
                if ($request->hasFile("instructors.{$i}.credentials.{$type}.pdf")) {
                    $file = $request->file("instructors.{$i}.credentials.{$type}.pdf");
                    $credEntry['pdf_path'] = $file->storeAs(
                        "pending/{$token}/instructors/{$i}",
                        "{$type}.pdf",
                        'local'
                    );
                }

                // Only store if at least one field is populated
                $hasData = ($credEntry['number'] || $credEntry['issued_date']
                    || $credEntry['validity_date'] || $credEntry['training_dates']
                    || $credEntry['pdf_path']);

                if ($hasData) {
                    $entry['credentials'][$type] = $credEntry;
                }
            }

            $instructorsData[] = $entry;
        }

        // ── Gather form data ───────────────────────────────────────
        $formData = $request->only([
            'org_name', 'org_address', 'head_name', 'designation',
            'telephone', 'fax', 'org_email',
            'rep_full_name', 'rep_position', 'rep_contact_number', 'rep_email',
            'first_name', 'middle_name', 'last_name',
            'sex', 'birthday', 'region', 'city', 'address',
        ]);

        // ── Create pending registration ────────────────────────────
        PendingRegistration::where('email', $request->email)->delete();

        $pending = PendingRegistration::create([
            'token'                 => $token,
            'email'                 => $request->email,
            'password'              => Hash::make($request->password),
            'profile_type'          => $request->profile_type,
            'accreditation_type_id' => $request->accreditation_type_id,
            'form_data'             => $formData,
            'documents_data'        => $documentsData,
            'instructors_data'      => $instructorsData,
            'expires_at'            => now()->addMinutes(5),
        ]);

        // ── Send verification email ────────────────────────────────
        $verificationUrl = route('register.verify', ['token' => $token]);

        try {
            Mail::to($request->email)->send(new VerifyRegistrationEmail($verificationUrl, $request->email));
        } catch (\Exception $e) {
            $pending->delete();
            Storage::disk('local')->deleteDirectory("pending/{$token}");
            Log::error('SMTP Error during registration logic: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Unable to send verification email due to a mail server error. Please try again later.'
            ], 500);
        }

        return response()->json([
            'status'  => 'pending',
            'message' => 'A verification link has been sent to ' . $request->email . '. Please check your inbox to complete your registration.',
            'email'   => $request->email,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /verify-email/{token}  — Finalize registration
    // ─────────────────────────────────────────────────────────────
    public function verify(string $token)
    {
        $pending = PendingRegistration::where('token', $token)->first();

        // ── Invalid or expired token ───────────────────────────────
        if (! $pending) {
            return view('landing.verify-result', [
                'status'  => 'error',
                'message' => 'This verification link is invalid or has already been used.',
            ]);
        }

        if ($pending->isExpired()) {
            $pending->delete();
            return view('landing.verify-result', [
                'status'  => 'error',
                'message' => 'This verification link has expired. Please register again.',
            ]);
        }

        // ── Double-check email isn't taken yet ─────────────────────
        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();
            return view('landing.verify-result', [
                'status'  => 'error',
                'message' => 'This email address is already registered. Please log in.',
            ]);
        }

        try {
            $trackingNumber = null;
            $applicantEmail = $pending->email;

            DB::transaction(function () use ($pending, &$trackingNumber) {
                $form = $pending->form_data;

                // 1. Create User
                $user = User::create([
                    'email'             => $pending->email,
                    'password'          => $pending->password,
                    'profile_type'      => $pending->profile_type,
                    'role_id'           => 1,
                    'email_verified_at' => now(),
                ]);

                // 2. Create Organization Profile
                $orgProfileId = null;
                if ($pending->profile_type === 'Organization') {
                    $orgProfile = OrganizationProfile::create([
                        'user_id'     => $user->id,
                        'name'        => $form['org_name']     ?? '',
                        'address'     => $form['org_address']  ?? '',
                        'head_name'   => $form['head_name']    ?? '',
                        'designation' => $form['designation']  ?? '',
                        'telephone'   => $form['telephone']    ?? null,
                        'fax'         => $form['fax']          ?? null,
                        'email'       => $form['org_email']    ?? '',
                    ]);
                    $orgProfileId = $orgProfile->id;

                    // 3. Authorized Representative
                    AuthorizedRepresentative::create([
                        'organization_profile_id' => $orgProfileId,
                        'full_name'               => $form['rep_full_name']      ?? '',
                        'position'                => $form['rep_position']       ?? '',
                        'contact_number'          => $form['rep_contact_number'] ?? '',
                        'email'                   => $form['rep_email']          ?? '',
                    ]);
                }

                // 4. Generate tracking number
                $year           = now()->format('Y');
                $sequence       = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $trackingNumber = "ARMS{$year}-{$sequence}";

                // 5. Create Application
                $application = Application::create([
                    'user_id'               => $user->id,
                    'accreditation_type_id' => $pending->accreditation_type_id,
                    'application_type'      => 'new',
                    'tracking_number'       => $trackingNumber,
                    'submitted_at'          => now(),
                ]);

                // 6. Persist document fields → user_documents → application_documents
                $fieldMap = DocumentField::all()->keyBy('code');
                $docsData = $pending->documents_data ?? [];

                foreach ($docsData as $code => $entry) {
                    $field = $fieldMap->get($code);
                    if (! $field) continue;

                    $filePath  = null;
                    $textValue = null;

                    if ($entry['input_type'] === 'file') {
                        $filePath = "public/documents/{$application->id}/{$code}.pdf";
                        Storage::disk('local')->move($entry['value'], $filePath);
                    } else {
                        $textValue = $entry['value'];
                    }

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

                // 7. Persist instructors + credentials
                $instructorsData = $pending->instructors_data ?? [];

                foreach ($instructorsData as $i => $instData) {
                    // Move service agreement PDF
                    $saPermanent = null;
                    if ($instData['service_agreement_path'] ?? null) {
                        $saPermanent = "public/instructors/{$user->id}/{$i}/service_agreement.pdf";
                        Storage::disk('local')->move($instData['service_agreement_path'], $saPermanent);
                    }

                    $instructor = Instructor::create([
                        'user_id'                => $user->id,
                        'first_name'             => $instData['first_name']  ?? '',
                        'middle_name'            => $instData['middle_name'] ?? null,
                        'last_name'              => $instData['last_name']   ?? '',
                        'service_agreement_path' => $saPermanent,
                    ]);

                    foreach ($instData['credentials'] ?? [] as $type => $credData) {
                        $credPermanent = null;
                        if ($credData['pdf_path'] ?? null) {
                            $credPermanent = "public/instructors/{$user->id}/{$i}/{$type}.pdf";
                            Storage::disk('local')->move($credData['pdf_path'], $credPermanent);
                        }

                        InstructorCredential::create([
                            'instructor_id'  => $instructor->id,
                            'type'           => $type,
                            'number'         => $credData['number']         ?? null,
                            'issued_date'    => $credData['issued_date']    ?? null,
                            'validity_date'  => $credData['validity_date']  ?? null,
                            'training_dates' => $credData['training_dates'] ?? null,
                            'pdf_path'       => $credPermanent,
                        ]);
                    }
                }

                // 8. Clean up temp folder
                Storage::disk('local')->deleteDirectory("pending/{$pending->token}");

                // 9. Create initial status log
                $submittedStatus = ApplicationStatus::where('name', 'Submitted')->first();
                if ($submittedStatus) {
                    ApplicationStatusLog::create([
                        'application_id' => $application->id,
                        'status_id'      => $submittedStatus->id,
                        'updated_by'     => null,
                        'remarks'        => 'Application submitted by applicant after email verification.',
                    ]);
                }

                $pending->delete();
            });

            // 10. Send confirmation email (non-fatal on failure)
            try {
                Mail::to($applicantEmail)->send(new ApplicationSubmittedEmail($trackingNumber, 'Submitted', $applicantEmail));
            } catch (\Exception $mailEx) {
                Log::warning('Verification success email failed to send: ' . $mailEx->getMessage());
            }

            return view('landing.verify-result', [
                'status'         => 'success',
                'trackingNumber' => $trackingNumber,
            ]);

        } catch (\Throwable $e) {
            Log::error('Registration verification failed: ' . $e->getMessage(), ['exception' => $e]);

            return view('landing.verify-result', [
                'status'  => 'error',
                'message' => 'Something went wrong while finalizing your registration. Please try again or contact support.',
            ]);
        }
    }
}
