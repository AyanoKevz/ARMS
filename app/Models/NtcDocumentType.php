<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NtcDocumentType extends Model
{
    /** The blank DOLE-OSHC forms a FATPro fills in, by document type code. */
    public const TEMPLATE_URLS = [
        'RTCMAN' => 'https://oshc.dole.gov.ph/wp-content/uploads/2026/10/OSHC-STO-NTC-Form.docx',
        'PROG'   => 'https://oshc.dole.gov.ph/wp-content/uploads/2026/10/OSHC-STO-Program-Form.docx',
    ];

    protected $fillable = ['name', 'code'];

    public function ntcDocuments()
    {
        return $this->hasMany(NtcDocument::class);
    }

    public static function templateUrl(string $code): ?string
    {
        return self::TEMPLATE_URLS[$code] ?? null;
    }
}
