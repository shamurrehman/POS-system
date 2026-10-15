{{-- Letterhead shown only on printed reports --}}
<div class="hidden print:block mb-4 border-b border-slate-300 pb-3">
    <div class="flex justify-between items-end">
        <div>
            <div class="text-xl font-bold">{{ setting('store_name') }}</div>
            <div class="text-xs text-slate-600">{{ setting('store_address') }} @if(setting('store_phone')) · {{ setting('store_phone') }} @endif</div>
            @if(setting('store_license_no'))<div class="text-xs text-slate-600">License: {{ setting('store_license_no') }}</div>@endif
        </div>
        <div class="text-right">
            <div class="text-lg font-semibold">{{ $title }}</div>
            @isset($subtitle)<div class="text-sm">{{ $subtitle }}</div>@endisset
            <div class="text-xs text-slate-500">Printed {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</div>
        </div>
    </div>
</div>
