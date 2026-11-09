<?php

namespace App\Http\Controllers;

use App\Models\Qdn;
use App\Support\CurrentEmployee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Main dashboard (route: dashboard).
 *
 *  - Everyone: charts of the QDNs issued TO them (latest issuance on
 *    qdn_validations.issued_to_employee_id = their Authify emp_id):
 *    total, by non-conformity, by classification, by month issued.
 *  - Approvers (approver1/2/3 in masterlist.employee_approver): additionally
 *    a table of every QDN issued to the employees they approve for
 *    (QDN No, Detection Area, Non-Conformity, Classification, Date Issued).
 *
 * "Date issued" = qdns.created_at.
 */
class DashboardController extends Controller
{
    private const MONTHS = 12;

    public function index(Request $request)
    {
        $empId = CurrentEmployee::employId();
        $department = CurrentEmployee::department();
        $approvees = CurrentEmployee::approveeIds();
        $isLeader = $approvees !== [];

        return Inertia::render('Dashboard', [
            'personal' => $empId ? $this->personalStats((string) $empId) : null,
            'isLeader' => $isLeader,
            'department' => $department,
            'approveeCount' => count($approvees),
            'departmentQdns' => $isLeader
                ? $this->departmentQdns($request, $approvees)
                : null,
            'filters' => $request->only(['search', 'from', 'to', 'classification']),
        ]);
    }

    private function issuedTo(string $empId): Builder
    {
        return Qdn::query()->whereHas('validation', function ($q) use ($empId) {
            $q->where('issued_to_employee_id', $empId);
        });
    }

    private function personalStats(string $empId): array
    {
        $base = fn () => $this->issuedTo($empId);

        $byNonconformity = $base()
            ->selectRaw("COALESCE(nonconformity_name, 'Unspecified') as label, COUNT(*) as total")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $byClassification = $base()
            ->selectRaw("COALESCE(classification, 'Unclassified') as label, COUNT(*) as total")
            ->groupBy('label')
            ->pluck('total', 'label');

        // Last 12 months, zero-filled, oldest first.
        $start = Carbon::now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $monthly = $base()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->countBy(fn ($q) => $q->created_at->format('Y-m'));

        $months = collect(range(0, self::MONTHS - 1))->map(function ($i) use ($start, $monthly) {
            $m = $start->copy()->addMonths($i);
            return ['label' => $m->format('M Y'), 'total' => (int) ($monthly[$m->format('Y-m')] ?? 0)];
        });

        $recent = $base()
            ->latest('created_at')->latest('id')
            ->limit(10)
            ->get(['id', 'qdn_no', 'nonconformity_name', 'classification', 'status', 'created_at'])
            ->map(fn (Qdn $q) => [
                'id' => $q->id,
                'qdn_no' => $q->qdn_no,
                'nonconformity' => $q->nonconformity_name,
                'classification' => $q->classification,
                'status' => $q->status,
                'status_label' => $q->statusLabel(),
                'date_issued' => $q->created_at?->toDateString(),
            ]);

        return [
            'total' => $base()->count(),
            'open' => $base()->whereNotIn('status', [Qdn::STATUS_CLOSED, Qdn::STATUS_INVALID_DISPOSITION])->count(),
            'byNonconformity' => $byNonconformity,
            'byClassification' => [
                'Minor' => (int) ($byClassification['Minor'] ?? 0),
                'Major' => (int) ($byClassification['Major'] ?? 0),
                'Critical' => (int) ($byClassification['Critical'] ?? 0),
                'Unclassified' => (int) ($byClassification['Unclassified'] ?? 0),
            ],
            'byMonth' => $months,
            'recent' => $recent,
        ];
    }

    /** @param string[] $approvees employids the current user approves for */
    private function departmentQdns(Request $request, array $approvees)
    {
        $query = Qdn::query()
            ->whereIn('status', Qdn::DEPARTMENT_VISIBLE_STATUSES)
            ->whereHas('validation', fn ($q) => $q->whereIn('issued_to_employee_id', $approvees));

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('qdn_no', 'like', "%{$search}%")
                    ->orWhere('detection_area', 'like', "%{$search}%")
                    ->orWhere('nonconformity_name', 'like', "%{$search}%");
            });
        }
        if (in_array($request->query('classification'), ['Minor', 'Major', 'Critical'], true)) {
            $query->where('classification', $request->query('classification'));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query->latest('created_at')->latest('id')
            ->paginate(15, ['id', 'qdn_no', 'detection_area', 'nonconformity_name', 'classification', 'status', 'created_at'])
            ->withQueryString()
            ->through(fn (Qdn $q) => [
                'id' => $q->id,
                'qdn_no' => $q->qdn_no,
                'detection_area' => $q->detection_area,
                'nonconformity' => $q->nonconformity_name,
                'classification' => $q->classification,
                'status' => $q->status,
                'status_label' => $q->statusLabel(),
                'date_issued' => $q->created_at?->toDateString(),
            ]);
    }
}
