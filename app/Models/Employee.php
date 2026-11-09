<?php

// Masterlist `login` account (password / profile). Not used for QDN
// employee lookups -- see App\Models\Masterlist\Employee.

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $connection = 'masterlist';
    protected $table = 'login';
    protected $primaryKey = 'EMPLOYID';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'EMPLOYID',
        'EMPNAME',
        'USERNAME',
        'PASSWRD',
        'ACCSTATUS',
        'ROLE',
    ];

    protected $hidden = [
        'PASSWRD',
    ];

    // Tell Laravel which column holds the password
    public function getAuthPassword()
    {
        return $this->PASSWRD;
    }

    public function getAuthPasswordName()
    {
        return 'PASSWRD';
    }

    // Table has no remember_token column — disable that feature safely
    public function getRememberTokenName()
    {
        return '';
    }

    /**
     * Departments via masterlist.employee_department (used by ProfileController).
     */
    public function departments()
    {
        return $this->belongsToMany(
            \App\Models\Lookups\Department::class,
            'employee_department',
            'employid',
            'department_id',
            'EMPLOYID',
            'id'
        );
    }

    public function details()
    {
        return $this->hasOne(\App\Models\Masterlist\Employee::class, 'employid', 'EMPLOYID');
    }
}