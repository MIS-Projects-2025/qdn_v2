<?php

namespace App\Http\Controllers;

use App\Models\Qdn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FVI / OQA list: every QDN whose Detection Area is Final Visual
 * Inspection or OQA (the same areas that require photos, see
 * Qdn::PHOTO_REQUIRED_AREAS), with photo count and a thumbnail.
 */
class FviOqaController extends Controller
{
    /** Tab key => detection_area substrings it matches. */
    public const AREAS = [
        'fvi' => ['Final Visual Inspection', 'FVI'],
        'oqa' => ['OQA'],
    ];

    public function index(Request $request): Response
    {
        $area = array_key_exists($request->query('area'), self::AREAS) ? $request->query('area') : '';

        $base = Qdn::query();
        $this->applyFilters($base, $request);

        $counts = [
            'all' => (clone $base)->where(fn ($w) => $this->matchAreas($w, array_merge(...array_values(self::AREAS))))->count(),
            'fvi' => (clone $base)->where(fn ($w) => $this->matchAreas($w, self::AREAS['fvi']))->count(),
            'oqa' => (clone $base)->where(fn ($w) => $this->matchAreas($w, self::AREAS['oqa']))->count(),
        ];

        $patterns = $area ? self::AREAS[$area] : array_merge(...array_values(self::AREAS));

        $qdns = $base
            ->where(fn ($w) => $this->matchAreas($w, $patterns))
            ->with('photos')
            ->withCount('photos')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Qdn $q) => [
                'id' => $q->id,
                'qdn_no' => $q->qdn_no,
                'area' => $this->areaKey($q->detection_area),
                'detection_area' => $q->detection_area,
                'customer_name' => $q->customer_name,
                'device_name' => $q->device_name,
                'lot_id' => $q->lot_id,
                'nonconformity' => $q->nonconformity_name,
                'classification' => $q->classification,
                'status' => $q->status,
                'status_label' => $q->statusLabel(),
                'issued_department' => $q->issued_department,
                'date_issued' => $q->created_at?->format('Y-m-d'),
                'photos_count' => $q->photos_count,
                'thumbnail' => $q->photos->first()?->url(),
            ]);

        return Inertia::render('Qdn/FviOqa/Index', [
            'qdns' => $qdns,
            'counts' => $counts,
            'filters' => array_merge($request->only(['q', 'date_from', 'date_to']), ['area' => $area]),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $query->where(fn ($w) => $w
                ->where('qdn_no', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('device_name', 'like', "%{$term}%")
                ->orWhere('lot_id', 'like', "%{$term}%"));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->query('date_from'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->query('date_to'))->endOfDay());
        }
    }

    private function matchAreas(Builder $w, array $patterns): void
    {
        foreach ($patterns as $p) {
            $w->orWhere('detection_area', 'like', "%{$p}%");
        }
    }

    private function areaKey(?string $detectionArea): ?string
    {
        foreach (self::AREAS as $key => $patterns) {
            foreach ($patterns as $p) {
                if ($detectionArea && stripos($detectionArea, $p) !== false) {
                    return $key;
                }
            }
        }

        return null;
    }
}
