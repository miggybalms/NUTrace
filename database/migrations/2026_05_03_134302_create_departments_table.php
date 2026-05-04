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
        Schema::create('departments', function (Blueprint $table) {
            // PK: Department_ID (Auto-incrementing)
            $table->id(); 

            // Name: VARCHAR(100)
            $table->string('Name', 100);

            // status: ENUM('Active', 'Inactive')
            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            // Custom timestamps to match your ERD exactly
            $table->dateTime('Create_at')->nullable();
            $table->dateTime('Update_at')->nullable();

            // Tip: If you prefer standard Laravel behavior (created_at/updated_at), 
            // you could use $table->timestamps(); instead of the two lines above.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
