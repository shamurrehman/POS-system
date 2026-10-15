@php $width = setting('receipt_width', '80'); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->invoice_no }}</title>
    <style>
        @page { size: {{ $width }}mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Consolas', 'Courier New', monospace; font-size: {{ $width == 58 ? '10px' : '12px' }}; color: #000; background: #fff; }
        .r { width: {{ $width }}mm; padding: 3mm {{ $width == 58 ? '2mm' : '4mm' }}; }
        .c { text-align: center; } .right { text-align: right; } .b { font-weight: bold; }
        .lg { font-size: 1.35em; } .sm { font-size: .85em; }
        hr { border: 0; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .total { font-size: 1.3em; font-weight: bold; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 3px 0; margin: 3px 0; }
        .actions { margin: 10px; font-family: sans-serif; }
        .actions button { padding: 8px 14px; font-size: 14px; cursor: pointer; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
@if(! request()->boolean('embed'))
    <div class="actions"><button onclick="window.print()">Print receipt</button> <button onclick="window.close()">Close</button></div>
@endif
<div class="r">
    <div class="c b lg">{{ setting('store_name') }}</div>
    @if(setting('store_address'))<div class="c sm">{{ setting('store_address') }}</div>@endif
    @if(setting('store_phone'))<div class="c sm">Tel: {{ setting('store_phone') }}</div>@endif
    @if(setting('store_license_no'))<div class="c sm">Lic: {{ setting('store_license_no') }}</div>@endif
    <hr>
    <div class="row"><span>Invoice: <b>{{ $sale->invoice_no }}</b></span></div>
    <div class="row"><span>{{ $sale->created_at->format('d/m/Y h:i A') }}</span><span>Cashier: {{ $sale->user?->name }}</span></div>
    @if($sale->customer_name || $sale->customer_phone)<div>Customer: {{ $sale->customer_name }} {{ $sale->customer_phone }}</div>@endif
    @if($sale->doctor_name)<div>Dr/Rx: {{ $sale->doctor_name }}</div>@endif
    <hr>
    <table>
        @foreach($sale->items as $item)
            <tr><td colspan="2" class="b">{{ $item->product_name }}</td></tr>
            <tr>
                <td class="sm">{{ $item->quantity }} x {{ number_format($item->unit_price, 2) }}@if($item->expiry_date) · Exp {{ $item->expiry_date->format('m/y') }}@endif</td>
                <td class="right">{{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <div class="row"><span>Items</span><span>{{ $sale->items->sum('quantity') }}</span></div>
    <div class="row"><span>Subtotal</span><span>{{ number_format($sale->subtotal, 2) }}</span></div>
    @if($sale->discount > 0)<div class="row"><span>Discount</span><span>-{{ number_format($sale->discount, 2) }}</span></div>@endif
    @if($sale->tax > 0)<div class="row"><span>Tax ({{ setting('tax_rate') }}%)</span><span>{{ number_format($sale->tax, 2) }}</span></div>@endif
    <div class="row total"><span>TOTAL</span><span>{{ money($sale->total) }}</span></div>
    <div class="row"><span>{{ $sale->payment_label }}</span><span>{{ number_format($sale->paid_amount, 2) }}</span></div>
    @if($sale->change_amount > 0)<div class="row b"><span>Change</span><span>{{ number_format($sale->change_amount, 2) }}</span></div>@endif
    @if($sale->returned_amount > 0)<div class="row"><span>Refunded</span><span>-{{ number_format($sale->returned_amount, 2) }}</span></div>@endif
    <hr>
    @if(setting('receipt_footer'))<div class="c">{{ setting('receipt_footer') }}</div>@endif
    <div class="c sm" style="margin-top:4px">Medicines once sold are returnable only with this receipt.</div>
    <div class="c sm" style="margin-top:6px">*** {{ $sale->invoice_no }} ***</div>
</div>
@if($autoPrint)
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
