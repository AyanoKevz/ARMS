<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'instructor_person_id',
        'first_name',
        'middle_name',
        'last_name',
        'ins_sex',
        'service_agreement_path',
        'cv_path',
        'status',
        'remarks',
        'cv_status',
        'cv_remarks',
        'update_request_status',
        'update_request_reason',
        'update_request_fields',
    ];

    protected $casts = [
        'update_request_fields' => 'array',
    ];

    /**
     * The FATPro applicant this instructor belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The application this instructor belongs to.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * The human this submission is for.
     *
     * A renewal files a fresh row for the same person, so this — not the row's
     * own id — is what identifies who the instructor actually is.
     */
    public function person()
    {
        return $this->belongsTo(InstructorPerson::class, 'instructor_person_id');
    }

    /**
     * All credentials attached to this instructor.
     */
    public function credentials()
    {
        return $this->hasMany(InstructorCredential::class);
    }

    /**
     * Whether the CV clears evaluation.
     *
     * An instructor with no CV on file has nothing to evaluate, so it counts as
     * settled — otherwise the default 'pending' would block every approval gate
     * for rosters that never carried a CV.
     */
    public function cvApproved(): bool
    {
        return !$this->cv_path || $this->cv_status === 'approved';
    }

    /**
     * Whether the CV is actively blocking — uploaded, and not yet approved.
     */
    public function cvRejected(): bool
    {
        return (bool) $this->cv_path && in_array($this->cv_status, ['rejected', 'returned'], true);
    }

    /**
     * The instructor roster attached to a FATPro's CURRENT accreditation.
     *
     * Every application carries its own copy of the roster — submitting a renewal
     * or reinstatement files each instructor afresh against the new application_id
     * — so a FATPro who has renewed has several rows per person, tied together by
     * instructor_person_id.
     *
     * Scoping to the application behind the active accreditation shows exactly one
     * entry per instructor, and self-corrects: once a renewal is approved it
     * becomes the active accreditation and its roster takes over automatically.
     *
     * Shared by the applicant dashboard and the FATPRO Instructor list so the two
     * pages cannot disagree about who is on the roster.
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function accreditedRosterFor(int $userId)
    {
        $accreditedApplicationId = Accreditation::where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('application_id');

        $query = static::where('user_id', $userId)->with(['credentials', 'person']);

        if ($accreditedApplicationId) {
            $query->where(function ($q) use ($accreditedApplicationId) {
                $q->where('application_id', $accreditedApplicationId)
                    // An instructor added from the portal is attached to the
                    // application currently under evaluation, which is not the
                    // accredited one during a renewal. Keep them visible to the
                    // FATPro while they wait for the evaluator.
                    ->orWhere('update_request_status', 'pending_review');
            });
        } else {
            // No accreditation yet (first-time applicant): there is no accredited
            // roster to scope to, so fall back to whatever has been evaluated
            // rather than showing an empty list — plus anything the FATPro has
            // deliberately submitted for review from the portal.
            $query->where(function ($q) {
                $q->where(function ($evaluated) {
                    $evaluated->where('status', '!=', 'pending')
                        ->whereDoesntHave('credentials', fn ($c) => $c->where('status', 'pending'));
                })->orWhere('update_request_status', 'pending_review');
            });
        }

        return $query->orderBy('id', 'desc')
            ->get()
            // One entry per human. Keyed on the person where there is one, and
            // falling back to the spelling only for rows filed before people
            // existed — which is the weaker test, since a middle name present on
            // one copy and blank on another reads as two different instructors.
            ->unique(fn ($item) => $item->instructor_person_id
                ? 'person:' . $item->instructor_person_id
                : 'name:' . strtolower(
                    trim($item->first_name) . '|' . trim($item->middle_name) . '|' . trim($item->last_name)
                ))
            ->sortBy('last_name')
            ->values();
    }

    /**
     * May this instructor be declared on an NTC for a training ending on $asOf?
     *
     * "Active with no expired credentials" means three things at once: the
     * instructor themselves cleared evaluation, their CV is not blocking, and
     * every credential they have on file is approved and still valid on the
     * LAST day of the training — not merely on the day the NTC is filed. A
     * credential that lapses midway through a four-day course would otherwise
     * slip through, because InstructorCredentialExpiryCheck only flips a row to
     * 'expired' once the date has actually passed.
     *
     * A credential with no validity_date on file cannot be shown to have
     * expired, so it passes on its date and is judged on its status alone.
     *
     * @param  \Carbon\Carbon|null  $asOf  Last training day; defaults to today.
     */
    public function isEligibleToConduct(?Carbon $asOf = null): bool
    {
        return $this->ineligibilityReason($asOf) === null;
    }

    /**
     * Why this instructor cannot be declared, or null if they can be.
     *
     * Returned as prose so the NTC form can show a disabled option with the
     * reason attached, rather than silently dropping people off the roster and
     * leaving the FATPro to guess who is missing and why.
     *
     * @param  \Carbon\Carbon|null  $asOf  Last training day; defaults to today.
     */
    public function ineligibilityReason(?Carbon $asOf = null): ?string
    {
        $asOf = ($asOf ?: Carbon::today())->copy()->startOfDay();

        if ($this->status !== 'approved') {
            return 'Instructor record is ' . ($this->status ?: 'pending') . ', not approved.';
        }

        if (!$this->cvApproved()) {
            return 'CV has not been approved.';
        }

        $credentials = $this->relationLoaded('credentials')
            ? $this->credentials
            : $this->credentials()->get();

        foreach ($credentials as $credential) {
            if ($credential->status !== 'approved') {
                return strtoupper($credential->type) . ' credential is '
                    . ($credential->status ?: 'pending') . ', not approved.';
            }

            if ($credential->validity_date
                && $credential->validity_date->copy()->startOfDay()->lessThan($asOf)) {
                return strtoupper($credential->type) . ' credential expires '
                    . $credential->validity_date->format('M d, Y')
                    . ', before the last training day.';
            }
        }

        return null;
    }

    /**
     * The soonest any credential on file stops being valid, or null if none
     * carries an expiry.
     *
     * The NTC form needs this client-side: eligibility is judged against the
     * LAST training day, but the form does not know that day until the FATPro
     * has picked it. Emitting the date lets the picker grey someone out the
     * moment the chosen dates run past their credentials, instead of letting
     * them submit and be refused by resolveInstructors().
     */
    public function earliestCredentialExpiry(): ?Carbon
    {
        $credentials = $this->relationLoaded('credentials')
            ? $this->credentials
            : $this->credentials()->get();

        return $credentials->pluck('validity_date')->filter()->min();
    }

    /**
     * The accredited roster with an eligibility verdict attached to each row.
     *
     * Wraps accreditedRosterFor rather than filtering it, so the NTC form can
     * render the ineligible entries greyed out with their reason instead of
     * hiding them. Callers that only want the selectable ones filter on
     * ->isEligibleToConduct().
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function rosterWithEligibilityFor(int $userId, ?Carbon $asOf = null)
    {
        return static::accreditedRosterFor($userId)->each(function ($instructor) use ($asOf) {
            $instructor->ineligibility_reason = $instructor->ineligibilityReason($asOf);
        });
    }

    /**
     * The person this submission is for, keyed the way the pickers post it.
     *
     * A row filed before people existed has none; such a row cannot be chosen
     * for a training, which the pickers express by leaving it unselectable.
     */
    public function personKey(): ?int
    {
        return $this->instructor_person_id;
    }

    /** "Dela Cruz, Juan Santos" — how the rosters list them. */
    public function listingName(): string
    {
        return trim($this->last_name . ', ' . $this->first_name . ' ' . $this->middle_name);
    }

    /** "Juan Dela Cruz" — how prose and emails name them. */
    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Convenience: get credential by type string.
     */
    public function credential(string $type): ?InstructorCredential
    {
        if ($this->relationLoaded('credentials')) {
            return $this->credentials->firstWhere('type', $type);
        }

        return $this->credentials()->where('type', $type)->first();
    }
}
