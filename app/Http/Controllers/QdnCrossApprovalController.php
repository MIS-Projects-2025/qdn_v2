<?php

namespace App\Http\Controllers;

use App\Models\Qdn;
use App\Models\QdnCrossApproval;
use App\Support\CurrentEmployee;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PE / EE / QA / Production approval -- the step after Dept Approval.
 *
 * Each of Process Engineering, Equipment Engineering, QA and Production must approve
 * once. Any Supervisor / Section Head / Manager (job title) in that
 * department can do it; the first one wins and everyone else in that
 * department then sees "Approved by ... on ...". When all four have
 * approved, the QDN moves on to QA Verification.
 */
class QdnCrossApprovalController extends Controller
{
    public function index(): Response
    {
        $myKey = CurrentEmployee::crossApprovalDepartmentKey();

        $qdns = Qdn::where('status', Qdn::STATUS_FOR_CROSS_APPROVAL)
            ->with('crossApprovals')
            ->orderBy('created_at')
            ->get(['id', 'qdn_no', 'customer_name', 'device_name', 'classification', 'issued_department', 'issuing_department', 'capa_round', 'status', 'created_at'])
            ->map(fn (Qdn $qdn) => [
                'id' => $qdn->id,
                'qdn_no' => $qdn->qdn_no,
                'customer_name' => $qdn->customer_name,
                'device_name' => $qdn->device_name,
                'classification' => $qdn->classification,
                'issued_department' => $qdn->issued_department,
                'created_at' => $qdn->created_at,
                'approvals' => $this->approvalSummary($qdn),
                // Per QDN: "other" only on Customer: Others QDNs my department raised.
                'my_key' => CurrentEmployee::crossApprovalDepartmentKeyFor($qdn),
            ]);

        return Inertia::render('Qdn/CrossApproval/Index', [
            'qdns' => $qdns,
            'myDepartmentKey' => $myKey,
            'myIssuingDepartment' => $myKey ? null : CurrentEmployee::otherIssuingDepartment(),
            'departments' => $this->departmentLabels(),
        ]);
    }

    public function show(Qdn $qdn): Response
    {
        $this->redirectIfNotAt($qdn, Qdn::STATUS_FOR_CROSS_APPROVAL, 'PE / EE / QA / Production approval');

        $qdn->load(['validation', 'rcaCauses', 'capaContainmentLots', 'capaCorrections', 'capaReturns', 'crossApprovals']);
        $myKey = CurrentEmployee::crossApprovalDepartmentKeyFor($qdn);
        $approvals = $this->approvalSummary($qdn);

        return Inertia::render('Qdn/CrossApproval/Show', [
            'qdn' => $qdn,
            'approvals' => $approvals,
            'myDepartmentKey' => $myKey,
            'canApprove' => $myKey !== null && ($approvals[$myKey]['approved'] ?? false) === false,
            // Shown under "Your approval" when the account can't approve,
            // so it's clear which department / titles the app read.
            'viewer' => [
                'department' => CurrentEmployee::department(),
                'titles' => CurrentEmployee::titleCandidates(),
            ],
        ]);
    }

    public function store(Request $request, Qdn $qdn): RedirectResponse
    {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $key = CurrentEmployee::crossApprovalDepartmentKeyFor($qdn);
        abort_if($key === null, 403, 'Only a Supervisor, Section Head or Manager of Process Engineering, Equipment Engineering, QA, Production or the issuing department can approve.');

        $label = QdnCrossApproval::departmentsFor($qdn)[$key]['label'];

        $result = DB::transaction(function () use ($qdn, $key, $label, $data) {
            // Lock the QDN row so two departments finishing at the same
            // moment can't both miss the "all three approved" check.
            $qdn = Qdn::whereKey($qdn->id)->lockForUpdate()->firstOrFail();
            abort_unless($qdn->status === Qdn::STATUS_FOR_CROSS_APPROVAL, 409, 'This QDN is not currently awaiting PE / EE / QA / Production approval.');

            $round = (int) ($qdn->capa_round ?: 1);
            $existing = QdnCrossApproval::where(['qdn_id' => $qdn->id, 'round' => $round, 'department_key' => $key])->first();
            if ($existing) {
                return ['already' => $existing];
            }

            try {
                QdnCrossApproval::create([
                    'qdn_id' => $qdn->id,
                    'round' => $round,
                    'department_key' => $key,
                    'department_name' => CurrentEmployee::department(),
                    'approved_by_employee_id' => CurrentEmployee::employId(),
                    'approved_by_name' => CurrentEmployee::name(),
                    'approved_by_title' => CurrentEmployee::jobTitle(),
                    'remarks' => $data['remarks'] ?? null,
                    'approved_at' => now(),
                ]);
            } catch (QueryException $e) {
                // Unique (qdn_id, round, department_key): someone else in
                // this department approved a split second earlier.
                return ['already' => QdnCrossApproval::where(['qdn_id' => $qdn->id, 'round' => $round, 'department_key' => $key])->first()];
            }

            // Every required approver for THIS QDN: the four fixed
            // departments, plus the issuing department when it isn't one.
            $required = array_keys(QdnCrossApproval::departmentsFor($qdn));
            $done = QdnCrossApproval::where('qdn_id', $qdn->id)->where('round', $round)
                ->whereIn('department_key', $required)
                ->distinct()->count('department_key');

            if ($done >= count($required)) {
                $qdn->update(['status' => Qdn::STATUS_FOR_QA_VERIFICATION]);
                return ['complete' => true];
            }

            return ['complete' => false];
        });

        if (isset($result['already'])) {
            $by = $result['already']?->approved_by_name ?? 'someone else';
            return back()->with('error', "{$label} already approved QDN {$qdn->qdn_no} ({$by}).");
        }

        return redirect()->route('qdn.cross-approval.index')->with('success', $result['complete']
            ? "{$label} approved QDN {$qdn->qdn_no}. All required departments have approved, so it moved to QA Verification."
            : "{$label} approved QDN {$qdn->qdn_no}. Waiting for the other departments.");
    }

    private function departmentLabels(): array
    {
        return collect(QdnCrossApproval::departments())->map(fn ($d) => $d['label'])->all();
    }

    /** [key => [label, approved, approved_by, title, approved_at, remarks]] for the current round. */
    private function approvalSummary(Qdn $qdn): array
    {
        $round = (int) ($qdn->capa_round ?: 1);
        $rows = $qdn->crossApprovals->where('round', $round)->keyBy('department_key');

        return collect(QdnCrossApproval::departmentsFor($qdn))->map(function ($dept, $key) use ($rows) {
            $row = $rows->get($key);
            return [
                'label' => $dept['label'],
                'approved' => (bool) $row,
                'approved_by' => $row?->approved_by_name,
                'approved_by_title' => $row?->approved_by_title,
                'approved_at' => $row?->approved_at?->toDateTimeString(),
                'remarks' => $row?->remarks,
            ];
        })->all();
    }
}
