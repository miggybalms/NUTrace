<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The token behind a NUTAG sticker.
 *
 * A NUTAG carries a random 16-byte token and a short MAC over it - never the
 * asset code, a URL or a name. This table is what turns a token back into a
 * code; without a row here the symbol is only cells.
 *
 * The row is keyed by the asset *code*, not by an asset id, so a sticker
 * printed for a code that is registered later (the registry preview) keeps the
 * same token once the asset exists. One code, one token, whatever is reprinted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_tags', function (Blueprint $table) {
            $table->id();

            // 32 hex characters = the 16 random bytes the symbol carries.
            $table->char('token', 32)->unique();

            // The code the tag resolves to. Uppercase, like every code the
            // app stores; unique, so a reprint cannot mint a second identity.
            $table->string('asset_code', 30)->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tags');
    }
};
