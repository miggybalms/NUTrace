<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns the signed QR codes need.
 *
 * qr_payload records the exact text the stored sticker image encodes (the
 * signed payload, or the plain code on a sticker printed before signing
 * existed). The image itself cannot be read back - it is a PNG in object
 * storage - so this is what lets `php artisan qr:resign` answer the office's
 * only real question about existing stickers: which ones still carry an
 * unsigned code and will have to be reprinted.
 *
 * qr_revoked_at marks a sticker the office no longer trusts (the tag was lost,
 * or the code is being retired). It is a revocation, not a delete: a scan of a
 * revoked sticker says so, instead of quietly finding nothing.
 *
 * Neither column is required for scanning: verification always reads the code
 * out of the QR itself and the asset's live row from this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->text('qr_payload')->nullable();
            $table->timestamp('qr_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['qr_payload', 'qr_revoked_at']);
        });
    }
};
