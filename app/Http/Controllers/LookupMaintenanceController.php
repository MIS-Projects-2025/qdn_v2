<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Defect;
use App\Models\DefectList;
use App\Support\CurrentEmployee;
use App\Models\Location;
use App\Models\Nonconformity;
use App\Models\Qdn;
use App\Models\ReasonRootCause;
use App\Models\Spec;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Maintenance (QA / QMS only, see QdnMaintenanceMiddleware): list, add,
 * edit and delete for the QDN lookup lists. One controller and one page
 * for all seven lists; each list is described in LISTS below.
 *
 * - protected: names that drive workflow logic and can't be renamed or
 *   deleted (Customer "Others" = Others flow; Source of Defect "Man" =
 *   Issued To picker on RCA). Matched trimmed, case-insensitive, the same
 *   way the workflow code matches them.
 * - references: table => column pairs that point at a row's id. A row
 *   still used by a QDN can't be deleted (QDNs keep a copy of the name,
 *   so renaming is safe and only affects new QDNs).
 */
class LookupMaintenanceController extends Controller
{
    private const LISTS = [
        'root-causes' => [
            'label' => 'Root Cause List',
            'model' => ReasonRootCause::class,
            'column' => ReasonRootCause::LABEL_COLUMN,
            'field' => 'Root Cause',
            'used_in' => 'RCA > Select Cause',
            'references' => ['qdn_rca_causes' => 'reason_root_cause_id'],
            // Extra columns shown and edited next to the name. key = column.
            'extras' => [
                ['key' => 'ir_code', 'label' => 'IR Code', 'type' => 'text', 'max' => 50],
                // 'codes': used when the DB column is numeric (qdn_db's schema).
                ['key' => 'root_cause_type', 'label' => 'Root Cause Type', 'type' => 'select', 'options' => ReasonRootCause::TYPES, 'codes' => ReasonRootCause::TYPE_CODES],
            ],
        ],
        'specs' => [
            'label' => 'Specs List',
            'model' => Spec::class,
            'column' => 'spec_name',
            'field' => 'Spec',
            'used_in' => null,
        ],
        'violations' => [
            'label' => 'Violation List',
            'model' => Violation::class,
            'column' => 'violation_name',
            'field' => 'Violation',
            'used_in' => null,
        ],
        'defects' => [
            'label' => 'Defect List',
            'model' => DefectList::class,
            'column' => 'defect_name',
            'max' => 45,
            'field' => 'Defect',
            'used_in' => null,
            'extras' => [
                ['key' => 'defect_description', 'label' => 'Description', 'type' => 'textarea'],
            ],
            // Who added / last changed a row, and when (the table's own column names).
            'audit' => [
                'created_num' => 'created_by_emp_num', 'created_name' => 'created_by_emp_name', 'created_at' => 'date_created',
                'updated_num' => 'updated_by_emp_num', 'updated_name' => 'update_by_emp_name', 'updated_at' => 'date_update',
            ],
        ],
        // The RCA form's Source of Defect (Man / Machine / ...), separate from defect_list.
        'defect-sources' => [
            'label' => 'Source of Defect',
            'model' => Defect::class,
            'column' => Defect::LABEL_COLUMN,
            'field' => 'Source of Defect',
            'used_in' => 'RCA > Source of Defect',
            'protected' => ['man' => '"Man" opens the Issued To picker on the RCA form.'],
            'references' => ['qdn_rca_causes' => 'defect_source_id'],
        ],
        'locations' => [
            'label' => 'Location List',
            'model' => Location::class,
            'column' => 'location_name',
            'field' => 'Location',
            'used_in' => 'Create QDN > Detection Area',
            'references' => ['qdns' => 'location_id'],
        ],
        'customers' => [
            'label' => 'Customer List',
            'model' => Customer::class,
            'column' => 'customer_name',
            'field' => 'Customer',
            'used_in' => 'Create QDN > Customer',
            'protected' => ['others' => '"Others" turns on the Others flow on Create QDN.'],
            'references' => ['qdns' => 'customer_id'],
        ],
        'nonconformities' => [
            'label' => 'Non-conformity List',
            'model' => Nonconformity::class,
            'column' => 'nonconformity_name',
            'field' => 'Non-conformity',
            'used_in' => 'Create QDN > Non-conformity',
            'category' => true,
            'references' => ['qdns' => 'nonconformity_id'],
        ],
    ];

