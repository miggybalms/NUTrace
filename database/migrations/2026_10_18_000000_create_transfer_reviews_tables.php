<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Executive Director's transfer reviews.
 *
 * Two tables of invitations and per-asset decisions, both purely additive: no
 * existing table, column or status is touched. A transfer request keeps living
 * in `requests` with the status it already had, and `request_items` still lists
 * the assets it covers. This migration only adds the paper trail of the review
 * the request is waiting on.
 *
 * Column names are deliberately all lowercase. The rest of this schema spells
 * several columns with a capital letter (requests.Note, assets.Asset_code), and
 * an unquoted reference to one of those is folded to lowercase by PostgreSQL
 * and refused — a whole afternoon's lesson. Nothing added here can be caught by
 * that.
 *
 * No `enum` columns either: the review stages are a single short string with an
 * index, so adding a stage later is a code change rather than a schema change,
 * and no driver has to agree on how an enum is spelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transfer_reviews')) {
            Schema::create('transfer_reviews', function (Blueprint $table) {
                $table->id();

                // The transfer request being reviewed. NOT unique: a resend
                // issues a replacement invitation, and the previous one is kept
                // as the record of what was sent and revoked. "One outstanding
                // invitation" is enforced in code, inside a transaction that
                // locks the request row.
                $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();

                // Only the hash of the link token is stored. The token itself
                // exists in the email that was sent and nowhere else — a leaked
                // database cannot be turned into a working link.
                $table->string('token_hash', 64)->nullable()->unique();

                // The mailbox the invitation was sent to — the configured one,
                // copied onto the row so the record shows where it went.
                $table->string('recipient_email', 150)->nullable();

                // awaiting_verification -> verified -> decided, or revoked.
                $table->string('status', 32)->default('awaiting_verification');

                $table->timestamp('expires_at')->nullable();

                // ── Verification of the recipient ───────────────────────────
                $table->string('code_hash', 255)->nullable();
                $table->timestamp('code_expires_at')->nullable();
                $table->timestamp('code_sent_at')->nullable();
                $table->unsignedInteger('code_attempts')->default(0);
                $table->unsignedInteger('code_sent_count')->default(0);
                $table->timestamp('code_window_started_at')->nullable();
                $table->timestamp('verified_at')->nullable();

                // ── Sending ────────────────────────────────────────────────
                $table->unsignedInteger('sent_count')->default(0);
                $table->timestamp('last_sent_at')->nullable();

                // ── The decision ───────────────────────────────────────────
                // approved | partially_approved | rejected
                $table->string('decision', 32)->nullable();
                $table->text('rejection_reason')->nullable();
                $table->unsignedInteger('approved_count')->default(0);
                $table->unsignedInteger('declined_count')->default(0);
                $table->timestamp('decided_at')->nullable();

                // When the approved assets actually changed hands. Approval
                // records the decision; this records that the system applied it.
                $table->timestamp('executed_at')->nullable();

                // ── Who issued it, and who took it away ────────────────────
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('revoked_reason', 255)->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('expires_at');
            });
        }

        if (! Schema::hasTable('transfer_review_items')) {
            Schema::create('transfer_review_items', function (Blueprint $table) {
                $table->id();

                $table->foreignId('review_id')->constrained('transfer_reviews')->cascadeOnDelete();

                // Nullable so a decision survives the asset row being deleted
                // later: the review is the record of what was decided.
                $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();

                // What the Executive Director decided about this one asset:
                // approved | declined. Until the decision lands there is no row.
                $table->string('decision', 32)->nullable();
                $table->timestamp('decided_at')->nullable();

                $table->timestamps();

                $table->unique(['review_id', 'asset_id']);
                $table->index('asset_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_review_items');
        Schema::dropIfExists('transfer_reviews');
    }
};
