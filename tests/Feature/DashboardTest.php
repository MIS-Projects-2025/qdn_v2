<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public static function makeQdnTables(bool $withSystemStatus = true): void
    {
        if ($withSystemStatus) {
            Schema::create('system_status', function (Blueprint $t) {
                $t->id(); $t->string('status')->default('online'); $t->text('message')->nullable(); $t->timestamps();
            });
        }
        Schema::create('qdns', function (Blueprint $t) {
            $t->id();
            $t->string('qdn_no');
            $t->string('detection_area')->nullable();
            $t->string('nonconformity_name')->nullable();
            $t->string('classification')->nullable();
            $t->string('status');
            $t->string('issued_department')->nullable();
            foreach (['customer_name', 'lot_id', 'device_name', 'package_name', 'issued_by', 'capa_approval_status', 'capa_approved_by', 'capa_approval_remarks'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamp('capa_approved_at')->nullable();
            $t->unsignedInteger('capa_round')->default(1);
            $t->timestamps();
        });
        Schema::create('qdn_validations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('qdn_id');
            $t->string('type')->default('validation');
            $t->boolean('is_valid')->default(true);
            $t->string('issued_to_employee_id')->nullable();
            $t->timestamps();
        });
    }

    public static function seedQdns(): void
    {
        $rows = [
            // qdn_no, area, nc, class, status, dept, issued_to, months ago
            ['QDN-0001', 'Wire Bond', 'Lifted Ball', 'Major', 'for_dept_rca', 'Production', '1705', 0],
            ['QDN-0002', 'Die Attach', 'Epoxy Void', 'Minor', 'closed', 'Production', '1705', 1],
            ['QDN-0003', 'Mold', 'Lifted Ball', 'Critical', 'for_dept_capa', 'Production', '1705', 3],
            ['QDN-0004', 'FVI', 'Scratch', 'Minor', 'issued', 'Production', '2001', 0],
            ['QDN-0005', 'FVI', 'Scratch', 'Minor', 'issued', 'Engineering', '3001', 0],
            ['QDN-0006', 'FVI', 'Scratch', 'Minor', 'pending_pe', 'Production', null, 0],
        ];
        foreach ($rows as $i => [$no, $area, $nc, $cls, $status, $dept, $to, $ago]) {
            $at = now()->subMonths($ago);
            $id = DB::table('qdns')->insertGetId([
                'qdn_no' => $no, 'detection_area' => $area, 'nonconformity_name' => $nc,
                'classification' => $cls, 'status' => $status, 'issued_department' => $dept,
                'created_at' => $at, 'updated_at' => $at,
            ]);
            if ($to) {
                DB::table('qdn_validations')->insert(['qdn_id' => $id, 'issued_to_employee_id' => $to, 'created_at' => $at, 'updated_at' => $at]);
            }
        }
    }

    /** masterlist.employee_approver on in-memory SQLite. */
    public static function makeApproverTable(array $rows): void
    {
        config(['database.connections.masterlist' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('masterlist');
        Schema::connection('masterlist')->create('employee_approver', function (Blueprint $t) {
            $t->increments('id');
            $t->string('employid', 50);
            $t->string('approver1', 250)->nullable();
            $t->string('approver2', 250)->nullable();
            $t->string('approver3', 250)->nullable();
        });
        DB::connection('masterlist')->table('employee_approver')->insert($rows);
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::makeQdnTables();
        self::seedQdns();
        // 1705 approves 2001 (as approver2) and 3001 (as approver1); nobody approves 1705's own QDNs here.
        self::makeApproverTable([
            ['employid' => '2001', 'approver1' => '9999', 'approver2' => '1705', 'approver3' => null],
            ['employid' => '3001', 'approver1' => '1705', 'approver2' => null, 'approver3' => null],
            ['employid' => '1705', 'approver1' => '9999', 'approver2' => null, 'approver3' => null],
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
    }

    private function as(string $jobTitle, string $empId = '1705'): static
    {
        return $this->withSession(['emp_data' => [
            'token' => 't', 'emp_id' => $empId, 'emp_name' => 'Juan Dela Cruz', 'emp_firstname' => 'Juan',
            'emp_jobtitle' => $jobTitle, 'emp_dept' => 'Production', 'emp_position' => '1',
        ]]);
    }

    public function test_non_approver_sees_own_charts_but_no_approver_table(): void
    {
        $this->as('Production Supervisor', '2001')->get('/' . env('APP_NAME', ''))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Dashboard')
                ->where('isLeader', false)
                ->where('departmentQdns', null)
                ->where('personal.total', 1));
    }

    public function test_individual_charts(): void
    {
        $this->as('Operator')->get('/' . env('APP_NAME', ''))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Dashboard')
                ->where('personal.total', 3)
                ->where('personal.open', 2)
                ->where('personal.byClassification.Major', 1)
                ->where('personal.byClassification.Minor', 1)
                ->where('personal.byClassification.Critical', 1)
                ->where('personal.byNonconformity.0.label', 'Lifted Ball')
                ->where('personal.byNonconformity.0.total', 2)
                ->has('personal.byMonth', 12)
                ->where('personal.byMonth.11.total', 1)
                ->has('personal.recent', 3));
    }

    public function test_approver_sees_qdns_of_employees_they_approve(): void
    {
        // Job title no longer matters: 1705 is approver for 2001 and 3001.
        $this->as('Operator')->get('/' . env('APP_NAME', ''))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('isLeader', true)
                ->where('approveeCount', 2)
                ->where('departmentQdns.total', 2) // QDN-0004 (2001) + QDN-0005 (3001, other dept)
                ->where('departmentQdns.data.0.qdn_no', 'QDN-0005')
                ->has('departmentQdns.data.0.detection_area'));
    }

    public function test_approver_can_filter_table(): void
    {
        $this->as('Operator')->get('/' . env('APP_NAME', '') . '?search=QDN-0004')
            ->assertInertia(fn (Assert $p) => $p
                ->where('departmentQdns.total', 1)
                ->where('departmentQdns.data.0.qdn_no', 'QDN-0004'));
    }

    public function test_dept_approval_queue_and_decision_follow_employee_approver(): void
    {
        DB::table('qdns')->where('qdn_no', 'QDN-0004')->update(['status' => 'for_dept_approval']);
        DB::table('qdns')->where('qdn_no', 'QDN-0001')->update(['status' => 'for_dept_approval']);
        $mine = DB::table('qdns')->where('qdn_no', 'QDN-0004')->value('id');
        $notMine = DB::table('qdns')->where('qdn_no', 'QDN-0001')->value('id');

        // Queue: only QDN-0004 (issued to 2001, whom 1705 approves).
        $this->as('Operator')->get('/qdn/approval')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Qdn/Approval/Index')
                ->has('qdns', 1)
                ->where('qdns.0.qdn_no', 'QDN-0004'));

        // 1705 can't approve their own QDN (approver is 9999).
        $this->as('Operator')->post("/qdn/approval/{$notMine}", ['decision' => 'correct'])->assertForbidden();

        // Approver of 2001's QDN may decide; it moves on to PE / EE / QA / Production approval.
        $this->as('Operator')->post("/qdn/approval/{$mine}", ['decision' => 'correct'])->assertRedirect();
        $this->assertSame('for_cross_approval', DB::table('qdns')->where('id', $mine)->value('status'));
    }
}
