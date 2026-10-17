<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting documents for a disposal request.
 *
 * The official form and its scanned signed copy live on disposal_forms; this
 * table is for everything else the office needs to keep with a disposal —
 * inspection reports, appraisals, board approvals, photographs of the item.
 *
 * Purely additive: no existing table or row is changed. Files are stored on the
 * private local disk and only ever served through an authorized route, never
 * through the public media bucket.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('disposal_documents')) {
            return;
        }

        Schema::create('disposal_documents', function (Blueprint $table) {
            $table->id();

            // The request is the record the documents belong to. Deleting a
            // request is not part of normal disposal processing, but if one is
            // ever removed its documents go with it.
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();

            // The form that was generated from the request, when there is one.
            // Kept as a convenience link, not as the owner: documents must
            // survive a form being regenerated.
            $table->foreignId('form_id')->nullable()->constrained('disposal_forms')->nullOnDelete();

            // A document can be about one asset on a multi-asset request.
            // Null means it covers the request as a whole.
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();

            $table->string('title', 150)->nullable();
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime', 120)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposal_documents');
    }
};
