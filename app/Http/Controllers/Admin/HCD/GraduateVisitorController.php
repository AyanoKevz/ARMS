<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Models\GraduateVisitor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who has used the public Graduate Lookup: each request for access, whether
 * the e-mail was verified, and what the visitor searched for.
 *
 * Paged on the server like the audit trail, since a public page keeps adding
 * to it.
 */
class GraduateVisitorController extends Controller
{
    /** Largest page the table may ask for. */
    private const MAX_PAGE = 100;

    /** Searches listed under each visitor; the count covers the rest. */
    private const SEARCHES_SHOWN = 5;

    /** Sortable table columns, by DataTables column index. */
    private const SORTABLE = [
        0 => 'created_at',
        1 => 'name',
        2 => 'organization',
        4 => 'verified_at',
    ];

    public function index()
    {
        $this->authorizeViewer();

        return view('admin.hcd.graduate_visitors');
    }

    /**
     * One page of visitors for the table (DataTables server-side protocol).
     */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeViewer();

        $filtered = $this->applyFilters(GraduateVisitor::query(), $request);

        $length = (int) $request->input('length', 25);
        $length = $length < 1 ? 25 : min($length, self::MAX_PAGE);
        $start  = max(0, (int) $request->input('start', 0));

        $column    = self::SORTABLE[(int) $request->input('order.0.column', 0)] ?? 'created_at';
        $direction = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = (clone $filtered)
            ->withCount('searches')
            ->with(['searches' => fn ($q) => $q->select(['id', 'graduate_visitor_id', 'search_term', 'results_count', 'created_at'])])
            ->orderBy($column, $direction)
            ->orderBy('id', $direction)
            ->skip($start)
            ->take($length)
            ->get();

        return response()->json([
            'draw'            => (int) $request->input('draw', 0),
            'recordsTotal'    => GraduateVisitor::count(),
            'recordsFiltered' => $filtered->count(),
            'data'            => $rows->map(fn (GraduateVisitor $visitor) => $this->row($visitor))->all(),
        ]);
    }

    private function authorizeViewer(): void
    {
        $role = \App\Support\AdminAccess::hcdRole(auth()->user());

        if (!in_array($role, GraduateVisitor::VIEWABLE_ROLES, true)) {
            abort(403, 'Unauthorized action.');
        }
    }

    /** Search box, verification filter and date range. */
    private function applyFilters($query, Request $request)
    {
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            // LOWER() on both sides: PostgreSQL's LIKE is case-sensitive.
            $term = '%' . mb_strtolower(addcslashes($search, '%_\\')) . '%';

            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(organization) LIKE ?', [$term])
                  ->orWhereRaw('LOWER(purpose) LIKE ?', [$term])
                  ->orWhereHas('searches', fn ($s) => $s->whereRaw('LOWER(search_term) LIKE ?', [$term]));
            });
        }

        $status = $request->input('status_filter');

        if ($status === 'verified') {
            $query->whereNotNull('verified_at');
        } elseif ($status === 'unverified') {
            $query->whereNull('verified_at');
        }

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
    private function row(GraduateVisitor $visitor): array
    {
        $muted = fn (string $text) => '<div style="font-size:.75rem;color:#888;">' . e($text) . '</div>';

        $verified = $visitor->verified_at
            ? '<span class="badge bg-success">Verified</span>'
                . $muted($visitor->verified_at->format('M d, Y h:i A'))
                . ($visitor->last_accessed_at && !$visitor->last_accessed_at->equalTo($visitor->verified_at)
                    ? $muted('Last opened ' . $visitor->last_accessed_at->format('M d, Y h:i A'))
                    : '')
            : '<span class="badge bg-secondary">Not verified</span>';

        return [
            '<span style="white-space:nowrap;">' . e($visitor->created_at?->format('M d, Y')) . '</span>'
                . $muted($visitor->created_at?->format('h:i:s A') ?? ''),
            '<strong>' . e($visitor->name) . '</strong>' . $muted($visitor->email),
            e($visitor->organization),
            '<span style="font-size:.85rem;">' . e($visitor->purpose) . '</span>',
            $verified,
            $this->searchesCell($visitor),
        ];
    }

    private function searchesCell(GraduateVisitor $visitor): string
    {
        if ($visitor->searches_count === 0) {
            return '<span class="text-muted">—</span>';
        }

        $items = $visitor->searches->take(self::SEARCHES_SHOWN)->map(function ($search) {
            return '<li>"' . e($search->search_term) . '" '
                . '<span style="color:#888;">· ' . e($search->results_count) . ' result(s) · '
                . e($search->created_at?->format('M d, h:i A')) . '</span></li>';
        })->implode('');

        $more = $visitor->searches_count > self::SEARCHES_SHOWN
            ? '<div style="color:#888;">+ ' . ($visitor->searches_count - self::SEARCHES_SHOWN) . ' more</div>'
            : '';

        return '<strong>' . $visitor->searches_count . '</strong> search(es)'
            . '<ul class="mb-0 ps-3" style="font-size:.78rem;">' . $items . '</ul>' . $more;
    }
}
