<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
    {
        Schema::create('employee_numbers', function (Blueprint $table) {
            // PK: Employee_Number_ID
            $table->id(); 

            // FK: Department_id (Assuming it references a 'departments' table)
            $table->foreignId('Department_id')->constrained('departments');

            // Full_Name: VARCHAR(100)
            $table->string('Full_Name', 100);

            // Employee_number: VARCHAR(20) UNIQUE
            $table->string('Employee_number', 20)->unique();

            // status: ENUM('Active', 'Inactive')
            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            // Create_at & Update_at (Laravel's standard timestamps)
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_numbers');
    }
};
