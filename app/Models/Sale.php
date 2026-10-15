<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'mobile' => 'Mobile / Bank Transfer',
    ];

    protected $fillable = [
        'invoice_no', 'customer_name', 'customer_phone', 'doctor_name', 'user_id', 'subtotal', 'discount',
        'tax', 'total', 'cost_total', 'paid_amount', 'change_amount', 'returned_amount', 'payment_method',
        'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
        ];
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getNetTotalAttribute(): float
    {
        return (float) $this->total - (float) $this->returned_amount;
    }

    public function getPaymentLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}
