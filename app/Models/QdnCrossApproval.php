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
    // Customer: Others only -- the issuer's own department
    // (qdns.issuing_department) is a fifth required approver. Not added
    // when that department is already one of the four above.
    public const DEPT_OTHER = 'other';

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

    /** True if $department is one of the four fixed approver departments. */
    public static function isFixedDepartment(?string $department): bool
    {
        $department = trim((string) $department);
        if ($department === '') {
            return false;
        }
        foreach (static::departments() as $dept) {
            foreach ($dept['names'] as $name) {
                if (strcasecmp($department, trim($name)) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Required approvers for one QDN: the four fixed departments for every
     * QDN, plus (Customer: Others only) the issuer's department, key
     * "other", when it isn't one of the four.
     */
    public static function departmentsFor(Qdn $qdn): array
    {
        $departments = static::departments();
        $issuing = static::otherIssuingDepartmentFor($qdn);

        if ($issuing !== null) {
            $departments[self::DEPT_OTHER] = [
                'label' => "{$issuing} (Issuing Dept)",
                'names' => [$issuing],
            ];
        }

        return $departments;
    }

    /** The "Issuing Dept" approver department for this QDN, or null if it has none. */
    public static function otherIssuingDepartmentFor(Qdn $qdn): ?string
    {
        $issuing = trim((string) $qdn->issuing_department);
        $isOthers = strcasecmp(trim((string) $qdn->customer_name), 'others') === 0;

        return ($isOthers && $issuing !== '' && !static::isFixedDepartment($issuing)) ? $issuing : null;
    }

    /** Query scope pieces: Customer: Others QDNs raised by $department. */
    public static function scopeIssuedByDepartment($query, string $department)
    {
        return $query->whereRaw('LOWER(TRIM(customer_name)) = ?', ['others'])
            ->whereRaw('LOWER(TRIM(issuing_department)) = ?', [mb_strtolower(trim($department))]);
    }

    public function qdn(): BelongsTo
    {
        return $this->belongsTo(Qdn::class);
    }
}
