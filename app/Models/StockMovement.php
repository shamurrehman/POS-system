<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public const TYPES = [
        'opening' => 'Opening Stock',
        'purchase' => 'Purchase / Stock In',
        'sale' => 'Sale',
        'sale_return' => 'Customer Return',
        'adjustment_in' => 'Adjustment (+)',
        'adjustment_out' => 'Adjustment (-)',
        'expired' => 'Expired Write-off',
        'damaged' => 'Damaged Write-off',
        'supplier_return' => 'Returned to Supplier',
    ];

    protected $fillable = [
        'product_id', 'batch_id', 'type', 'quantity', 'balance_after', 'unit_cost', 'reference_no',
        'reference_type', 'reference_id', 'note', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'quantity' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }
}
