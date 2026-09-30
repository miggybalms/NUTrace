<?php

namespace App\Models;

use App\Support\Inventory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'Asset_code', 'Asset_name', 'Category', 'Condition', 'Lifecycle_Status',
    'accusion_date', 'accusion_cost', 'purchase_Price', 'warranty_months', 'supplier', 'model', 'manufacture',
    'serial_Number', 'asset_location', 'qr_code_path',
    'lifespan_months', 'expiration_date', 'repair_counts', 'last_maintenance_date', 'next_maintenance_date', 'maintenance_interval'
])]
class Asset extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Assets the institution still holds.
     *
     * A disposed asset leaves the inventory when its disposal record is
     * archived, and must stop appearing in every Assets list. Its row stays so
     * the archived record can still name it.
     */
    public function scopeInInventory(Builder $query): Builder
    {
        return Inventory::excludeRemoved($query, $this->getTable());
    }

    // Table name and casts are inferred; add date cast for accusion_date
    protected function casts(): array
    {
        return [
            'accusion_date' => 'date',
            'expiration_date' => 'date',
            'last_maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'purchase_Price' => 'decimal:2',
            'accusion_cost' => 'decimal:2',
        ];
    }
}
