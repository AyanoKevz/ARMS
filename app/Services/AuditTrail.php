<?php

namespace App\Services;

use App\Models\Accreditation;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\Instructor;
use App\Models\NtcDocument;
use App\Models\NtcReport;
use App\Models\PostTrainingReport;
use App\Models\PtrDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes the admin audit trail.
 *
 * Every admin action that changes something is a named POST/DELETE route with
 * the record it acts on bound in the URL, so one map from route name to a
 * readable description covers them all — no controller has to remember to log.
 *
 * Logging must never cost the admin their action: every failure in here is
 * caught and written to the application log instead.
 */
class AuditTrail
{
    /**
     * Route name => [action key, what a person reads].
     *
     * Only these routes are recorded. A new admin action that should appear in
     * the trail is added here.
     */
    public const ACTIONS = [
        // ── Applications: decking, evaluation, interview ─────────────────────
        'admin.hcd.applications.update_evaluation'   => ['application.assigned',           'Assigned an evaluator / moved to evaluation'],
        'admin.hcd.documents.evaluate'               => ['application.document_evaluated', 'Evaluated an application document'],
        'admin.hcd.applications.evaluate_item'       => ['application.item_evaluated',     'Evaluated an application item'],
        'admin.hcd.applications.finalize_evaluation' => ['application.evaluation_saved',   'Saved the application evaluation'],
        'admin.hcd.applications.schedule_interview'  => ['interview.scheduled',            'Scheduled an interview'],
        'admin.hcd.applications.start_interview'     => ['interview.started',              'Started an interview'],
        'admin.hcd.applications.stop_interview'      => ['interview.stopped',              'Stopped an interview'],
        'admin.hcd.applications.interview_result'    => ['interview.result_recorded',      'Recorded an interview result'],
        'admin.hcd.applications.destroy'             => ['application.deleted',            'Deleted an application'],
        'admin.hcd.applications.unarchive'           => ['application.unarchived',         'Unarchived an application'],

        // ── Recommendation and payment ───────────────────────────────────────
        'admin.hcd.applications.generate_recommendation'           => ['application.recommendation_generated', 'Generated the recommendation'],
        'admin.hcd.applications.request_payment'                   => ['payment.requested',  'Requested payment'],
        'admin.hcd.applications.evaluate_payment'                  => ['payment.evaluated',  'Evaluated a proof of payment'],
        'admin.hcd.applications.archive_payment'                   => ['application.archived', 'Archived an application from payment'],
        'admin.accreditation.applications.generate_recommendation' => ['application.recommendation_generated', 'Generated the recommendation'],
        'admin.accreditation.applications.request_payment'         => ['payment.requested',  'Requested payment'],
        'admin.accreditation.applications.evaluate_payment'        => ['payment.evaluated',  'Evaluated a proof of payment'],
        'admin.accreditation.applications.archive_payment'         => ['application.archived', 'Archived an application from payment'],

        // ── Accreditations ───────────────────────────────────────────────────
        'admin.hcd.accreditations.revoke'                  => ['accreditation.revoked',     'Revoked an accreditation'],
        'admin.hcd.accreditations.archive'                 => ['accreditation.archived',    'Archived an accreditation'],
        'admin.hcd.accreditations.unarchive'               => ['accreditation.unarchived',  'Unarchived an accreditation'],
        'admin.hcd.accreditations.upload_scanned'          => ['accreditation.certificate_uploaded', 'Uploaded the scanned certificate'],
        'admin.accreditation.accreditations.upload_scanned' => ['accreditation.certificate_uploaded', 'Uploaded the scanned certificate'],

        // ── Instructors ──────────────────────────────────────────────────────
        'admin.hcd.instructors.request_update'  => ['instructor.update_requested', 'Requested an instructor update'],
        'admin.hcd.instructors.complete_update' => ['instructor.update_completed', 'Approved an instructor update'],
        'admin.hcd.instructors.reject_update'   => ['instructor.update_rejected',  'Rejected an instructor update'],

        // ── Training reports ─────────────────────────────────────────────────
        'admin.hcd.reports.ntc.documents.evaluate'                => ['ntc.document_evaluated',  'Evaluated an NTC document'],
        'admin.hcd.reports.ntc.finalize_evaluation'               => ['ntc.evaluation_saved',    'Saved the NTC evaluation'],
        'admin.hcd.reports.ntc.details.update'                    => ['ntc.details_corrected',   'Corrected the NTC details'],
        'admin.hcd.reports.post_training.documents.evaluate'      => ['ptr.document_evaluated',  'Evaluated a Post Training Report item'],
        'admin.hcd.reports.post_training.finalize_evaluation'     => ['ptr.evaluation_saved',    'Saved the Post Training Report evaluation'],

        // ── Admin accounts ───────────────────────────────────────────────────
        'admin.hcd.directory.admins.invite'           => ['admin.invited',          'Invited a new admin'],
        'admin.accreditation.directory.admins.invite' => ['admin.invited',          'Invited a new admin'],
        'profile.update'                              => ['account.profile_updated', self::COMMON_ACTIONS['account.profile_updated']],
        'profile.change_password'                     => ['account.password_changed', self::COMMON_ACTIONS['account.password_changed']],
    ];

