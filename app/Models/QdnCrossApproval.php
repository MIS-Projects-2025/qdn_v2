<?php

namespace App\Models;

use App\Support\CurrentEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One department's sign-off in the cross-department approval step
 * (Process Engineering, Equipment Engineering, QA, Production) that follows Dept
 * Approval. First approver per department per CAPA round wins.
 */
class QdnCrossApproval extends Model
{
    protected $table = 'qdn_cross_approvals';

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'round' => 'integer',
    ];

    public const DEPT_PE = 'pe';
    public const DEPT_EE = 'ee';
    public const DEPT_QA = 'qa';
    public const DEPT_PROD = 'prod';

    /** key => [label, department names that count as this group] */
    public static function departments(): array
    {
        return [
            self::DEPT_PE => ['label' => 'Process Engineering', 'names' => [CurrentEmployee::DEPARTMENT_PE]],
            self::DEPT_EE => ['label' => 'Equipment Engineering', 'names' => CurrentEmployee::DEPARTMENT_EE],
            self::DEPT_QA => ['label' => 'QA', 'names' => CurrentEmployee::DEPARTMENT_QA],
            self::DEPT_PROD => ['label' => 'Production', 'names' => CurrentEmployee::DEPARTMENT_PRODUCTION],
        ];
    }

    public function qdn(): BelongsTo
    {
        return $this->belongsTo(Qdn::class);
    }
}
