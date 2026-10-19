<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditTrail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The HCD admin audit trail.
 *
 * Each role sees only its own role's entries: a Team Lead sees what Team Leads
 * did, an Evaluator what Evaluators did, a Training Evaluator what Training
 * Evaluators did. The role is taken from the signed-in account, never from the
 * request, so it cannot be widened by editing a URL.
 *
 * The trail grows with every action, so unlike the other lists it is paged on
 * the server: the browser only ever receives one page of rows.
 */
class AuditLogController extends Controller
{
    /** Largest page the table may ask for. */
    private const MAX_PAGE = 100;

    /** Sortable table columns, by DataTables column index. */
    private const SORTABLE = [
        0 => 'created_at',
        1 => 'actor_name',
        2 => 'description',
        3 => 'subject_label',
    ];

    public function index()
    {
        $role = $this->viewerRole();

        // Only the actions this role can take (the Team Lead gets them all).
        // Built from the known actions rather than queried, so opening the
        // page costs nothing however large the trail grows.
        $actions = AuditTrail::actionOptionsFor($role);

        return view('admin.hcd.audit_logs', compact('role', 'actions'));
    }

    /**
     * One page of entries for the table (DataTables server-side protocol).
     */
    public function data(Request $request): JsonResponse
    {
        $role = $this->viewerRole();

        $base     = AuditLog::forRole($role);
        $filtered = $this->applyFilters(AuditLog::forRole($role), $request);

        $length = (int) $request->input('length', 25);
        $length = $length < 1 ? 25 : min($length, self::MAX_PAGE);
        $start  = max(0, (int) $request->input('start', 0));

        $column    = self::SORTABLE[(int) $request->input('order.0.column', 0)] ?? 'created_at';
        $direction = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = (clone $filtered)
            ->orderBy($column, $direction)
            // Entries written in the same second keep a stable order.
            ->orderBy('id', $direction)
            ->skip($start)
            ->take($length)
            ->get();

        return response()->json([
            'draw'            => (int) $request->input('draw', 0),
            'recordsTotal'    => $base->count(),
            'recordsFiltered' => $filtered->count(),
            'data'            => $rows->map(fn (AuditLog $log) => $this->row($log))->all(),
        ]);
    }

    /**
     * The signed-in admin's role, if it is one with an audit page. The role is
     * the whole of the access rule, so it comes from the account alone.
     */
    private function viewerRole(): string
    {
        $role = AuditTrail::roleOf(auth()->user());

        if (!in_array($role, AuditLog::VIEWABLE_ROLES, true)) {
            abort(403, 'Unauthorized action. Your role does not have an audit log.');
        }

        return $role;
    }

    /** Search box, action filter and date range. */
    private function applyFilters($query, Request $request)
    {
        $search = trim((string) $request->input('search.value', $request->input('q', '')));

        if ($search !== '') {
            // LOWER() on both sides: PostgreSQL's LIKE is case-sensitive.
            $term = '%' . mb_strtolower(addcslashes($search, '%_\\')) . '%';

            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(actor_name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(actor_email) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(subject_label) LIKE ?', [$term]);
            });
        }

        if ($action = $request->input('action_filter')) {
            $query->where('action', (string) $action);
        }

        // Whole days in the app's timezone, as a range the
        // (admin_role, created_at) index can serve.
        if ($from = $this->date($request->input('date_from'))) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $this->date($request->input('date_to'))) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query;
    }

    private function date($value): ?Carbon
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** One table row, as escaped HTML per cell. */
    private function row(AuditLog $log): array
    {
        $record = $log->subject_label ? e($log->subject_label) : '<span class="text-muted">—</span>';

        if ($log->subject_label && $log->subject_url) {
            $record = '<a href="' . e($log->subject_url) . '">' . e($log->subject_label) . '</a>';
        }

        $details = $this->detailsText($log->properties);

        return [
            '<span style="white-space:nowrap;">' . e($log->created_at?->format('M d, Y')) . '</span>'
                . '<div style="font-size:.75rem;color:#888;">' . e($log->created_at?->format('h:i:s A')) . '</div>',
            '<strong>' . e($log->actor_name) . '</strong>'
                . '<div style="font-size:.75rem;color:#888;">' . e($log->actor_email) . '</div>',
            e($log->description),
            $record,
            $details !== '' ? '<span style="font-size:.8rem;">' . e($details) . '</span>' : '<span class="text-muted">—</span>',
            // The IP address is still recorded, just not shown.
        ];
    }

    /** "Status: approved · Remarks: …" from the stored details. */
    private function detailsText(?array $properties): string
    {
        if (!$properties) {
            return '';
        }

        $parts = [];

        foreach ($properties as $key => $value) {
            $label = ucwords(str_replace('_', ' ', (string) $key));

            if (is_array($value)) {
                $value = collect($value)->map(fn ($n, $status) => $n . ' ' . $status)->implode(', ');
            }

            $parts[] = $label . ': ' . $value;
        }

        return implode(' · ', $parts);
    }
}
