<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // User who receives the notification
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Notification information
            $table->string('title');
            $table->text('message');

            // REQUEST, REPAIR, or REPLACEMENT
            $table->string('type');

            // Related record
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();

            // false = unread, true = read
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            // Faster queries for user's unread notifications
            $table->index(['user_id', 'is_read']);

            // Find the related request/repair/replacement
            $table->index(['reference_type', 'reference_id']);

            // Faster filtering by notification type
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};