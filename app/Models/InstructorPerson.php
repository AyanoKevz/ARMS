<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * One human on a FATPro's instructor roster.
 *
 * The distinction from {@see Instructor} matters: that table holds a person's
 * SUBMISSION against one application — their service agreement, CV, credentials
 * and the evaluator's verdicts on them — and a renewal re-submits everybody, so
 * the same human gains a fresh row and a fresh id every cycle.
 *
 * Anything that must survive a renewal refers to this table instead: which NTC
 * declared whom, and which instructors conducted a training. Those records
 * would otherwise point at a roster entry that no longer appears to exist.
 */
class InstructorPerson extends Model
{
    protected $table = 'instructor_people';

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'ins_sex',
    ];

    /** The FATPro this person belongs to. */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Every per-application submission filed for this person, newest first. */
    public function records()
    {
        return $this->hasMany(Instructor::class)->orderByDesc('id');
    }

    /**
     * This person's submission against a given application, or their most
     * recent one when that application has none.
     *
     * An evaluator looking at a past training wants the credentials as they
     * stood for it, which live on the submission filed with that round's
     * application — not on whatever has been filed since.
     */
    public function recordFor(?int $applicationId = null): ?Instructor
    {
        $records = $this->relationLoaded('records')
            ? $this->records
            : $this->records()->with('credentials')->get();

        if ($applicationId) {
            $match = $records->firstWhere('application_id', $applicationId);

            if ($match) {
                return $match;
            }
        }

        return $records->first();
    }

    /**
     * Their credentials as short labels, e.g. "EMS · valid to Mar 04, 2027".
     *
     * Read off the submission filed with $applicationId where there is one, so
     * a past training shows the credentials that were current for it rather
     * than whatever has been filed since.
     *
     * @return array<int, string>
     */
    public function credentialLabelsFor(?int $applicationId = null): array
    {
        $record = $this->recordFor($applicationId);

        if (!$record) {
            return [];
        }

        return $record->credentials
            ->map(fn ($credential) => strtoupper($credential->type)
                . ($credential->validity_date
                    ? ' · valid to ' . $credential->validity_date->format('M d, Y')
                    : ''))
            ->values()
            ->all();
    }
    /** "Dela Cruz, Juan Santos" — how the roster and the dialogs list them. */
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
     * Is this person eligible to conduct a training ending on $asOf?
     *
     * Delegates to the submission that carries the documents, since eligibility
     * is a fact about what was filed and approved, not about the person.
     */
    public function ineligibilityReason(?Carbon $asOf = null, ?int $applicationId = null): ?string
    {
        $record = $this->recordFor($applicationId);

        if (!$record) {
            return 'No instructor record has been filed for this person.';
        }

        return $record->ineligibilityReason($asOf);
    }
}
