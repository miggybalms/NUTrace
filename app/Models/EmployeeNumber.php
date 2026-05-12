<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeNumber extends Model
{
    protected $table = 'employee_numbers';
    protected $fillable = ['Employee_number', 'Full_Name', 'Department_id'];
    public $timestamps = true;

    // Relationship to users
    public function user()
    {
        return $this->hasOne(User::class, 'employee_numbers_id');
    }
}
