<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store multiple assets belonging to one request
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('asset_id');

            $table->timestamps();

            // Link request_items to requests
            $table->foreign('request_id')
                ->references('id')
                ->on('requests')
                ->onDelete('cascade');

            // Link request_items to assets
            $table->foreign('asset_id')
                ->references('id')
                ->on('assets')
                ->onDelete('restrict');

            // Prevent the same asset from being added
            // twice to the same request
            $table->unique(['request_id', 'asset_id']);

            // Faster searching by asset
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};