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
use App\Services\CacheService;
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
use App\Services\PctService;

class ApplicationController extends Controller
{
    protected PctService $pctService;

    public function __construct(PctService $pctService)
    {
        $this->pctService = $pctService;
    }
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
        // Throttle autoResume: run at most once per 5 minutes per session (not on every page load)
        $lastRun = session('pct_auto_resume_at', 0);
        if ((time() - $lastRun) > 300) {
            $this->pctService->autoResumeAllScheduledInterviews();
            session(['pct_auto_resume_at' => time()]);
        }
        $selectedYear = $request->input('year', now()->year);

        // ── Cache all dashboard stats (busted when application state changes) ──
        $dashboardData = CacheService::remember(
            CacheService::dashboardKey((int) $selectedYear),
            CacheService::TTL_DASHBOARD,
            function () use ($selectedYear) {
                // ── Statuses we care about ───────────────────────────────────
                $pendingStatuses    = ['Submitted'];
                $underReviewStatuses = ['Under Evaluation', 'For Update'];
                $scheduledStatuses  = ['Scheduled for Interview'];
                $activeFATProStatus = 'active';

                // ── Stat Cards ──────────────────────────────────────────
                $totalActiveFATPro = \App\Models\Accreditation::where('status', $activeFATProStatus)->count();
                $totalRevokedFATPro = \App\Models\Accreditation::where('status', 'revoked')->count();

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

                $totalRejected = Application::whereHas('latestStatus', fn($q) =>
                    $q->whereHas('status', fn($q2) => $q2->where('name', 'Rejected'))
                )->count();

                // ── Monthly Tables & Chart ──────────────────────────────────
                $monthlyNew = Application::where('application_type', 'new')
                    ->whereYear('created_at', $selectedYear)
                    ->selectRaw('CAST(EXTRACT(MONTH FROM created_at) AS INT) as month, COUNT(*) as total')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month');

                $monthlyRenewal = Application::where('application_type', 'renewal')
                    ->whereYear('created_at', $selectedYear)
                    ->selectRaw('CAST(EXTRACT(MONTH FROM created_at) AS INT) as month, COUNT(*) as total')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month');

                $monthlyAccredited = \App\Models\Accreditation::whereYear('date_of_accreditation', $selectedYear)
                    ->selectRaw('CAST(EXTRACT(MONTH FROM date_of_accreditation) AS INT) as month, COUNT(*) as total')
                    ->groupBy('month')
                    ->orderBy('month')
                    ->pluck('total', 'month');

                $monthlyRows = collect(range(1, 12))->map(fn($m) => [
                    'month'      => \Carbon\Carbon::create()->month($m)->format('F'),
                    'new'        => $monthlyNew->get($m, 0),
                    'renewal'    => $monthlyRenewal->get($m, 0),
                    'accredited' => $monthlyAccredited->get($m, 0),
                ]);

                // ── Status breakdown donut ────────────────────────────────────
                $statusBreakdown = \App\Models\ApplicationStatusLog::select(
                        'application_statuses.name',
                        \Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT application_status_logs.application_id) as total')
                    )
                    ->join('application_statuses', 'application_statuses.id', '=', 'application_status_logs.status_id')
                    ->whereIn('application_status_logs.id', function ($sub) {
                        $sub->selectRaw('MAX(id)')
                            ->from('application_status_logs')
                            ->groupBy('application_id');
                    })
                    ->groupBy('application_statuses.name')
                    ->pluck('total', 'name');

                // ── Available years ─────────────────────────────────────────
                $availableYears = Application::selectRaw('CAST(EXTRACT(YEAR FROM created_at) AS INT) as yr')
                    ->groupBy('yr')
                    ->orderByDesc('yr')
                    ->pluck('yr');

                if ($availableYears->isEmpty()) {
                    $availableYears = collect([now()->year]);
                }

                return compact(
                    'totalActiveFATPro',
                    'totalRevokedFATPro',
                    'newPending', 'newUnderReview',
                    'renewalPending', 'renewalUnderReview',
                    'scheduledInterviews', 'totalRejected',
                    'monthlyRows', 'availableYears',
                    'statusBreakdown'
                );
            }
        );

        // Unpack cached payload for the view
        extract($dashboardData);

        return view('admin.hcd.dashboard', compact(
            'totalActiveFATPro',
            'totalRevokedFATPro',
            'newPending', 'newUnderReview',
            'renewalPending', 'renewalUnderReview',
            'scheduledInterviews', 'totalRejected',
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
        $applications = CacheService::remember(
            CacheService::pendingKey(),
            CacheService::TTL_LIST,
            fn () => Application::with([
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
                ->get()
        );

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

            // ── PCT: Initialize Steps 1+2 (auto) and start Step 3 (Evaluation)
            $this->pctService->initializeFromEvaluation($application);

            // Notify applicant via email
            $applicantEmail = $application->user?->email;
            if ($applicantEmail) {
                \Illuminate\Support\Facades\Mail::to($applicantEmail)
                    ->queue(new \App\Mail\ApplicationEvaluationStartedEmail($application));
            }
        }

        // Bust listing + dashboard caches
        CacheService::bustApplicationCaches();

        return redirect()->route('admin.hcd.applications.show', $application->id)->with('success', 'Application ' . $application->tracking_number . ' is now Under Evaluation.');
    }

    /**
     * Display a listing of applications under evaluation.
     */
    public function underReview()
    {
        $this->checkVerifierAccess();
        $applications = CacheService::remember(
            CacheService::underReviewKey(),
            CacheService::TTL_LIST,
            fn () => Application::with([
                'user.organizationProfile.authorizedRepresentatives',
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
                ->get()
        );

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
            if (!in_array($statusName, ['Awaiting Payment', 'Payment Verification', 'Approved', 'Rejected'])) {
                abort(403, 'Unauthorized action. Verifiers can only view applications awaiting payment, payment verification, or completed/archived applications.');
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
            'instructors.credentials',
            'pctEntries',
        ]);

        // Self-heal: ensure PCT is initialized for applications in/past evaluation
        $this->pctService->initializeMissingEntries($application);

        // Auto-resume Interview (Step 5) if current time is >= scheduled interview time
        $this->pctService->autoResumeInterviewIfScheduled($application);

        // Load all accreditations for this user (for history display)
        $accreditationHistory = \App\Models\Accreditation::where('user_id', $application->user_id)
            ->with('accreditationType')
            ->orderBy('created_at', 'desc')
            ->get();

        // PCT Summary for timeline card
        $pctSummary = $this->pctService->getSummary($application);

        return view('admin.hcd.show_application_info', compact('application', 'accreditationHistory', 'pctSummary'));
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

        // Re-load all documents for this application to check if all are approved (excluding unuploaded ones)
        $allDocs = $application->documents()->with('userDocument', 'documentField')->get()->reject(function ($doc) {
            $field = $doc->documentField;
            if (!$field) return false;
            
            $userDoc = $doc->userDocument;
            if ($field->input_type === 'file') {
                return !$userDoc || is_null($userDoc->file_path) || $userDoc->file_path === '';
            }
            return !$userDoc || is_null($userDoc->value) || $userDoc->value === '';
        });
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
                            Mail::to($application->user->email)->queue(new DocumentsApprovedEmail($application));
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
     * AJAX endpoint to save evaluation for a single item (document, instructor, or credential) immediately.
     */
    public function evaluateItem(Request $request, Application $application)
    {
        $this->checkVerifierAccess();

        $request->validate([
            'item_type' => ['required', 'in:document,instructor,credential'],
            'item_id'   => ['required', 'integer'],
            'status'    => ['required', 'in:approved,rejected,pending,returned'],
            'remarks'   => ['nullable', 'string', 'max:1000'],
        ]);

        $itemType = $request->input('item_type');
        $itemId   = $request->input('item_id');
        $status   = $request->input('status');
        $remarks  = $request->input('status') === 'rejected' ? $request->input('remarks') : null;

        if ($itemType === 'document') {
            $item = ApplicationDocument::where('application_id', $application->id)->findOrFail($itemId);
        } elseif ($itemType === 'instructor') {
            $item = \App\Models\Instructor::where('application_id', $application->id)->findOrFail($itemId);
        } else {
            $item = \App\Models\InstructorCredential::whereHas('instructor', function ($q) use ($application) {
                $q->where('application_id', $application->id);
            })->findOrFail($itemId);
        }

        $item->update([
            'status'  => $status,
            'remarks' => $remarks,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Evaluation saved immediately.',
        ]);
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
            'evaluations.*.status' => ['required', 'in:approved,rejected,pending,returned'],
            'evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
            'instructor_evaluations' => ['nullable', 'array'],
            'instructor_evaluations.*.id' => ['required', 'exists:instructors,id'],
            'instructor_evaluations.*.status' => ['required', 'in:approved,rejected,pending,returned'],
            'instructor_evaluations.*.remarks' => ['nullable', 'string', 'max:1000'],
            'credential_evaluations' => ['nullable', 'array'],
            'credential_evaluations.*.id' => ['required', 'exists:instructor_credentials,id'],
            'credential_evaluations.*.status' => ['required', 'in:approved,rejected,pending,returned'],
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
                $newStatus = $eval['status'];

                // Guard: Never allow an already-approved item to revert to pending.
                // Accepted documents must stay accepted when sending rejection email.
                // Only an explicit 'rejected' or 'returned' can change an approved item.
                if ($doc->status === 'approved' && !in_array($newStatus, ['rejected', 'returned'])) {
                    // Item is already approved and not being deliberately rejected — skip update.
                    continue;
                }

                $doc->update([
                    'status' => $newStatus,
                    'remarks' => $newStatus === 'rejected' ? ($eval['remarks'] ?? null) : null,
                ]);

                if ($newStatus === 'rejected') {
                    $hasRejections = true;
                }
            }
        }

        foreach ($instructorEvals as $eval) {
            $inst = \App\Models\Instructor::find($eval['id']);
            if ($inst) {
                $newStatus = $eval['status'];

                // Guard: Never allow an already-approved instructor to revert to pending.
                if ($inst->status === 'approved' && !in_array($newStatus, ['rejected', 'returned'])) {
                    continue;
                }

                $inst->update([
                    'status' => $newStatus,
                    'remarks' => $newStatus === 'rejected' ? ($eval['remarks'] ?? null) : null,
                ]);

                if ($newStatus === 'rejected') {
                    $hasRejections = true;
                }
            }
        }

        foreach ($credentialEvals as $eval) {
            $cred = \App\Models\InstructorCredential::find($eval['id']);
            if ($cred) {
                $newStatus = $eval['status'];

                // Guard: Never allow an already-approved credential to revert to pending.
                if ($cred->status === 'approved' && !in_array($newStatus, ['rejected', 'returned'])) {
                    continue;
                }

                $cred->update([
                    'status' => $newStatus,
                    'remarks' => $newStatus === 'rejected' ? ($eval['remarks'] ?? null) : null,
                ]);

                if ($newStatus === 'rejected') {
                    $hasRejections = true;
                }
            }
        }

        // Hardened Backend Guardrail: Check if there are any rejected items already in the database
        $hasRejectionsInDb = $application->documents()->whereIn('status', ['rejected', 'returned'])->exists()
            || $application->instructors()->whereIn('status', ['rejected', 'returned'])->exists()
            || \App\Models\InstructorCredential::whereIn('instructor_id', $application->instructors->pluck('id'))
                ->whereIn('status', ['rejected', 'returned'])
                ->exists();

        if ($hasRejectionsInDb) {
            $hasRejections = true;
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
                                        
                Mail::to($application->user->email)->queue(new DocumentRejectionEmail($application, collect(), $rejectedInstructors, $rejectedCredentials));

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

                // ── PCT: Pause Step 3 (waiting for applicant re-upload)
                $this->pctService->pauseCurrentStep($application);

                // Send Email
                $rejectedDocs = $application->documents()->where('status', 'rejected')->get();
                $rejectedInstructors = $application->instructors()->where('status', 'rejected')->get();
                $rejectedCredentials = \App\Models\InstructorCredential::whereIn('instructor_id', $application->instructors->pluck('id'))
                                        ->where('status', 'rejected')->get();
                                        
                Mail::to($application->user->email)->queue(new DocumentRejectionEmail($application, $rejectedDocs, $rejectedInstructors, $rejectedCredentials));

                // Bust caches — status changed to For Update
                CacheService::bustApplicationCaches();

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
                        Mail::to($application->user->email)->queue(new InstructorUpdateCompleteEmail($inst));
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
        // Check if all are approved (secondary safety check) (excluding unuploaded ones)
        $allDocs = $application->documents()->with('userDocument', 'documentField')->get()->reject(function ($doc) {
            $field = $doc->documentField;
            if (!$field) return false;
            
            $userDoc = $doc->userDocument;
            if ($field->input_type === 'file') {
                return !$userDoc || is_null($userDoc->file_path) || $userDoc->file_path === '';
            }
            return !$userDoc || is_null($userDoc->value) || $userDoc->value === '';
        });
        $allApproved = $allDocs->every(fn($d) => $d->status === 'approved');
        $allInstApproved = $application->instructors()->get()->every(fn($i) => $i->status === 'approved');
        $allCredApproved = \App\Models\InstructorCredential::whereIn('instructor_id', $application->instructors->pluck('id'))->get()->every(fn($c) => $c->status === 'approved');

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

            // ── PCT: Complete Step 3 (Evaluation), Start Step 4 (Pending Interview)
            $this->pctService->transitionToStep($application, 4);

            $emailSent = true;
            if ($application->user && $application->user->email) {
                try {
                    Mail::to($application->user->email)->queue(new DocumentsApprovedEmail($application));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send documents approved email: ' . $e->getMessage());
                    $emailSent = false;
                }
            }

            // Bust caches
            CacheService::bustApplicationCaches();

            $message = 'All documents approved! You can now schedule the interview.';
            if (!$emailSent) {
                $message .= ' (Warning: Email notification failed to send. Please check mailer/network settings.)';
            }

            return response()->json([
                'success' => true,
                'action' => 'proceed_to_interview',
                'message' => $message,
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

        // ⏱ PCT: Complete Step 4 (Pending Interview), Start Step 5 (Interview), then immediately PAUSE Step 5
        if ($isNewInterview) {
            $this->pctService->transitionToStep($application, 5);
            $this->pctService->pauseCurrentStep($application);
        }

        // Always send / re-send email confirmation to the applicant
        if ($application->user && $application->user->email) {
            $isUpdate = !$isNewInterview;
            Mail::to($application->user->email)->queue(new \App\Mail\InterviewScheduleEmail($application, $interview, $isUpdate));
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

        $sameDayInterviews = $query->with('application.user.organizationProfile.authorizedRepresentatives', 'application.user.individualProfile')->get();

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
        $this->pctService->autoResumeAllScheduledInterviews();
        $applications = Application::with([
            'user.organizationProfile.authorizedRepresentatives',
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
        $this->pctService->autoResumeAllScheduledInterviews();
        $applications = Application::with([
            'user.organizationProfile.authorizedRepresentatives',
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
     * Start the interview manually (resumes Step 5 PCT).
     */
    public function startInterview(Request $request, Application $application)
    {
        $this->checkVerifierAccess();
        $activePct = $application->activePctEntry;
        
        if ($activePct && $activePct->step_number === 5 && $activePct->stepStatus() === 'paused') {
            $this->pctService->resumeCurrentStep($application);
            return back()->with('success', 'Interview started successfully.');
        }

        return back()->with('error', 'Cannot start the interview. Ensure the application is scheduled and not already started.');
    }

    /**
     * Stop the interview manually (completes Step 5, starts Step 6 PCT).
     */
    public function stopInterview(Request $request, Application $application)
    {
        $this->checkVerifierAccess();
        $activePct = $application->activePctEntry;
        
        if ($activePct && $activePct->step_number === 5 && $activePct->stepStatus() === 'active') {
            $this->pctService->transitionToStep($application, 6);
            return back()->with('success', 'Interview stopped. You can now record the result.');
        }

        return back()->with('error', 'Cannot stop the interview. Ensure the interview is currently running.');
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
            $application->load('user');

            // Log status: Awaiting Payment
            $awaitingPaymentStatus = ApplicationStatus::where('name', 'Awaiting Payment')->first();
            if ($awaitingPaymentStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $awaitingPaymentStatus->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Interview passed. Payment instructions automatically emailed to applicant.',
                ]);
            }

            // ⏱ PCT: Complete Step 6 (Result) ➔ Step 7 (Recommendation & Payment)
            $this->pctService->transitionToStep($application, 7);

            // Trigger payment instructions email automatically
            if ($application->user && $application->user->email) {
                try {
                    Mail::send('emails.payment_instructions', ['application' => $application], function ($message) use ($application) {
                        $message->to($application->user->email)
                            ->subject('Action Required: Submit Payment - ' . $application->tracking_number);
                    });
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send auto payment instructions email: ' . $e->getMessage());
                }
            }

            // Notify Verifiers
            $verifiers = \App\Models\User::whereHas('adminProfile.adminRole', function ($q) {
                $q->where('name', 'Verifier');
            })->get();
            \Illuminate\Support\Facades\Notification::send($verifiers, new \App\Notifications\AwaitingPaymentNotification($application));

            // Bust caches — status changed to Awaiting Payment
            CacheService::bustApplicationCaches();

            return back()->with('success', 'Interview passed. Application status updated to Awaiting Payment.');

        } else {
            // ── NOT PASSED: Send email then archive application ───────────────

            $applicantEmail = $application->user?->email;
            $trackingNumber = $application->tracking_number;

            // Send email BEFORE archiving so relationships are still intact
            if ($applicantEmail) {
                Mail::to($applicantEmail)
                    ->queue(new ApplicationResultEmail($application, 'not_passed', null));
            }

            // Log status: Rejected (Archived)
            $rejectedStatus = ApplicationStatus::findByName('Rejected');
            if ($rejectedStatus) {
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $rejectedStatus->id,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Interview not passed. Application marked as Rejected/Archived.',
                ]);
            }

            // ── PCT: Complete all active steps (final)
            $this->pctService->completeAllSteps($application);

            // Bust caches — application archived
            CacheService::bustApplicationCaches();

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
            Mail::to($request->email)->queue(new \App\Mail\AdminInvitationEmail($invitationUrl, $request->email));
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
        $applications = CacheService::remember(
            CacheService::renewalPendingKey(),
            CacheService::TTL_LIST,
            fn () => Application::with([
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
                ->get()
        );

        return view('admin.hcd.renewal_pending', compact('applications'));
    }

    /**
     * Display a listing of renewal / reinstatement applications under evaluation.
     */
    public function renewalUnderReview()
    {
        $this->checkVerifierAccess();
        $applications = CacheService::remember(
            CacheService::renewalUnderReviewKey(),
            CacheService::TTL_LIST,
            fn () => Application::with([
                'user.organizationProfile.authorizedRepresentatives',
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
                ->get()
        );

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
            ->with(['user.organizationProfile.authorizedRepresentatives', 'accreditationType', 'application'])
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
            ->with(['user.organizationProfile.authorizedRepresentatives', 'accreditationType', 'application']);

        if ($status === 'revoked') {
            $query->where('status', 'revoked');
        } elseif ($status === 'expired') {
            $query->where('status', 'expired');
        }

        $accreditations = $query->get();

        return view('admin.hcd.inactive_fatpros', compact('accreditations', 'status'));
    }

    /**
     * Archive an active or revoked/expired FATPro accreditation and move its application to the archived list.
     */
    public function archiveAccreditation(Request $request, \App\Models\Accreditation $accreditation)
    {
        $application = $accreditation->application;

        \Illuminate\Support\Facades\DB::transaction(function () use ($accreditation) {
            // Update accreditation status to archived.
            // Note: We do NOT mark the application as Rejected because this FATPro already passed evaluation and received accreditation.
            $accreditation->update([
                'status' => 'archived',
            ]);
        });

        // Bust application listing and tracking caches
        \App\Services\CacheService::bustApplicationCaches();
        if ($application?->tracking_number) {
            \App\Services\CacheService::bustTrackingCache($application->tracking_number);
        }

        return back()->with('success', 'FATPro accreditation #' . $accreditation->accreditation_number . ' has been moved to the Archived list.');
    }

    /**
     * Unarchive an archived FATPro accreditation and restore it to active/expired status.
     */
    public function unarchiveAccreditation(Request $request, \App\Models\Accreditation $accreditation)
    {
        $application = $accreditation->application;

        \Illuminate\Support\Facades\DB::transaction(function () use ($accreditation, $application) {
            // Restore status based on validity date
            $newStatus = 'active';
            if ($accreditation->validity_date && \Carbon\Carbon::parse($accreditation->validity_date)->isPast()) {
                $newStatus = 'expired';
            }

            $accreditation->update([
                'status' => $newStatus,
            ]);

            // Clean up any legacy 'Rejected' status log if it was logged during previous archiving
            if ($application) {
                $rejectedStatus = \App\Models\ApplicationStatus::where('name', 'Rejected')->first();
                if ($rejectedStatus) {
                    \App\Models\ApplicationStatusLog::where('application_id', $application->id)
                        ->where('status_id', $rejectedStatus->id)
                        ->where('remarks', 'like', '%Archived%')
                        ->delete();
                }
            }
        });

        \App\Services\CacheService::bustApplicationCaches();
        if ($application?->tracking_number) {
            \App\Services\CacheService::bustTrackingCache($application->tracking_number);
        }

        return back()->with('success', 'FATPro accreditation #' . $accreditation->accreditation_number . ' has been unarchived and restored to ' . ucfirst($accreditation->status) . '.');
    }

    /**
     * Unarchive an archived/rejected application and restore its previous status.
     */
    public function unarchiveApplication(Request $request, Application $application)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($application) {
            // If accreditation exists, restore accreditation
            if ($application->accreditation) {
                $newStatus = 'active';
                if ($application->accreditation->validity_date && \Carbon\Carbon::parse($application->accreditation->validity_date)->isPast()) {
                    $newStatus = 'expired';
                }
                $application->accreditation->update(['status' => $newStatus]);
            }

            // Remove the latest Rejected status log entry to revert to previous status
            $rejectedStatus = \App\Models\ApplicationStatus::where('name', 'Rejected')->first();
            if ($rejectedStatus) {
                $latestRejectedLog = \App\Models\ApplicationStatusLog::where('application_id', $application->id)
                    ->where('status_id', $rejectedStatus->id)
                    ->latest()
                    ->first();

                if ($latestRejectedLog) {
                    $latestRejectedLog->delete();
                }
            }
        });

        \App\Services\CacheService::bustApplicationCaches();
        if ($application->tracking_number) {
            \App\Services\CacheService::bustTrackingCache($application->tracking_number);
        }

        return back()->with('success', 'Application ' . $application->tracking_number . ' has been unarchived and restored to its original status.');
    }

    /**
     * Generate and stream the Accreditation Certificate as a PDF.
     */
    public function downloadCertificate(Request $request, \App\Models\Accreditation $accreditation)
    {
        $accreditation->load([
            'user.organizationProfile.authorizedRepresentatives',
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

        // Allow admin to customise the Executive Director name on the certificate
        $executiveDirector = $request->query('executive_director', 'JOSE MARIA S. BATINO');

        $pdf = Pdf::loadView('admin.accreditation_certificate', [
            'accreditation'     => $accreditation,
            'fatproName'        => $fatproName,
            'executiveDirector' => $executiveDirector,
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
                Mail::to($accreditation->user->email)->queue(new AccreditationRevokedEmail($accreditation));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send accreditation revoked email: ' . $e->getMessage());
            }
        }

        // Bust dashboard and listing caches — accreditation count changed
        CacheService::bustApplicationCaches();

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
                ->queue(new InstructorUpdateRequestEmail($instructor));
        }

        return back()->with('success', 'Update request sent to applicant for instructor ' . $instructor->first_name . ' ' . $instructor->last_name . '.');
    }

    /**
     * Display a listing of archived/rejected applications.
     */
    public function archived()
    {
        $applications = CacheService::remember(
            CacheService::archivedKey(),
            CacheService::TTL_LIST,
            fn () => Application::with([
                'user.organizationProfile.authorizedRepresentatives',
                'user.individualProfile',
                'accreditationType',
                'accreditation',
                'latestStatus.status',
            ])
                ->where(function ($query) {
                    $query->whereHas('latestStatus', function ($q) {
                        $q->whereHas('status', function ($s) {
                            $s->where('name', 'Rejected');
                        });
                    })
                    ->orWhereHas('accreditation', function ($q) {
                        $q->where('status', 'archived');
                    });
                })
                ->orderBy('updated_at', 'desc')
                ->get()
        );

        return view('admin.hcd.archived', compact('applications'));
    }

    /**
     * Delete an archived application permanently.
     */
    public function destroy(Application $application)
    {
        $trackingNumber = $application->tracking_number;
        $user = $application->user;

        \Illuminate\Support\Facades\DB::transaction(function () use ($application, $user) {
            // Delete related child records
            $application->documents()->delete();
            $application->statusLogs()->delete();
            $application->interview()->delete();
            $application->payment()->delete();
            $application->pctEntries()->delete();
            $application->instructors()->delete();

            if ($application->accreditation) {
                $application->accreditation()->delete();
            }

            // Delete application
            $application->delete();

            // If user has no remaining applications and is an applicant user, clean up profiles and user account
            if ($user && $user->applications()->count() === 0 && strtolower($user->role?->name ?? '') === 'applicant') {
                $user->organizationProfile()?->delete();
                $user->individualProfile()?->delete();
                $user->userDocuments()?->delete();
                $user->delete();
            }
        });

        // Bust application listing and tracking caches
        \App\Services\CacheService::bustApplicationCaches();
        if ($trackingNumber) {
            \App\Services\CacheService::bustTrackingCache($trackingNumber);
        }

        return redirect()->route('admin.hcd.applications.archived')
            ->with('success', 'Application ' . $trackingNumber . ' and associated applicant data have been permanently deleted.');
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
            'user.organizationProfile.authorizedRepresentatives',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'payment',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->whereIn('name', ['Awaiting Payment', 'Payment Verification']);
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.awaiting_payment', compact('applications'));
    }

    /**
     * Display a listing of applications awaiting or completed certificate release.
     */
    public function releasingList()
    {
        $this->checkEvaluatorAccess(); // Block evaluators, only allow verifiers

        $applications = Application::with([
            'user.organizationProfile.authorizedRepresentatives',
            'user.individualProfile',
            'accreditationType',
            'latestStatus.status',
            'accreditation',
        ])
            ->whereHas('latestStatus', function ($query) {
                $query->whereHas('status', function ($q) {
                    $q->where('name', 'Approved');
                });
            })
            ->whereHas('accreditation', function ($query) {
                $query->whereNull('scanned_certificate');
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('admin.hcd.releasing', compact('applications'));
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

        $application->load(['user.organizationProfile.authorizedRepresentatives', 'user.individualProfile', 'accreditationType', 'interview']);

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
                        ->subject('Action Required: Submit Payment - ' . $application->tracking_number);
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
        ]);

        $accreditationType = $application->accreditationType;
        $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
        $fatProName = $application->user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';
        $recommendationPath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/recommendation_letter";

        // Upload signed recommendation letter if provided
        if ($request->hasFile('signed_recommendation_letter')) {
            if ($payment->signed_recommendation_letter && Storage::disk('local')->exists($payment->signed_recommendation_letter)) {
                Storage::disk('local')->delete($payment->signed_recommendation_letter);
            }
            $filename = "signed_recommendation_" . time() . ".pdf";
            $path = $request->file('signed_recommendation_letter')->storeAs($recommendationPath, $filename, 'local');
            $payment->signed_recommendation_letter = $path;
        }

        $payment->proof_of_payment_status  = $request->input('proof_of_payment_status');
        $payment->proof_of_payment_remarks = $request->input('proof_of_payment_remarks');


        $payment->save();

        $allApproved = ($payment->proof_of_payment_status === 'approved');

        if ($allApproved) {
            if (!$payment->signed_recommendation_letter) {
                return back()->with('error', 'You must upload the signed recommendation letter before final approval.')->withInput();
            }

            // ── PCT: Complete Step 7 (Recommendation & Payment), Start Step 8 (Certificate Issuance)
            $this->pctService->completeCurrentStep($application);
            $this->pctService->startStep($application, 8);

            // Create or update accreditation record
            $datePrefix = now()->format('ymd'); // YYMMDD
            $isRenewalOrReinstatement = in_array($application->application_type, ['renewal', 'reinstatement']);
            $today = now()->toDateString();

            if ($isRenewalOrReinstatement) {
                // For renewal/reinstatement: mark previous accreditation as expired and create a new one
                $prevAccreditation = Accreditation::where('user_id', $application->user_id)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($prevAccreditation) {
                    $oldAccNumber = $prevAccreditation->accreditation_number;
                    $parts = explode('-', $oldAccNumber);
                    $suffix = '000';
                    if (count($parts) > 0) {
                        $lastPart = end($parts);
                        preg_match('/\d+$/', $lastPart, $matches);
                        $suffix = isset($matches[0]) ? str_pad(substr($matches[0], -3), 3, '0', STR_PAD_LEFT) : '000';
                    }
                    $accNumber = "235-{$datePrefix}-{$suffix}";

                    // Mark previous accreditation as expired
                    $prevAccreditation->update([
                        'status' => 'expired',
                    ]);

                    $accreditation = Accreditation::create([
                        'user_id'               => $application->user_id,
                        'application_id'        => $application->id,
                        'accreditation_type_id' => $application->accreditation_type_id,
                        'accreditation_number'  => $accNumber,
                        'date_of_accreditation' => $today,
                        'validity_date'         => now()->addYears(3)->toDateString(),
                        'status'                => 'active',
                        'scanned_certificate'   => null,
                    ]);
                } else {
                    // Edge case: no previous accreditation found, create a new one
                    $accNumber = $this->generateNewAccreditationNumber($datePrefix);
                    $accreditation = Accreditation::create([
                        'user_id'               => $application->user_id,
                        'application_id'        => $application->id,
                        'accreditation_type_id' => $application->accreditation_type_id,
                        'accreditation_number'  => $accNumber,
                        'date_of_accreditation' => $today,
                        'validity_date'         => now()->addYears(3)->toDateString(),
                        'status'                => 'active',
                    ]);
                }
            } else {
                // For new applications: create a fresh accreditation record
                $accNumber = $this->generateNewAccreditationNumber($datePrefix);
                $accreditation = Accreditation::create([
                    'user_id'               => $application->user_id,
                    'application_id'        => $application->id,
                    'accreditation_type_id' => $application->accreditation_type_id,
                    'accreditation_number'  => $accNumber,
                    'date_of_accreditation' => $today,
                    'validity_date'         => now()->addYears(3)->toDateString(),
                    'status'                => 'active',
                ]);
            }

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
                        ->queue(new ApplicationResultEmail($application, 'passed', $accreditation));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Accreditation success email failed: ' . $e->getMessage());
                }
            }

            return redirect()->route('admin.hcd.applications.show', $application->id)->with('success', 'Application ' . $application->tracking_number . ' successfully approved and accredited! Number: ' . $accNumber);
        } else {
            // Rejections made: Notify applicant to resubmit
            $hasRejections = ($payment->proof_of_payment_status === 'rejected');

            if ($hasRejections) {
                // ── PCT: Pause Step 7 (waiting for payment re-upload)
                $this->pctService->pauseCurrentStep($application);

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

                // Log status: Awaiting Payment (so the applicant can resubmit on their tracker/portal)
                $awaitingPaymentStatus = ApplicationStatus::where('name', 'Awaiting Payment')->first();
                $statusId = $awaitingPaymentStatus ? $awaitingPaymentStatus->id : $application->latestStatus->status_id;

                // Add log entry
                ApplicationStatusLog::create([
                    'application_id' => $application->id,
                    'status_id'      => $statusId,
                    'updated_by'     => auth()->id(),
                    'remarks'        => 'Payment evaluation complete. Proof of payment was rejected, and applicant has been requested to resubmit.',
                ]);

                return redirect()->route('admin.hcd.applications.show', $application->id)->with('success', 'Payment evaluation submitted. Rejections were recorded and applicant has been notified.');
            } elseif (!$payment->proof_of_payment && $payment->signed_recommendation_letter) {
                // ── PCT: Pause Step 7 (waiting for applicant to submit payment)
                $this->pctService->pauseCurrentStep($application);
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
                    ->queue(new ApplicationResultEmail($application, 'not_passed', null));
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
        $allowedTypes = ['proof_of_payment', 'signed_recommendation_letter'];
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

    /**
     * Verifier uploads the scanned physical certificate (Step 8 completion).
     */
    public function uploadScannedCertificate(Request $request, \App\Models\Accreditation $accreditation)
    {
        $this->checkEvaluatorAccess();

        $request->validate([
            'scanned_certificate' => 'required|file|mimes:pdf|max:10240',
        ]);

        $application = $accreditation->application;

        $accreditationType = $application->accreditationType;
        $accreditationName = $accreditationType ? $accreditationType->name : 'Unknown';
        $sanitizedAccreditation = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $accreditationName));
        $fatProName = $application->user->name;
        $sanitizedFatPro = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $fatProName)) ?: 'unknown';
        $certificatePath = "public/{$sanitizedAccreditation}/{$sanitizedFatPro}/certificate";

        if ($request->hasFile('scanned_certificate')) {
            if ($accreditation->scanned_certificate && Storage::disk('local')->exists($accreditation->scanned_certificate)) {
                Storage::disk('local')->delete($accreditation->scanned_certificate);
            }
            $filename = "scanned_certificate_" . time() . ".pdf";
            $path = $request->file('scanned_certificate')->storeAs($certificatePath, $filename, 'local');
            $accreditation->scanned_certificate = $path;
            $accreditation->save();
        }

        // ── PCT: Complete Step 8 (Certificate Issuance)
        $this->pctService->completeCurrentStep($application);

        // Send Email to Applicant
        if ($application->user?->email) {
            try {
                Mail::send('emails.certificate_ready', ['application' => $application], function ($message) use ($application) {
                    $message->to($application->user->email)
                        ->subject('Ready for Pickup: Your Accreditation Certificate - ' . $application->tracking_number);
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Ready for pickup email failed: ' . $e->getMessage());
            }
        }

        // Add log entry
        ApplicationStatusLog::create([
            'application_id' => $application->id,
            'status_id'      => $application->latestStatus->status_id,
            'updated_by'     => auth()->id(),
            'remarks'        => 'Scanned certificate uploaded. Applicant has been emailed that the certificate is ready for pickup.',
        ]);

        return back()->with('success', 'Scanned certificate successfully uploaded. The applicant has been notified by email.');
    }

    /**
     * Securely serve scanned certificate files to authenticated admin users.
     */
    public function viewScannedCertificate(\App\Models\Accreditation $accreditation)
    {
        $path = $accreditation->scanned_certificate;
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'Scanned certificate not found.');
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
     * Generate a unique accreditation number for new accreditations.
     */
    private function generateNewAccreditationNumber(string $datePrefix): string
    {
        // Use a single SQL query to find the max numeric suffix — avoids loading the full table into PHP
        $maxFromDb = (int) Accreditation::selectRaw(
            "MAX(CAST(SPLIT_PART(accreditation_number, '-', 3) AS INTEGER))"
        )->value('max') ?? 0;

        $maxIncrement = max(46, $maxFromDb);

        do {
            $maxIncrement++;
            $suffixStr = str_pad($maxIncrement, 3, '0', STR_PAD_LEFT);
            $accNumber = "235-{$datePrefix}-{$suffixStr}";
        } while (Accreditation::where('accreditation_number', $accNumber)->exists());

        return $accNumber;
    }
}
