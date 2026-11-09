<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only mirror of the legacy qdn_db.qdn_record table -- the OLD QDN
 * system's own full record set, completely separate from this app's own
 * `qdns` table (see Qdn::class / QdnController::records(), which is a
 * different "QDN Records" page). Lives on the `tspi_qa` connection, same
 * one already used elsewhere for one-time legacy qdn_db lookups
 * (customer_list/package_list/machine_list/location_list/
 * nonconformity_list). If your real config/database.php names this
 * connection something other than `tspi_qa`, change the value below --
 * nothing else in this feature needs to know the name.
 *
 * Nothing in this app writes to qdn_record -- it's browsed via
 * LegacyQdnRecordController only (index + show), never created/updated/
 * deleted here.
 */
class QdnRecord extends Model
{
    protected $connection = 'tspi_qa';

    protected $table = 'qdn_record';

    // date_created is the only "created" column qdn_record has, and
    // there's no updated_at at all -- Eloquent's automatic
    // created_at/updated_at handling doesn't apply to this table.
    public $timestamps = false;

    protected $casts = [
        'detection_datetime' => 'datetime',
        'response_24hr_sched' => 'datetime',
        'respond_24hr_date' => 'datetime',
        'disposition_date' => 'datetime',
        'date_closed' => 'datetime',
        'date_created' => 'datetime',
        'lot_qty' => 'integer',
        'qdn_status' => 'integer',
        'nonconformity_classification' => 'integer',
        'response_status' => 'integer',
    ];

    // qdn_status column comment: 0 = pending; 1 = pending and valid;
    // 2 = closed and valid; 3 = closed and invalid.
    public const STATUS_LABELS = [
        0 => 'Pending',
        1 => 'Pending and Valid',
        2 => 'Closed and Valid',
        3 => 'Closed and Invalid',
    ];

    // nonconformity_classification column comment: 1 = minor; 2 = major; 3 = critical.
    public const CLASSIFICATION_LABELS = [
        1 => 'Minor',
        2 => 'Major',
        3 => 'Critical',
    ];

    // response_status column comment: 0 = pending; 1 = responded.
    public const RESPONSE_STATUS_LABELS = [
        0 => 'Pending',
        1 => 'Responded',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->qdn_status] ?? "Unknown ({$this->qdn_status})";
    }

    public function classificationLabel(): ?string
    {
        if ($this->nonconformity_classification === null) {
            return null;
        }

        return self::CLASSIFICATION_LABELS[$this->nonconformity_classification]
            ?? "Unknown ({$this->nonconformity_classification})";
    }

    public function responseStatusLabel(): string
    {
        return self::RESPONSE_STATUS_LABELS[$this->response_status] ?? "Unknown ({$this->response_status})";
    }
}
