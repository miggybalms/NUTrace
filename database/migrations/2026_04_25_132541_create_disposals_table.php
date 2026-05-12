<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
    {
        Schema::create('disposals', function (Blueprint $table) {
            // PK: Disposal_ID
            $table->id('Disposal_ID');

            // FK: Asset_id
            $table->foreignId('Asset_id')->constrained('assets');

            // FK: Request_id
            $table->foreignId('Request_id')->constrained('requests');

            // notes: text
            $table->text('notes')->nullable();

            // Approve_by: Varchar(255)
            $table->string('Approve_by', 255);

            // Description: VARCHAR(255)
            $table->string('Description', 255);

            // disposal_date: DATE
            $table->date('disposal_date');

            // disposal_reason: ENUM('Beyond Repair', 'Replace', 'Obsolete', 'Lost', 'Damage')
            $table->enum('disposal_reason', ['Beyond Repair', 'Replace', 'Obsolete', 'Lost', 'Damage']);

            // Create_at & Updated_at (Timestamps)
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposals');
    }
};
