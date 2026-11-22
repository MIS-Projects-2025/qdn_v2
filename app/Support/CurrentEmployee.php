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

        return once(fn() => rescue(function () use ($empId) {
            $id = \App\Models\Masterlist\Employee::find($empId)?->currentPosition?->empposition_id;

            return $id === null ? null : trim((string) $id);
        }, null, report: true));
    }

    public static function isPositionAnyOf(array $positions): bool
    {
        // Trimmed, and "02" counts as "2" -- an exact === on the raw
        // session value missed codes with padding or whitespace.
        $normalize = fn($v) => is_numeric(trim((string) $v))
            ? (string) (int) trim((string) $v)
            : strtolower(trim((string) $v));
        $positions = array_map($normalize, $positions);

        foreach ([static::position(), static::masterlistPositionId()] as $candidate) {
            if (
                $candidate !== null && trim($candidate) !== ''
                && in_array($normalize($candidate), $positions, true)
            ) {
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
        return once(fn() => rescue(fn() => \App\Models\Masterlist\EmployeeApprover::query()
            ->where(fn($q) => $q->where('approver1', $empId)
                ->orWhere('approver2', $empId)
                ->orWhere('approver3', $empId))
            ->pluck('employid')
            ->map(fn($id) => trim((string) $id))
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
     * factory.id values for an employee, from masterlist.employee_factory
     * (EMPLOYID, factory_id -> factory.id). Cached per request; a
     * masterlist error counts as "no factory".
     *
     * @return string[]
     */
    public static function factoryIdsFor(?string $empId): array
    {
        $empId = trim((string) $empId);
        if ($empId === '') {
            return [];
        }

        return once(fn() => rescue(fn() => \App\Models\Masterlist\EmployeeFactory::query()
            ->where('EMPLOYID', $empId)
            ->whereNotNull('factory_id')
            ->pluck('factory_id')
            ->map(fn($id) => trim((string) $id))
            ->filter(fn($id) => $id !== '')
            ->unique()
            ->values()
            ->all(), [], report: true));
    }

    /**
     * True if the signed-in employee and the QDN's Issued To employee share
     * a factory in masterlist.employee_factory. False when either has no
     * factory row, or the QDN has no Issued To yet.
     */
    public static function isSameFactoryAsIssuedTo(\App\Models\Qdn $qdn): bool
    {
        $mine = static::factoryIdsFor(static::employId());
        $theirs = static::factoryIdsFor($qdn->validation?->issued_to_employee_id);

        return $mine !== [] && array_intersect($mine, $theirs) !== [];
    }

    /** isApproverFor() for the QDN's (latest) PE validation Issued To employee. */
    public static function isIssuedToApproverFor(\App\Models\Qdn $qdn): bool
    {
        return static::isApproverFor($qdn->validation?->issued_to_employee_id);
    }

    // -- Department role: Supervisor / Section Head / Engineer / Manager ----
    //
    // Per the user: "RCA, CAPA and Approval: any Supervisor, Engineer,
    // Section Head or Manager from their department can submit". One rule
    // shared by Dept RCA, Dept CAPA, Dept Approval and the PE / EE / QA /
    // Production (+ Issuing Dept) approval, so they never disagree.

    // Position codes (Authify emp_position / masterlist empposition_id) that
    // count as the department role even when no title says so.
    public const RCA_CAPA_ALLOWED_POSITIONS = ['2', '3', '4'];
    public const DEPARTMENT_ROLE_POSITIONS = self::RCA_CAPA_ALLOWED_POSITIONS;

    // The four roles, for messages. Matching uses the patterns below.
    public const RCA_CAPA_TITLES = ['Supervisor', 'Section Head', 'Engineer', 'Manager'];
    public const DEPARTMENT_ROLE_TITLES = self::RCA_CAPA_TITLES;

    // Whole-word patterns, including the short forms used in job titles:
    // "Engr" / "Engr." / "Eng'r" / "Engg", "Sup" / "Supv" / "Supvr", "Mgr",
    // "Sec Head" / "Sect. Head". "Process Engineer" and "Senior Engineer"
    // match; "Engineering Technician" does not.
    public const DEPARTMENT_ROLE_TITLE_PATTERNS = [
        '/\\bsupervisors?\\b|\\bsupv?r?\\b\\.?/i',
        '/\\bsect?(ion)?\\.?\\s*head\\b/i',
        '/\\bengineers?\\b|\\beng\'?r\\b\\.?|\\bengg\\b/i',
        '/\\bmanagers?\\b|\\bmgr\\b\\.?/i',
    ];

    /**
     * Supervisor / Section Head / Engineer / Manager by any title the app
     * can read (see departmentRoleTitleCandidates()), or position code 2/3/4.
     * Department is NOT checked here; see hasDepartmentRoleIn().
     */
    public static function hasDepartmentRole(): bool
    {
        return static::hasDepartmentRoleTitle()
            || static::isPositionAnyOf(self::DEPARTMENT_ROLE_POSITIONS);
    }

    /** hasDepartmentRole() AND a member of $department (Authify or masterlist). */
    public static function hasDepartmentRoleIn(?string $department): bool
    {
        return trim((string) $department) !== ''
            && static::isInDepartment((string) $department)
            && static::hasDepartmentRole();
    }

    public static function hasDepartmentRoleTitle(): bool
    {
        foreach (static::departmentRoleTitleCandidates() as $title) {
            if (static::titleIsDepartmentRole($title)) {
                return true;
            }
        }

        return false;
    }

    public static function titleIsDepartmentRole(?string $title): bool
    {
        // "Process_Engineer" / "QA-Engineer" style titles: treat _ as a space.
        $title = str_replace('_', ' ', (string) $title);
        foreach (self::DEPARTMENT_ROLE_TITLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every place a title / position name can live: Authify emp_jobtitle,
     * emp_position_level, emp_position (when it is a name, not a code) and
     * emp_job_level; masterlist position name, position level and
     * designation (employee_designation -> designation_name).
     *
     * @return string[]
     */
    public static function departmentRoleTitleCandidates(): array
    {
        $titles = static::titleCandidates();
        $titles[] = static::jobTitle();
        $titles[] = session('emp_data.emp_position');
        $titles[] = session('emp_data.emp_job_level');

        $empId = trim((string) static::employId());
        if ($empId !== '') {
            $titles[] = once(fn() => rescue(fn() => \App\Models\Masterlist\EmployeeDesignation::with('designation')
                ->where('employid', $empId)->first()?->designation?->designation_name, null, report: false));
        }

        return array_values(array_unique(array_filter(
            array_map(fn($t) => is_scalar($t) && !is_numeric(trim((string) $t)) ? trim((string) $t) : '', $titles),
            fn($t) => $t !== ''
        )));
    }

    /**
     * Plain-words reason the current employee doesn't count as the
     * department role for $department, with the values checked, or null
     * if they do. Used in the 403s and on the RCA / CAPA / Approval pages.
     */
    public static function departmentRoleBlockReason(?string $department): ?string
    {
        $reasons = [];
        $department = trim((string) $department);

        if ($department === '' || !static::isInDepartment($department)) {
            $mine = static::departmentCandidates();
            $reasons[] = 'you are not in ' . ($department !== '' ? "the {$department} department" : 'the QDN\'s department')
                . ' (your department: ' . ($mine ? implode(' / ', $mine) : 'none found') . ')';
        }

        if (!static::hasDepartmentRole()) {
            $titles = static::departmentRoleTitleCandidates();
            $reasons[] = 'your title (' . ($titles ? implode(' / ', $titles) : 'none found')
                . ') is not Supervisor, Section Head, Engineer or Manager and your position code ('
                . (static::position() ?? 'none') . ' / ' . (static::masterlistPositionId() ?? 'none')
                . ') is not 2, 3 or 4';
        }

        return $reasons ? implode('; ', $reasons) : null;
    }

    // -- Dept RCA / Dept CAPA ----------------------------------------------

    /** Kept for the sidebar (qdnRoles.canAnswerRcaOrCapa): the role alone, no department. */
    public static function canAnswerRcaOrCapa(): bool
    {
        return static::hasDepartmentRole();
    }

    public static function hasRcaCapaTitle(): bool
    {
        return static::hasDepartmentRoleTitle();
    }

    /**
     * Who can submit Dept RCA / Dept CAPA on this QDN:
     *   1. Supervisor / Section Head / Engineer / Manager (or position code
     *      2/3/4) in the QDN's Issued To department (qdns.issued_department), or
     *   2. approver1/2/3 of the Issued To employee (masterlist.employee_approver), or
     *   3. Customer: Others only: the issuer or the Issued To employee.
     * The same-factory condition was dropped: the department is enough.
     */
    public static function canAnswerRcaOrCapaFor(\App\Models\Qdn $qdn): bool
    {
        if (static::hasDepartmentRoleIn($qdn->issued_department)) {
            return true;
        }

        if (static::isIssuedToApproverFor($qdn)) {
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

    // -- Dept Approval -------------------------------------------------------

    /**
     * Who can mark a CAPA Correct / Wrong at Dept Approval: Supervisor /
     * Section Head / Engineer / Manager (or position code 2/3/4) in the QDN's
     * Issued To department, or approver1/2/3 of the Issued To employee.
     */
    public static function canApproveDeptFor(\App\Models\Qdn $qdn): bool
    {
        return static::hasDepartmentRoleIn($qdn->issued_department)
            || static::isIssuedToApproverFor($qdn);
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
        $wanted = static::normalizeDepartment($department);
        if ($wanted === '') {
            return false;
        }

        // Authify emp_dept first (no query), then the masterlist department.
        if (static::normalizeDepartment(static::department()) === $wanted) {
            return true;
        }

        return static::normalizeDepartment(static::masterlistDepartment()) === $wanted;
    }

    /**
     * Lower-cased, trimmed, every run of whitespace (including non-breaking
     * spaces and tabs) collapsed to one space. "Process  Engineering",
     * "PROCESS ENGINEERING" and "Process Engineering\u{00A0}" all compare
     * equal to "process engineering".
     */
    public static function normalizeDepartment(?string $department): string
    {
        $department = (string) $department;
        $collapsed = preg_replace('/[\s\x{00A0}]+/u', ' ', $department);

        return strtolower(trim($collapsed ?? $department));
    }

    /**
     * The signed-in employee's department name from the masterlist
     * (employee_department -> department.dep_name), cached for the request.
     * Authify's emp_dept is a copy taken at sign-in and can be a code, an
     * old name, or blank; this is the source of truth the rest of the app
     * already uses for "Issued To". A masterlist error counts as unknown.
     */
    public static function masterlistDepartment(): ?string
    {
        $empId = trim((string) static::employId());
        if ($empId === '') {
            return null;
        }

        return once(fn() => rescue(function () use ($empId) {
            $name = \App\Models\Masterlist\Employee::with('currentDepartment.department')
                ->find($empId)?->DEPARTMENT;

            return $name === null || trim((string) $name) === '' ? null : trim((string) $name);
        }, null, report: true));
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

    // Every spelling that counts as Process Engineering for isPe(). Matched
    // ignoring case and extra spaces; anything starting with "Process
    // Engineering" also counts. Add your own spelling here if needed.
    public const DEPARTMENT_PE_NAMES = [
        'Process Engineering',
        'Process Engineering Department',
        'Process Engineering Dept',
        'Process Engineering Dept.',
        'Process Engg',
        'Process Engg.',
        "Process Eng'g",
        'Process Eng',
        'PE',
        'PE Department',
        'PE Dept',
    ];

    // Likewise "QA" -- confirmed to cover two distinct real department
    // names, both treated as QA for QA Verification purposes.
    public const DEPARTMENT_QA = ['Quality Assurance', 'Quality Management System'];

    // Department names for the cross-department approval step (PE, EE, QA, Production). Add any
    // other spelling your Authify emp_dept uses for the same department.
    public const DEPARTMENT_EE = ['Equipment Engineering'];
    public const DEPARTMENT_PRODUCTION = ['Production'];

    /**
     * Which cross-approval group (QdnCrossApproval::DEPT_*) the current
     * employee can approve for: Supervisor / Section Head / Engineer /
     * Manager (hasDepartmentRole()), in Process Engineering, Equipment
     * Engineering, QA or Production.
     */
    public static function crossApprovalDepartmentKey(): ?string
    {
        if (!static::hasDepartmentRole()) {
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
     * "Issuing Dept" (Supervisor / Section Head / Engineer / Manager,
     * department not one of the fixed four).
     */
    public static function otherIssuingDepartment(): ?string
    {
        if (!static::hasDepartmentRole()) {
            return null;
        }

        // Authify emp_dept first, then the masterlist department.
        foreach (static::departmentCandidates() as $department) {
            if (!\App\Models\QdnCrossApproval::isFixedDepartment($department)) {
                return $department;
            }
        }

        return null;
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
            $titles = array_merge($titles, once(fn() => rescue(function () use ($empId) {
                $employee = \App\Models\Masterlist\Employee::with([
                    'currentPosition.position',
                    'currentPositionLevel.positionLevel',
                ])->find($empId);

                return $employee ? [$employee->JOB_TITLE, $employee->POSITION_LEVEL] : [];
            }, [], report: true)));
        }

        return array_values(array_unique(array_filter(
            array_map(fn($t) => is_scalar($t) ? trim((string) $t) : '', $titles),
            fn($t) => $t !== ''
        )));
    }

    /**
     * Department: PE (Process Engineering). True when EITHER the Authify
     * session department (emp_dept) OR the masterlist department is one of
     * DEPARTMENT_PE_NAMES, or starts with "Process Engineering" (e.g.
     * "Process Engineering - FOL"). Comparison ignores case and extra
     * spaces.
     *
     * Before this, only the Authify emp_dept was checked, and only for an
     * exact "Process Engineering". Anyone whose emp_dept said something
     * else (a code, "Process Engg", "PE", trailing "Dept", or an old value
     * kept in the session) was refused on the PE Queue and Validate pages
     * even though the masterlist shows Process Engineering.
     */
    public static function isPe(): bool
    {
        foreach (static::departmentCandidates() as $department) {
            if (static::isPeDepartmentName($department)) {
                return true;
            }
        }

        return false;
    }

    public static function isPeDepartmentName(?string $department): bool
    {
        $normalized = static::normalizeDepartment($department);
        if ($normalized === '') {
            return false;
        }

        foreach (self::DEPARTMENT_PE_NAMES as $name) {
            if ($normalized === static::normalizeDepartment($name)) {
                return true;
            }
        }

        return str_starts_with($normalized, 'process engineering');
    }

    /** @return string[] the Authify emp_dept and the masterlist department, non-empty, de-duplicated */
    public static function departmentCandidates(): array
    {
        $candidates = [];
        foreach ([static::department(), static::masterlistDepartment()] as $department) {
            $department = trim((string) $department);
            if ($department !== '' && !in_array($department, $candidates, true)) {
                $candidates[] = $department;
            }
        }

        return $candidates;
    }

    /**
     * Message for a 403 on a PE-only page, showing the department values the
     * app actually saw so a mismatch can be spotted straight away.
     */
    public static function peDeniedMessage(): string
    {
        $authify = trim((string) static::department());
        $masterlist = trim((string) static::masterlistDepartment());

        return sprintf(
            'Only Department: Process Engineering can access QDN validation. Your department is recorded as "%s" in Authify (emp_dept) and "%s" in the masterlist (employee %s).',
            $authify !== '' ? $authify : 'blank',
            $masterlist !== '' ? $masterlist : 'not found',
            trim((string) static::employId()) ?: 'unknown',
        );
    }

    public static function isQa(): bool
    {
        return static::isInAnyDepartment(self::DEPARTMENT_QA);
    }
}