    /** Recorded for every admin, whatever their role. */
    public const COMMON_ACTIONS = [
        'auth.login'               => 'Signed in',
        'auth.logout'              => 'Signed out',
        'account.profile_updated'  => 'Updated their profile',
        'account.password_changed' => 'Changed their password',
    ];

    /**
     * The actions each role can actually take, for the audit page's Action
     * filter — an Evaluator has no use for "Evaluated an NTC document". Mirrors
     * the role checks in the HCD controllers. A role not listed here (the Team
     * Lead) is offered every action.
     */
    public const ROLE_ACTIONS = [
        'Evaluator' => [
            'application.document_evaluated', 'application.item_evaluated', 'application.evaluation_saved',
            'interview.scheduled', 'interview.started', 'interview.stopped', 'interview.result_recorded',
            'application.recommendation_generated', 'application.archived', 'application.unarchived',
            'application.deleted',
            'accreditation.revoked', 'accreditation.archived', 'accreditation.unarchived',
            'instructor.update_requested', 'instructor.update_completed', 'instructor.update_rejected',
        ],
        'Training Evaluator' => [
            'ntc.document_evaluated', 'ntc.evaluation_saved', 'ntc.details_corrected',
            'ptr.document_evaluated', 'ptr.evaluation_saved',
        ],
    ];

    /**
     * The Action filter's options for one role: key => label, sorted by label.
     *
     * @return array<string, string>
     */
    public static function actionOptionsFor(string $role): array
    {
        $all = self::COMMON_ACTIONS;

        foreach (self::ACTIONS as [$key, $label]) {
            $all[$key] = $label;
        }

        if (isset(self::ROLE_ACTIONS[$role])) {
            $allowed = array_merge(array_keys(self::COMMON_ACTIONS), self::ROLE_ACTIONS[$role]);
            $all     = array_intersect_key($all, array_flip($allowed));
        }

        asort($all);

        return $all;
    }

    /**
     * Request fields worth keeping as the detail of a decision. Anything else —
     * passwords above all, and uploaded files — is never stored.
     */
    private const DETAIL_FIELDS = [
        'status', 'action', 'result', 'remarks', 'reason', 'mode', 'venue',
        'interview_date', 'interview_time', 'date', 'time', 'item_type',
        'proof_of_payment_status', 'proof_of_payment_remarks', 'email',
    ];

    /** Array inputs that carry many verdicts at once; stored as a tally. */
    private const VERDICT_LISTS = [
        'evaluations', 'instructor_evaluations', 'credential_evaluations', 'cv_evaluations',
    ];

    /** The role name an admin holds, or null for anyone who is not an admin. */
    public static function roleOf(?User $user): ?string
    {
        return $user?->loadMissing('adminProfile.adminRole')->adminProfile?->adminRole?->name;
    }

    /**
     * Record one admin action from a completed request.
     */
    public static function recordRequest(Request $request, User $actor, string $routeName): void
    {
        [$action, $description] = self::ACTIONS[$routeName];

        $subject = self::subjectFrom($request);

        self::record($actor, $action, $description, $subject, self::detailsFrom($request), $request->ip());
    }

