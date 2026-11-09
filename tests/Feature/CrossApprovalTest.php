<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PE / EE / QA approval after Dept Approval: first Supervisor / Section
 * Head / Manager per department wins; all three -> QA Verification.
 */
class CrossApprovalTest extends TestCase
{
    private int $qdnId;

    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        DashboardTest::makeApproverTable([
            ['employid' => '2001', 'approver1' => '1705', 'approver2' => null, 'approver3' => null],
        ]);
        // Relations the Show page eager-loads (empty is fine).
        foreach (['qdn_rca_causes', 'qdn_capa_containment_lots', 'qdn_capa_corrections', 'qdn_capa_returns'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('qdn_id'); $t->unsignedInteger('round')->default(1);
                $t->unsignedInteger('sort_order')->default(0); $t->timestamps();
            });
        }
        Schema::create('qdn_cross_approvals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('qdn_id');
            $t->unsignedInteger('round')->default(1);
            $t->string('department_key', 10);
            $t->string('department_name')->nullable();
            $t->string('approved_by_employee_id')->nullable();
            $t->string('approved_by_name')->nullable();
            $t->string('approved_by_title')->nullable();
            $t->text('remarks')->nullable();
            $t->dateTime('approved_at');
            $t->timestamps();
            $t->unique(['qdn_id', 'round', 'department_key']);
        });

        $this->qdnId = DB::table('qdns')->insertGetId([
            'qdn_no' => 'QDN-0100', 'status' => 'for_dept_approval', 'issued_department' => 'Production',
            'classification' => 'Major', 'capa_round' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('qdn_validations')->insert(['qdn_id' => $this->qdnId, 'issued_to_employee_id' => '2001', 'created_at' => now(), 'updated_at' => now()]);

        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
    }

    private function as(string $id, string $name, string $title, string $dept): static
    {
        return $this->withSession(['emp_data' => [
            'token' => 't', 'emp_id' => $id, 'emp_name' => $name, 'emp_jobtitle' => $title, 'emp_dept' => $dept, 'emp_position' => '1',
        ]]);
    }

    private function qdnStatus(): string
    {
        return DB::table('qdns')->where('id', $this->qdnId)->value('status');
    }

    public function test_full_flow_first_approver_per_department_wins(): void
    {
        // Dept approver says Correct -> PE/EE/QA step (not QA Verification yet).
        $this->as('1705', 'Dept Mgr', 'Production Manager', 'Production')
            ->post("/qdn/approval/{$this->qdnId}", ['decision' => 'correct'])->assertRedirect();
        $this->assertSame('for_cross_approval', $this->qdnStatus());

        // Operator in PE can't approve.
        $this->as('3000', 'PE Op', 'Operator', 'Process Engineering')
            ->post("/qdn/cross-approval/{$this->qdnId}")->assertForbidden();

        // First PE supervisor approves.
        $this->as('3001', 'Ana PE', 'PE Supervisor', 'Process Engineering')
            ->post("/qdn/cross-approval/{$this->qdnId}", ['remarks' => 'ok'])->assertRedirect();

        // Second PE section head is told it's already approved; nothing new recorded.
        $this->as('3002', 'Ben PE', 'Section Head - PE', 'Process Engineering')
            ->post("/qdn/cross-approval/{$this->qdnId}")->assertSessionHas('error');
        $this->assertSame(1, DB::table('qdn_cross_approvals')->where('department_key', 'pe')->count());

        // Show page tells PE it's done and who did it; EE still can approve.
        $this->as('3002', 'Ben PE', 'Section Head - PE', 'Process Engineering')
            ->get("/qdn/cross-approval/{$this->qdnId}")
            ->assertInertia(fn (Assert $p) => $p->component('Qdn/CrossApproval/Show')
                ->where('canApprove', false)
                ->where('approvals.pe.approved', true)
                ->where('approvals.pe.approved_by', 'Ana PE')
                ->where('approvals.ee.approved', false));

        $this->as('4001', 'Carl EE', 'Equipment Engineering Manager', 'Equipment Engineering')
            ->post("/qdn/cross-approval/{$this->qdnId}")->assertRedirect();
        $this->assertSame('for_cross_approval', $this->qdnStatus());

        // QA (QMS counts as QA)
        $this->as('5001', 'Dina QA', 'QA Supervisor', 'Quality Management System')
            ->post("/qdn/cross-approval/{$this->qdnId}")->assertRedirect();
        $this->assertSame('for_cross_approval', $this->qdnStatus());

        // Production is the last one -> QA Verification.
        $this->as('6001', 'Eli Prod', 'Production Section Head', 'Production')
            ->post("/qdn/cross-approval/{$this->qdnId}")->assertRedirect();
        $this->assertSame('for_qa_verification', $this->qdnStatus());
        $this->assertSame(4, DB::table('qdn_cross_approvals')->where('qdn_id', $this->qdnId)->count());
    }

    public function test_queue_and_badge_for_department_still_pending(): void
    {
        DB::table('qdns')->where('id', $this->qdnId)->update(['status' => 'for_cross_approval']);

        $this->as('4001', 'Carl EE', 'Equipment Engineering Manager', 'Equipment Engineering')
            ->get('/qdn/cross-approval')
            ->assertInertia(fn (Assert $p) => $p->component('Qdn/CrossApproval/Index')
                ->where('myDepartmentKey', 'ee')
                ->has('qdns', 1)
                ->where('qdns.0.approvals.ee.approved', false)
                ->where('qdnCounts.crossApproval', 1)
                ->where('qdnRoles.crossApprovalDepartment', 'ee'));

        $this->as('4001', 'Carl EE', 'Equipment Engineering Manager', 'Equipment Engineering')
            ->post("/qdn/cross-approval/{$this->qdnId}");

        $this->as('4002', 'Dan EE', 'EE Supervisor', 'Equipment Engineering')
            ->get('/qdn/cross-approval')
            ->assertInertia(fn (Assert $p) => $p
                ->where('qdns.0.approvals.ee.approved', true)
                ->where('qdns.0.approvals.ee.approved_by', 'Carl EE')
                ->where('qdnCounts.crossApproval', 0));
    }

    public function test_back_to_a_stage_page_after_it_moved_on_redirects_to_record(): void
    {
        // QDN already left Dept Approval (e.g. Back pressed after approving).
        DB::table('qdns')->where('id', $this->qdnId)->update(['status' => 'for_cross_approval']);

        $this->as('1705', 'Dept Mgr', 'Production Manager', 'Production')
            ->get("/qdn/approval/{$this->qdnId}")
            ->assertRedirect("/qdn/records/{$this->qdnId}")
            ->assertSessionHas('success');
    }
}
