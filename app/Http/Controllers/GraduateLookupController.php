<?php

namespace App\Http\Controllers;

use App\Mail\GraduateLookupAccessEmail;
use App\Models\Accreditation;
use App\Models\GraduateSearch;
use App\Models\GraduateVisitor;
use App\Models\PostTrainingReport;
use App\Models\PtrParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The public Graduate Lookup.
 *
 * A graduate is a participant on an ACCEPTED post training report. Anyone may
 * look them up, but not anonymously:
 *
 *   1. the visitor gives a name, organization, e-mail and purpose;
 *   2. a signed, expiring link is e-mailed to that address;
 *   3. opening it marks the e-mail verified and unlocks the search page for
 *      this browser session, until the link itself would have expired;
 *   4. every search is logged against the visitor, for HCD to review.
 *
 * One search box answers two ways at once:
 *
 *   - by graduate — name or certificate number — listing each matching
 *     graduate with who trained them and when;
 *   - by training provider — organization name or accreditation number —
 *     listing that provider's graduates, training by training.
 *
 * There is no browse-everything view, and both answers are capped, so the
 * list cannot be pulled out in one go.
 *
 * Nothing here is tied to one accreditation type. FATPros are the first to
 * file post training reports; any other type that does later shows up with
 * no change, since a graduate is found through the report, not the type.
 */
class GraduateLookupController extends Controller
{
    /** Session key holding the verified visitor and when their access ends. */
    private const SESSION_KEY = 'graduate_lookup';

    /** Shortest search term accepted; anything shorter matches too much. */
    private const MIN_TERM = 3;

    /** More providers than this match → ask for a narrower search. */
    private const MAX_PROVIDERS = 10;

    /** Graduates listed by name match; past this the visitor is asked to narrow it. */
    private const MAX_GRADUATES = 50;

    /** The participant columns the public page may show. */
    private const PUBLIC_COLUMNS = [
        'id', 'post_training_report_id', 'row_no', 'certificate_number',
        'first_name', 'middle_name', 'last_name', 'suffix',
    ];

    /** Step 1: who is asking, and why. */
    public function create()
    {
        return view('landing.graduates.request');
    }

    /** Step 2: record the request and e-mail the access link. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'organization' => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
            'purpose'      => ['required', 'string', 'max:500'],
        ], [], [
            'name' => 'full name',
        ]);

        $visitor = GraduateVisitor::create($data + [
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        $accessUrl = URL::temporarySignedRoute(
            'graduates.access',
            now()->addHours(GraduateVisitor::LINK_VALID_HOURS),
            ['visitor' => $visitor->id]
        );

        try {
            Mail::to($visitor->email)->send(new GraduateLookupAccessEmail(
                $accessUrl,
                $visitor->name,
                GraduateVisitor::LINK_VALID_HOURS
            ));
        } catch (\Exception $e) {
            // Nobody can open a link that never arrived, so the request is not
            // worth keeping in the log as an unverified visit.
            $visitor->delete();
            Log::error('SMTP Error during graduate lookup request: ' . $e->getMessage());

            return back()->withInput()
                ->with('error', 'We could not send the email right now. Please try again in a few minutes.');
        }

        return redirect()->route('graduates.request')
            ->with('sent_to', $visitor->email);
    }

    /** Step 3: the e-mailed link. Verifies the e-mail and opens the search. */
    public function access(Request $request, GraduateVisitor $visitor)
    {
        // Checked here rather than by the `signed` middleware so an expired
        // link lands on a page that explains it instead of a bare 403.
        if (! $request->hasValidSignature()) {
            return redirect()->route('graduates.request')
                ->with('error', 'This link is invalid or has expired. Please request a new one.');
        }

        $now = now();

        $visitor->forceFill([
            'verified_at'      => $visitor->verified_at ?? $now,
            'last_accessed_at' => $now,
        ])->save();

        // Access ends when the link does, however long the session lives.
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, [
            'visitor_id' => $visitor->id,
            'expires_at' => (int) $request->query('expires'),
        ]);

