@if($sale->status === 'returned')
    <span class="badge badge-red">Returned</span>
@elseif($sale->status === 'partially_returned')
    <span class="badge badge-amber">Part returned</span>
@else
    <span class="badge badge-green">Paid</span>
@endif
