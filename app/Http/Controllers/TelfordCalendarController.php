<?php

namespace App\Http\Controllers;

use App\Models\TelfordCalendar;
use App\Support\CurrentEmployee;
use App\Support\TableExport;
use App\Support\TableImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Maintenance > TELFORD Calendar (QA / QMS, see QdnMaintenanceMiddleware):
 * list, add, edit and delete telford_calendar rows. Who added / last
 * changed a row, and when, is filled in from the signed-in employee.
 */
class TelfordCalendarController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $rows = $this->filteredQuery($filters)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (TelfordCalendar $r) => [
                'id' => $r->id,
                'cal_year' => $r->cal_year,
                'cal_quarter' => $r->cal_quarter,
                'cal_month' => $r->cal_month,
                'cal_workweek' => $r->cal_workweek,
                'cal_date' => $r->cal_date?->format('Y-m-d'),
                'created_by' => $r->created_by_emp_name,
                'date_created' => $r->date_created?->format('Y-m-d H:i'),
                'updated_by' => $r->updated_by_emp_name,
                'date_updated' => $r->date_updated?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Qdn/Maintenance/Calendar', [
            'rows' => $rows,
            'filters' => (object) array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'years' => TelfordCalendar::query()->whereNotNull('cal_year')
                ->distinct()->orderByDesc('cal_year')->pluck('cal_year'),
            'lists' => LookupMaintenanceController::tabs(),
        ]);
    }

    /**
     * Download every row matching the current filters (not just the page
     * on screen) as CSV or Excel. ?format=csv|xlsx plus year/month/workweek.
     */
    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'csv';

        $rows = $this->filteredQuery($filters)->lazy()->map(fn (TelfordCalendar $r) => [
            $r->id,
            $r->cal_year,
            $r->cal_quarter,
            $r->cal_month,
            $r->cal_workweek,
            $r->cal_date?->format('Y-m-d'),
            $r->created_by_emp_num,
            $r->created_by_emp_name,
            $r->date_created?->format('Y-m-d H:i:s'),
            $r->updated_by_emp_num,
            $r->updated_by_emp_name,
            $r->date_updated?->format('Y-m-d H:i:s'),
        ]);

        $name = 'telford-calendar' . (isset($filters['year']) ? "-{$filters['year']}" : '');

        return TableExport::download($format, $name, [
            'id', 'cal_year', 'cal_quarter', 'cal_month', 'cal_workweek', 'cal_date',
            'created_by_emp_num', 'created_by_emp_name', 'date_created',
            'updated_by_emp_num', 'updated_by_emp_name', 'date_updated',
        ], $rows);
    }

    /** Columns an upload may use; each also accepts a plain-English heading. */
    private const IMPORT_COLUMNS = [
        'cal_date' => ['cal_date', 'date'],
        'cal_year' => ['cal_year', 'year'],
        'cal_quarter' => ['cal_quarter', 'quarter'],
        'cal_month' => ['cal_month', 'month'],
        'cal_workweek' => ['cal_workweek', 'workweek', 'work week', 'ww'],
    ];

    /** Blank upload template (.xlsx, or ?format=csv) with the five headings and one example row. */
    public function template(Request $request)
    {
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';

        return TableExport::download($format, 'telford-calendar-template', array_keys(self::IMPORT_COLUMNS), [
            ['2026-11-01', '2027', 1, 11, 1],
        ]);
    }

    /**
     * Upload a .xlsx or .csv to add or update rows, matched by cal_date:
     * a date already in the table is updated (Updated by = you), a new
     * date is added (Created by = you). Every row is checked first; if any
     * row has a problem nothing is saved and the problems are listed.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', function ($attribute, $file, $fail) {
                if (!in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'csv', 'txt'], true)) {
                    $fail('Upload an Excel (.xlsx) or CSV (.csv) file. Old .xls files: open in Excel and Save As .xlsx.');
                }
            }],
        ], [], ['file' => 'file']);

        try {
            $sheet = TableImport::read($request->file('file'));
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        // heading in the file -> our column
        $map = [];
        foreach (self::IMPORT_COLUMNS as $column => $aliases) {
            foreach ($aliases as $alias) {
                if (in_array($alias, $sheet['headings'], true)) {
                    $map[$column] = $alias;
                    break;
                }
            }
        }
        $missing = array_diff(array_keys(self::IMPORT_COLUMNS), array_keys($map));
        if ($missing) {
            throw ValidationException::withMessages([
                'file' => 'Missing column(s): ' . implode(', ', $missing) . '. Row 1 must have the headings cal_date, cal_year, cal_quarter, cal_month, cal_workweek (download the template).',
            ]);
        }
        if (!$sheet['rows']) {
            throw ValidationException::withMessages(['file' => 'The file has headings but no rows.']);
        }

        $problems = [];
        $clean = [];
        $seen = [];
        foreach ($sheet['rows'] as $line => $row) {
            $get = fn ($c) => $row[$map[$c]] ?? null;
            $date = TableImport::excelDate($get('cal_date'));
            $values = [
                'cal_date' => $date,
                'cal_year' => $get('cal_year'),
                'cal_quarter' => $get('cal_quarter'),
                'cal_month' => $get('cal_month'),
                'cal_workweek' => static::normalizeWorkweek($get('cal_workweek')),
            ];
            $errors = [];
            if ($date === null) {
                $errors[] = 'cal_date "' . ($get('cal_date') ?? '') . '" is not a date (use 2026-11-01)';
            } elseif (isset($seen[$date])) {
                $errors[] = "cal_date {$date} is also on row {$seen[$date]}";
            }
            if ($values['cal_year'] === null || $values['cal_year'] === '' || mb_strlen($values['cal_year']) > 45) {
                $errors[] = 'cal_year is required (up to 45 characters)';
            }
            foreach (['cal_quarter' => 4, 'cal_month' => 12, 'cal_workweek' => self::WORKWEEK_MAX] as $col => $max) {
                $v = $values[$col];
                if ($v === null || !preg_match('/^\d+(\.0+)?$/', $v) || (int) $v < 1 || (int) $v > $max) {
                    $errors[] = "{$col} must be a whole number from 1 to {$max}" . ($v !== null && $v !== '' ? " (got \"{$v}\")" : '');
                }
            }
            if ($errors) {
                $problems[] = "Row {$line}: " . implode('; ', $errors) . '.';
                continue;
            }
            $seen[$date] = $line;
            $values['cal_year'] = preg_replace('/\.0+$/', '', $values['cal_year']);
            foreach (['cal_quarter', 'cal_month', 'cal_workweek'] as $col) {
                $values[$col] = (int) $values[$col];
            }
            $clean[] = $values;
        }

        if ($problems) {
            $shown = array_slice($problems, 0, 30);
            if (count($problems) > 30) {
                $shown[] = '... and ' . (count($problems) - 30) . ' more.';
            }
            throw ValidationException::withMessages([
                'file' => count($problems) . ' row(s) have problems, so nothing was saved. Fix them and upload again.',
                'import_rows' => implode("\n", $shown),
            ]);
        }

        $empNum = $this->limit(CurrentEmployee::employId());
        $empName = $this->limit(CurrentEmployee::name());
        $added = $updated = $unchanged = 0;

        DB::transaction(function () use ($clean, $empNum, $empName, &$added, &$updated, &$unchanged) {
            $existing = TelfordCalendar::whereIn('cal_date', array_column($clean, 'cal_date'))
                ->get()->keyBy(fn ($r) => $r->cal_date->format('Y-m-d'));

            foreach ($clean as $values) {
                $row = $existing->get($values['cal_date']);
                if (!$row) {
                    TelfordCalendar::create($values + [
                        'created_by_emp_num' => $empNum,
                        'created_by_emp_name' => $empName,
                        'date_created' => now(),
                    ]);
                    $added++;
                    continue;
                }
                $row->fill(array_diff_key($values, ['cal_date' => true]));
                if (!$row->isDirty()) {
                    $unchanged++;
                    continue;
                }
                $row->fill([
                    'updated_by_emp_num' => $empNum,
                    'updated_by_emp_name' => $empName,
                    'date_updated' => now(),
                ])->save();
                $updated++;
            }
        });

        return redirect()->route('qdn.maintenance.calendar.index')
            ->with('success', "Upload done: {$added} added, {$updated} updated, {$unchanged} unchanged.");
    }

    /**
     * Work week as stored (int). TELFORD work weeks are numbers like 644
     * (year digit + week), and may be typed or uploaded as "WW644" /
     * "ww 644" -- the WW prefix is dropped here. Up to 4 digits.
     */
    public const WORKWEEK_MAX = 9999;

    public static function normalizeWorkweek($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/^\s*ww\s*/i', '', trim((string) $value));
        $value = preg_replace('/\.0+$/', '', $value);

        return $value === '' ? null : $value;
    }

    private function filters(Request $request): array
    {
        $request->merge(['workweek' => static::normalizeWorkweek($request->query('workweek'))]);

        return $request->validate([
            'year' => ['nullable', 'string', 'max:45'],
            'workweek' => ['nullable', 'integer', 'min:1', 'max:' . self::WORKWEEK_MAX],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
    }

    private function filteredQuery(array $filters)
    {
        return TelfordCalendar::query()
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('cal_year', $v))
            ->when($filters['workweek'] ?? null, fn ($q, $v) => $q->where('cal_workweek', $v))
            ->when($filters['month'] ?? null, fn ($q, $v) => $q->where('cal_month', $v))
            ->orderBy('cal_date');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        TelfordCalendar::create($data + [
            'created_by_emp_num' => $this->limit(CurrentEmployee::employId()),
            'created_by_emp_name' => $this->limit(CurrentEmployee::name()),
            'date_created' => now(),
        ]);

        return back()->with('success', "TELFORD calendar date {$data['cal_date']} added.");
    }

    public function update(Request $request, int $id)
    {
        $row = TelfordCalendar::findOrFail($id);
        $data = $this->validated($request, $id);

        $row->update($data + [
            'updated_by_emp_num' => $this->limit(CurrentEmployee::employId()),
            'updated_by_emp_name' => $this->limit(CurrentEmployee::name()),
            'date_updated' => now(),
        ]);

        return back()->with('success', "TELFORD calendar date {$data['cal_date']} saved.");
    }

    public function destroy(int $id)
    {
        $row = TelfordCalendar::findOrFail($id);
        $date = $row->cal_date?->format('Y-m-d');
        $row->delete();

        return back()->with('success', "TELFORD calendar date {$date} deleted.");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['cal_workweek' => static::normalizeWorkweek($request->input('cal_workweek'))]);

        return $request->validate([
            'cal_date' => ['required', 'date_format:Y-m-d',
                Rule::unique('telford_calendar', 'cal_date')->ignore($ignoreId)],
            'cal_year' => ['required', 'string', 'max:45'],
            'cal_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'cal_month' => ['required', 'integer', 'min:1', 'max:12'],
            'cal_workweek' => ['required', 'integer', 'min:1', 'max:' . self::WORKWEEK_MAX],
        ], [
            'cal_date.unique' => 'This date is already in the TELFORD calendar.',
        ], [
            'cal_date' => 'Date', 'cal_year' => 'Year', 'cal_quarter' => 'Quarter',
            'cal_month' => 'Month', 'cal_workweek' => 'Work Week',
        ]);
    }

    /** Columns are varchar(45). */
    private function limit(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, 45);
    }
}
