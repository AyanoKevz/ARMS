<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'proof_of_payment',
        'proof_of_payment_status',
        'proof_of_payment_remarks',
        'e_signature',
        'e_signature_status',
        'e_signature_remarks',
        'id_photo',
        'id_photo_status',
        'id_photo_remarks',
        'signed_recommendation_letter',
    ];

    /**
     * Get the application that owns this payment record.
     */
    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
