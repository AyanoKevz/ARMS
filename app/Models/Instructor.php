<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'first_name',
        'middle_name',
        'last_name',
        'ins_sex',
        'service_agreement_path',
        'status',
        'remarks',
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
     * All credentials attached to this instructor.
     */
    public function credentials()
    {
        return $this->hasMany(InstructorCredential::class);
    }

    /**
     * The instructor roster attached to a FATPro's CURRENT accreditation.
     *
     * Every application carries its own copy of the roster — submitting a renewal
     * or reinstatement clones each instructor against the new application_id — so
     * a FATPro who has renewed has two rows per person. They are not reliably
     * separated by name either: a middle name filled in on one copy and blank on
     * the other reads as two different people.
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

        $query = static::where('user_id', $userId)->with('credentials');

        if ($accreditedApplicationId) {
            $query->where('application_id', $accreditedApplicationId);
        } else {
            // No accreditation yet (first-time applicant): there is no accredited
            // roster to scope to, so fall back to whatever has been evaluated
            // rather than showing an empty list.
            $query->where('status', '!=', 'pending')
                ->whereDoesntHave('credentials', fn ($q) => $q->where('status', 'pending'));
        }

        return $query->orderBy('id', 'desc')
            ->get()
            // Safety net for legacy rows that predate application scoping.
            ->unique(fn ($item) => strtolower(
                trim($item->first_name) . '|' . trim($item->middle_name) . '|' . trim($item->last_name)
            ))
            ->sortBy('last_name')
            ->values();
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
