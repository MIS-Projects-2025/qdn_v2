<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps qdn_new_db.analog_calendar topped up one fiscal year at a time,
 * instead of someone having to hand-run a seeder (or raw SQL) every
 * fiscal year. Each fiscal year is 364 days / 52 work weeks, the same
 * 4-4-5 weeks-per-month pattern starting in November used to seed fiscal
 * year 2027 (2026-11-01 .. 2027-10-30) -- see WORKWEEK_MONTH_BREAKS.
 *
 * Run modes:
 *
 *   php artisan calendar:generate-year
 *     "Auto" mode -- finds the latest cal_date already in the table and,
 *     if today is within --lead-days of running out (default 60),
 *     generates the NEXT fiscal year starting the day after the current
 *     latest cal_date. Does nothing if there's still plenty of runway,
 *     so it's cheap and safe to run on a schedule -- see
 *     routes/console.php, where this is registered to run daily.
 *
 *     IMPORTANT: 364 days (52 work weeks) is not a full calendar year
 *     (365/366 days), so chaining fiscal years back-to-back like this
 *     DRIFTS the start date backward by ~1-2 days every year -- fiscal
 *     2027 starts 2026-11-01, but fiscal 2028 would then start
 *     2027-10-31, fiscal 2029 around 2028-10-30, and so on. Real 52/53-
 *     week fiscal calendars normally correct for this with an occasional
 *     53rd (leap) week to stay anchored to a fixed date every few years.
 *     This command does NOT do that -- it was never specified how often
 *     a 53-week year should happen, so if you need the fiscal year to
 *     keep landing on Nov 1 long-term, that correction needs to be added
 *     (e.g. insert 371 days every ~5-6 years instead of 364) before this
 *     drifts far enough to matter to you.
 *
 *   php artisan calendar:generate-year 2027 --start=2026-11-01
 *     Explicit/bootstrap mode -- generates exactly the given fiscal year
 *     starting on the given date, regardless of what's already in the
 *     table (existing dates are still skipped, never duplicated). Use
 *     this once, for the very first fiscal year -- auto mode needs at
 *     least one existing fiscal year to extrapolate "next start date"
 *     from, so it can't bootstrap an empty table itself.
 *
 * created_by_emp_num/created_by_emp_name come from .env
 * (ANALOG_CALENDAR_EMP_NUM / ANALOG_CALENDAR_EMP_NAME) instead of a
 * hardcoded placeholder, since this is meant to run unattended --
 * set those two once in .env rather than editing this file every year.
 *
 * Writes go through DB::table()->insert() rather than the AnalogCalendar
 * Eloquent model -- that model is intentionally read-only from this
 * app's side (see its docblock: "rows are maintained elsewhere").
 */
class GenerateAnalogCalendarYear extends Command
{
    protected $signature = 'calendar:generate-year
        {year? : Fiscal year to generate, e.g. 2027 (omit for auto mode)}
        {--start= : Start date for that fiscal year, e.g. 2026-11-01 (required together with year)}
        {--lead-days=60 : Auto mode only -- generate the next fiscal year once fewer than this many days of runway are left}
        {--force : Auto mode: generate even if there is still plenty of runway left. Explicit mode: has no extra effect (existing dates are always skipped either way).}';

    protected $description = 'Top up qdn_new_db.analog_calendar with the next fiscal year (4-4-5 weeks/month, 52 work weeks), auto-detecting when one is needed';

    private const DAYS_PER_FISCAL_YEAR = 364; // 52 work weeks

    // Work week -> fiscal month, 4-4-5 pattern. Keys are the LAST work
    // week of that month; e.g. weeks 1-4 are November (11), weeks 5-8 are
    // December (12), etc.
    private const WORKWEEK_MONTH_BREAKS = [
        4 => 11, 8 => 12, 13 => 1, 17 => 2, 21 => 3, 26 => 4,
        30 => 5, 34 => 6, 39 => 7, 43 => 8, 47 => 9, 52 => 10,
    ];

    public function handle(): int
    {
        $year = $this->argument('year');

        return $year ? $this->generateExplicit((int) $year) : $this->generateAuto();
    }

    private function generateExplicit(int $year): int
    {
        $start = $this->option('start');

        if (!$start) {
            $this->error('--start=YYYY-MM-DD is required when a fiscal year is given explicitly.');
            $this->line('  e.g. php artisan calendar:generate-year 2027 --start=2026-11-01');
            return self::FAILURE;
        }

        $this->insertFiscalYear($year, Carbon::parse($start));

        return self::SUCCESS;
    }

    private function generateAuto(): int
    {
        $latest = DB::table('analog_calendar')->max('cal_date');

        if (!$latest) {
            $this->error('analog_calendar is empty -- auto mode has nothing to extrapolate from. Bootstrap the first fiscal year explicitly:');
            $this->line('  php artisan calendar:generate-year 2027 --start=2026-11-01');
            return self::FAILURE;
        }

        $latestDate = Carbon::parse($latest)->startOfDay();
        $today = Carbon::today();

        // How many days of calendar are still ahead of us -- 0 (not
        // negative) if the table has already fallen behind today.
        $daysRemaining = $latestDate->lt($today) ? 0 : $today->diffInDays($latestDate);
        $leadDays = (int) $this->option('lead-days');

        if (!$this->option('force') && $daysRemaining > $leadDays) {
            $this->info("Calendar has {$daysRemaining} day(s) of runway left (latest cal_date: {$latestDate->toDateString()}) -- nothing to do yet.");
            return self::SUCCESS;
        }

        $latestYear = (int) DB::table('analog_calendar')->where('cal_date', $latestDate->toDateString())->value('cal_year');
        $nextYear = $latestYear + 1;
        $nextStart = $latestDate->copy()->addDay();

        $this->info("Runway low ({$daysRemaining} day(s) left) -- generating fiscal year {$nextYear}, starting {$nextStart->toDateString()}.");

        $this->insertFiscalYear($nextYear, $nextStart);

        return self::SUCCESS;
    }

    private function insertFiscalYear(int $year, Carbon $start): void
    {
        $existing = DB::table('analog_calendar')
            ->whereBetween('cal_date', [
                $start->toDateString(),
                $start->copy()->addDays(self::DAYS_PER_FISCAL_YEAR - 1)->toDateString(),
            ])
            ->pluck('cal_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $rows = [];

        for ($n = 0; $n < self::DAYS_PER_FISCAL_YEAR; $n++) {
            $current = $start->copy()->addDays($n);

            if (isset($existing[$current->toDateString()])) {
                continue; // already seeded
            }

            $week = intdiv($n, 7) + 1; // 1..52

            $rows[] = [
                'cal_date' => $current->toDateString(),
                'cal_workweek' => 700 + $week,
                'cal_year' => (string) $year,
                'cal_quarter' => (int) ceil($week / 13),
                'cal_month' => $this->monthForWeek($week),
                'created_by_emp_num' => env('ANALOG_CALENDAR_EMP_NUM', 'SYSTEM'),
                'created_by_emp_name' => env('ANALOG_CALENDAR_EMP_NAME', 'Automated Calendar Generator'),
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('analog_calendar')->insert($chunk);
        }

        $skipped = self::DAYS_PER_FISCAL_YEAR - count($rows);
        $this->info(
            count($rows) . " analog_calendar row(s) inserted for fiscal year {$year}"
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
