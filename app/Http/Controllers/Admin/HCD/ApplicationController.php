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
use App\Mail\ApplicationResultEmail;
use App\Mail\InstructorUpdateRequestEmail;
use App\Mail\InstructorUpdateCompleteEmail;
use App\Mail\AccreditationRevokedEmail;
use App\Mail\DocumentsApprovedEmail;
use Barryvdh\DomPDF\Facade\Pdf;

class ApplicationController extends Controller
{
    /**
     * Helper to block Verifier role from accessing evaluator-specific actions.
     */
    private function checkVerifierAccess()
    {
        $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        if (strtolower($isAdminRole) === 'verifier') {
            abort(403, 'Unauthorized action. Verifiers do not have access to this page.');
        }
    }

    /**
     * Helper to block Evaluator role from accessing verifier-specific actions (e.g. Recommendation/Payment).
     */
    private function checkEvaluatorAccess()
    {
        $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        if (strtolower($isAdminRole) === 'evaluator') {
            abort(403, 'Unauthorized action. Evaluators do not have access to this page.');
        }
    }

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
        $this->checkVerifierAccess();
        // Get applications that haven't been evaluated yet
        // Get applications that are still 'Submitted' based on their latest status log
        $applications = Application::with([
            'user.organizationProfile.authorizedRepresentatives',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
        ])
            ->where('application_type', 'new')
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
        $this->checkVerifierAccess();
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

