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
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->string('Approve_by')->nullable();
            $table->text('Repair_Description')->nullable();
            $table->decimal('Repair_Cost', 10, 2)->nullable();
            $table->enum('status', [
                'Pending', 'In Progress', 'Completed', 'Cancelled'
            ])->default('Pending');
            $table->text('notes')->nullable();
            $table->datetime('Repair_Date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
