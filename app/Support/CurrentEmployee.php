<?php

namespace App\Support;

/**
 * Resolves the logged-in user's identity for the current request, per
 * App\Http\Middleware\AuthMiddleware -- your authify SSO doesn't touch
 * Laravel's Auth facade at all; it writes everything to
 * session('emp_data') as a plain array:
 *
 *   session('emp_data') = [
 *       'token', 'emp_id', 'emp_name', 'emp_firstname', 'emp_jobtitle',
 *       'emp_dept', 'emp_prodline', 'emp_station', 'emp_position',
 *       'generated_at',
 *   ]
 *
 * This does NOT hit the masterlist connection/DB -- department, station,
 * etc. for the CURRENT user are already in session courtesy of
 * AuthMiddleware's authify_sessions lookup, so there's no need to query
 * employee.masterlist just to find out who's logged in. (masterlist is
 * still used elsewhere -- see app/Models/Masterlist/Employee.php -- for looking up
 * an arbitrary *other* employee, e.g. the "Issued To" person on a QDN.)
 */
class CurrentEmployee
{
    public static function data(): ?array
    {
        return session('emp_data');
    }

    public static function employId(): ?string
    {
        return session('emp_data.emp_id');
    }

    public static function name(): ?string
    {
        return session('emp_data.emp_name');
    }

    public static function department(): ?string
    {
        return session('emp_data.emp_dept');
    }

    public static function station(): ?string
    {
        return session('emp_data.emp_station');
    }

    public static function prodline(): ?string
    {
        return session('emp_data.emp_prodline');
    }

    public static function jobTitle(): ?string
    {
        return session('emp_data.emp_jobtitle');
    }

    /**
     * Numeric position code from authify (session('emp_data.emp_position'),
     * set in AuthMiddleware from authify_sessions.emp_position) -- a
     * separate field from emp_jobtitle above. Cast to string for
     * comparison since authify may hand this back as either an int or a
     * numeric string depending on the row.
     */
    public static function position(): ?string
    {
        $position = session('emp_data.emp_position');

        return $position === null ? null : (string) $position;
    }

    /**
     * EMPPOSITION from the masterlist (employee_position.empposition_id)
     * for the signed-in employee. Authify's emp_position isn't always
     * the same code -- it can be blank, a name, or out of date until the
     * next sign-in -- so isPositionAnyOf() checks this too. Cached for
     * the request; a masterlist error just counts as "no match".
     */
    public static function masterlistPositionId(): ?string
    {
        $empId = trim((string) static::employId());
        if ($empId === '') {
            return null;
        }

        return once(fn () => rescue(function () use ($empId) {
            $id = \App\Models\Masterlist\Employee::find($empId)?->currentPosition?->empposition_id;

            return $id === null ? null : trim((string) $id);
        }, null, report: true));
    }

