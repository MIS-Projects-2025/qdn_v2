<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * qdn_new_db.defect_list -- the Defect List (defect_name + description),
 * maintained on Maintenance > Defect List. Not the same table as
 * defect_source (App\Models\Defect: Man / Machine / ..., the RCA form's
 * Source of Defect). Audit columns are filled in by the Maintenance page;
 * note the table's own spelling: update_by_emp_name and date_update.
 */
class DefectList extends Model
{
    protected $table = 'defect_list';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'date_created' => 'datetime',
        'date_update' => 'datetime',
    ];
}
