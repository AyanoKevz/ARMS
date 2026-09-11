<?php

namespace App\Support;

/**
 * Where each FATPro applicant's files live on disk, under storage/app/{path}.
 *
 * Keyed by the applicant's permanent user id rather than their business or
 * individual name. A renewal or reinstatement re-submits that name every time,
 * and any change to it — a corrected typo, added punctuation, a legal rename —
 * used to fork the applicant into a brand-new, differently-named folder while
 * every previously uploaded file stayed behind in the old one, undiscoverable
 * by later code that only looks under the current name.
 */
class ApplicantStoragePath
{
    /** public/{accreditation_type}/user_{id}/documents */
    public static function documents(?string $accreditationName, int $userId): string
    {
        return self::base($accreditationName, $userId) . '/documents';
    }

    /** public/{accreditation_type}/user_{id}/instructor_credentials */
    public static function credentials(?string $accreditationName, int $userId): string
    {
        return self::base($accreditationName, $userId) . '/instructor_credentials';
    }

    /** public/{accreditation_type}/user_{id}/reports/ntc */
    public static function ntcReports(?string $accreditationName, int $userId): string
    {
        return self::base($accreditationName, $userId) . '/reports/ntc';
    }

    /** public/{accreditation_type}/user_{id}/recommendation_letter */
    public static function recommendationLetter(?string $accreditationName, int $userId): string
    {
        return self::base($accreditationName, $userId) . '/recommendation_letter';
    }

    /** public/{accreditation_type}/user_{id}/certificate */
    public static function certificate(?string $accreditationName, int $userId): string
    {
        return self::base($accreditationName, $userId) . '/certificate';
    }

    /** public/{accreditation_type}/user_{id}/proof_of_payments */
    public static function proofOfPayments(?string $accreditationName, int $userId): string
    {
        return self::base($accreditationName, $userId) . '/proof_of_payments';
    }

    /** public/{accreditation_type}/user_{id} */
    public static function base(?string $accreditationName, int $userId): string
    {
        $sanitizedAccreditation = self::sanitize($accreditationName ?? 'Unknown');

        return "public/{$sanitizedAccreditation}/user_{$userId}";
    }

    private static function sanitize(string $value): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $value)) ?: 'unknown';
    }
}
