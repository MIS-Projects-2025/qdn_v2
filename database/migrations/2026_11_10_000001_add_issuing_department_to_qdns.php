<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The issuer's own department (and employee id), saved when the QDN is
 * created. Customer: Others QDNs use issuing_department as the fifth
 * "Issuing Dept" approver in PE / EE / QA / Production approval.
 * Existing QDNs stay NULL (no fifth approver) until filled in by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qdns', function (Blueprint $table) {
            if (!Schema::hasColumn('qdns', 'issued_by_employee_id')) {
                $table->string('issued_by_employee_id')->nullable()->after('issued_by');
            }
            if (!Schema::hasColumn('qdns', 'issuing_department')) {
                $table->string('issuing_department')->nullable()->after('issued_by_employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('qdns', function (Blueprint $table) {
            foreach (['issuing_department', 'issued_by_employee_id'] as $column) {
                if (Schema::hasColumn('qdns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