    public function index(Request $request, string $list = 'root-causes')
    {
        $config = $this->config($list);
        $column = $config['column'];
        $hasCategory = !empty($config['category']);

        $rows = $config['model']::query()
            ->orderBy($column)
            ->get()
            ->map(function (Model $row) use ($config, $column, $hasCategory, $list) {
                $name = (string) $row->{$column};

                return [
                    'id' => $row->getKey(),
                    'name' => $name,
                    'category' => $hasCategory ? $row->categoryNumber() : null,
                    'category_label' => $hasCategory ? $row->classificationLabel() : null,
                    'locked_reason' => $this->protectedReason($config, $name),
                    'photo_area' => $list === 'locations' && Qdn::requiresPhotos($name),
                    'audit' => $this->auditText($config, $row),
                    'extras' => (object) collect($config['extras'] ?? [])
                        ->mapWithKeys(fn($e) => [$e['key'] => $this->extraForDisplay($e, $row->{$e['key']} ?? null)])
                        ->all(),
                ];
            })
            ->values();

        return Inertia::render('Qdn/Maintenance/Index', [
            'lists' => self::tabs(),
            'current' => [
                'key' => $list,
                'label' => $config['label'],
                'field' => $config['field'],
                'used_in' => $config['used_in'],
                'has_category' => $hasCategory,
                'has_audit' => isset($config['audit']),
                'max' => $config['max'] ?? 255,
                'extras' => collect($config['extras'] ?? [])->map(fn($e) => \Illuminate\Support\Arr::except($e, ['codes']))->values()->all(),
            ],
            'categories' => $hasCategory
                ? collect(Nonconformity::CATEGORY_LABELS)->map(fn($label, $value) => compact('value', 'label'))->values()
                : [],
            'rows' => $rows,
        ]);
    }

    public function store(Request $request, string $list)
    {
        $config = $this->config($list);
        $data = $this->validated($request, $config);

        $config['model']::create($this->attributes($config, $data) + $this->auditValues($config, 'created'));

        return redirect()->route('qdn.maintenance.index', $list)
            ->with('success', "{$config['field']} \"{$data['name']}\" added.");
    }

    public function update(Request $request, string $list, int $id)
    {
        $config = $this->config($list);
        $row = $config['model']::findOrFail($id);
        $current = (string) $row->{$config['column']};
        $data = $this->validated($request, $config, $id);

        if (($reason = $this->protectedReason($config, $current))
            && strcasecmp(trim($current), trim($data['name'])) !== 0) {
            return back()->with('error', "\"{$current}\" can't be renamed. {$reason}");
        }

        $row->fill($this->attributes($config, $data));
        if ($row->isDirty()) {
            $row->fill($this->auditValues($config, 'updated'))->save();
        }

        return redirect()->route('qdn.maintenance.index', $list)
            ->with('success', "{$config['field']} \"{$data['name']}\" saved.");
    }

    public function destroy(string $list, int $id)
    {
        $config = $this->config($list);
        $row = $config['model']::findOrFail($id);
        $name = (string) $row->{$config['column']};

        if ($reason = $this->protectedReason($config, $name)) {
            return back()->with('error', "\"{$name}\" can't be deleted. {$reason}");
        }

        $uses = $this->usageCount($config, $id);
        if ($uses > 0) {
            return back()->with('error', "\"{$name}\" is used by {$uses} QDN record(s) and can't be deleted. Rename it instead.");
        }

        $row->delete();

        return redirect()->route('qdn.maintenance.index', $list)
            ->with('success', "{$config['field']} \"{$name}\" deleted.");
    }

    /** Tabs on every Maintenance page: the lookup lists, then TELFORD Calendar. */
    public static function tabs(): array
    {
        return collect(self::LISTS)
            ->map(fn($c, $key) => [
                'key' => $key,
                'label' => $c['label'],
                'href' => route('qdn.maintenance.index', $key),
            ])
            ->values()
            ->push([
                'key' => 'calendar',
                'label' => 'TELFORD Calendar',
                'href' => route('qdn.maintenance.calendar.index'),
            ])
            ->all();
    }

    private function config(string $list): array
    {
        abort_unless(isset(self::LISTS[$list]), 404);

        return self::LISTS[$list];
    }

