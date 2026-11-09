<?php

namespace App\Http\Controllers;

use App\Models\Masterlist\Employee;
use App\Models\Qdn;
use App\Models\QdnValidation;
use App\Support\CurrentEmployee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Lets Department: PE correct who a QDN was issued to, after the fact --
 * e.g. PE originally validated it, but the wrong employee/department was
 * picked. Same authority as the original assignment (PeValidationController
 * @store), so it's gated the same way (assertIsPe()).
 *
 * Every reassignment INSERTS a new QdnValidation row (type=reassignment)
 * rather than editing the original one -- see QdnValidation::TYPE_REASSIGNMENT
 * and Qdn::validation()/validations(). qdns.issued_department is updated in
 * place though, since every department-scoped query (IssuedQdnController,
 * QdnWorkflowController's assertOwningDepartment(), the RCA/CAPA/Approval
 * queues, QdnNotificationCounts) filters on that single denormalized column,
 * not on the validation history.
 */
class QdnReassignController extends Controller
{
    private function assertIsPe(): void
    {
        if (!CurrentEmployee::isPe()) {
            throw new HttpException(403, 'Only Department: PE can reassign QDN issuance.');
        }
    }

    /**
     * Every QDN currently owned by a department (or awaiting PE's RCA
     * validation, which is effectively still that department's QDN) --
     * not scoped to any one department, since PE oversees all of them.
     */
    public function index(): Response
    {
        $this->assertIsPe();

        return Inertia::render('Qdn/Reassign/Index', [
            'qdns' => Qdn::whereIn('status', Qdn::REASSIGNABLE_STATUSES)
                ->orderBy('created_at')
                ->get(['id', 'qdn_no', 'customer_name', 'device_name', 'classification', 'issued_department', 'status', 'created_at'])
                ->map(fn (Qdn $qdn) => [
                    ...$qdn->toArray(),
                    'status_label' => $qdn->statusLabel(),
                ]),
        ]);
    }

    /**
     * Reassignment form -- current issuance (from the latest QdnValidation
     * row) plus the full history, so PE can see what's being corrected and
     * why before picking a new employee.
     */
    public function edit(Qdn $qdn): Response
    {
        $this->assertIsPe();
        $this->assertReassignable($qdn);

        return Inertia::render('Qdn/Reassign/Edit', [
            'qdn' => $qdn,
            'currentValidation' => $qdn->validation,
            'history' => $qdn->validations()->get(),
        ]);
    }

    /**
     * Record the correction. Mirrors PeValidationController@store's
     * Employee lookup + snapshot pattern, but always type=reassignment and
     * never touches `status` -- reassigning who owns a QDN doesn't move it
     * to a different workflow step.
     */
    public function update(Request $request, Qdn $qdn): RedirectResponse
    {
        $this->assertIsPe();
        $this->assertReassignable($qdn);

        $data = $request->validate([
            'issued_to_employee_id' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $employee = Employee::find($data['issued_to_employee_id']);

        if (!$employee) {
            return back()->withErrors(['issued_to_employee_id' => 'Select a valid employee.']);
        }

        QdnValidation::create([
            'qdn_id' => $qdn->id,
            'type' => QdnValidation::TYPE_REASSIGNMENT,
            'is_valid' => true,
            'issued_to_employee_id' => $employee->EMPLOYID,
            'issued_to_name' => $employee->EMPLOYNAME,
            'department' => $employee->DEPARTMENT,
            'station' => $employee->STATION,
            'prodline' => $employee->PRODLINE,
            'team' => $employee->TEAM,
            'validated_by' => CurrentEmployee::name(),
            'validated_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        $qdn->update([
            'issued_department' => $employee->DEPARTMENT,
        ]);

        return redirect()
            ->route('pe.reassign.index')
            ->with('success', "QDN {$qdn->qdn_no} reissued to {$employee->EMPLOYNAME} ({$employee->DEPARTMENT}).");
    }

    private function assertReassignable(Qdn $qdn): void
    {
        if (!in_array($qdn->status, Qdn::REASSIGNABLE_STATUSES, true)) {
            throw new HttpException(422, 'This QDN is not currently in a reassignable state.');
        }
    }
}
