<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes the original PE "validate + issue" decision
     * (QdnValidation::TYPE_VALIDATION, the default -- backfills every
     * existing row) from a later PE "reassign the issuance" correction
     * (QdnValidation::TYPE_REASSIGNMENT, see QdnReassignController).
     * Reassignment keeps full history instead of overwriting the original
     * row -- Qdn::validation() now resolves to the LATEST row of either
     * type via latestOfMany(), while Qdn::validations() (plural) still
     * exposes every row, oldest first, for a full audit trail.
     */
    public function up(): void
    {
        Schema::table('qdn_validations', function (Blueprint $table) {
            $table->string('type')->default('validation')->after('qdn_id');
        });
    }

    public function down(): void
    {
        Schema::table('qdn_validations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
