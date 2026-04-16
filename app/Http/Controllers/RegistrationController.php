<?php

namespace App\Http\Controllers;

use App\Mail\VerifyRegistrationEmail;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\AuthorizedRepresentative;
use App\Models\OrganizationProfile;
use App\Models\PendingRegistration;
use App\Models\User;
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
        // ── Validation ──────────────────────────────────────────
        $request->validate([
            'accreditation_type_id' => ['required', 'integer', 'exists:accreditation_types,id'],
            'profile_type'          => ['required', 'in:Individual,Organization'],
            'email'                 => ['required', 'email', 'unique:users,email', 'unique:pending_registrations,email'],
            'password'              => ['required', 'confirmed', Password::min(8)],

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
            'rep_contact_number' => ['required_if:profile_type,Organization', 'nullable', 'string', 'max:20'],
            'rep_email'          => ['required_if:profile_type,Organization', 'nullable', 'email', 'max:255'],

            // Document uploads
            'documents'          => ['required', 'array'],
            'documents.*'        => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        // ── Generate token ───────────────────────────────────────
        $token = Str::random(64);

        // ── Store uploaded PDFs to a private temp directory ──────
        $documentsData = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $code => $file) {
                $tempPath = $file->storeAs(
                    "pending/{$token}",
                    "{$code}.pdf",
                    'local'
                );
                $documentsData[$code] = $tempPath;
            }
        }

        // ── Gather form data to store as JSON ────────────────────
        $formData = $request->only([
            'org_name', 'org_address', 'head_name', 'designation',
            'telephone', 'fax', 'org_email',
            'rep_full_name', 'rep_position', 'rep_contact_number', 'rep_email',
            'first_name', 'middle_name', 'last_name', 'sex', 'birthday',
            'region', 'city', 'address',
        ]);

        // ── Create pending registration ───────────────────────────
        PendingRegistration::where('email', $request->email)->delete(); // overwrite stale

        PendingRegistration::create([
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
        Mail::to($request->email)->send(new VerifyRegistrationEmail($verificationUrl, $request->email));

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
            return view('LandingPage.verify_result', [
                'status'  => 'error',
                'message' => 'This verification link is invalid or has already been used.',
            ]);
        }

        if ($pending->isExpired()) {
            $pending->delete();
            return view('LandingPage.verify_result', [
                'status'  => 'error',
                'message' => 'This verification link has expired. Please register again.',
            ]);
        }

        // ── Double-check email isn't taken yet ────────────────────
        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();
            return view('LandingPage.verify_result', [
                'status'  => 'error',
                'message' => 'This email address is already registered. Please log in.',
            ]);
        }

        try {
            $trackingNumber = null;

            DB::transaction(function () use ($pending, &$trackingNumber) {
                $form = $pending->form_data;

                // 1. Create User
                $user = User::create([
                    'email'                      => $pending->email,
                    'password'                   => $pending->password, // already hashed
                    'profile_type'               => $pending->profile_type,
                    'role_id'                    => 2, // applicant
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
                $sequence       = str_pad(Application::count() + 1, 6, '0', STR_PAD_LEFT);
                $trackingNumber = "ARMS-{$year}-{$sequence}";

                // 5. Create Application
                $application = Application::create([
                    'user_id'               => $user->id,
                    'accreditation_type_id' => $pending->accreditation_type_id,
                    'application_type'      => 'new',
                    'tracking_number'       => $trackingNumber,
                    'submitted_at'          => now(),
                ]);

                // 6. Move documents from temp → permanent storage
                $docTypeMap = []; // code => document_type_id
                $docTypes = \App\Models\DocumentType::all()->keyBy('code');
                $docsData = $pending->documents_data ?? [];

                foreach ($docsData as $code => $tempPath) {
                    $finalPath = "public/documents/{$application->id}/{$code}.pdf";
                    Storage::disk('local')->move($tempPath, $finalPath);

                    $docType = $docTypes->get($code);
                    if ($docType) {
                        \App\Models\ApplicationDocument::create([
                            'application_id'   => $application->id,
                            'document_type_id' => $docType->id,
                            'file_path'        => $finalPath,
                            'status'           => 'pending',
                        ]);
                    }
                }

                // Clean up the temp folder
                Storage::disk('local')->deleteDirectory("pending/{$pending->token}");

                // 7. Create initial status log — "Submitted"
                $submittedStatus = ApplicationStatus::where('name', 'Submitted')->first();
                if ($submittedStatus) {
                    ApplicationStatusLog::create([
                        'application_id' => $application->id,
                        'status_id'      => $submittedStatus->id,
                        'updated_by'     => null, // self-submitted
                        'remarks'        => 'Application submitted by applicant after email verification.',
                    ]);
                }

                // 8. Delete pending record
                $pending->delete();
            });

            return view('LandingPage.verify_result', [
                'status'         => 'success',
                'trackingNumber' => $trackingNumber,
            ]);

        } catch (\Throwable $e) {
            Log::error('Registration verification failed: ' . $e->getMessage(), ['exception' => $e]);

            return view('LandingPage.verify_result', [
                'status'  => 'error',
                'message' => 'Something went wrong while finalizing your registration. Please try again or contact support.',
            ]);
        }
    }
}
