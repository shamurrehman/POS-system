@php
    $cls = match ($type) {
        'purchase', 'opening', 'adjustment_in' => 'badge-green',
        'sale' => 'badge-blue',
        'sale_return' => 'badge-purple',
        'expired', 'damaged' => 'badge-red',
        default => 'badge-amber',
    };
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>
