{{-- Shared report toolbar: filters slot + Print + CSV export --}}
<div class="no-print flex flex-wrap items-end justify-between gap-3 mb-4">
    <div>
        <h2 class="page-title">{{ $title }}</h2>
        @isset($subtitle)<p class="page-subtitle">{{ $subtitle }}</p>@endisset
    </div>
    <form class="flex flex-wrap items-end gap-2">
        {{ $slot ?? '' }}
        {!! $filters ?? '' !!}
        <button class="btn btn-dark">Show</button>
        <button type="button" onclick="window.print()" class="btn btn-secondary"><x-icon name="printer" class="w-4 h-4" /> Print</button>
        @if($csv ?? true)
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-secondary"><x-icon name="download" class="w-4 h-4" /> Excel / CSV</a>
        @endif
    </form>
</div>
