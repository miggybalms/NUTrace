<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table) {
            // PK: Repair_id
            $table->id('Repair_id');

            // FK: Assets_id
            $table->foreignId('Assets_id')->constrained('assets');

            // FK: Request_id
            $table->foreignId('Request_id')->constrained('requests');

            // Repair_Description: Text
            $table->text('Repair_Description');

            // Repair_Date: DateTime
            $table->dateTime('Repair_Date');

            // Approve_by: Varchar(255)
            $table->string('Approve_by', 255);

            // Repair_Cost: Decimal(10,2)
            $table->decimal('Repair_Cost', 10, 2);

            // status: ENUM('Pending', 'In Progress', 'Completed', 'Cancelled')
            $table->enum('status', ['Pending', 'In Progress', 'Completed', 'Cancelled'])->default('Pending');

            // Repair_result: ENUM('Repairable', 'Beyond Repair', 'For Replacement')
            $table->enum('Repair_result', ['Repairable', 'Beyond Repair', 'For Replacement'])->nullable();

            // notes: text
            $table->text('notes')->nullable();

            // create_at & update_at (Timestamps)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