    public static function isPositionAnyOf(array $positions): bool
    {
        // Trimmed, and "02" counts as "2" -- an exact === on the raw
        // session value missed codes with padding or whitespace.
        $normalize = fn ($v) => is_numeric(trim((string) $v))
            ? (string) (int) trim((string) $v)
            : strtolower(trim((string) $v));
        $positions = array_map($normalize, $positions);

        foreach ([static::position(), static::masterlistPositionId()] as $candidate) {
            if ($candidate !== null && trim($candidate) !== ''
                && in_array($normalize($candidate), $positions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Case-insensitive substring match against the current employee's job
     * title -- same case-insensitivity reasoning as isInDepartment()
     * above (title casing isn't guaranteed consistent either). $needles
     * are substrings, not exact titles -- e.g. 'Manager' matches
     * "Production Manager", "Senior Manager", "Manager - MIS/Facilities",
     * etc.
     *
     * ASSUMPTION: session('emp_data.emp_jobtitle') carries the same text
     * as employee.masterlist.JOB_TITLE (confirmed via a real DISTINCT
     * JOB_TITLE query, but emp_jobtitle itself comes from your authify
     * SSO, a separate system per the class docblock above -- not
     * cross-checked against masterlist). If gating doesn't work as
     * expected, dump session('emp_data') for a logged-in Supervisor/
     * Section Head/Manager and compare emp_jobtitle's exact text against
     * the JOB_TITLE list already confirmed.
     */
    public static function jobTitleContainsAny(array $needles): bool
    {
        $title = static::jobTitle();
        if (!$title) {
            return false;
        }

        foreach ($needles as $needle) {
            if (stripos($title, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    // "Supervisor and Up" -- required to submit Dept RCA / Dept CAPA (per
    // the user: "RCA and CAPA can only answer by Supervisor, Section, and
    // Manager"). Confirmed against a real DISTINCT JOB_TITLE query --
    // every Section Head / Section Manager title also contains "Manager"
    // or "Section Head", so these three substrings cover the full set
    // without needing an exhaustive exact-match list.
    public const SUPERVISOR_AND_UP_TITLES = ['Supervisor', 'Section Head', 'Manager'];

    // Dept Approval's Correct/Wrong decision is narrower -- Section Head
    // and Manager only, NOT plain Supervisor (per the user, explicitly:
    // "Approver should be Section Head and Manager").
    public const APPROVER_TITLES = ['Section Head', 'Manager'];

    // Additionally let these authify position codes submit RCA/CAPA even
    // when emp_jobtitle doesn't contain Supervisor/Section Head/Manager --
    // per the user: "EMPPOSITION=2, 3, 4 can submit RCA and CAPA". Does
    // NOT extend to Dept Approval (isApprover() below) -- that one was
    // never asked to change.
    public const RCA_CAPA_ALLOWED_POSITIONS = ['2', '3', '4'];

    public static function isSupervisorAndUp(): bool
    {
        return static::jobTitleContainsAny(self::SUPERVISOR_AND_UP_TITLES);
    }

    /**
     * Approvers come from masterlist.employee_approver (Masterlist\EmployeeApprover):
     * the current employee is an approver for every employid row that lists
     * them as approver1, approver2 or approver3. Replaces the old
     * job-title rule (APPROVER_TITLES) for Dept Approval and the dashboard.
     *
     * @return string[] employids this employee approves for
     */
    public static function approveeIds(): array
    {
        $empId = static::employId();
        if ($empId === null || $empId === '') {
            return [];
        }
        $empId = trim((string) $empId);

        // once(): cached for the current request only.
        return static::approveeIdsFor($empId);
    }

    private static function approveeIdsFor(string $empId): array
    {
        return once(fn () => rescue(fn () => \App\Models\Masterlist\EmployeeApprover::query()
            ->where(fn ($q) => $q->where('approver1', $empId)
                ->orWhere('approver2', $empId)
                ->orWhere('approver3', $empId))
            ->pluck('employid')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all(), [], report: true));
    }

    public static function isApprover(): bool
    {
        return static::approveeIds() !== [];
    }

    /** True if the QDN's (latest) Issued To employee lists this user as an approver. */
    public static function isApproverFor(?string $issuedToEmployeeId): bool
    {
        return $issuedToEmployeeId !== null
            && in_array(trim($issuedToEmployeeId), static::approveeIds(), true);
    }

    /**
     * Who's allowed to submit Dept RCA / Dept CAPA: Supervisor-and-up by
     * job title (the original rule), OR one of the whitelisted position
     * codes (RCA_CAPA_ALLOWED_POSITIONS) regardless of job title. Single
     * source of truth for both QdnWorkflowController's canSubmit props
     * (Rca/Show, Capa/Show) and its assertCanAnswerRcaOrCapa() guard, so
     * the Submit button and the server-side check never disagree.
     */
    public static function canAnswerRcaOrCapa(): bool
    {
        return static::isSupervisorAndUp()
            || static::isPositionAnyOf(self::RCA_CAPA_ALLOWED_POSITIONS);
    }

    /**
     * canAnswerRcaOrCapa() for one QDN. Customer: Others QDNs are handled
     * by the people who raised them, so on those the issuer (qdns.issued_by)
     * and the Issued To employee (qdn_validations.issued_to_employee_id) can
     * also submit RCA / CAPA, whatever their position. Every other QDN keeps
     * the Supervisor-and-up / position-code rule unchanged. The owning-
     * department check (assertOwningDepartment) still applies on top.
     */
    public static function canAnswerRcaOrCapaFor(\App\Models\Qdn $qdn): bool
    {
        if (static::canAnswerRcaOrCapa()) {
            return true;
        }

        if (strcasecmp(trim((string) $qdn->customer_name), 'others') !== 0) {
            return false;
        }

        $name = trim((string) static::name());
        if ($name !== '' && strcasecmp(trim((string) $qdn->issued_by), $name) === 0) {
            return true;
        }

        $empId = trim((string) static::employId());
        $issuedTo = trim((string) $qdn->validation?->issued_to_employee_id);

        return $empId !== '' && $issuedTo !== '' && $empId === $issuedTo;
    }

    /**
     * Trimmed + case-insensitive on purpose -- department names come from
     * two different sources that don't necessarily agree on casing
     * (session('emp_data.emp_dept') vs. employee.masterlist.DEPARTMENT),
     * and an exact === match has already broken once on this app (the
     * real PE department name turned out to be "Process Engineering",
     * not "PE" -- see DEPARTMENT_PE below).
     */
    public static function isInDepartment(string $department): bool
    {
        $current = static::department();

        return $current !== null && strcasecmp(trim($current), trim($department)) === 0;
    }

    /**
     * Same as isInDepartment(), but true if the current department matches
     * ANY of the given names -- needed because "QA" turned out to map to
     * two distinct real department names, not one (see DEPARTMENT_QA).
     */
    public static function isInAnyDepartment(array $departments): bool
    {
        foreach ($departments as $department) {
            if (static::isInDepartment($department)) {
                return true;
            }
        }

        return false;
    }

    // The literal "PE" from the original spec turned out to be shorthand,
    // not the real stored value -- confirmed via a logged-in PE
    // employee's actual session('emp_data.emp_dept').
    public const DEPARTMENT_PE = 'Process Engineering';

    // Likewise "QA" -- confirmed to cover two distinct real department
    // names, both treated as QA for QA Verification purposes.
    public const DEPARTMENT_QA = ['Quality Assurance', 'Quality Management System'];

    // Department names for the cross-department approval step (PE, EE, QA, Production). Add any
    // other spelling your Authify emp_dept uses for the same department.
    public const DEPARTMENT_EE = ['Equipment Engineering'];
    public const DEPARTMENT_PRODUCTION = ['Production'];

    /**
     * Which cross-approval group (QdnCrossApproval::DEPT_*) the current
     * employee can approve for: Supervisor / Section Head / Manager by job
     * title, in Process Engineering, Equipment Engineering, QA or Production.
     */
    public static function crossApprovalDepartmentKey(): ?string
    {
        if (!static::isSupervisorAndUpByAnyTitle()) {
            return null;
        }
        foreach (\App\Models\QdnCrossApproval::departments() as $key => $dept) {
            if (static::isInAnyDepartment($dept['names'])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Supervisor / Section Head / Manager by ANY of the places the app can
     * read a title from, not just Authify's emp_jobtitle:
     *   - session emp_jobtitle and emp_position_level (Authify)
     *   - masterlist JOB_TITLE (position name) and POSITION_LEVEL
     * A Manager whose Authify job title is e.g. "Process Engineering Head"
     * but whose position level is "Manager" used to fail the
     * emp_jobtitle-only check, which hid the PE / EE / QA / Production
     * Approve button and badge. Used for cross approval only.
     */
    /**
     * crossApprovalDepartmentKey() for one QDN: the fixed department key,
     * or "other" when the QDN is Customer: Others and this Supervisor+
     * belongs to the department that raised it (qdns.issuing_department).
     */
    public static function crossApprovalDepartmentKeyFor(\App\Models\Qdn $qdn): ?string
    {
        if ($key = static::crossApprovalDepartmentKey()) {
            return $key;
        }

        return static::isIssuingDepartmentApproverFor($qdn) ? \App\Models\QdnCrossApproval::DEPT_OTHER : null;
    }

    public static function isIssuingDepartmentApproverFor(\App\Models\Qdn $qdn): bool
    {
        $issuing = \App\Models\QdnCrossApproval::otherIssuingDepartmentFor($qdn);

        return $issuing !== null
            && static::otherIssuingDepartment() !== null
            && static::isInDepartment($issuing);
    }

    /**
     * The current employee's department when they could approve as an
     * "Issuing Dept" (Supervisor+, department not one of the fixed four).
     */
    public static function otherIssuingDepartment(): ?string
    {
        $department = trim((string) static::department());
        if ($department === '' || \App\Models\QdnCrossApproval::isFixedDepartment($department)) {
            return null;
        }

        return static::isSupervisorAndUpByAnyTitle() ? $department : null;
    }

    public static function isSupervisorAndUpByAnyTitle(): bool
    {
        // Fast path: Authify job title already matches, no masterlist query.
        if (static::isSupervisorAndUp()) {
            return true;
        }

        foreach (static::titleCandidates() as $title) {
            foreach (self::SUPERVISOR_AND_UP_TITLES as $needle) {
                if (stripos($title, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return string[] every non-empty title/level text known for the current employee */
    public static function titleCandidates(): array
    {
        $titles = [
            session('emp_data.emp_jobtitle'),
            session('emp_data.emp_position_level'),
        ];

        $empId = trim((string) static::employId());
        if ($empId !== '') {
            $titles = array_merge($titles, once(fn () => rescue(function () use ($empId) {
                $employee = \App\Models\Masterlist\Employee::with([
                    'currentPosition.position',
                    'currentPositionLevel.positionLevel',
                ])->find($empId);

                return $employee ? [$employee->JOB_TITLE, $employee->POSITION_LEVEL] : [];
            }, [], report: true)));
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($t) => is_scalar($t) ? trim((string) $t) : '', $titles),
            fn ($t) => $t !== ''
        )));
    }

    public static function isPe(): bool
    {
        return static::isInDepartment(self::DEPARTMENT_PE);
    }

    public static function isQa(): bool
    {
        return static::isInAnyDepartment(self::DEPARTMENT_QA);
    }
}
