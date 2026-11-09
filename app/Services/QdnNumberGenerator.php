<?php

namespace App\Services;

use App\Models\AnalogCalendar;
use App\Models\Qdn;
use Illuminate\Support\Facades\DB;

/**
 * Generates the auto QDN No in the format yy-xxxxxxx, e.g. 27-0000001
 * (two-digit fiscal year, then a 7-digit sequence that resets yearly).
 * The year segment -- and therefore the once-a-year sequence reset -- now
 * comes from the company's own analog_calendar (cal_year for today's
 * cal_date), not PHP's Gregorian now()->year. The two diverge whenever a
 * fiscal year starts before January 1: per analog_calendar, fiscal year
 * 2027 starts 2026-11-01, so a QDN created in November/December 2026
 * already needs a "27-" prefix, not "26-". Falls back to now()->year
 * if today's date has no analog_calendar row yet (e.g. the calendar
 * hasn't been seeded that far ahead), so Create QDN never hard-fails over
 * missing calendar data.
 *
 * Generation happens inside store() (not on the create form's initial
 * GET) so an abandoned form doesn't burn a number, and we lock the row
 * set for the current year to avoid a race between two people submitting
 * at the same moment.
 */
class QdnNumberGenerator
{
    public static function next(): string
    {
        $year = static::currentCalYear();

        // qdns lives on the default connection (qdn_new_db), same as
        // this DB::transaction() call, so no explicit connection() needed.
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
     * Today's cal_year from analog_calendar (e.g. '2027' for a date that
     * falls in fiscal year 2027 even though the Gregorian year is still
     * 2026). Falls back to now()->year, cast to the same string shape as
     * cal_year, when today isn't in the calendar yet.
     */
    private static function currentCalYear(): string
    {
        $calYear = AnalogCalendar::query()
            ->where('cal_date', now()->toDateString())
            ->value('cal_year');

        return $calYear !== null ? (string) $calYear : (string) now()->year;
    }
}