    /**
     * Record one admin action. Silently skipped for a non-admin.
     */
    public static function record(
        User $actor,
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?string $ip = null
    ): void {
        try {
            $role = self::roleOf($actor);

            if (!$role) {
                return;
            }

            [$label, $url] = $subject ? self::describe($subject) : [null, null];

            AuditLog::create([
                'user_id'       => $actor->id,
                'actor_name'    => Str::limit((string) $actor->name, 250, ''),
                'actor_email'   => Str::limit((string) $actor->email, 250, ''),
                'admin_role'    => Str::limit($role, 50, ''),
                'action'        => $action,
                'description'   => Str::limit($description, 250, ''),
                'subject_type'  => $subject ? class_basename($subject) : null,
                'subject_id'    => $subject?->getKey(),
                'subject_label' => $label !== null ? Str::limit($label, 250, '') : null,
                'subject_url'   => $url,
                'properties'    => $properties ?: null,
                'ip_address'    => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log write failed: ' . $e->getMessage());
        }
    }

    /** The first record bound in the route — what the action was done to. */
    private static function subjectFrom(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * The decision details worth keeping: whitelisted scalar fields, and a
     * tally (e.g. 3 approved, 1 rejected) for a batch of verdicts.
     */
    private static function detailsFrom(Request $request): array
    {
        $details = [];

        foreach (self::DETAIL_FIELDS as $field) {
            $value = $request->input($field);

            if (is_scalar($value) && trim((string) $value) !== '') {
                $details[$field] = Str::limit(trim((string) $value), 300);
            }
        }

        // Who an application was handed to, by name rather than a bare id.
        if ($evaluatorId = $request->input('evaluator_id')) {
            $details['assigned_to'] = User::find($evaluatorId)?->name ?? ('User #' . $evaluatorId);
        }

        foreach (self::VERDICT_LISTS as $list) {
            $items = $request->input($list);

            if (!is_array($items)) {
                continue;
            }

            $tally = [];

            foreach ($items as $item) {
                $status = is_array($item) ? ($item['status'] ?? null) : null;

                if (is_string($status) && $status !== '') {
                    $tally[$status] = ($tally[$status] ?? 0) + 1;
                }
            }

            if ($tally) {
                $details[$list] = $tally;
            }
        }

        return $details;
    }

    /**
     * A readable name for the record and a link to where it can be opened.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function describe(Model $subject): array
    {
        try {
            // Load what the labels below read, in one go per relation.
            match (true) {
                $subject instanceof ApplicationDocument => $subject->loadMissing(['application', 'documentField']),
                $subject instanceof NtcDocument         => $subject->loadMissing(['ntcReport', 'documentType']),
                $subject instanceof PtrDocument         => $subject->loadMissing(['postTrainingReport', 'documentType']),
                default                                 => null,
            };

            return match (true) {
                $subject instanceof Application => [
                    $subject->tracking_number,
                    route('admin.hcd.applications.show', $subject->id),
                ],
                $subject instanceof ApplicationDocument => [
                    trim(($subject->application?->tracking_number ?? 'Application') . ' — ' . ($subject->documentField?->name ?? 'Document')),
                    $subject->application_id ? route('admin.hcd.applications.show', $subject->application_id) : null,
                ],
                $subject instanceof Accreditation => [
                    $subject->accreditation_number,
                    $subject->application_id ? route('admin.hcd.applications.show', $subject->application_id) : null,
                ],
                $subject instanceof Instructor => [
                    'Instructor ' . $subject->fullName(),
                    $subject->application_id ? route('admin.hcd.applications.show', $subject->application_id) : null,
                ],
                $subject instanceof NtcReport => [
                    $subject->reference_number,
                    route('admin.hcd.reports.ntc.show', $subject->id),
                ],
                $subject instanceof NtcDocument => [
                    trim(($subject->ntcReport?->reference_number ?? 'NTC') . ' — ' . ($subject->documentType?->name ?? 'Document')),
                    route('admin.hcd.reports.ntc.show', $subject->ntc_report_id),
                ],
                $subject instanceof PostTrainingReport => [
                    $subject->reference_number,
                    route('admin.hcd.reports.post_training.show', $subject->id),
                ],
                $subject instanceof PtrDocument => [
                    trim(($subject->postTrainingReport?->reference_number ?? 'Post Training Report') . ' — ' . ($subject->documentType?->name ?? 'Item')),
                    route('admin.hcd.reports.post_training.show', $subject->post_training_report_id),
                ],
                default => [class_basename($subject) . ' #' . $subject->getKey(), null],
            };
        } catch (\Throwable $e) {
            return [class_basename($subject) . ' #' . $subject->getKey(), null];
        }
    }
}
