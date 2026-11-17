<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Division-aware answers to "who is this admin and what may they lead".
 *
 * Both HCD and Accreditation have a Team Lead, and they share one `Team Lead`
 * admin role row: the division is what tells them apart. A Team Lead check
 * that reads only the role name therefore lets an Accreditation Team Lead
 * through HCD's Team Lead gates (and the other way round). Every check that
 * needs to know "Team Lead of WHICH division" goes through here.
 */
final class AdminAccess
{
    public const HCD           = 'HCD';
    public const ACCREDITATION = 'Accreditation';

    public const TEAM_LEAD = 'Team Lead';
    public const VERIFIER  = 'Verifier';

    /** The admin's division name, or null for a non-admin. */
    public static function division(?User $user): ?string
    {
        return $user?->loadMissing('adminProfile.division')->adminProfile?->division?->name;
    }

    /** The admin's role name exactly as stored, or null for a non-admin. */
    public static function role(?User $user): ?string
    {
        return $user?->loadMissing('adminProfile.adminRole')->adminProfile?->adminRole?->name;
    }

    public static function inDivision(?User $user, string $division): bool
    {
        return strcasecmp((string) self::division($user), $division) === 0;
    }

    public static function isTeamLeadOf(?User $user, string $division): bool
    {
        return self::inDivision($user, $division)
            && strcasecmp((string) self::role($user), self::TEAM_LEAD) === 0;
    }

    /**
     * The role HCD's own checks should see.
     *
     * The HCD controllers gate on role names alone (deny-lists such as "block
     * the Verifier"). Accreditation admins only ever take part in the FATPro
     * pipeline as Verifiers — payment and certificate release — so that is the
     * role they present here, whatever they hold in their own division. Without
     * this an Accreditation Team Lead would read as HCD's Team Lead.
     */
    public static function hcdRole(?User $user): string
    {
        if (self::inDivision($user, self::ACCREDITATION)) {
            return self::VERIFIER;
        }

        return (string) self::role($user);
    }

    /**
     * The role recorded in the audit trail.
     *
     * HCD's audit page shows each role its own entries, so an Accreditation
     * Team Lead recorded as plain "Team Lead" would surface in HCD's log.
     */
    public static function auditRole(?User $user): ?string
    {
        $role = self::role($user);

        if ($role !== null && self::isTeamLeadOf($user, self::ACCREDITATION)) {
            return self::ACCREDITATION . ' ' . self::TEAM_LEAD;
        }

        return $role;
    }

    /** Admin users belonging to one division. */
    public static function membersOf(string $division): Builder
    {
        return User::whereHas('adminProfile.division', fn ($q) => $q->where('name', $division));
    }

    /** Team Leads of one division only. */
    public static function teamLeadsOf(string $division): Builder
    {
        return self::membersOf($division)
            ->whereHas('adminProfile.adminRole', fn ($q) => $q->where('name', self::TEAM_LEAD));
    }

    /** Whether an accreditation type is processed by the Accreditation division. */
    public static function isAccreditationDivisionType(?int $accreditationTypeId): bool
    {
        return in_array((int) $accreditationTypeId, self::accreditationDivisionTypes(), true);
    }

    /** @return array<int, int> */
    public static function accreditationDivisionTypes(): array
    {
        return array_map('intval', array_keys(config('accreditation.accreditation_division_types', [])));
    }

    /** "OSH Practitioner" / "OSH Consultant" for a division type id. */
    public static function divisionTypeLabel(?int $accreditationTypeId): string
    {
        return config('accreditation.accreditation_division_types')[(int) $accreditationTypeId] ?? '—';
    }
}
