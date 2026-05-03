<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Models\Accreditation;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\ApplicationStatusLog;
use App\Models\Instructor;
use App\Models\InstructorCredential;
use App\Models\Interview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\DocumentRejectionEmail;
use App\Mail\InterviewResultEmail;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicationController extends Controller
{
    /**
     * Admin Dashboard — summary stats, monthly table, chart data.
     */
    public function dashboard(Request $request)
    {
        $selectedYear = $request->input('year', now()->year);

        // ── Statuses we care about ───────────────────────────────────────────
        $pendingStatuses    = ['Submitted'];
        $underReviewStatuses = ['Under Evaluation', 'For Update'];
        $scheduledStatuses  = ['Scheduled for Interview'];
        $activeFATProStatus = 'active'; // accreditations.status

        // ── Stat Cards ───────────────────────────────────────────────────────
        $totalActiveFATPro = \App\Models\Accreditation::where('status', $activeFATProStatus)->count();

        $newPending = Application::where('application_type', 'new')
            ->whereHas('latestStatus', fn($q) => $q->whereHas('status', fn($q2) => $q2->whereIn('name', $pendingStatuses)))
            ->count();

        $newUnderReview = Application::where('application_type', 'new')
            ->whereHas('latestStatus', fn($q) => $q->whereHas('status', fn($q2) => $q2->whereIn('name', $underReviewStatuses)))
            ->count();

        $renewalPending = Application::where('application_type', 'renewal')
            ->whereHas('latestStatus', fn($q) => $q->whereHas('status', fn($q2) => $q2->whereIn('name', $pendingStatuses)))
            ->count();

        $renewalUnderReview = Application::where('application_type', 'renewal')
            ->whereHas('latestStatus', fn($q) => $q->whereHas('status', fn($q2) => $q2->whereIn('name', $underReviewStatuses)))
            ->count();

        $scheduledInterviews = Application::whereHas('latestStatus', fn($q) =>
            $q->whereHas('status', fn($q2) => $q2->whereIn('name', $scheduledStatuses))
        )->count();

        // ── Monthly Tables & Chart (filter by selected year) ─────────────────
        // Monthly new applications
        $monthlyNew = Application::where('application_type', 'new')
            ->whereYear('created_at', $selectedYear)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Monthly renewal applications
        $monthlyRenewal = Application::where('application_type', 'renewal')
            ->whereYear('created_at', $selectedYear)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Monthly accredited (issued accreditation records)
        $monthlyAccredited = \App\Models\Accreditation::whereYear('date_of_accreditation', $selectedYear)
            ->selectRaw('MONTH(date_of_accreditation) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Build per-month rows (1–12)
        $monthlyRows = collect(range(1, 12))->map(fn($m) => [
            'month'      => \Carbon\Carbon::create()->month($m)->format('F'),
            'new'        => $monthlyNew->get($m, 0),
            'renewal'    => $monthlyRenewal->get($m, 0),
            'accredited' => $monthlyAccredited->get($m, 0),
        ]);

        // ── Donut chart: Application status breakdown ────────────────────────
        $statusBreakdown = \App\Models\ApplicationStatusLog::select('application_statuses.name', \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT application_status_logs.application_id) as total'))
            ->join('application_statuses', 'application_statuses.id', '=', 'application_status_logs.status_id')
            ->whereIn('application_status_logs.id', function ($sub) {
                $sub->selectRaw('MAX(id)')
                    ->from('application_status_logs')
                    ->groupBy('application_id');
            })
            ->groupBy('application_statuses.name')
            ->pluck('total', 'name');

        // ── Available years for filter ───────────────────────────────────────
        $availableYears = Application::selectRaw('YEAR(created_at) as yr')
            ->groupBy('yr')
            ->orderByDesc('yr')
            ->pluck('yr');

        if ($availableYears->isEmpty()) {
            $availableYears = collect([now()->year]);
        }

        return view('admin.hcd.dashboard', compact(
            'totalActiveFATPro',
            'newPending', 'newUnderReview',
            'renewalPending', 'renewalUnderReview',
            'scheduledInterviews',
            'monthlyRows', 'selectedYear', 'availableYears',
            'statusBreakdown'
        ));
    }

    /**
     * Display a listing of pending applications.
     */
    public function pending()
    {
        // Get applications that haven't been evaluated yet
        // Get applications that are still 'Submitted' based on their latest status log
        $applications = Application::with([
            'user.organizationProfile.authorizedRepresentatives',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Submitted');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.hcd.pending', compact('applications'));
    }

    /**
     * Update an application's status to Under Evaluation.
     */
    public function updateToEvaluation(Request $request, Application $application)
    {
        // Use relationship logic for status with null-safe operators
        $latestStatusName = $application->latestStatus?->status?->name;
        
        if ($latestStatusName !== 'Submitted') {
            return back()->with('error', 'Only newly submitted applications can be moved to evaluation.');
        }

        $underEvaluationStatus = \App\Models\ApplicationStatus::where('name', 'Under Evaluation')->first();

        if (!$underEvaluationStatus) {
            return back()->with('error', 'Configuration error: "Under Evaluation" status not found.');
        }

        // Assign the evaluating admin
        $application->handled_by_admin_id = auth()->id();
        $application->save();

        if ($underEvaluationStatus) {
            \App\Models\ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $underEvaluationStatus->id,
                'updated_by'     => auth()->id(),
                'remarks'        => 'Application is now being evaluated.',
            ]);
        }

        return back()->with('success', 'Application ' . $application->tracking_number . ' is now Under Evaluation.');
    }

    /**
     * Display a listing of applications under evaluation.
     */
    public function underReview()
    {
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->whereIn('name', ['Under Evaluation', 'For Update']);
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.under_review', compact('applications'));
    }

    /**
     * Display the specified application.
     */
    public function show(Application $application)
    {
        $application->load([
            'user.organizationProfile.authorizedRepresentatives',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'statusLogs.status',
            'documents.documentField.documentType',
            'documents.userDocument',
            'interview',
            'accreditation',
            'user.instructors.credentials',
        ]);

        return view('admin.hcd.show_application_info', compact('application'));
    }

    /**
     * Evaluate (approve or reject) a single application document.
     * When all documents for the application are approved, auto-promotes
     * the application status to "Scheduled for Interview".
     */
    public function evaluateDocument(Request $request, ApplicationDocument $document)
    {
        $request->validate([
            'action'  => ['required', 'in:approve,reject'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = $request->input('action') === 'approve' ? 'approved' : 'rejected';
        $remarks   = $request->input('action') === 'reject' ? $request->input('remarks') : null;

        $document->update([
            'status'  => $newStatus,
            'remarks' => $remarks,
        ]);

        $application = $document->application;

        // Re-load all documents for this application to check if all are approved
        $allDocs   = $application->documents()->get();
        $allApproved = $allDocs->every(fn($d) => $d->status === 'approved');

        $statusChanged = false;
        $newStatusName = null;

        if ($allApproved && $allDocs->count() > 0) {
            $currentStatus = $application->latestStatus?->status?->name;

            if ($currentStatus !== 'Scheduled for Interview') {
                $scheduledStatus = ApplicationStatus::where('name', 'Scheduled for Interview')->first();

                if ($scheduledStatus) {
                    ApplicationStatusLog::create([
                        'application_id' => $application->id,
                        'status_id'      => $scheduledStatus->id,
                        'updated_by'     => auth()->id(),
                        'remarks'        => 'All documents approved. Application scheduled for interview.',
                    ]);
                    $statusChanged = true;
                    $newStatusName = 'Scheduled for Interview';
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'        => true,
                'doc_status'     => $newStatus,
                'remarks'        => $remarks,
                'status_changed' => $statusChanged,
                'new_app_status' => $newStatusName,
            ]);
        }

        return back()->with('success', 'Document ' . ucfirst($newStatus) . ' successfully.');
    }

    /**
     * Finalize the entire evaluation process.
     * Updates all document statuses and triggers either Rejection Email (Status: For Update)
     * or proceed to Interview (Status: Scheduled for Interview).
     */
    public function finalizeEvaluation(Request $request, Application $application)
    {
        $request->validate([
            'evaluations' => ['nullable', 'array'],
            'evaluations.*.id' => ['required', 'exists:application_documents,id'],
            'evaluations.*.status' => ['required', 'in:approved,rejected'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
            'instructor_evaluations' => ['nullable', 'array'],
            'instructor_evaluations.*.id' => ['required', 'exists:instructors,id'],
            'instructor_evaluations.*.status' => ['required', 'in:approved,rejected'],
            'instructor_evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
            'credential_evaluations' => ['nullable', 'array'],
            'credential_evaluations.*.id' => ['required', 'exists:instructor_credentials,id'],
            'credential_evaluations.*.status' => ['required', 'in:approved,rejected'],
            'credential_evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $evaluations = $request->input('evaluations', []);
        $instructorEvals = $request->input('instructor_evaluations', []);
        $credentialEvals = $request->input('credential_evaluations', []);
        
        $hasRejections = false;

        foreach ($evaluations as $eval) {
            $doc = ApplicationDocument::find($eval['id']);
            if ($doc) {
                $doc->update([
                    'status' => $eval['status'],
                    'remarks' => $eval['status'] === 'rejected' ? ($eval['remarks'] ?? null) : null,
                ]);

                if ($eval['status'] === 'rejected') {
                    $hasRejections = true;
                }
            }
        }

        foreach ($instructorEvals as $eval) {
            $inst = \App\Models\Instructor::find($eval['id']);
            if ($inst) {
                $inst->update([
                    'status' => $eval['status'],
                    'remarks' => $eval['status'] === 'rejected' ? ($eval['remarks'] ?? null) : null,
                ]);

                if ($eval['status'] === 'rejected') {
                    $hasRejections = true;
                }
            }
        }

        foreach ($credentialEvals as $eval) {
            $cred = \App\Models\InstructorCredential::find($eval['id']);
            if ($cred) {
                $cred->update([
                    'status' => $eval['status'],
                    'remarks' => $eval['status'] === 'rejected' ? ($eval['remarks'] ?? null) : null,
                ]);

                if ($eval['status'] === 'rejected') {
                    $hasRejections = true;
                }
            }
        }

        if ($hasRejections) {
            // Status: For Update (ID 3)
            $forUpdateStatus = ApplicationStatus::where('name', 'For Update')->first();
            if ($forUpdateStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id' => $forUpdateStatus->id,
                    'updated_by' => auth()->id(),
                    'remarks' => 'Application has rejected documents. Notification sent to applicant.',
                ]);
            }

            // Send Email
            $rejectedDocs = $application->documents()->where('status', 'rejected')->get();
            $rejectedInstructors = $application->user->instructors()->where('status', 'rejected')->get();
            $rejectedCredentials = \App\Models\InstructorCredential::whereIn('instructor_id', $application->user->instructors->pluck('id'))
                                    ->where('status', 'rejected')->get();
                                    
            Mail::to($application->user->email)->send(new DocumentRejectionEmail($application, $rejectedDocs, $rejectedInstructors, $rejectedCredentials));

            return response()->json([
                'success' => true,
                'action' => 'rejection_sent',
                'message' => 'Rejection notice sent successfully.',
                'new_status' => 'For Update'
            ]);
        } else {
            // Check if all are approved (secondary safety check)
            $allApproved = $application->documents()->get()->every(fn($d) => $d->status === 'approved');
            $allInstApproved = $application->user->instructors()->get()->every(fn($i) => $i->status === 'approved');
            $allCredApproved = \App\Models\InstructorCredential::whereIn('instructor_id', $application->user->instructors->pluck('id'))->get()->every(fn($c) => $c->status === 'approved');

            if ($allApproved && $allInstApproved && $allCredApproved) {
                // Status: Scheduled for Interview (ID 4)
                $scheduledStatus = ApplicationStatus::where('name', 'Scheduled for Interview')->first();
                if ($scheduledStatus) {
                    ApplicationStatusLog::create([
                        'application_id' => $application->id,
                        'status_id' => $scheduledStatus->id,
                        'updated_by' => auth()->id(),
                        'remarks' => 'All documents approved. Proceeding to interview schedule.',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'action' => 'proceed_to_interview',
                    'message' => 'All documents approved! You can now schedule the interview.',
                    'new_status' => 'Scheduled for Interview'
                ]);
            }
        }

        return response()->json(['success' => false, 'message' => 'Incomplete evaluation.']);
    }

    /**
     * Save (or update) the interview schedule for an application.
     */
    public function scheduleInterview(Request $request, Application $application)
    {
        $request->validate([
            'interview_date' => ['required', 'date', 'after_or_equal:today'],
            'interview_time' => ['required', 'date_format:H:i'],
            'mode'           => ['required', 'in:online,f2f'],
            'venue'          => ['nullable', 'string', 'max:500'],
        ]);

        $interview = Interview::updateOrCreate(
            ['application_id' => $application->id],
            [
                'interview_date' => $request->input('interview_date'),
                'interview_time' => $request->input('interview_time'),
                'mode'           => $request->input('mode'),
                'venue'          => $request->input('mode') === 'f2f' ? $request->input('venue') : null,
            ]
        );

        // Send email confirmation to the applicant
        if ($application->user && $application->user->email) {
            Mail::to($application->user->email)->send(new \App\Mail\InterviewScheduleEmail($application, $interview));
        }

        return back()->with('success', 'Interview schedule saved successfully.');
    }

    /**
     * List applicants with status "For Interview" that have no schedule yet.
     */
    public function pendingInterview()
    {
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'interview',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Scheduled for Interview');
                });
            })
            ->whereDoesntHave('interview')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.pending_interview', compact('applications'));
    }

    /**
     * List applicants who already have an interview scheduled.
     */
    public function scheduledInterviews()
    {
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'interview',
        ])
            ->whereHas('interview')
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.scheduled_interviews', compact('applications'));
    }

    /**
     * Record the interview result: Passed → accredit, Not Passed → delete application.
     */
    public function recordInterviewResult(Request $request, Application $application)
    {
        $request->validate([
            'result' => ['required', 'in:passed,not_passed'],
        ]);

        // Guard: must have an interview scheduled
        if (! $application->interview) {
            return back()->with('error', 'No interview schedule found for this application.');
        }

        // Guard: already processed
        if ($application->accreditation) {
            return back()->with('error', 'This application has already been accredited.');
        }

        if ($request->input('result') === 'passed') {
            // ── PASSED: Create accreditation record ──────────────────────────

            // Build accreditation number: e.g. 235-251222-006
            $datePrefix = now()->format('ymd'); // YYMMDD
            $today = now()->toDateString();
            
            $countToday = Accreditation::whereDate('date_of_accreditation', $today)->count();
            
            do {
                $countToday++;
                $incrementStr = str_pad($countToday, 3, '0', STR_PAD_LEFT);
                $accNumber = "235-{$datePrefix}-{$incrementStr}";
            } while (Accreditation::where('accreditation_number', $accNumber)->exists());

            $today = now()->toDateString();

            $accreditation = Accreditation::create([
                'user_id'               => $application->user_id,
                'application_id'        => $application->id,
                'accreditation_type_id' => $application->accreditation_type_id,
                'accreditation_number'  => $accNumber,
                'date_of_accreditation' => $today,
                'validity_date'         => now()->addYears(3)->toDateString(),
                'status'                => 'active',
            ]);

            // Log status: Approved
            $approvedStatus = ApplicationStatus::where('name', 'Approved')->first();
            if ($approvedStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $approvedStatus->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Interview passed. Application approved and accreditation issued: ' . $accNumber,
                ]);
            }

            // Send email
            if ($application->user?->email) {
                $application->load('accreditationType');
                Mail::to($application->user->email)
                    ->send(new InterviewResultEmail($application, 'passed', $accreditation));
            }

            return back()->with('success', 'Application approved. Accreditation number ' . $accNumber . ' has been issued and the applicant has been notified.');

        } else {
            // ── NOT PASSED: Send email then delete application ───────────────

            $applicantEmail = $application->user?->email;
            $trackingNumber = $application->tracking_number;

            // Send email BEFORE deletion so relationships are still intact
            if ($applicantEmail) {
                Mail::to($applicantEmail)
                    ->send(new InterviewResultEmail($application, 'not_passed', null));
            }

            // Delete application (cascade removes: documents, status_logs, interview, accreditation)
            $application->delete();

            return redirect()->route('admin.hcd.interviews.scheduled')
                ->with('success', 'Application ' . $trackingNumber . ' has been rejected and removed. The applicant has been notified.');
        }
    }

    /**
     * Invite a new admin.
     */
    public function inviteAdmin(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'unique:users,email', 'unique:pending_admins,email'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $token = \Illuminate\Support\Str::random(64);
        
        $loggedInUser = auth()->user();
        $divisionId = $loggedInUser->adminProfile->division_id ?? null;

        $pendingAdmin = \App\Models\PendingAdmin::create([
            'token' => $token,
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'position' => $request->position,
            'division_id' => $divisionId,
            'expires_at' => now()->addDays(7),
        ]);

        $invitationUrl = url('/admin/setup-password/' . $token);

        try {
            Mail::to($request->email)->send(new \App\Mail\AdminInvitationEmail($invitationUrl, $request->email));
        } catch (\Exception $e) {
            $pendingAdmin->delete();
            \Illuminate\Support\Facades\Log::error('SMTP Error during admin invitation: ' . $e->getMessage());
            
            return response()->json([
                'status'  => 'error',
                'message' => 'Unable to send invitation email due to a mail server error. Please try again later.'
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Invitation sent to ' . $request->email . ' successfully.',
        ]);
    }

    /**
     * Show a list of admins in the same division as the currently authenticated admin.
     */
    public function adminsList()
    {
        $loggedInUser = auth()->user();
        $divisionId = $loggedInUser->adminProfile->division_id ?? null;

        $admins = \App\Models\User::whereHas('role', function ($query) {
                $query->where('name', 'Admin')
                      ->orWhere('name', 'Super Admin');
            })
            ->whereHas('adminProfile', function ($query) use ($divisionId) {
                if ($divisionId) {
                    $query->where('division_id', $divisionId);
                }
            })
            ->with('adminProfile.division')
            ->get();

        return view('admin.hcd.admins_list', compact('admins'));
    }

    /**
     * Display a listing of pending renewal / reinstatement applications.
     */
    public function renewalPending()
    {
        $applications = Application::with([
            'user.organizationProfile.authorizedRepresentatives',
            'user.individualProfile',
            'user.accreditations',
            'accreditationType',
            'latestStatus.status',
        ])
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Submitted');
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.hcd.renewal_pending', compact('applications'));
    }

    /**
     * Display a listing of renewal / reinstatement applications under evaluation.
     */
    public function renewalUnderReview()
    {
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'user.accreditations',
            'accreditationType',
            'latestStatus.status',
        ])
            ->whereIn('application_type', ['renewal', 'reinstatement'])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->whereIn('name', ['Under Evaluation', 'For Update']);
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.renewal_under_review', compact('applications'));
    }

    /**
     * Show a list of active FATPro users.
     */
    public function activeFatprosList()
    {
        $accreditations = \App\Models\Accreditation::where('status', 'active')
            ->whereHas('accreditationType', function ($query) {
                $query->where('name', 'like', '%FATPro%')
                      ->orWhere('name', 'like', '%First Aid Training Providers%');
            })
            ->with(['user.organizationProfile', 'accreditationType', 'application'])
            ->get();

        return view('admin.hcd.active_fatpros', compact('accreditations'));
    }

    /**
     * Show a list of revoked/expired FATPro users.
     */
    public function inactiveFatprosList(Request $request)
    {
        $status = $request->input('status', ''); // 'revoked' or 'expired' or empty

        $query = \App\Models\Accreditation::whereIn('status', ['revoked', 'expired'])
            ->whereHas('accreditationType', function ($q) {
                $q->where('name', 'like', '%FATPro%')
                  ->orWhere('name', 'like', '%First Aid Training Providers%');
            })
            ->with(['user.organizationProfile', 'accreditationType', 'application']);

        if ($status === 'revoked') {
            $query->where('status', 'revoked');
        } elseif ($status === 'expired') {
            $query->where('status', 'expired');
        }

        $accreditations = $query->get();

        return view('admin.hcd.inactive_fatpros', compact('accreditations', 'status'));
    }

    /**
     * Generate and stream the Accreditation Certificate as a PDF.
     */
    public function downloadCertificate(\App\Models\Accreditation $accreditation)
    {
        $accreditation->load([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
        ]);

        $user = $accreditation->user;

        // Resolve FATPro display name
        if ($user->profile_type === 'Organization' && $user->organizationProfile) {
            $fatproName = $user->organizationProfile->name ?? $user->name;
        } elseif ($user->individualProfile) {
            $fatproName = $user->individualProfile->full_name ?? $user->name;
        } else {
            $fatproName = $user->name;
        }

        $pdf = Pdf::loadView('admin.accreditation_certificate', [
            'accreditation' => $accreditation,
            'fatproName'    => $fatproName,
        ])->setPaper('a4', 'portrait');

        $filename = 'Accreditation_Certificate_' . $accreditation->accreditation_number . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Serve a local-disk document file to the admin browser.
     */
    public function serveDocument(ApplicationDocument $document)
    {
        $userDoc = $document->userDocument;

        if (! $userDoc || ! $userDoc->file_path) {
            abort(404, 'File path not found.');
        }

        $path = $userDoc->file_path;

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found on disk.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $filename = basename($path);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Serve a local-disk instructor credential file to the admin browser.
     */
    public function serveInstructorCredential(InstructorCredential $credential)
    {
        if (! $credential || ! $credential->pdf_path) {
            abort(404, 'File path not found.');
        }

        $path = $credential->pdf_path;

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found on disk.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $filename = basename($path);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Serve a local-disk instructor service agreement file to the admin browser.
     */
    public function serveInstructorServiceAgreement(Instructor $instructor)
    {
        if (! $instructor || ! $instructor->service_agreement_path) {
            abort(404, 'File path not found.');
        }

        $path = $instructor->service_agreement_path;

        if (! Storage::disk('local')->exists($path)) {
            abort(404, 'File not found on disk.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $filename = basename($path);

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
