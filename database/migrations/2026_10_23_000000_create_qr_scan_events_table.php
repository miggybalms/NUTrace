<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every verified sticker scan, reduced to a fingerprint.
 *
 * The payload itself is never stored: an HMAC of it is enough to recognise the
 * same sticker twice, and a database dump then holds no sticker text and no
 * asset code that could be turned back into a QR.
 *
 * `source_key` is who presented the sticker - the signed-in account, or a
 * hash of the network address when there is no session (the phone app). When
 * one fingerprint arrives from two different sources inside the detection
 * window, the scan is flagged as a possible copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qr_scan_events')) {
            return;
        }

        Schema::create('qr_scan_events', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64);
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('asset_code', 60)->nullable();
            $table->unsignedBigInteger('viewer_id')->nullable();
            $table->string('source_key', 80);
            $table->string('status', 32);
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->index(['fingerprint', 'seen_at']);
            $table->index(['seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scan_events');
    }
};
