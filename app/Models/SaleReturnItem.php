<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $fillable = ['sale_return_id', 'sale_item_id', 'quantity', 'amount', 'restocked'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'restocked' => 'boolean',
        ];
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
