@if($product->stock_status === 'out')
    <span class="badge badge-red">Out of stock</span>
@elseif($product->stock_status === 'low')
    <span class="badge badge-amber">Low · {{ $product->stock_quantity }}</span>
@else
    <span class="badge badge-green">{{ $product->stock_quantity }} {{ $product->unit }}</span>
@endif
