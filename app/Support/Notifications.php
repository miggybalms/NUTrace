<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Single entry point for the in-app notification bell.
 *
 * type: REQUEST | REPAIR | REPLACEMENT | DISPOSAL ...
 * reference_type: request | repair | replacement | disposal ...
 */
class Notifications
{
    public static function create(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): void {
        if (! $userId) {
            return;
        }

        try {
            DB::table('notifications')->insert([
                'user_id'        => $userId,
                'title'          => $title,
                'message'        => $message,
                'type'           => strtoupper($type),
                'reference_id'   => $referenceId,
                'reference_type' => $referenceType ? strtolower($referenceType) : null,
                'is_read'        => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to create notification: '.$e->getMessage(), [
                'user_id' => $userId,
                'type'    => $type,
            ]);
        }
    }
}
