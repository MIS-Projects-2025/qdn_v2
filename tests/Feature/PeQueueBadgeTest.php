<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Process Engineering > PE Queue badge: counts the same QDNs the PE Queue
 * page lists (pending_pe + for_pe_rca_validation), for PE only.
 */
class PeQueueBadgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        DashboardTest::makeApproverTable([]);

        foreach (['pending_pe', 'pending_pe', 'for_pe_rca_validation', 'for_dept_rca', 'closed'] as $i => $status) {
            DB::table('qdns')->insert([
                'qdn_no' => 'QDN-02' . $i, 'status' => $status, 'issued_department' => 'Production',
                'classification' => 'Major', 'capa_round' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
    }

    private function as(string $title, string $dept): static
    {
        return $this->withSession(['emp_data' => [
            'token' => 't', 'emp_id' => '3001', 'emp_name' => 'Test', 'emp_jobtitle' => $title, 'emp_dept' => $dept, 'emp_position' => '1',
        ]]);
    }

    public function test_pe_gets_badge_matching_pe_queue(): void
    {
        $this->as('PE Technician', 'Process Engineering')
            ->get('/pe/queue')
            ->assertInertia(fn (Assert $p) => $p->component('Qdn/PeQueue/Index')
                ->has('qdns', 3)
                ->where('qdnCounts.peQueue', 3));
    }

    public function test_non_pe_gets_zero(): void
    {
        $this->as('Production Supervisor', 'Production')
            ->get('/qdn/rca')
            ->assertInertia(fn (Assert $p) => $p->where('qdnCounts.peQueue', 0));
    }
}
