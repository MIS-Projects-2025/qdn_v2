<?php

namespace Tests\Feature;

use App\Support\CurrentEmployee;
use Tests\TestCase;

class CrossApprovalTitleTest extends TestCase
{
    public function test_manager_by_position_level_can_approve(): void
    {
        session(['emp_data' => ['emp_id' => '27-0000001', 'emp_dept' => 'Process Engineering', 'emp_jobtitle' => 'Process Engineering Head', 'emp_position_level' => 'Manager']]);
        $this->assertSame('pe', CurrentEmployee::crossApprovalDepartmentKey());
    }

    public function test_title_still_required(): void
    {
        session(['emp_data' => ['emp_id' => '27-0000002', 'emp_dept' => 'Process Engineering', 'emp_jobtitle' => 'Process Engineer', 'emp_position_level' => 'Rank and File']]);
        $this->assertNull(CurrentEmployee::crossApprovalDepartmentKey());
    }

    public function test_job_title_manager_still_works(): void
    {
        session(['emp_data' => ['emp_id' => '1', 'emp_dept' => 'Production', 'emp_jobtitle' => 'Production Manager']]);
        $this->assertSame('prod', CurrentEmployee::crossApprovalDepartmentKey());
    }
}
