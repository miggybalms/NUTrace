<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'user_id', 'Asset_code', 'Asset_name', 'Category', 'Condition', 'Lifecycle_Status',
    'accusion_date', 'accusion_cost', 'purchase_Price', 'warranty_months', 'supplier', 'model', 'manufacture',
    'serial_Number', 'asset_location', 'qr_code_path', 'qr_code_url', 'file_name', 'file_path', 'file_size', 'mime_type', 'url'
])]
class Asset extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Table name and casts are inferred; add date cast for accusion_date
    protected function casts(): array
    {
        return [
            'accusion_date' => 'date',
            'purchase_Price' => 'decimal:2',
            'accusion_cost' => 'decimal:2',
        ];
    }
}
