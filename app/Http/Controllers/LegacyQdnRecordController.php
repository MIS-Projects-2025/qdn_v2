<?php

namespace App\Http\Controllers;

use App\Models\QdnRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only browser for the legacy qdn_db.qdn_record table (the OLD QDN
 * system's full record set) -- distinct from this app's own "QDN
 * Records" page (QdnController::records(), which reads the current
 * `qdns` table). Nothing here ever writes back to qdn_record.
 */
class LegacyQdnRecordController extends Controller
{
    // Searchable columns, whitelisted the same way QdnController's
    // RECORDS_SORTABLE is -- qdn_no is the obvious primary identifier;
    // lot_id_no was specifically asked for too.
    private const SEARCHABLE_COLUMNS = ['qdn_no', 'lot_id_no'];

    /**
     * Server-side search -- GET /qdn/legacy-records?q=... re-queries the
     * database on every search (not a client-side filter of a fully
     * loaded list), same pattern as QdnController::records().
     */
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q', ''));

        $query = QdnRecord::query();

        if ($term !== '') {
            $query->where(function ($w) use ($term) {
                foreach (self::SEARCHABLE_COLUMNS as $column) {
                    $w->orWhere($column, 'like', "%{$term}%");
                }
            });
        }

        $records = $query->orderByDesc('date_created')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (QdnRecord $r) => [
                'id' => $r->id,
                'qdn_no' => $r->qdn_no,
                'customer' => $r->customer,
                'device_name' => $r->device_name,
                'lot_id_no' => $r->lot_id_no,
                'issued_by_name' => $r->issued_by_name,
                'issued_to_name' => $r->issued_to_name,
                'qdn_status_label' => $r->statusLabel(),
                'date_created' => $r->date_created,
            ]);

        return Inertia::render('Qdn/LegacyRecords/Index', [
            'records' => $records,
            'filters' => $request->only(['q']),
        ]);
    }

    /**
     * Every column on the record, rendered as a read-only form on the
     * frontend (Qdn/LegacyRecords/Show.jsx) -- not a table row, a filled-
     * out form, per your request.
     */
    public function show(QdnRecord $qdnRecord): Response
    {
        return Inertia::render('Qdn/LegacyRecords/Show', [
            'record' => array_merge($qdnRecord->toArray(), [
                'qdn_status_label' => $qdnRecord->statusLabel(),
                'nonconformity_classification_label' => $qdnRecord->classificationLabel(),
                'response_status_label' => $qdnRecord->responseStatusLabel(),
            ]),
        ]);
    }
}
