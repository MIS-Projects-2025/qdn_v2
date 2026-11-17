<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LookupMaintenanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        if (!Schema::hasColumn('qdns', 'customer_id')) {
            Schema::table('qdns', fn (Blueprint $t) => $t->unsignedBigInteger('customer_id')->nullable());
        }
        $lookups = [
            'customer_list' => 'customer_name', 'location_list' => 'location_name',
            'spec_list' => 'spec_name', 'violation_list' => 'violation_name',
        ];
        foreach ($lookups as $table => $col) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) use ($col) { $t->id(); $t->string($col); });
            }
        }
        if (!Schema::hasTable('reason_root_cause')) {
            Schema::create('reason_root_cause', function (Blueprint $t) { $t->id(); $t->string('root_cause'); });
            (require base_path('database/migrations/2026_11_16_000001_add_ir_code_and_type_to_reason_root_cause.php'))->up();
        }
        (require base_path('database/migrations/2026_11_16_000002_create_defect_list_table.php'))->up();
        if (!Schema::hasTable('nonconformity_list')) {
            Schema::create('nonconformity_list', function (Blueprint $t) { $t->id(); $t->string('nonconformity_name'); $t->string('nonconformity_category')->nullable(); });
        }
        if (!Schema::hasTable('defect_source')) {
            Schema::create('defect_source', function (Blueprint $t) { $t->id(); $t->string('defect_source'); $t->timestamps(); });
        }
        if (!Schema::hasTable('qdn_rca_causes')) {
            Schema::create('qdn_rca_causes', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('qdn_id'); $t->unsignedBigInteger('reason_root_cause_id')->nullable(); $t->unsignedBigInteger('defect_source_id')->nullable(); });
        }
        if (!Schema::hasTable('admin')) {
            Schema::create('admin', function (Blueprint $t) { $t->id(); $t->string('emp_id'); });
        }
        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
        $this->withoutVite();
    }

    private function as(string $dept, string $id = '1705'): static
    {
        return $this->withSession(['emp_data' => ['token' => 't', 'emp_id' => $id, 'emp_name' => 'Ana', 'emp_jobtitle' => 'Engineer', 'emp_dept' => $dept]]);
    }

    public function test_only_qa_qms_or_admin_can_open(): void
    {
        $this->as('Production')->get('/qdn/maintenance/customers')->assertRedirect(route('qdn.dashboard'));
        $this->as('Quality Assurance')->get('/qdn/maintenance/customers')->assertOk()
            ->assertInertia(fn ($p) => $p->component('Qdn/Maintenance/Index')->where('current.key', 'customers'));
        $this->as('Quality Management System')->get('/qdn/maintenance')->assertOk()
            ->assertInertia(fn ($p) => $p->where('current.key', 'root-causes')->has('lists', 9));
        DB::table('admin')->insert(['emp_id' => '9']);
        $this->as('IT', '9')->get('/qdn/maintenance/specs')->assertOk();
        $this->as('Production')->post('/qdn/maintenance/specs', ['name' => 'X'])->assertRedirect(route('qdn.dashboard'));
        $this->assertSame(0, DB::table('spec_list')->count());
        $this->as('Quality Assurance')->get('/qdn/maintenance/nope')->assertNotFound();
    }

    public function test_crud_and_duplicates(): void
    {
        $this->as('Quality Assurance')->post('/qdn/maintenance/specs', ['name' => '  JEDEC J-STD-020 '])->assertRedirect('/qdn/maintenance/specs');
        $this->assertSame('JEDEC J-STD-020', DB::table('spec_list')->value('spec_name'));
        $this->as('Quality Assurance')->post('/qdn/maintenance/specs', ['name' => 'jedec j-std-020'])->assertSessionHasErrors('name');
        $id = DB::table('spec_list')->value('id');
        $this->as('Quality Assurance')->put("/qdn/maintenance/specs/{$id}", ['name' => 'IPC-A-610'])->assertSessionHasNoErrors();
        $this->assertSame('IPC-A-610', DB::table('spec_list')->value('spec_name'));
        $this->as('Quality Assurance')->delete("/qdn/maintenance/specs/{$id}")->assertSessionHas('success');
        $this->assertSame(0, DB::table('spec_list')->count());

        $this->as('Quality Assurance')->post('/qdn/maintenance/nonconformities', ['name' => 'Bent lead'])->assertSessionHasErrors('category');
        $this->as('Quality Assurance')->post('/qdn/maintenance/nonconformities', ['name' => 'Bent lead', 'category' => 2])->assertSessionHasNoErrors();
        $this->as('Quality Assurance')->get('/qdn/maintenance/nonconformities')
            ->assertInertia(fn ($p) => $p->where('rows.0.category_label', 'Major')->has('categories', 3));

        $this->as('Quality Assurance')->post('/qdn/maintenance/defect-sources', ['name' => 'Measurement'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('defect_source')->value('created_at'));
    }

    public function test_others_and_man_are_locked_and_used_rows_cant_be_deleted(): void
    {
        $others = DB::table('customer_list')->insertGetId(['customer_name' => 'Others']);
        $acme = DB::table('customer_list')->insertGetId(['customer_name' => 'Acme']);
        $man = DB::table('defect_source')->insertGetId(['defect_source' => 'Man']);

        $this->as('Quality Assurance')->put("/qdn/maintenance/customers/{$others}", ['name' => 'Other customers'])->assertSessionHas('error');
        $this->as('Quality Assurance')->delete("/qdn/maintenance/customers/{$others}")->assertSessionHas('error');
        $this->as('Quality Assurance')->delete("/qdn/maintenance/defect-sources/{$man}")->assertSessionHas('error');
        $this->assertSame('Others', DB::table('customer_list')->where('id', $others)->value('customer_name'));
        $this->assertSame(1, DB::table('defect_source')->count());

        $this->as('Quality Assurance')->get('/qdn/maintenance/customers')
            ->assertInertia(fn ($p) => $p->where('rows.1.name', 'Others')->where('rows.1.locked_reason', fn ($r) => str_contains($r, 'Others flow')));

        DB::table('qdns')->insert(['qdn_no' => 'Q-1', 'customer_id' => $acme, 'status' => 'pending_pe', 'created_at' => now(), 'updated_at' => now()]);
        $this->as('Quality Assurance')->delete("/qdn/maintenance/customers/{$acme}")->assertSessionHas('error');
        $this->assertSame(2, DB::table('customer_list')->count());
        // Renaming a used row is fine.
        $this->as('Quality Assurance')->put("/qdn/maintenance/customers/{$acme}", ['name' => 'Acme Corp'])->assertSessionHasNoErrors();
        $this->assertSame('Acme Corp', DB::table('customer_list')->where('id', $acme)->value('customer_name'));
    }

    public function test_root_cause_ir_code_and_type(): void
    {
        $this->as('Quality Assurance')->post('/qdn/maintenance/root-causes', [
            'name' => 'Wrong setup', 'extras' => ['ir_code' => ' IR-001 ', 'root_cause_type' => 'Escape'],
        ])->assertSessionHasNoErrors();
        $row = DB::table('reason_root_cause')->first();
        $this->assertSame('IR-001', $row->ir_code);
        $this->assertSame('Escape', $row->root_cause_type);

        $this->as('Quality Assurance')->post('/qdn/maintenance/root-causes', [
            'name' => 'Other', 'extras' => ['root_cause_type' => 'Occurrence'],
        ])->assertSessionHasErrors('extras.root_cause_type');

        $this->as('Quality Assurance')->put("/qdn/maintenance/root-causes/{$row->id}", [
            'name' => 'Wrong setup', 'extras' => ['ir_code' => '', 'root_cause_type' => 'System'],
        ])->assertSessionHasNoErrors();
        $row = DB::table('reason_root_cause')->first();
        $this->assertNull($row->ir_code);
        $this->assertSame('System', $row->root_cause_type);

        $this->as('Quality Assurance')->get('/qdn/maintenance/root-causes')
            ->assertInertia(fn ($p) => $p
                ->where('current.extras.0.key', 'ir_code')
                ->where('current.extras.1.options', ['Escape', 'Event', 'System'])
                ->where('rows.0.extras.root_cause_type', 'System'));

        // Lists without extras are unchanged.
        $this->as('Quality Assurance')->post('/qdn/maintenance/specs', ['name' => 'IPC'])->assertSessionHasNoErrors();
        $this->as('Quality Assurance')->get('/qdn/maintenance/specs')->assertInertia(fn ($p) => $p->where('current.extras', []));
    }

    public function test_root_cause_type_saved_as_code_when_column_is_integer(): void
    {
        // qdn_db's schema: root_cause_type is an INT column.
        Schema::drop('reason_root_cause');
        Schema::create('reason_root_cause', function (Blueprint $t) {
            $t->id(); $t->string('root_cause'); $t->string('ir_code', 50)->nullable(); $t->integer('root_cause_type')->nullable();
        });
        DB::table('reason_root_cause')->insert(['root_cause' => 'Legacy', 'root_cause_type' => 7]);

        $this->as('Quality Assurance')->post('/qdn/maintenance/root-causes', [
            'name' => 'Disciplinary Action', 'extras' => ['ir_code' => 'AQ-#1', 'root_cause_type' => 'Escape'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, (int) DB::table('reason_root_cause')->where('root_cause', 'Disciplinary Action')->value('root_cause_type'));

        $id = DB::table('reason_root_cause')->where('root_cause', 'Disciplinary Action')->value('id');
        $this->as('Quality Assurance')->put("/qdn/maintenance/root-causes/{$id}", [
            'name' => 'Disciplinary Action', 'extras' => ['ir_code' => 'AQ-#1', 'root_cause_type' => 'System'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, (int) DB::table('reason_root_cause')->where('id', $id)->value('root_cause_type'));

        $this->as('Quality Assurance')->get('/qdn/maintenance/root-causes')
            ->assertInertia(fn ($p) => $p
                ->where('rows.0.name', 'Disciplinary Action')->where('rows.0.extras.root_cause_type', 'System')
                ->where('rows.1.name', 'Legacy')->where('rows.1.extras.root_cause_type', '7')
                ->missing('current.extras.1.codes'));
    }

    public function test_defect_list_uses_defect_list_table_with_description_and_audit(): void
    {
        $this->as('Quality Assurance')->post('/qdn/maintenance/defects', [
            'name' => 'Bent lead', 'extras' => ['defect_description' => "Lead bent past spec.\nCheck trim/form."],
        ])->assertSessionHasNoErrors();
        $row = DB::table('defect_list')->first();
        $this->assertSame('Bent lead', $row->defect_name);
        $this->assertStringContainsString('Check trim/form.', $row->defect_description);
        $this->assertSame('1705', $row->created_by_emp_num);
        $this->assertSame('Ana', $row->created_by_emp_name);
        $this->assertNotNull($row->date_created);
        $this->assertNull($row->date_update);

        // defect_name is varchar(45).
        $this->as('Quality Assurance')->post('/qdn/maintenance/defects', ['name' => str_repeat('x', 46)])
            ->assertSessionHasErrors('name');

        // Saving without changes doesn't stamp Updated; a real change does.
        $this->as('Quality Assurance')->put("/qdn/maintenance/defects/{$row->id}", [
            'name' => 'Bent lead', 'extras' => ['defect_description' => $row->defect_description],
        ])->assertSessionHasNoErrors();
        $this->assertNull(DB::table('defect_list')->value('date_update'));
        $this->as('Quality Assurance')->put("/qdn/maintenance/defects/{$row->id}", [
            'name' => 'Bent leads', 'extras' => ['defect_description' => 'x'],
        ])->assertSessionHasNoErrors();
        $row = DB::table('defect_list')->first();
        $this->assertSame('Bent leads', $row->defect_name);
        $this->assertSame('Ana', $row->update_by_emp_name);
        $this->assertNotNull($row->date_update);

        $this->as('Quality Assurance')->get('/qdn/maintenance/defects')
            ->assertInertia(fn ($p) => $p
                ->where('current.has_audit', true)->where('current.max', 45)
                ->where('current.extras.0.type', 'textarea')
                ->where('rows.0.audit.updated', fn ($v) => str_starts_with($v, 'Ana ')));

        $this->as('Quality Assurance')->delete("/qdn/maintenance/defects/{$row->id}")->assertSessionHas('success');
        $this->assertSame(0, DB::table('defect_list')->count());
        $this->assertSame(0, DB::table('defect_source')->count());
    }
}
