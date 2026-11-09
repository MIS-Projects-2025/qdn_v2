<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QdnValidation extends Model
{
    // Default connection, alongside qdns (real FK to qdns.id, same schema).

    // PE's original "validate + issue" decision (PeValidationController@store).
    public const TYPE_VALIDATION = 'validation';
    // A later PE correction to who/what department a QDN was issued to,
    // recorded as a brand-new row rather than overwriting the original --
    // see QdnReassignController@update. Qdn::validation() (singular)
    // always resolves to the latest row regardless of type; Qdn::validations()
    // (plural) exposes the full history, oldest first.
    public const TYPE_REASSIGNMENT = 'reassignment';

    protected $fillable = [
        'qdn_id',
        'type',
        'is_valid',
        'issued_to_employee_id',
        'issued_to_name',
        'department',
        'station',
        'prodline',
        'team',
        'validated_by',
        'validated_at',
        'remarks',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function qdn(): BelongsTo
    {
        return $this->belongsTo(Qdn::class);
    }
}
