<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer: Others can leave Machine No blank on Create QDN (see
     * QdnController@store) -- machine_id is `bigint unsigned NOT NULL`
     * with no actual FK constraint (confirmed via SHOW CREATE TABLE
     * qdns), so this only drops the NOT NULL; there's no constraint to
     * drop/re-add.
     *
     * lot_id / lot_qty / device_name are NOT touched here -- they stay
     * NOT NULL. Leaving those blank gets "N/A" (or 0 for the numeric
     * lot_qty) substituted in QdnController@store instead of a real
     * NULL, so they don't need a schema change. package_name and
     * machine_num are already `DEFAULT NULL` in your qdns table, so
     * those didn't need one either.
     *
     * Requires doctrine/dbal for ->change() -- if this migration fails
     * with an error mentioning doctrine/dbal, run
     * `composer require doctrine/dbal` first, then re-run
     * `php artisan migrate`.
     */
    public function up(): void
    {
        Schema::table('qdns', function (Blueprint $table) {
            $table->unsignedBigInteger('machine_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Will fail if any row already has machine_id = NULL -- clean
        // those up (or just don't roll this back) before downgrading.
        Schema::table('qdns', function (Blueprint $table) {
            $table->unsignedBigInteger('machine_id')->nullable(false)->change();
        });
    }
};
