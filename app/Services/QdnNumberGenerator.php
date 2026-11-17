<?php

namespace App\Services;

use App\Models\Qdn;
use App\Models\TelfordCalendar;
use Illuminate\Support\Facades\DB;

/**
 * Generates the auto QDN No in the format yy-xxxxxxx, e.g. 27-0000001
 * (two-digit year, then a 7-digit sequence that resets yearly).
 *
 * The year segment -- and therefore the once-a-year sequence reset -- comes
 * from the TELFORD calendar (telford_calendar.cal_year for today's cal_date,
 * maintained on Maintenance > TELFORD Calendar), not the regular calendar.
 * So a QDN created on a date that TELFORD puts in year 2027 gets "27-" even
 * if the Gregorian year is still 2026, and numbering restarts at 0000001 on
 * the first day of each TELFORD year.
 *
 * If today has no telford_calendar row yet (the calendar hasn't been filled
 * that far ahead), it falls back to the regular year so Create QDN never
 * hard-fails over missing calendar data. Keep the TELFORD calendar filled in
 * ahead of time to avoid that.
 *
 * Generation happens inside store() (not on the create form's initial GET)
 * so an abandoned form doesn't burn a number, and the rows for the current
 * year are locked to avoid a race between two people submitting at once.
 */
class QdnNumberGenerator
{
    public static function next(): string
    {
        $year = static::currentTelfordYear();

        // qdns and telford_calendar both live on the default connection
        // (qdn_new_db), same as this DB::transaction() call.
        return DB::transaction(function () use ($year) {
            $prefix = substr($year, -2) . '-';

            $lastNumber = Qdn::where('qdn_no', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('qdn_no')
                ->value('qdn_no');

            $nextSequence = $lastNumber
                ? ((int) substr($lastNumber, strlen($prefix))) + 1
                : 1;

            return $prefix . str_pad((string) $nextSequence, 7, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Today's cal_year from telford_calendar (e.g. '2027'). Falls back to
     * now()->year when today isn't in the TELFORD calendar yet.
     */
    public static function currentTelfordYear(): string
    {
        $calYear = TelfordCalendar::query()
            ->whereDate('cal_date', now()->toDateString())
            ->value('cal_year');

        $calYear = $calYear !== null ? trim((string) $calYear) : '';

        return $calYear !== '' ? $calYear : (string) now()->year;
    }
}
