<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lookup: spec_list. App-owned (not copied from qdn_db), created by
 * database/migrations/2026_11_09_000002_create_spec_and_violation_lists.php
 * and maintained on Maintenance > Specs List (QA / QMS).
 */
class Spec extends Model
{
    protected $table = 'spec_list';

    public $timestamps = false;

    protected $guarded = [];
}
