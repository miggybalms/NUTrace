<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the institution currently holds.
 *
 * An asset leaves the inventory when its disposal record is archived: it stops
 * appearing in the Assets list, the registry, the department views and the
 * inventory download. The row itself is kept, so the archived disposal record
 * can still name the asset and its accountability / repair / replacement /
 * audit history stays readable.
 *
 * Every read helper here tolerates the inventory columns being absent, because
 * the code ships before `php artisan migrate` runs on the server.
 */
class Inventory
{
    /** Timestamp set when the asset stopped being part of the inventory. */
    public const REMOVED_AT = 'inventory_removed_at';

    /** Who took it out of the inventory. */
    public const REMOVED_BY = 'inventory_removed_by';

    private static ?bool $columnsReady = null;

    /** Has the inventory-removal migration been run? */
    public static function columnsReady(): bool
    {
        if (self::$columnsReady === null) {
            self::$columnsReady = Schema::hasColumn('assets', self::REMOVED_AT)
                && Schema::hasColumn('assets', self::REMOVED_BY);
        }

        return self::$columnsReady;
    }

    /**
     * Assets the institution still holds. Before the migration has run this is
     * a no-op, so nothing disappears from any page in the meantime.
     *
     * Accepts either builder — the listings are a mix of DB::table() and
     * Eloquent — and hands back the same object it was given.
     *
     * @template T of Builder|EloquentBuilder
     *
     * @param  T  $query
     * @return T
     */
    public static function excludeRemoved(Builder|EloquentBuilder $query, string $table = 'assets'): Builder|EloquentBuilder
    {
        if (! self::columnsReady()) {
            return $query;
        }

        return $query->whereNull($table . '.' . self::REMOVED_AT);
    }

    /** Has this asset left the inventory? */
    public static function isRemoved($asset): bool
    {
        if (! $asset || ! self::columnsReady()) {
            return false;
        }

        return ! empty($asset->{self::REMOVED_AT} ?? null);
    }

    /**
     * Take an asset out of the inventory. Never deletes the row: the asset has
     * to stay reachable for its disposal record and its own history.
     *
     * @return bool true when the asset was in the inventory and has now left it
     */
    public static function remove(int $assetId, ?int $adminId = null): bool
    {
        if ($assetId <= 0 || ! self::columnsReady()) {
            return false;
        }

        $affected = DB::table('assets')
            ->where('id', $assetId)
            ->whereNull(self::REMOVED_AT)
            ->update([
                self::REMOVED_AT => now(),
                self::REMOVED_BY => $adminId,
                'updated_at'     => now(),
            ]);

        return $affected > 0;
    }
}
