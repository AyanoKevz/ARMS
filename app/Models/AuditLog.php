<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the admin audit trail: who did what, when, and to which record.
 *
 * Append-only — there is no updated_at, and nothing edits or deletes a row.
 * The actor's name, e-mail and role are copied in at the time of the action,
 * so the trail still reads correctly after an account is renamed, has its
 * role changed, or is deleted.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** The roles whose admins can open the audit page — each sees only its own. */
    public const VIEWABLE_ROLES = ['Team Lead', 'Evaluator', 'Training Evaluator'];

    protected $fillable = [
        'user_id',
        'actor_name',
        'actor_email',
        'admin_role',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'subject_label',
        'subject_url',
        'properties',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Only the entries written by admins holding this role. */
    public function scopeForRole($query, string $role)
    {
        return $query->where('admin_role', $role);
    }
}
