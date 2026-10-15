<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $fillable = ['return_no', 'sale_id', 'user_id', 'total', 'tax', 'cost_total', 'reason'];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'tax' => 'decimal:2',
            'cost_total' => 'decimal:2',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
