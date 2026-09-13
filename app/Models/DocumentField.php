<?php

namespace App\Models;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentField extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type_id',
        'name',
        'code',
        'input_type',
    ];

    /**
     * Fields whose value is chosen from a fixed list rather than typed.
     *
     * The new-application form has always rendered these as a <select>; the
     * resubmit forms used a plain text box, so a rejected value could come back
     * as anything ("cda", "Coop", a typo). Keeping the list here means the forms
     * and the server checks cannot drift apart.
     */
    public const SELECT_OPTIONS = [
        'LEGAL_02_TYPE' => [
            'DTI' => 'Department of Trade and Industry (DTI)',
            'SEC' => 'Securities and Exchange Commission (SEC)',
            'CDA' => 'Cooperative Development Authority (CDA)',
        ],
    ];

    /**
     * The allowed choices for this field, or null when it is free input.
     *
     * @return array<string, string>|null
     */
    public function selectOptions(): ?array
    {
        return self::SELECT_OPTIONS[$this->code] ?? null;
    }

    /**
     * Render a stored value for humans.
     *
     * Fixed-choice fields store the short code ("CDA") because it is what the
     * validation rules and the SEC-only Articles of Incorporation branch match
     * on — see RegistrationController and RenewalController. Screens should show
     * the full agency name instead, so map it here at display time rather than
     * changing what is written to the database.
     */
    public function displayValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->selectOptions()[$value] ?? $value;
    }

    /**
     * Get the document type (section) this field belongs to.
     */
    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Get all user documents submitted for this field.
     */
    public function userDocuments()
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * Retrieve all document fields, from cache when available.
     * Keyed by 'code' for O(1) lookup.
     *
     * @return \Illuminate\Support\Collection<string, static>  (keyed by code)
     */
    public static function allCached(): \Illuminate\Support\Collection
    {
        return CacheService::remember(
            CacheService::documentFieldsKey(),
            CacheService::TTL_REFERENCE,
            // document_type is eager-loaded because forAccreditationType()
            // below filters on it for every caller, and this collection is
            // built once per cache period.
            fn () => static::with('documentType')->get()->keyBy('code')
        );
    }

    /**
     * The fields belonging to one accreditation type's checklist.
     *
     * document_types gained an accreditation_type_id when the Practitioner
     * checklist was added. Without filtering, a FATPro registration would be
     * validated against the practitioner uploads and vice versa — harmless
     * while every added rule was `nullable`, but wrong the moment one is not.
     * A group with a NULL type is shared and always included.
     */
    public static function forAccreditationType(int $accreditationTypeId): \Illuminate\Support\Collection
    {
        return static::allCached()->filter(function (self $field) use ($accreditationTypeId) {
            $typeId = $field->documentType?->accreditation_type_id;

            return $typeId === null || (int) $typeId === $accreditationTypeId;
        });
    }
}
