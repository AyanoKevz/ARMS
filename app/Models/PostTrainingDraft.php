<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Work in progress on a Post Training Report.
 *
 * Seven requirements is more than one sitting, so what the FATPro has typed,
 * chosen and uploaded is kept here between visits. It is not a
 * post_training_reports row: nothing here has been submitted, and the
 * evaluator's queue has no business showing it.
 *
 * Files are the one thing that cannot sit in the payload. Each is uploaded to
 * ptr_staging the moment it is picked and only its token is recorded, which is
 * the same arrangement the Directory's ID pictures already use.
 */
class PostTrainingDraft extends Model
{
    protected $fillable = [
        'ntc_report_id',
        'user_id',
        'payload',
        'saved_at',
    ];

    protected $casts = [
        'payload'  => 'array',
        'saved_at' => 'datetime',
    ];

    public function ntcReport()
    {
        return $this->belongsTo(NtcReport::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staging tokens this draft is holding on to.
     *
     * Both the documents and the participants' ID pictures, since the nightly
     * prune has to know which staged files are still spoken for and which are
     * genuinely abandoned.
     *
     * @return array<int, string>
     */
    public function stagedTokens(): array
    {
        $payload = $this->payload ?? [];
        $tokens  = [];

        foreach ($payload['documents'] ?? [] as $document) {
            if (!empty($document['token'])) {
                $tokens[] = $document['token'];
            }
        }

        foreach ($payload['participants'] ?? [] as $participant) {
            if (!empty($participant['photo_token'])) {
                $tokens[] = $participant['photo_token'];
            }
        }

        return array_values(array_unique($tokens));
    }
}
