<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
    {
        Schema::create('asset_files', function (Blueprint $table) {
            // PK: Asset_file_ID
            $table->id('Asset_file_ID');

            // FK: Asset_id (Assumes an 'assets' table exists)
            $table->foreignId('Asset_id')->constrained('assets')->onDelete('cascade');

            // file_name: VARCHAR(255)
            $table->string('file_name', 255);

            // file_path: VARCHAR(255)
            $table->string('file_path', 255);

            // file_size: INT
            $table->integer('file_size');

            // mime_type: VARCHAR(255)
            $table->string('mime_type', 255);

            // uploaded_at: VARCHAR(255) 
            // Note: Usually this is a timestamp, but I used string to match your ERD
            $table->string('uploaded_at', 255); 

            $table->text('url')->nullable();
            
            // Standard Laravel timestamps (optional, but recommended)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_files');
    }
};