        return redirect()->route('graduates.search');
    }

    /** Step 4: search graduates and providers, and list what matched. */
    public function search(Request $request)
    {
        $visitor = $this->sessionVisitor($request);

        if (! $visitor) {
            return redirect()->route('graduates.request')
                ->with('error', 'Please request access to search the list of graduates.');
        }

        $term              = trim((string) $request->input('q', ''));
        $searched          = $request->has('q');
        $graduates         = collect();
        $graduatesTruncated = false;
        $providers         = collect();
        $tooMany           = false;
        $error             = null;

        if ($searched) {
            if (mb_strlen($term) < self::MIN_TERM) {
                $error = 'Please enter at least ' . self::MIN_TERM . ' characters.';
            } elseif (mb_strlen($term) > 100) {
                $error = 'Search is limited to 100 characters.';
            } else {
                [$graduates, $graduatesTruncated] = $this->findMatchingGraduates($term);
                [$providers, $tooMany]            = $this->findProviders($term);

                GraduateSearch::create([
                    'graduate_visitor_id' => $visitor->id,
                    'search_term'         => $term,
                    'results_count'       => $graduates->count() + $providers->sum('graduate_count'),
                    'ip_address'          => $request->ip(),
                ]);
            }
        }

        return view('landing.graduates.search', compact(
            'visitor', 'term', 'searched', 'graduates', 'graduatesTruncated', 'providers', 'tooMany', 'error'
        ));
    }

    /** The verified visitor of this session, while their access lasts. */
    private function sessionVisitor(Request $request): ?GraduateVisitor
    {
        $access = $request->session()->get(self::SESSION_KEY);

        if (! is_array($access) || ($access['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return GraduateVisitor::find($access['visitor_id'] ?? null);
    }

    /**
     * Graduates matching the term by certificate number or name.
     *
     * A certificate number must match exactly. A name matches when every word
     * of the term appears in one of the name parts, so "maria santos" finds
     * Maria Cruz Santos and "santos maria" finds her too.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: bool}  [graduates, truncated]
     */
    private function findMatchingGraduates(string $term): array
    {
        $words = preg_split('/\s+/', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY);

        $graduates = PtrParticipant::query()
            ->where('status', 'approved')
            ->whereHas('postTrainingReport', fn ($q) => $q->where('status', 'accepted'))
            ->where(function ($q) use ($term, $words) {
                $q->whereRaw('LOWER(certificate_number) = ?', [mb_strtolower($term)])
                  ->orWhere(function ($name) use ($words) {
                      foreach ($words as $word) {
                          $like = '%' . addcslashes($word, '%_\\') . '%';

                          $name->where(function ($part) use ($like) {
                              $part->whereRaw('LOWER(first_name) LIKE ?', [$like])
                                   ->orWhereRaw('LOWER(middle_name) LIKE ?', [$like])
                                   ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                                   ->orWhereRaw('LOWER(suffix) LIKE ?', [$like]);
                          });
                      }
                  });
            })
            // Only what the page shows: no contact details, age or employer
            // ever leave the database for a public request.
            ->select(self::PUBLIC_COLUMNS)
            ->with([
                'postTrainingReport.accreditation.user.organizationProfile',
                'postTrainingReport.accreditation.accreditationType',
                'postTrainingReport.ntcReport.trainingType',
                'postTrainingReport.ntcReport.trainingDates',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::MAX_GRADUATES + 1)
            ->get();

        $truncated = $graduates->count() > self::MAX_GRADUATES;

        return [$graduates->take(self::MAX_GRADUATES)->values(), $truncated];
    }

    /** The provider's name as the public pages show it. */
    public static function providerName(?Accreditation $accreditation): string
    {
        return $accreditation?->user?->organizationProfile?->name ?? 'N/A';
    }

    /**
     * Providers matching the term, each with its graduates grouped by training.
     *
     * Matches an accreditation number exactly, or part of the provider's name.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: bool}  [providers, tooMany]
     */
    private function findProviders(string $term): array
    {
        // LOWER() on both sides: PostgreSQL's LIKE is case-sensitive.
        $like = '%' . mb_strtolower(addcslashes($term, '%_\\')) . '%';

        $accreditationIds = Accreditation::query()
            ->whereIn('id', PostTrainingReport::where('status', 'accepted')->select('accreditation_id'))
            ->where(function ($q) use ($term, $like) {
                $q->whereRaw('UPPER(accreditation_number) = ?', [mb_strtoupper($term)])
                  ->orWhereHas('user.organizationProfile', fn ($o) => $o->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->limit(self::MAX_PROVIDERS + 1)
            ->pluck('id');

        if ($accreditationIds->count() > self::MAX_PROVIDERS) {
            return [collect(), true];
        }

        $reports = PostTrainingReport::query()
            ->where('status', 'accepted')
            ->whereIn('accreditation_id', $accreditationIds)
            ->with([
                'accreditation.user.organizationProfile',
                'accreditation.accreditationType',
                'ntcReport.trainingType',
                'ntcReport.trainingMode',
                'ntcReport.trainingDates',
                'participants' => fn ($q) => $q->where('status', 'approved')->select(self::PUBLIC_COLUMNS),
            ])
            ->get()
            ->filter(fn ($report) => $report->participants->isNotEmpty());

        $providers = $reports
            ->groupBy('accreditation_id')
            ->map(function ($group) {
                $accreditation = $group->first()->accreditation;

                return [
                    'name'                 => self::providerName($accreditation),
                    'accreditation_type'   => $accreditation?->accreditationType?->name ?? 'Accredited Training Provider',
                    'accreditation_number' => $accreditation?->accreditation_number,
                    'trainings'            => $group
                        ->sortByDesc(fn ($report) => $report->ntcReport?->training_start_date)
                        ->values(),
                    'graduate_count'       => $group->sum(fn ($report) => $report->participants->count()),
                ];
            })
            ->sortBy('name')
            ->values();

        return [$providers, false];
    }
}