            // Notify applicant via email
            $applicantEmail = $application->user?->email;
            if ($applicantEmail) {
                \Illuminate\Support\Facades\Mail::to($applicantEmail)
                    ->send(new \App\Mail\ApplicationEvaluationStartedEmail($application));
            }
        }

        return redirect()->route('admin.hcd.applications.show', $application->id)->with('success', 'Application ' . $application->tracking_number . ' is now Under Evaluation.');
    }

    /**
     * Display a listing of applications under evaluation.
     */
    public function underReview()
    {
        $this->checkVerifierAccess();
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
        ])
            ->where('application_type', 'new')
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->whereIn('name', ['Under Evaluation', 'For Update']);
                });
            })
            // Exclude FATPros who are already accredited (active) — they don't belong here
            ->whereDoesntHave('user.accreditations', function ($q) {
                $q->where('status', 'active');
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
        $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        $isVerifier  = strtolower($isAdminRole) === 'verifier';
        $isEvaluator = strtolower($isAdminRole) === 'evaluator';
        
        if ($isVerifier) {
            $statusName = $application->latestStatus?->status?->name;
            if (!in_array($statusName, ['Awaiting Payment', 'Approved', 'Rejected'])) {
                abort(403, 'Unauthorized action. Verifiers can only view applications awaiting payment or completed/archived applications.');
            }
        }



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

        // Load all accreditations for this user (for history display)
        $accreditationHistory = \App\Models\Accreditation::where('user_id', $application->user_id)
            ->with('accreditationType')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.hcd.show_application_info', compact('application', 'accreditationHistory'));
    }

    /**
     * Evaluate (approve or reject) a single application document.
     * When all documents for the application are approved, auto-promotes
     * the application status to "Scheduled for Interview".
     */
    public function evaluateDocument(Request $request, ApplicationDocument $document)
    {
        $this->checkVerifierAccess();
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

                    if ($application->user && $application->user->email) {
                        try {
                            Mail::to($application->user->email)->send(new DocumentsApprovedEmail($application));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to send documents approved email: ' . $e->getMessage());
                        }
                    }
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
        $this->checkVerifierAccess();
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

        $application->load('accreditation');
        $isAccredited = (bool) $application->accreditation;
        
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
            if ($isAccredited) {
                // If it's an accredited update, we only reset the pending_review fields to admin_requested
                foreach ($application->user->instructors as $inst) {
                    if ($inst->update_request_status === 'pending_review') {
                        $requestedFields = $inst->update_request_fields ?? [];
                        $newFields = [];
                        
                        if (in_array('service_agreement', $requestedFields) && $inst->status === 'rejected') {
                            $newFields[] = 'service_agreement';
                        }
                        
                        $credTypes = array_filter($requestedFields, fn($f) => $f !== 'service_agreement');
                        $inst->load('credentials');
                        foreach ($credTypes as $type) {
                            $cred = $inst->credentials->firstWhere('type', $type);
                            if ($cred && $cred->status === 'rejected') {
                                $newFields[] = $type;
                            }
                        }

                        if (!empty($newFields)) {
                            $inst->update([
                                'update_request_status' => 'admin_requested',
                                'update_request_fields' => $newFields,
                            ]);
                        } else {
                            $inst->update([
                                'update_request_status' => 'completed',
                                'update_request_reason' => null,
                                'update_request_fields' => null,
                            ]);
                        }
                    }
                }

                $rejectedInstructors = $application->user->instructors()->where('status', 'rejected')->get();
                $rejectedCredentials = \App\Models\InstructorCredential::whereIn('instructor_id', $application->user->instructors->pluck('id'))
                                        ->where('status', 'rejected')->get();
                                        
                Mail::to($application->user->email)->send(new DocumentRejectionEmail($application, collect(), $rejectedInstructors, $rejectedCredentials));

                return response()->json([
                    'success' => true,
                    'action' => 'rejection_sent',
                    'message' => 'Instructor update rejected. Rejection notice sent successfully.',
                ]);
            } else {
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
            }
        }

        // ── Already-accredited path: instructor credential updates only ──────
        // For accredited FATPros, no interview step is needed.
        // Check if any instructor had pending_review updates and mark them complete, then email.
        if ($isAccredited) {
            $emailsSent = 0;
            foreach ($application->user->instructors as $inst) {
                if ($inst->update_request_status !== 'pending_review') {
                    continue;
                }

                $requestedFields = $inst->update_request_fields ?? [];

                $saApproved = !in_array('service_agreement', $requestedFields)
                    || $inst->fresh()->status === 'approved';

                $credTypes = array_filter($requestedFields, fn($f) => $f !== 'service_agreement');
                $inst->load('credentials'); // refresh from DB after evaluations were saved
                $credsApproved = true;
                foreach ($credTypes as $type) {
                    $cred = $inst->credentials->firstWhere('type', $type);
                    if ($cred && $cred->status !== 'approved') {
                        $credsApproved = false;
                        break;
                    }
                }

                if ($saApproved && $credsApproved) {
                    $inst->update([
                        'update_request_status' => 'completed',
                        'update_request_reason' => null,
                        'update_request_fields' => null,
                    ]);

                    try {
                        Mail::to($application->user->email)->send(new InstructorUpdateCompleteEmail($inst));
                        $emailsSent++;
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to send instructor update complete email: ' . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'success' => true,
                'action'  => 'update_accepted',
                'message' => 'Instructor credentials approved' . ($emailsSent > 0 ? ' and applicant notified via email.' : '.'),
            ]);
        }

        // ── New / Renewal / Reinstatement path ──────────────────────────────
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

            if ($application->user && $application->user->email) {
                try {
                    Mail::to($application->user->email)->send(new DocumentsApprovedEmail($application));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send documents approved email: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'action' => 'proceed_to_interview',
                'message' => 'All documents approved! You can now schedule the interview.',
                'new_status' => 'Scheduled for Interview'
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Incomplete evaluation.']);
    }

    /**
     * Save (or update) the interview schedule for an application.
     */
    public function scheduleInterview(Request $request, Application $application)
    {
        $this->checkVerifierAccess();
        $isNewInterview = !$application->interview;

        $request->validate([
            'interview_date' => array_merge(
                ['required', 'date'],
                $isNewInterview ? ['after_or_equal:today'] : []
            ),
            'interview_time' => ['required', 'date_format:H:i'],
            'mode'           => ['required', 'in:online,f2f'],
            'venue'          => ['required', 'string', 'max:500'],
        ]);

        // ── 2-hour interval check ──────────────────────────────────────────
        $requestedTime = \Carbon\Carbon::createFromFormat('H:i', $request->input('interview_time'));

        $sameDayInterviews = Interview::where('interview_date', $request->input('interview_date'))
            ->where('application_id', '!=', $application->id)
            ->get();

        $conflict = null;
        foreach ($sameDayInterviews as $existing) {
            $existingTime = \Carbon\Carbon::createFromFormat('H:i:s', $existing->interview_time);
            $diffMinutes  = abs($requestedTime->diffInMinutes($existingTime));
            if ($diffMinutes < 120) {
                $conflict = $existing;
                break;
            }
        }

        if ($conflict) {
            $conflictTime = \Carbon\Carbon::parse($conflict->interview_time)->format('h:i A');
            return back()->withErrors([
                'interview_time' => "Another interview is scheduled at {$conflictTime} on this date. Please allow at least a 2-hour gap between interviews.",
            ])->withInput();
        }

        $interview = Interview::updateOrCreate(
            ['application_id' => $application->id],
            [
                'interview_date' => $request->input('interview_date'),
                'interview_time' => $request->input('interview_time'),
                'mode'           => $request->input('mode'),
                'venue'          => $request->input('venue'),
            ]
        );

        // Always send / re-send email confirmation to the applicant
        if ($application->user && $application->user->email) {
            $isUpdate = !$isNewInterview;
            Mail::to($application->user->email)->send(new \App\Mail\InterviewScheduleEmail($application, $interview, $isUpdate));
        }

        $action = $isNewInterview ? 'scheduled' : 'updated';
        return back()->with('success', "Interview {$action} successfully. The applicant has been notified via email.");
    }

    /**
     * API: Check if an interview slot conflicts with existing interviews (2-hour interval).
     */
    public function checkInterviewSlot(Request $request)
    {
        $this->checkVerifierAccess();
        $request->validate([
            'date'           => ['required', 'date'],
            'time'           => ['required', 'date_format:H:i'],
            'application_id' => ['nullable', 'integer'],
        ]);

        $requestedTime = \Carbon\Carbon::createFromFormat('H:i', $request->input('time'));

        $query = Interview::where('interview_date', $request->input('date'));

        if ($request->filled('application_id')) {
            $query->where('application_id', '!=', $request->input('application_id'));
        }

        $sameDayInterviews = $query->with('application.user.organizationProfile', 'application.user.individualProfile')->get();

        $conflict = null;
        foreach ($sameDayInterviews as $existing) {
            $existingTime = \Carbon\Carbon::createFromFormat('H:i:s', $existing->interview_time);
            $diffMinutes  = abs($requestedTime->diffInMinutes($existingTime));
            if ($diffMinutes < 120) {
                $conflict = $existing;
                break;
            }
        }

        if ($conflict) {
            $app = $conflict->application;
            $user = $app?->user;
            $name = 'Another applicant';
            if ($user) {
                if ($user->profile_type === 'Organization' && $user->organizationProfile) {
                    $name = $user->organizationProfile->name;
                } elseif ($user->individualProfile) {
                    $name = trim(($user->individualProfile->first_name ?? '') . ' ' . ($user->individualProfile->last_name ?? ''));
                }
            }

            $conflictTime = \Carbon\Carbon::parse($conflict->interview_time)->format('h:i A');
            return response()->json([
                'available' => false,
                'message'   => "{$name} ({$app->tracking_number}) has an interview at {$conflictTime}. Please allow at least a 2-hour gap.",
            ]);
        }

        return response()->json(['available' => true]);
    }

    /**
     * List applicants with status "For Interview" that have no schedule yet.
     */
    public function pendingInterview()
    {
        $this->checkVerifierAccess();
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
        $this->checkVerifierAccess();
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
        $this->checkVerifierAccess();
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
            // Log status: Awaiting Payment
            $awaitingPaymentStatus = ApplicationStatus::where('name', 'Awaiting Payment')->first();
            if ($awaitingPaymentStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $awaitingPaymentStatus->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Interview passed. Application is now awaiting recommendation form and payment verification.',
                ]);
            }

            // Notify Verifiers
            $verifiers = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                $q->where('name', 'Verifier');
            })->get();
            \Illuminate\Support\Facades\Notification::send($verifiers, new \App\Notifications\AwaitingPaymentNotification($application));

            return back()->with('success', 'Interview passed. Application status updated to Awaiting Payment.');

        } else {
            // ── NOT PASSED: Send email then archive application ───────────────

            $applicantEmail = $application->user?->email;
            $trackingNumber = $application->tracking_number;

            // Send email BEFORE archiving so relationships are still intact
            if ($applicantEmail) {
                Mail::to($applicantEmail)
                    ->send(new ApplicationResultEmail($application, 'not_passed', null));
            }

            // Log status: Rejected (Archived)
            $rejectedStatus = ApplicationStatus::where('name', 'Rejected')->first();
            if ($rejectedStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $rejectedStatus->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Interview not passed. Application marked as Rejected/Archived.',
                ]);
            }

            return redirect()->route('admin.hcd.interviews.scheduled')
                ->with('success', 'Application ' . $trackingNumber . ' has been rejected and archived. The applicant has been notified.');
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
            'admin_role_id' => ['required', 'exists:admin_roles,id'],
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
            'admin_role_id' => $request->admin_role_id,
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
            ->with(['role', 'adminProfile.division', 'adminProfile.adminRole'])
            ->get();

        $adminRoles = \App\Models\AdminRole::all();

        return view('admin.hcd.admins_list', compact('admins', 'adminRoles'));
    }

    /**
     * Display a listing of pending renewal / reinstatement applications.
     */
    public function renewalPending()
    {
        $this->checkVerifierAccess();
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
        $this->checkVerifierAccess();
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
        // Load the absolute latest accreditation per user, and check if it is active.
        $accreditations = \App\Models\Accreditation::whereIn('id', function ($query) {
                $query->selectRaw('MAX(id)')
                      ->from('accreditations')
                      ->groupBy('user_id');
            })
            ->where('status', 'active')
            ->whereHas('accreditationType', function ($query) {
                $query->where('name', 'like', '%FATPro%')
                      ->orWhere('name', 'like', '%First Aid Training Providers%');
            })
            ->with(['user.organizationProfile', 'accreditationType', 'application'])
            ->orderBy('created_at', 'desc')
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
     * Revoke an active accreditation.
     */
    public function revokeAccreditation(\App\Models\Accreditation $accreditation)
    {
        $this->checkVerifierAccess();
        if ($accreditation->status !== 'active') {
            return back()->with('error', 'Only active accreditations can be revoked.');
        }

        $accreditation->update(['status' => 'revoked']);

        if ($accreditation->user && $accreditation->user->email) {
            try {
                Mail::to($accreditation->user->email)->send(new AccreditationRevokedEmail($accreditation));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send accreditation revoked email: ' . $e->getMessage());
            }
        }

        return back()->with('success', 'Accreditation ' . $accreditation->accreditation_number . ' has been revoked successfully.');
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

    /**
     * Admin initiates an instructor credentials update request.
     * Sets update_request_status = 'admin_requested', saves the reason and fields,
     * and sends an email notification to the applicant.
     */
    public function requestInstructorUpdate(Request $request, Instructor $instructor)
    {
        $this->checkVerifierAccess();
        $inputFields = $request->input('fields', []);
        
        $requestedFields = [];
        $reasons = [];

        foreach ($inputFields as $key => $data) {
            if (!empty($data['requested'])) {
                $requestedFields[] = $key;
                $reasons[$key] = $data['reason'] ?? 'No specific reason provided';

                if ($key === 'service_agreement') {
                    $instructor->update(['status' => 'returned']);
                } else {
                    $instructor->credentials()->where('type', $key)->update(['status' => 'returned']);
                }
            }
        }

        if (empty($requestedFields)) {
            return back()->with('error', 'Please select at least one document to update.');
        }

        $instructor->update([
            'update_request_status' => 'admin_requested',
            'update_request_fields' => $requestedFields,
            'update_request_reason' => json_encode($reasons),
        ]);

        if ($instructor->user && $instructor->user->email) {
            Mail::to($instructor->user->email)
                ->send(new InstructorUpdateRequestEmail($instructor));
        }

        return back()->with('success', 'Update request sent to applicant for instructor ' . $instructor->first_name . ' ' . $instructor->last_name . '.');
    }

    /**
     * Display a listing of archived/rejected applications.
     */
    public function archived()
    {
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Rejected');
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.archived', compact('applications'));
    }

    /**
     * Display a listing of applications awaiting payment.
     */
    public function awaitingPaymentList()
    {
        $isAdminRole = auth()->user()?->adminProfile?->adminRole?->name ?? '';
        $isEvaluator = strtolower($isAdminRole) === 'evaluator';

        // Allow both Verifiers and Evaluators to access the list so they can view and print the recommendation form.
        $applications = Application::with([
            'user.organizationProfile',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'payment',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Awaiting Payment');
                });
            })
            ->when($isEvaluator, function ($query) {
                $query->whereDoesntHave('payment', function ($q) {
                    $q->whereNotNull('signed_recommendation_letter');
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.awaiting_payment', compact('applications'));
    }

    /**
     * Generate dynamic OSHC recommendation form PDF.
     */
    public function generateRecommendationPDF(Request $request, Application $application)
    {
        $this->checkVerifierAccess();
        
        if ($request->isMethod('get')) {
            $data = session()->get("rec_pdf_{$application->id}");
            if (!$data) {
                return redirect()->route('admin.hcd.applications.show', $application->id)
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

            return redirect()->route('admin.hcd.applications.generate_recommendation', $application->id);
        }

        $application->load(['user.organizationProfile', 'user.individualProfile', 'accreditationType', 'interview']);

        // Resolve applicant display name
        $user = $application->user;
        if ($user->profile_type === 'Organization' && $user->organizationProfile) {
            $applicantName = $user->organizationProfile->name ?? $user->name;
        } elseif ($user->individualProfile) {
            $applicantName = $user->individualProfile->full_name ?? $user->name;
        } else {
            $applicantName = $user->name;
        }

        // Split interviewers list
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

    /**
     * Send payment instruction request notice to applicant.
     */
    public function requestPayment(Request $request, Application $application)
    {
        $this->checkEvaluatorAccess();
        $application->load('user');

        if ($application->user && $application->user->email) {
            try {
                Mail::send('emails.payment_instructions', ['application' => $application], function ($message) use ($application) {
                    $message->to($application->user->email)
                        ->subject('Action Required: Submit Recommendation and Payment - ' . $application->tracking_number);
                });

                // Add log entry
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $application->latestStatus->status_id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Recommendation form generated. Payment instructions requested and emailed to applicant.',
                ]);

                return back()->with('success', 'Payment request successfully sent to the applicant.');
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send payment request email: ' . $e->getMessage());
                return back()->with('error', 'Failed to send email. Make sure SMTP is configured correctly.');
            }
        }

        return back()->with('error', 'Applicant email address not found.');
    }

    /**
     * Verifier payment evaluation.
     */
    public function evaluatePayment(Request $request, Application $application)
    {
        $this->checkEvaluatorAccess();
        
        $payment = $application->payment ?? new \App\Models\ApplicationPayment(['application_id' => $application->id]);
        $hasLetter = $payment->signed_recommendation_letter && \Illuminate\Support\Facades\Storage::disk('local')->exists($payment->signed_recommendation_letter);

        $request->validate([
            'signed_recommendation_letter' => $hasLetter ? 'nullable|file|mimes:pdf|max:10240' : 'required|file|mimes:pdf|max:10240',
            'proof_of_payment_status'      => 'required|in:pending,approved,rejected',
            'proof_of_payment_remarks'     => 'nullable|string|max:1000',
            'e_signature_status'           => 'required|in:pending,approved,rejected',
            'e_signature_remarks'          => 'nullable|string|max:1000',
            'id_photo_status'              => 'required|in:pending,approved,rejected',
            'id_photo_remarks'             => 'nullable|string|max:1000',
        ]);

        $accreditationType = $application->accreditationType;
        $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
        $fatProName = $application->user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';
        $baseDocPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/documents";

        // Upload signed recommendation letter if provided
        if ($request->hasFile('signed_recommendation_letter')) {
            if ($payment->signed_recommendation_letter && Storage::disk('local')->exists($payment->signed_recommendation_letter)) {
                Storage::disk('local')->delete($payment->signed_recommendation_letter);
            }
            $filename = "signed_recommendation_" . time() . ".pdf";
            $path = $request->file('signed_recommendation_letter')->storeAs($baseDocPath, $filename, 'local');
            $payment->signed_recommendation_letter = $path;
        }

        $payment->proof_of_payment_status  = $request->input('proof_of_payment_status');
        $payment->proof_of_payment_remarks = $request->input('proof_of_payment_remarks');
        if ($payment->proof_of_payment_status === 'rejected') {
            if ($payment->proof_of_payment && Storage::disk('local')->exists($payment->proof_of_payment)) {
                Storage::disk('local')->delete($payment->proof_of_payment);
            }
            $payment->proof_of_payment = null;
        }

        $payment->e_signature_status  = $request->input('e_signature_status');
        $payment->e_signature_remarks = $request->input('e_signature_remarks');
        if ($payment->e_signature_status === 'rejected') {
            if ($payment->e_signature && Storage::disk('local')->exists($payment->e_signature)) {
                Storage::disk('local')->delete($payment->e_signature);
            }
            $payment->e_signature = null;
        }

        $payment->id_photo_status  = $request->input('id_photo_status');
        $payment->id_photo_remarks = $request->input('id_photo_remarks');
        if ($payment->id_photo_status === 'rejected') {
            if ($payment->id_photo && Storage::disk('local')->exists($payment->id_photo)) {
                Storage::disk('local')->delete($payment->id_photo);
            }
            $payment->id_photo = null;
        }

        $payment->save();

        $allApproved = ($payment->proof_of_payment_status === 'approved') &&
                       ($payment->e_signature_status === 'approved') &&
                       ($payment->id_photo_status === 'approved');

        if ($allApproved) {
            if (!$payment->signed_recommendation_letter) {
                return back()->with('error', 'You must upload the signed recommendation letter before final approval.')->withInput();
            }

            // Create accreditation record
            $datePrefix = now()->format('ymd'); // YYMMDD
            $isRenewalOrReinstatement = in_array($application->application_type, ['renewal', 'reinstatement']);
            $suffixStr = null;

            if ($isRenewalOrReinstatement) {
                $prevAccreditation = Accreditation::where('user_id', $application->user_id)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($prevAccreditation) {
                    $parts = explode('-', $prevAccreditation->accreditation_number);
                    $suffixStr = str_pad(end($parts), 3, '0', STR_PAD_LEFT);
                }
            }

            if (!$suffixStr) {
                $maxIncrement = 46;
                $allAccreditations = Accreditation::all();
                foreach ($allAccreditations as $acc) {
                    $parts = explode('-', $acc->accreditation_number);
                    $suffixVal = (int) end($parts);
                    if ($suffixVal > $maxIncrement) {
                        $maxIncrement = $suffixVal;
                    }
                }

                do {
                    $maxIncrement++;
                    $suffixStr = str_pad($maxIncrement, 3, '0', STR_PAD_LEFT);
                    $accNumber = "235-{$datePrefix}-{$suffixStr}";
                } while (Accreditation::where('accreditation_number', $accNumber)->exists());
            } else {
                $accNumber = "235-{$datePrefix}-{$suffixStr}";
            }

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
                    'remarks'        => 'Recommendation form and payments evaluated and approved. Accreditation number ' . $accNumber . ' issued.',
                ]);
            }

            // Notify applicant via in-app/portal database notification
            if ($application->user) {
                try {
                    $application->user->notify(new \App\Notifications\ApplicationApprovedNotification($application, $accNumber));
                } catch (\Exception $notifEx) {
                    \Illuminate\Support\Facades\Log::warning('Applicant portal notification failed: ' . $notifEx->getMessage());
                }
            }

            // Send success email
            if ($application->user?->email) {
                try {
                    $application->load('accreditationType');
                    Mail::to($application->user->email)
                        ->send(new ApplicationResultEmail($application, 'passed', $accreditation));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Accreditation success email failed: ' . $e->getMessage());
                }
            }

            return redirect()->route('admin.hcd.applications.awaiting_payment')->with('success', 'Application ' . $application->tracking_number . ' successfully approved and accredited! Number: ' . $accNumber);
        } else {
            // Rejections made: Notify applicant to resubmit
            $hasRejections = ($payment->proof_of_payment_status === 'rejected') ||
                             ($payment->e_signature_status === 'rejected') ||
                             ($payment->id_photo_status === 'rejected');

            if ($hasRejections) {
                if ($application->user && $application->user->email) {
                    try {
                        Mail::send('emails.payment_rejection', ['application' => $application, 'payment' => $payment], function ($message) use ($application) {
                            $message->to($application->user->email)
                                ->subject('Action Required: Correct Your Payment Requirements - ' . $application->tracking_number);
                        });
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Payment rejection email failed: ' . $e->getMessage());
                    }
                }

                // Add log entry
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $application->latestStatus->status_id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Payment evaluation complete. Rejected items were identified, and applicant has been requested to resubmit.',
                ]);

                return redirect()->route('admin.hcd.applications.awaiting_payment')->with('success', 'Payment evaluation submitted. Rejections were recorded and applicant has been notified.');
            }
        }

        return back()->with('success', 'Payment evaluation updated successfully.');
    }

    /**
     * Manually archive/reject application if it will not proceed from payment stage.
     */
    public function archiveFromPayment(Request $request, Application $application)
    {
        $this->checkEvaluatorAccess();
        $trackingNumber = $application->tracking_number;
        $rejectedStatus = ApplicationStatus::where('name', 'Rejected')->first();
        if ($rejectedStatus) {
            ApplicationStatusLog::create([
                'application_id' => $application->id,
                'status_id'      => $rejectedStatus->id,
                'updated_by'     => auth()->id(),
                'remarks'        => 'Application will not proceed. Manually archived/rejected from payment verification stage.',
            ]);
        }

        if ($application->user?->email) {
            try {
                Mail::to($application->user->email)
                    ->send(new ApplicationResultEmail($application, 'not_passed', null));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Archived email failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('admin.hcd.applications.awaiting_payment')
            ->with('success', 'Application ' . $trackingNumber . ' has been successfully archived/rejected.');
    }

    /**
     * Securely serve payment files to authenticated admin users.
     */
    public function servePaymentFile(\App\Models\ApplicationPayment $payment, $fileType)
    {
        $allowedTypes = ['proof_of_payment', 'e_signature', 'id_photo', 'signed_recommendation_letter'];
        if (!in_array($fileType, $allowedTypes)) {
            abort(404, 'Invalid file type requested.');
        }

        $path = $payment->$fileType;
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }

        $fullPath = Storage::disk('local')->path($path);
        $filename = basename($path);

        $mime = 'application/pdf';
        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($ext === 'png') {
            $mime = 'image/png';
        }

        return response()->file($fullPath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
