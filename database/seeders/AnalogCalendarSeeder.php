<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds qdn_new_db.analog_calendar for fiscal year 2027: 364 days
 * (52 work weeks) starting 2026-11-01, a 4-4-5 weeks-per-month fiscal
 * calendar (Nov -> Oct). Reimplements, in PHP, the raw INSERT the user
 * supplied directly (which built the date range via a MySQL
 * numbers-table CROSS JOIN trick) -- same output, just portable/
 * version-controlled and run through `php artisan db:seed` instead of
 * pasted into phpMyAdmin/a MySQL client by hand.
 *
 * Safe to re-run: any cal_date already in the table is skipped, same as
 * the original SQL's `WHERE NOT EXISTS`.
 *
 * Writes go through DB::table()->insert() rather than the AnalogCalendar
 * Eloquent model -- that model is intentionally read-only from this
 * app's side (see its docblock: "rows are maintained elsewhere"), so
 * this seeder doesn't need to add mass-assignment ($fillable) to it just
 * to write these rows.
 *
 * EDIT the two constants below before running -- same
 * 'YOUR_EMP_NUM'/'YOUR NAME' placeholders the original INSERT had.
 *
 * Run with: php artisan db:seed --class=AnalogCalendarSeeder
 * (deliberately NOT registered in DatabaseSeeder::run() -- this is a
 * one-off fiscal-year population task, not something that should fire on
 * every `php artisan db:seed`.)
 */
class AnalogCalendarSeeder extends Seeder
{
    private const CREATED_BY_EMP_NUM = 'YOUR_EMP_NUM';
    private const CREATED_BY_EMP_NAME = 'YOUR NAME';

    private const START_DATE = '2026-11-01';
    private const TOTAL_DAYS = 364; // 52 work weeks

    // Work week -> fiscal month, 4-4-5 pattern (Nov start, Oct end).
    // Keys are the LAST work week of that month; e.g. weeks 1-4 are
    // November (11), weeks 5-8 are December (12), etc.
    private const WORKWEEK_MONTH_BREAKS = [
        4 => 11, 8 => 12, 13 => 1, 17 => 2, 21 => 3, 26 => 4,
        30 => 5, 34 => 6, 39 => 7, 43 => 8, 47 => 9, 52 => 10,
    ];

    public function run(): void
    {
        $start = Carbon::parse(self::START_DATE);
        $end = $start->copy()->addDays(self::TOTAL_DAYS - 1);

        $existing = DB::table('analog_calendar')
            ->whereBetween('cal_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('cal_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $rows = [];

        for ($n = 0; $n < self::TOTAL_DAYS; $n++) {
            $current = $start->copy()->addDays($n);

            if (isset($existing[$current->toDateString()])) {
                continue; // already seeded
            }

            $week = intdiv($n, 7) + 1; // 1..52

            $rows[] = [
                'cal_date' => $current->toDateString(),
                'cal_workweek' => 700 + $week,
                'cal_year' => '2027',
                'cal_quarter' => (int) ceil($week / 13),
                'cal_month' => $this->monthForWeek($week),
                'created_by_emp_num' => self::CREATED_BY_EMP_NUM,
                'created_by_emp_name' => self::CREATED_BY_EMP_NAME,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('analog_calendar')->insert($chunk);
        }

        $skipped = self::TOTAL_DAYS - count($rows);
        $this->command?->info(
            count($rows) . ' analog_calendar rows inserted for fiscal year 2027'
            . ($skipped > 0 ? " ({$skipped} already existed, skipped)." : '.')
        );
    }

    private function monthForWeek(int $week): int
    {
        foreach (self::WORKWEEK_MONTH_BREAKS as $maxWeek => $month) {
            if ($week <= $maxWeek) {
                return $month;
            }
        }

        return 10; // unreachable at 52 weeks total, kept as a safe default
    }
}
