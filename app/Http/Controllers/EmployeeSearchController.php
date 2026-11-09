<?php

namespace App\Http\Controllers;

use App\Models\Masterlist\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-side, type-ahead employee search -- backs every "select an
 * employee" picker across the QDN feature (RCA Select Responsible /
 * Issued To, PE Validate's Issued To, CAPA Inspected By / Operator /
 * Supervisor / Corrective Action Responsible). Replaces the old pattern
 * of preloading a whole department's (or the whole company's)
 * employee_masterlist roster into page props on every load -- results
 * are only ever fetched a page at a time (max 20), as the user types
 * either an EMPLOYID or part of an EMPLOYNAME.
 *
 * Narrowing params (all optional, combinable):
 *   - department: exact match against DEPARTMENT (RCA/CAPA "Responsible"
 *     / "Issued To", scoped to the QDN's issued_department)
 *   - titles[]: exact JOB_TITLE match, OR'd together (CAPA Inspected By)
 *   - like[]: JOB_TITLE LIKE patterns, OR'd together (CAPA
 *     Operator/Supervisor, e.g. '%Supervisor%')
 * With none of those, searches the whole employee_masterlist (PE
 * Validate's Issued To, and RCA's "Issued To (Man)" picker -- neither was
 * spec'd with a department restriction).
 */
class EmployeeSearchController extends Controller
{
    private const MAX_RESULTS = 20;

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string'],
            'titles' => ['nullable', 'array'],
            'titles.*' => ['string'],
            'like' => ['nullable', 'array'],
            'like.*' => ['string'],
        ]);

        // Built on the normalized masterlist (Masterlist\Employee =
        // employee_details) and the Lookups models: department via
        // employee_department -> department.dep_name, job title via
        // employee_position -> position.emp_position_name, plus
        // employee_position_level -> position_level.position_level_name.
        $query = Employee::query()->active()->withQdnRoster();

        if (!empty($data['department'])) {
            $query->whereHas('currentDepartment.department', function ($q) use ($data) {
                $q->where('dep_name', $data['department']);
            });
        }

        // titles[] / like[] match the employee's position name OR position
        // level name, so e.g. '%Supervisor%' finds supervisors whichever
        // of the two holds the word.
        if (!empty($data['titles']) || !empty($data['like'])) {
            $match = function (string $column) use ($data) {
                return function ($q) use ($data, $column) {
                    $q->where(function ($q) use ($data, $column) {
                        if (!empty($data['titles'])) {
                            $q->orWhereIn($column, $data['titles']);
                        }
                        foreach ($data['like'] ?? [] as $pattern) {
                            $q->orWhere($column, 'like', $pattern);
                        }
                    });
                };
            };

            $query->where(function ($q) use ($match) {
                $q->whereHas('currentPosition.position', $match('emp_position_name'))
                    ->orWhereHas('currentPositionLevel.positionLevel', $match('position_level_name'));
            });
        }

        // Type an employee ID or any part of the name.
        $term = trim((string) ($data['q'] ?? ''));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('employid', 'like', "%{$term}%")
                    ->orWhere('firstname', 'like', "%{$term}%")
                    ->orWhere('lastname', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT_WS(' ', firstname, lastname) LIKE ?", ["%{$term}%"])
                    ->orWhereRaw("CONCAT_WS(' ', firstname, middlename, lastname) LIKE ?", ["%{$term}%"]);
            });
        }

        $employees = $query->orderBy('lastname')
            ->orderBy('firstname')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (Employee $e) => $e->toQdnRoster())
            ->values();

        return response()->json(['employees' => $employees]);
    }
}
