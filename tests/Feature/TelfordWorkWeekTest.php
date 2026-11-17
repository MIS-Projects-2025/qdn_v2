<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Records "Work Week" filter and its dropdown come from the TELFORD
 * calendar (telford_calendar), not analog_calendar.
 */
class TelfordWorkWeekTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        foreach (['customer_name', 'device_name', 'package_name', 'issued_by'] as $c) {
            if (!Schema::hasColumn('qdns', $c)) {
                Schema::table('qdns', fn (Blueprint $t) => $t->string($c)->nullable());
            }
        }
        Schema::create('qdn_rca_causes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('qdn_id'); $t->string('defect_source_name')->nullable(); $t->integer('sort_order')->default(0);
        });
        $nc = new \App\Models\Nonconformity; $cu = new \App\Models\Customer;
        Schema::connection($nc->getConnectionName())->create($nc->getTable(), fn (Blueprint $t) => [$t->id(), $t->string('nonconformity_name')->nullable()]);
        Schema::connection($cu->getConnectionName())->create($cu->getTable(), fn (Blueprint $t) => [$t->id(), $t->string('customer_name')->nullable()]);
        Schema::create('telford_calendar', function (Blueprint $t) {
            $t->increments('id'); $t->string('cal_year', 45)->nullable(); $t->smallInteger('cal_quarter')->nullable();
            $t->smallInteger('cal_month')->nullable(); $t->integer('cal_workweek')->nullable(); $t->date('cal_date')->nullable()->unique();
        });
        // TELFORD WW5 of 2031 = 2030-12-01 .. 2030-12-07
        foreach (range(1, 7) as $d) {
            DB::table('telford_calendar')->insert(['cal_year' => '2031', 'cal_workweek' => 5, 'cal_date' => sprintf('2030-12-%02d', $d)]);
        }
        DB::table('qdns')->insert([
            ['qdn_no' => '31-0000001', 'status' => 'issued', 'created_at' => '2030-12-03 10:00:00', 'updated_at' => '2030-12-03 10:00:00'],
            ['qdn_no' => '31-0000002', 'status' => 'issued', 'created_at' => '2030-12-09 10:00:00', 'updated_at' => '2030-12-09 10:00:00'],
        ]);
        $this->withoutVite();
        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
        $this->withSession(['emp_data' => [
            'token' => 't', 'emp_id' => '1705', 'emp_name' => 'Juan Dela Cruz', 'emp_firstname' => 'Juan',
            'emp_jobtitle' => 'QA Engineer', 'emp_dept' => 'Quality Assurance', 'emp_position' => '1',
        ]]);
    }

    public function test_records_work_week_uses_telford_calendar(): void
    {
        $this->get('/qdn/records?workweek=2031-5')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('qdns.total', 1)
                ->where('qdns.data.0.qdn_no', '31-0000001')
                ->where('filterOptions.workWeeks.0.value', '2031-5')
                ->where('filterOptions.workWeeks.0.date_start', '2030-12-01')
                ->where('filterOptions.workWeeks.0.date_end', '2030-12-07'));
    }
}
