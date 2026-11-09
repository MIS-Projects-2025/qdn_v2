<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQdnValidationRequest;
use App\Models\Masterlist\Employee;
use App\Models\Qdn;
use App\Models\QdnValidation;
use App\Support\CurrentEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PeValidationController extends Controller
{
    /**
     * Only employees whose employee.masterlist DEPARTMENT is "PE" can
     * see or act on the validation queue -- this is what makes it
     * visible to "all PE", not just whoever happens to hit the route.
     */
    private function assertIsPe(): void
    {
        if (!CurrentEmployee::isPe()) {
            throw new HttpException(403, 'Only Department: PE can access QDN validation.');
        }
    }

    /**
     * The list view for this queue now lives at pe.queue.index
     * (PeQueueController), combined with the PE: RCA Validation queue --
     * this index() was removed when the two were merged into one
     * page/nav link.
     */

    /**
     * Step 2: Validate a single QDN.
     */
    public function show(Qdn $qdn): Response
    {
        $this->assertIsPe();

        return Inertia::render('Qdn/Validate', [
            'qdn' => $qdn,
            // "Issued To" is a server-side type-ahead (EmployeeSearchSelect
            // -> GET /employees/search, unscoped -- any employee company-
            // wide) instead of preloading the entire employee_masterlist
            // into every page load. employeeLookup() below is unused by
            // this page now, left in place in case anything else calls it.
        ]);
    }

    /**
     * AJAX lookup used by the Validate form: given an EMPLOYID, resolve
     * DEPARTMENT / STATION / PRODLINE / TEAM from employee.masterlist.
     */
    public function employeeLookup(Request $request): JsonResponse
    {
        $this->assertIsPe();

        $request->validate(['employ_id' => ['required', 'string']]);

        $employee = Employee::find($request->string('employ_id'));

        return response()->json([
            'department' => $employee?->DEPARTMENT,
            'station' => $employee?->STATION,
            'prodline' => $employee?->PRODLINE,
            'team' => $employee?->TEAM,
        ]);
    }

    /**
     * Record the PE decision -- now a full disposition (Rework / Split
     * Lot / Shutdown / Shipback / Use As Is / Invalid), not a plain
     * Valid/Invalid choice: PE only reviews a QDN once, at this step, so
     * whatever used to be decided later at PE: RCA Validation
     * (QdnWorkflowController@peRcaStore) is decided upfront here instead.
     * Invalid closes the QDN immediately; every other disposition routes
     * it to the selected employee's department for RCA (then straight to
     * CAPA once RCA is submitted -- see QdnWorkflowController@rcaStore).
     */
    public function store(StoreQdnValidationRequest $request, Qdn $qdn): RedirectResponse
    {
        $this->assertIsPe();

        $data = $request->validated();
        $isInvalid = $data['disposition'] === Qdn::DISPOSITION_INVALID;

        $employee = null;
        if (!empty($data['issued_to_employee_id'])) {
            $employee = Employee::find($data['issued_to_employee_id']);
        }

        QdnValidation::create([
            'qdn_id' => $qdn->id,
            'type' => QdnValidation::TYPE_VALIDATION,
            // Kept for QdnDetailCard's "PE Validation" section (Decision:
            // Valid/Invalid) -- true for every disposition except Invalid.
            'is_valid' => !$isInvalid,
            'issued_to_employee_id' => $employee?->EMPLOYID,
            'issued_to_name' => $employee?->EMPLOYNAME,
            'department' => $employee?->DEPARTMENT,
            'station' => $employee?->STATION,
            'prodline' => $employee?->PRODLINE,
            'team' => $employee?->TEAM,
            // Server-derived from session('emp_data'), not client input.
            'validated_by' => CurrentEmployee::name(),
            'validated_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        $qdn->update([
            // Non-Invalid QDNs enter the full workflow (RCA -> CAPA -> Dept
            // approval -> QA verification) -- see QdnWorkflowController
            // for every step after this one.
            'status' => $isInvalid ? Qdn::STATUS_INVALID : Qdn::STATUS_FOR_DEPT_RCA,
            // Denormalized so the receiving department's queue can filter
            // with a plain WHERE -- see IssuedQdnController@index and
            // QdnWorkflowController's assertOwningDepartment().
            'issued_department' => $isInvalid ? null : $employee?->DEPARTMENT,
            // The disposition itself, recorded here now instead of at a
            // later PE RCA Validation step -- same field QdnDetailCard's
            // "PE Disposition" section and peDispositionLabel() already
            // read.
            'pe_disposition' => $data['disposition'],
            'pe_disposition_remarks' => $data['remarks'] ?? null,
            'pe_disposition_by' => CurrentEmployee::name(),
            'pe_disposition_at' => now(),
        ]);

        return redirect()
            ->route('pe.queue.index')
            ->with('success', $isInvalid
                ? "QDN {$qdn->qdn_no} marked invalid."
                : "QDN {$qdn->qdn_no} disposition recorded ({$qdn->peDispositionLabel()}), routed to {$employee?->DEPARTMENT} for RCA.");
    }
}
