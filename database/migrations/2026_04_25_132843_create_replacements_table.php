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
        Schema::create('replacements', function (Blueprint $table) {
            // PK: Replacement_id
            $table->id('Replacement_id');

            // FK: Request_id
            $table->foreignId('Request_id')->constrained('requests');

            // FK: old_assets_id
            $table->foreignId('old_assets_id')->constrained('assets');

            // FK: new_assets_id
            $table->foreignId('new_assets_id')->constrained('assets');

            // reason: Varchar(255)
            $table->string('reason', 255);

            // notes: text
            $table->text('notes')->nullable();

            // Replacement_Date: DateTime
            $table->dateTime('Replacement_Date');

            // Approve_by: Varchar(255)
            $table->string('Approve_by', 255);

            // status: ENUM('Pending', 'Approved', 'Ordered', 'Received', 'Complete', 'Cancelled')
            $table->enum('status', ['Pending', 'Approved', 'Ordered', 'Received', 'Complete', 'Cancelled'])->default('Pending');

            // replacement_reason: ENUM('Beyond Repair', 'Obsolete', 'End of Lifespan', 'Lost', 'Damage')
            $table->enum('replacement_reason', ['Beyond Repair', 'Obsolete', 'End of Lifespan', 'Lost', 'Damage']);

            // create_at & update_at (Laravel standard timestamps)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacements');
    }
};
