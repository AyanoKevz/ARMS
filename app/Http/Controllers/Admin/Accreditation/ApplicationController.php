<?php

namespace App\Http\Controllers\Admin\Accreditation;

use App\Http\Controllers\Admin\HCD\ApplicationController as HCDApplicationController;
use App\Mail\AdminInvitationEmail;
use App\Models\AdminRole;
use App\Models\Application;
use App\Models\PendingAdmin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Accreditation Division Portal Controller
 *
 * Extends the HCD ApplicationController (DRY) - Verifier users belong to the
 * "Accreditation" division, so AuthController routes them here on login.
 *
 * Only view-rendering is overridden to point at admin/accreditation/ views.
 * All business logic is inherited from the parent.
 */
class ApplicationController extends HCDApplicationController
{
    /**
     * Accreditation Dashboard.
     */
    public function dashboard(Request $request)
    {
        $parentResponse = parent::dashboard($request);
        $viewData = $parentResponse->getData();

        return view('admin.accreditation.dashboard', $viewData);
    }

    /**
     * Recommendation / Payment list (Verifier primary function).
     */
    public function awaitingPaymentList()
    {
        $parentResponse = parent::awaitingPaymentList();
        $viewData = $parentResponse->getData();

        return view('admin.accreditation.awaiting_payment', $viewData);
    }

    /**
     * Certificate Releasing / Issuance list (Verifier primary function).
     */
    public function releasingList()
    {
        $parentResponse = parent::releasingList();
        $viewData = $parentResponse->getData();

        return view('admin.accreditation.releasing', $viewData);
    }

    /**
     * Show a single application.
     * Reuses the HCD show_application_info view - it already handles $isVerifier
     * logic internally via the admin role check, so no view swap needed.
     */
    public function show(Application $application)
    {
        return parent::show($application);
    }

    /**
     * Admin directory for the Accreditation division.
     *
     * The parent builds the same division-scoped list; only the view differs.
     * No $adminRoles are passed because this portal has a single role — every
     * admin here is a Verifier — so the invite form has no role picker.
     */
    public function adminsList()
    {
        $parentResponse = parent::adminsList();
        $viewData = $parentResponse->getData();

        return view('admin.accreditation.admins_list', $viewData);
    }

    /**
     * Invite an admin into the Accreditation division.
     *
     * Overridden rather than inherited for two reasons:
     *   1. The parent requires the Team Lead role. That role does not exist in this
     *      portal, so inheriting it would make the feature unreachable — any admin
     *      in the Accreditation division may invite here.
     *   2. The role is not chosen by the user. Everyone in this portal is a Verifier,
     *      so it is resolved server-side and the form has no role field.
     */
    public function inviteAdmin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'unique:users,email', 'unique:pending_admins,email'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $verifierRole = AdminRole::where('name', 'Verifier')->first();

        if (!$verifierRole) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The Verifier role is missing from this installation. Please contact the system administrator.',
            ], 500);
        }

        $divisionId = auth()->user()->adminProfile->division_id ?? null;

        $pendingAdmin = PendingAdmin::create([
            'token' => Str::random(64),
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'position' => $request->position,
            'admin_role_id' => $verifierRole->id,
            'division_id' => $divisionId,
            'expires_at' => now()->addDays(7),
        ]);

        $invitationUrl = url('/admin/setup-password/' . $pendingAdmin->token);

        try {
            Mail::to($request->email)->send(new AdminInvitationEmail($invitationUrl, $request->email));
        } catch (\Exception $e) {
            // AdminInvitationEmail is deliberately not queued so this rollback works —
            // see the note on that class.
            $pendingAdmin->delete();
            Log::error('SMTP Error during accreditation admin invitation: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Unable to send invitation email due to a mail server error. Please try again later.',
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Invitation sent to ' . $request->email . ' successfully.',
        ]);
    }

    /**
     * Generate Recommendation PDF - overridden to:
     * 1. Remove the checkVerifierAccess() block (Verifiers ARE the ones generating this).
     * 2. Use admin.accreditation.* route names for redirects.
     */
    public function generateRecommendationPDF(Request $request, Application $application)
    {
        // No checkVerifierAccess() here - Verifiers generate recommendation PDFs.

        if ($request->isMethod('get')) {
            $data = session()->get("rec_pdf_{$application->id}");
            if (!$data) {
                return redirect()->route('admin.accreditation.applications.show', $application->id)
                    ->with('error', 'Unable to retrieve recommendation form data. Please generate the form again.');
            }
        } else {
            $data = $request->validate([
                'date'           => 'required|date',
                'from'           => 'required|string',
                'to'             => 'required|string',
                'specialization' => 'nullable|string',
                'evaluator'      => 'required|string',
                'interviewers'   => 'nullable|string',
                'recommended_by' => 'required|string',
                'approved_by'    => 'required|string',
            ]);

            session()->put("rec_pdf_{$application->id}", $data);

            return redirect()->route('admin.accreditation.applications.generate_recommendation', $application->id);
        }

        $application->load(['user.organizationProfile.authorizedRepresentatives', 'user.individualProfile', 'accreditationType', 'interview']);

        $user = $application->user;
        if ($user->profile_type === 'Organization' && $user->organizationProfile) {
            $applicantName = $user->organizationProfile->name ?? $user->name;
        } elseif ($user->individualProfile) {
            $applicantName = $user->individualProfile->full_name ?? $user->name;
        } else {
            $applicantName = $user->name;
        }

        $rawInterviewers = $data['interviewers'] ?? '';
        $interviewers = array_filter(array_map('trim', explode("\n", $rawInterviewers)));

        $pdf = Pdf::loadView('admin.hcd.recommendation_pdf', [
            'application'    => $application,
            'applicantName'  => $applicantName,
            'date'           => $data['date'],
            'from'           => $data['from'],
            'to'             => $data['to'],
            'specialization' => $data['specialization'],
            'evaluator'      => $data['evaluator'],
            'interviewers'   => $interviewers,
            'recommended_by' => $data['recommended_by'],
            'approved_by'    => $data['approved_by'],
        ])->setPaper('a4', 'portrait');

        $filename = 'Recommendation_Form_' . $application->tracking_number . '.pdf';
        return $pdf->stream($filename);
    }
}