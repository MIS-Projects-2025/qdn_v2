<?php

namespace App\Support;

use App\Models\Qdn;

/**
 * Per-request "you have N pending" counts for the sidebar nav badges --
 * Issued (QDNs for My Department), Dept: RCA, Dept: CAPA, Dept: Approval,
 * QA Verification. Shared to every Inertia page via
 * App\Http\Middleware\AuthMiddleware (see the two `Inertia::share(...)`
 * calls added there), so NavLinks.jsx can read `qdnCounts` from
 * usePage().props on any screen, not just the queue pages themselves.
 *
 * Each count deliberately mirrors the exact same scoping its
 * corresponding index page already uses (see
 * QdnWorkflowController::rcaIndex/capaIndex/approvalIndex/qaIndex and
 * IssuedQdnController::index) -- one definition of "what's in this
 * queue," so the badge number never drifts out of sync with what you
 * actually see after clicking it.
 */
class QdnNotificationCounts
{
    public static function crossApprovalCount(): int
    {
        $pending = fn (string $key) => Qdn::where('status', Qdn::STATUS_FOR_CROSS_APPROVAL)
            ->whereDoesntHave('crossApprovals', fn ($q) => $q
                ->where('department_key', $key)
                ->whereRaw('qdn_cross_approvals.round = COALESCE(qdns.capa_round, 1)'));

        if ($key = CurrentEmployee::crossApprovalDepartmentKey()) {
            return $pending($key)->count();
        }

        if ($department = CurrentEmployee::otherIssuingDepartment()) {
            $qdns = $pending(\App\Models\QdnCrossApproval::DEPT_OTHER);
            \App\Models\QdnCrossApproval::scopeIssuedByDepartment($qdns, $department);

            return $qdns->count();
        }

        return 0;
    }

    public static function forCurrentEmployee(): array
    {
        $department = CurrentEmployee::department();

        // Each badge is computed on its own: one failing query (e.g. the
        // masterlist approver lookup) used to make HandleInertiaRequests'
        // rescue() return null for the whole array, which wiped every
        // badge at once. Now a failure only zeroes that one badge and is
        // logged.
        $safe = fn (callable $count) => (int) rescue($count, 0, report: true);

        return [
            // "QDNs for My Department" is a whole-lifecycle status board
            // (Qdn::DEPARTMENT_VISIBLE_STATUSES, issued through closed),
            // not a single action queue -- RCA/CAPA/Approval below already
            // cover "needs your action right now," so this one is the
            // department's overall still-open count (everything except
            // the two terminal statuses).
            'issued' => $safe(fn () => $department
                ? Qdn::whereIn('status', Qdn::DEPARTMENT_VISIBLE_STATUSES)
                    ->whereNotIn('status', [Qdn::STATUS_CLOSED, Qdn::STATUS_INVALID_DISPOSITION])
                    ->where('issued_department', $department)
                    ->count()
                : 0),

            'rca' => $safe(fn () => $department
                ? Qdn::where('status', Qdn::STATUS_FOR_DEPT_RCA)
                    ->where('issued_department', $department)
                    ->count()
                : 0),

            'capa' => $safe(fn () => $department
                ? Qdn::where('status', Qdn::STATUS_FOR_DEPT_CAPA)
                    ->where('issued_department', $department)
                    ->count()
                : 0),

            // Approver-based (masterlist.employee_approver), same scope as
            // QdnWorkflowController::approvalIndex.
            'approval' => $safe(fn () => ($approvees = CurrentEmployee::approveeIds())
                ? Qdn::where('status', Qdn::STATUS_FOR_DEPT_APPROVAL)
                    ->whereHas('validation', fn ($q) => $q->whereIn('issued_to_employee_id', $approvees))
                    ->count()
                : 0),

            // PE / EE / QA / Production approval: QDNs still waiting on MY department.
            // Plus, for a Supervisor+ of any other department, Customer:
            // Others QDNs their department raised ("Issuing Dept" approver).
            'crossApproval' => $safe(fn () => self::crossApprovalCount()),

            // Not department-scoped -- same shared-queue model as
            // QdnWorkflowController::qaIndex (any Department: QA employee
            // sees every QDN awaiting verification, not just their own
            // department's). Zero for everyone else so the badge never
            // shows on QA Verification for people who can't even see that
            // link (NavLinks already hides it behind isQa).
            'qaVerification' => $safe(fn () => CurrentEmployee::isQa()
                ? Qdn::where('status', Qdn::STATUS_FOR_QA_VERIFICATION)->count()
                : 0),
        ];
    }
}