    private function validated(Request $request, array $config, ?int $ignoreId = null): array
    {
        $model = new $config['model'];
        $table = $model->getTable();
        $column = $config['column'];

        $rules = [
            'name' => [
                'required', 'string', 'max:' . ($config['max'] ?? 255),
                // Case-insensitive duplicate check (most MySQL collations
                // already compare this way, LOWER() makes it explicit).
                function ($attribute, $value, $fail) use ($table, $column, $ignoreId, $model, $config) {
                    $exists = DB::table($table)
                        ->whereRaw("LOWER(TRIM(`{$column}`)) = ?", [mb_strtolower(trim($value))])
                        ->when($ignoreId, fn($q) => $q->where($model->getKeyName(), '!=', $ignoreId))
                        ->exists();
                    if ($exists) {
                        $fail("{$config['field']} \"" . trim($value) . "\" already exists.");
                    }
                },
            ],
        ];

        if (!empty($config['category'])) {
            $rules['category'] = ['required', Rule::in(array_keys(Nonconformity::CATEGORY_LABELS))];
        }

        $names = ['name' => $config['field'], 'category' => 'Category'];
        foreach ($config['extras'] ?? [] as $extra) {
            $rules["extras.{$extra['key']}"] = $extra['type'] === 'select'
                ? ['nullable', Rule::in($extra['options'])]
                : ['nullable', 'string', 'max:' . ($extra['max'] ?? ($extra['type'] === 'textarea' ? 65535 : 255))];
            $names["extras.{$extra['key']}"] = $extra['label'];
        }

        $data = $request->validate($rules, [], $names);
        $data['name'] = trim($data['name']);

        return $data;
    }

    private function attributes(array $config, array $data): array
    {
        $attributes = [$config['column'] => $data['name']];

        if (!empty($config['category'])) {
            $attributes['nonconformity_category'] = (int) $data['category'];
        }

        $table = (new $config['model'])->getTable();
        foreach ($config['extras'] ?? [] as $extra) {
            $value = trim((string) ($data['extras'][$extra['key']] ?? ''));
            $value = $value === '' ? null : $value;
            // Numeric column (e.g. qdn_db's root_cause_type INT): save the code.
            if ($value !== null && isset($extra['codes']) && $this->isNumericColumn($table, $extra['key'])) {
                $value = array_search($value, $extra['codes'], true) ?: null;
            }
            $attributes[$extra['key']] = $value;
        }

        return $attributes;
    }

    /** created_* / updated_* audit columns for this list ($which = created|updated), or [] if it has none. */
    private function auditValues(array $config, string $which): array
    {
        if (!isset($config['audit'])) {
            return [];
        }
        $a = $config['audit'];
        $limit = fn($v) => $v === null ? null : mb_substr((string) $v, 0, 45);

        return [
            $a["{$which}_num"] => $limit(CurrentEmployee::employId()),
            $a["{$which}_name"] => $limit(CurrentEmployee::name()),
            $a["{$which}_at"] => now(),
        ];
    }

    private function auditText(array $config, Model $row): ?array
    {
        if (!isset($config['audit'])) {
            return null;
        }
        $a = $config['audit'];
        $fmt = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d H:i') : null;

        return [
            'created' => trim(($row->{$a['created_name']} ?? '') . ' ' . ($fmt($row->{$a['created_at']}) ?? '')) ?: null,
            'updated' => trim(($row->{$a['updated_name']} ?? '') . ' ' . ($fmt($row->{$a['updated_at']}) ?? '')) ?: null,
        ];
    }

    /** Show a stored code (1) as its label (Escape); unknown codes stay as they are. */
    private function extraForDisplay(array $extra, $value)
    {
        if ($value !== null && isset($extra['codes']) && is_numeric($value)) {
            return $extra['codes'][(int) $value] ?? (string) $value;
        }

        return $value;
    }

    private array $numericColumns = [];

    private function isNumericColumn(string $table, string $column): bool
    {
        return $this->numericColumns["{$table}.{$column}"] ??= in_array(
            strtolower((string) rescue(fn() => Schema::getColumnType($table, $column), '', false)),
            ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'],
            true,
        );
    }

    private function protectedReason(array $config, string $name): ?string
    {
        return ($config['protected'] ?? [])[mb_strtolower(trim($name))] ?? null;
    }

    private function usageCount(array $config, int $id): int
    {
        $total = 0;
        foreach ($config['references'] ?? [] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                $total += DB::table($table)->where($column, $id)->count();
            }
        }

        return $total;
    }
}
