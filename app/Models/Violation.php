<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lookup: violation_list. App-owned (not copied from qdn_db), created by
 * database/migrations/2026_11_09_000002_create_spec_and_violation_lists.php
 * and maintained on Maintenance > Violation List (QA / QMS).
 */
class Violation extends Model
{
    protected $table = 'violation_list';

    public $timestamps = false;

    protected $guarded = [];
}
