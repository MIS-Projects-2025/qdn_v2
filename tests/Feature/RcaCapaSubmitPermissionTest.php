<?php

namespace Tests\Feature;

use App\Models\Qdn;
use App\Support\CurrentEmployee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Who can submit Dept RCA / Dept CAPA: anyone in the Issued To department
 * whose title is Supervisor, Section Head, Engineer or Manager (or position
 * code 2/3/4), plus approver1/2/3 of the Issued To employee.
 */
class RcaCapaSubmitPermissionTest extends TestCase
{
    private int $qdnId;

    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        Schema::table('qdns', function (Blueprint $t) {
            $t->string('rca_submitted_by')->nullable();
            $t->timestamp('rca_submitted_at')->nullable();
        });
        Schema::create('qdn_rca_causes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('qdn_id');
            $t->string('cause_type');
            $t->timestamps();
        });
        // 5001 (Production operator) is the Issued To; 7001 is their approver1.
        DashboardTest::makeApproverTable([
            ['employid' => '5001', 'approver1' => '7001', 'approver2' => null, 'approver3' => null],
        ]);
        // masterlist.employee_factory: everyone in factory 1 except 8005 (factory 2).
        Schema::connection('masterlist')->create('employee_factory', function (Blueprint $t) {
            $t->increments('id');
            $t->string('EMPLOYID', 50);
            $t->unsignedInteger('factory_id')->nullable();
        });
        foreach (['5001' => 1, '7001' => 1, '8001' => 1, '8002' => 1, '8003' => 1, '8004' => 1, '8005' => 2] as $emp => $factory) {
            DB::connection('masterlist')->table('employee_factory')->insert(['EMPLOYID' => $emp, 'factory_id' => $factory]);
        }

        $this->qdnId = DB::table('qdns')->insertGetId([
            'qdn_no' => '26-0000001', 'status' => 'for_dept_rca', 'issued_department' => 'Production',
            'classification' => 'Major', 'customer_name' => 'ACME', 'capa_round' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('qdn_validations')->insert([
            'qdn_id' => $this->qdnId, 'issued_to_employee_id' => '5001', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
    }

    private function as(string $empId, string $title, string $dept, string $position = '1'): static
    {
        return $this->withSession(['emp_data' => [
            'token' => 't', 'emp_id' => $empId, 'emp_name' => 'Test ' . $empId, 'emp_jobtitle' => $title,
            'emp_dept' => $dept, 'emp_position' => $position,
        ]]);
    }

    private function emptyRca(): array
    {
        return ['event' => [], 'escape' => [], 'system' => []];
    }

    public function test_issued_to_approver1_can_submit_rca_without_supervisor_title(): void
    {
        $this->as('7001', 'Line Leader', 'Production')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertRedirect();

        $this->assertSame(Qdn::STATUS_FOR_DEPT_CAPA, DB::table('qdns')->value('status'));
    }

    public function test_issued_to_approver1_from_another_department_sees_and_submits_rca(): void
    {
        $this->as('7001', 'Line Leader', 'Production Planning')
            ->get('/qdn/rca')
            ->assertInertia(fn (Assert $p) => $p->has('qdns', 1)->where('qdnCounts.rca', 1));

        $this->as('7001', 'Line Leader', 'Production Planning')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertRedirect();

        $this->assertSame(Qdn::STATUS_FOR_DEPT_CAPA, DB::table('qdns')->value('status'));
    }

    public function test_engineer_in_issued_to_department_can_submit_rca(): void
    {
        $this->as('8001', 'Process Engineer', 'Production')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertRedirect();

        $this->assertSame(Qdn::STATUS_FOR_DEPT_CAPA, DB::table('qdns')->value('status'));
    }

    public function test_operator_who_is_not_an_approver_cannot_submit_rca(): void
    {
        $this->as('8002', 'Production Operator', 'Production')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertForbidden();

        $this->as('8003', 'Engineering Technician', 'Production')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertForbidden();

        $this->assertSame('for_dept_rca', DB::table('qdns')->value('status'));
    }

    public function test_supervisor_in_another_factory_cannot_submit_rca(): void
    {
        $this->as('8005', 'Production Supervisor', 'Production')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertForbidden();

        $this->as('8006', 'Production Supervisor', 'Production') // no employee_factory row
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertForbidden();

        $this->assertSame('for_dept_rca', DB::table('qdns')->value('status'));
    }

    public function test_engineer_in_another_department_cannot_submit_rca(): void
    {
        $this->as('8004', 'Process Engineer', 'Process Engineering')
            ->post("/qdn/rca/{$this->qdnId}", $this->emptyRca())
            ->assertForbidden();
    }

    public function test_title_rule_matches_whole_words(): void
    {
        foreach (['Production Supervisor' => true, 'Section Head - Mold' => true, 'Senior Engineer' => true,
            'Process Engineer II' => true, 'Assistant Manager' => true, 'Engineering Technician' => false,
            'Production Operator' => false] as $title => $expected) {
            session(['emp_data' => ['emp_id' => '', 'emp_jobtitle' => $title, 'emp_position' => '1']]);
            $this->assertSame($expected, CurrentEmployee::hasRcaCapaTitle(), $title);
        }
    }
}
