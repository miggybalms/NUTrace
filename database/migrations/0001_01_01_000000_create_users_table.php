<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreignId('employee_numbers_id')->constrained('employee_numbers')->onDelete('cascade');
            $table->string('email', 100)->unique();
            $table->string('password');
            $table->enum('role', [
                'Admin', 'Employee', 'Department Head', 'Facilities'
            ])->default('Employee');
            $table->enum('status', [
                'Active', 'Inactive'
            ])->default('Active');
            $table->string('profile_photo', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
