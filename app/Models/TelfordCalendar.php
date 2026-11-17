<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * qdn_new_db.telford_calendar -- TELFORD work-week calendar, one row per
 * day (cal_year / cal_quarter / cal_month / cal_workweek / cal_date).
 * Maintained on Maintenance > TELFORD Calendar (TelfordCalendarController).
 * Audit columns are date_created / date_updated plus who did it, not
 * Eloquent's created_at / updated_at, hence $timestamps = false.
 */
class TelfordCalendar extends Model
{
    protected $table = 'telford_calendar';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'cal_date' => 'date:Y-m-d',
        'cal_quarter' => 'integer',
        'cal_month' => 'integer',
        'cal_workweek' => 'integer',
        'date_created' => 'datetime',
        'date_updated' => 'datetime',
    ];
}
