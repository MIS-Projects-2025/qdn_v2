<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the cross-department approval step after Dept Approval:
 *   for_dept_approval -> for_cross_approval -> for_qa_verification
 *
 * One Supervisor / Section Head / Manager from each of Process Engineering,
 * Equipment Engineering and QA must approve. The first approver in each
 * department wins (unique qdn_id + round + department_key).
 */
return new class extends Migration
{
    private const STATUSES = [
        'pending_pe', 'issued', 'invalid', 'for_dept_rca', 'for_pe_rca_validation',
        'for_dept_capa', 'for_dept_approval', 'for_cross_approval',
        'for_qa_verification', 'closed', 'invalid_disposition',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `qdns` MODIFY `status` ENUM('" . implode("','", self::STATUSES) . "') NOT NULL DEFAULT 'pending_pe'"
            );
        }

        Schema::create('qdn_cross_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qdn_id')->constrained('qdns')->cascadeOnDelete();
            // Matches qdns.capa_round, so a CAPA sent back and resubmitted
            // needs fresh approvals.
            $table->unsignedInteger('round')->default(1);
            $table->string('department_key', 10); // pe | ee | qa
            $table->string('department_name')->nullable();
            $table->string('approved_by_employee_id')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_title')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('approved_at');
            $table->timestamps();

            $table->unique(['qdn_id', 'round', 'department_key'], 'qdn_cross_approvals_first_wins');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qdn_cross_approvals');

        if (DB::getDriverName() === 'mysql') {
            DB::table('qdns')->where('status', 'for_cross_approval')->update(['status' => 'for_dept_approval']);
            $statuses = array_values(array_diff(self::STATUSES, ['for_cross_approval']));
            DB::statement(
                "ALTER TABLE `qdns` MODIFY `status` ENUM('" . implode("','", $statuses) . "') NOT NULL DEFAULT 'pending_pe'"
            );
        }
    }
};
