<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const DOSAGE_FORMS = [
        'Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Drops', 'Cream', 'Ointment',
        'Gel', 'Inhaler', 'Powder', 'Sachet', 'Suppository', 'Spray', 'Solution', 'Device', 'Other',
    ];

    protected $fillable = [
        'name', 'generic_name', 'barcode', 'category_id', 'manufacturer', 'dosage_form', 'strength',
        'unit', 'purchase_price', 'sale_price', 'reorder_level', 'rack_location',
        'requires_prescription', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'requires_prescription' => 'boolean',
            'is_active' => 'boolean',
            'stock_quantity' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('generic_name', 'like', "%{$term}%")
                ->orWhere('barcode', $term)
                ->orWhere('manufacturer', 'like', "%{$term}%");
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'reorder_level');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->name.' '.($this->strength ?? ''));
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out';
        }

        return $this->stock_quantity <= $this->reorder_level ? 'low' : 'ok';
    }

    /** Sellable batches in first-expiry-first-out order. */
    public function sellableBatches()
    {
        return $this->batches()
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()->toDateString()))
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('id');
    }
}
