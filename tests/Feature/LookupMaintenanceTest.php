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
            'reason_root_cause' => 'root_cause', 'spec_list' => 'spec_name', 'violation_list' => 'violation_name',
        ];
        foreach ($lookups as $table => $col) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) use ($col) { $t->id(); $t->string($col); });
            }
        }
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
            ->assertInertia(fn ($p) => $p->where('current.key', 'root-causes')->has('lists', 7));
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

        $this->as('Quality Assurance')->post('/qdn/maintenance/defects', ['name' => 'Measurement'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('defect_source')->value('created_at'));
    }

    public function test_others_and_man_are_locked_and_used_rows_cant_be_deleted(): void
    {
        $others = DB::table('customer_list')->insertGetId(['customer_name' => 'Others']);
        $acme = DB::table('customer_list')->insertGetId(['customer_name' => 'Acme']);
        $man = DB::table('defect_source')->insertGetId(['defect_source' => 'Man']);

        $this->as('Quality Assurance')->put("/qdn/maintenance/customers/{$others}", ['name' => 'Other customers'])->assertSessionHas('error');
        $this->as('Quality Assurance')->delete("/qdn/maintenance/customers/{$others}")->assertSessionHas('error');
        $this->as('Quality Assurance')->delete("/qdn/maintenance/defects/{$man}")->assertSessionHas('error');
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
}
