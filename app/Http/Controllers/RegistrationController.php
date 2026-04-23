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
    // ─────────────────────────────────────────────────────────────
    //  POST /register  — Save pending data, send verification email
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        // ── Guard: PHP silently drops files beyond max_file_uploads ──
        // When this happens the $_FILES array is incomplete and PHP emits a
        // warning that corrupts the JSON response. Detect it early and return
        // a clean JSON error so the front-end shows a helpful message.
        if (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
            $maxUploads = (int) ini_get('max_file_uploads');
            if (count($_FILES) >= $maxUploads) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Too many file uploads. Please contact support or try reducing the number of files.',
                ], 422);
            }
        }

        // ── Validation ──────────────────────────────────────────
        // Build dynamic per-field rules from document_fields table
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

        $request->validate(array_merge([
            'accreditation_type_id' => ['required', 'integer', 'exists:accreditation_types,id'],
            'profile_type'          => ['required', 'in:Individual,Organization'],
            'email'                 => ['required', 'email', 'unique:users,email', 'unique:pending_registrations,email'],
            'password'              => ['required', 'confirmed', Password::min(8)->letters()->numbers()],

            // Organization fields (required when profile_type = Organization)
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

            'documents' => ['nullable', 'array'],
        ], $documentRules));

        // ── Generate token ───────────────────────────────────────
        $token = Str::random(64);

        // ── Store each document field value to a temp area ───────
        // documents[FIELD_CODE] can be a file (PDF), a date string, or a text value
        $documentsData = []; // code => ['type' => 'file|text|date', 'value' => path|string]

        $allDocumentFields = DocumentField::all()->keyBy('code');

        foreach ($allDocumentFields as $code => $field) {
            if ($field->input_type === 'file') {
                if ($request->hasFile("documents.{$code}")) {
                    $file     = $request->file("documents.{$code}");
                    $tempPath = $file->storeAs("pending/{$token}", "{$code}.pdf", 'local');
                    $documentsData[$code] = ['input_type' => 'file', 'value' => $tempPath];
                }
            } else {
                // text or date
                $val = $request->input("documents.{$code}");
                if (! is_null($val) && $val !== '') {
                    $documentsData[$code] = ['input_type' => $field->input_type, 'value' => $val];
                }
            }
        }

        // ── Gather form data to store as JSON ────────────────────
        $formData = $request->only([
            'org_name',
            'org_address',
            'head_name',
            'designation',
            'telephone',
            'fax',
            'org_email',
            'rep_full_name',
            'rep_position',
            'rep_contact_number',
            'rep_email',
            'first_name',
            'middle_name',
            'last_name',
            'sex',
            'birthday',
            'region',
            'city',
            'address',
        ]);

        // ── Create pending registration ───────────────────────────
        PendingRegistration::where('email', $request->email)->delete(); // overwrite stale

        $pending = PendingRegistration::create([
            'token'                 => $token,
            'email'                 => $request->email,
            'password'              => Hash::make($request->password),
            'profile_type'          => $request->profile_type,
            'accreditation_type_id' => $request->accreditation_type_id,
            'form_data'             => $formData,
            'documents_data'        => $documentsData,
            'expires_at'            => now()->addMinutes(5),
        ]);

        // ── Send verification email ───────────────────────────────
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

        // ── Invalid or expired token ──────────────────────────────
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

        // ── Double-check email isn't taken yet ────────────────────
        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();
            return view('landing.verify-result', [
                'status'  => 'error',
                'message' => 'This email address is already registered. Please log in.',
            ]);
        }

        try {
            $trackingNumber = null;
            $applicantEmail = $pending->email; // Capture before deletion

            DB::transaction(function () use ($pending, &$trackingNumber) {
                $form = $pending->form_data;

                // 1. Create User
                $user = User::create([
                    'email'                      => $pending->email,
                    'password'                   => $pending->password, // already hashed
                    'profile_type'               => $pending->profile_type,
                    'role_id'                    => 1, // applicant
                    'email_verified_at'          => now(),
                ]);

                // 2. Create Profile
                $orgProfileId = null;
                if ($pending->profile_type === 'Organization') {
                    $orgProfile = OrganizationProfile::create([
                        'user_id'     => $user->id,
                        'name'        => $form['org_name'] ?? '',
                        'address'     => $form['org_address'] ?? '',
                        'head_name'   => $form['head_name'] ?? '',
                        'designation' => $form['designation'] ?? '',
                        'telephone'   => $form['telephone'] ?? null,
                        'fax'         => $form['fax'] ?? null,
                        'email'       => $form['org_email'] ?? '',
                    ]);
                    $orgProfileId = $orgProfile->id;

                    // 3. Authorized Representative
                    AuthorizedRepresentative::create([
                        'organization_profile_id' => $orgProfileId,
                        'full_name'               => $form['rep_full_name'] ?? '',
                        'position'                => $form['rep_position'] ?? '',
                        'contact_number'          => $form['rep_contact_number'] ?? '',
                        'email'                   => $form['rep_email'] ?? '',
                    ]);
                }

                // 4. Generate tracking number
                $year           = now()->format('Y');
                $sequence = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $trackingNumber = "ARMS{$year}-{$sequence}";

                // 5. Create Application
                $application = Application::create([
                    'user_id'               => $user->id,
                    'accreditation_type_id' => $pending->accreditation_type_id,
                    'application_type'      => 'new',
                    'tracking_number'       => $trackingNumber,
                    'submitted_at'          => now(),
                ]);

                // 6. Persist each field value → user_documents → application_documents
                $fieldMap  = DocumentField::all()->keyBy('code');
                $docsData  = $pending->documents_data ?? [];

                foreach ($docsData as $code => $entry) {
                    $field = $fieldMap->get($code);
                    if (! $field) {
                        continue;
                    }

                    $filePath  = null;
                    $textValue = null;

                    if ($entry['input_type'] === 'file') {
                        // Move from temp to permanent location
                        $filePath = "public/documents/{$application->id}/{$code}.pdf";
                        Storage::disk('local')->move($entry['value'], $filePath);
                    } else {
                        // text or date — store as plain value
                        $textValue = $entry['value'];
                    }

                    $userDoc = UserDocument::updateOrCreate(
                        [
                            'user_id'           => $user->id,
                            'document_field_id' => $field->id,
                        ],
                        [
                            'file_path' => $filePath,
                            'value'     => $textValue,
                        ]
                    );

                    ApplicationDocument::create([
                        'application_id'   => $application->id,
                        'document_field_id'=> $field->id,
                        'user_document_id' => $userDoc->id,
                        'status'           => 'pending',
                    ]);
                }

                // Clean up the temp folder
                Storage::disk('local')->deleteDirectory("pending/{$pending->token}");

                // 7. Create initial status log
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

            // 8. Try to send confirmation email, but don't fail if mail server hangs
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
