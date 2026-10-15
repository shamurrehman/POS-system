{{-- Key figures for a period; expects $summary from ReportService::summary() --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
    <div class="stat"><div class="stat-label">Net sales</div><div class="stat-value">{{ money($summary['net_sales']) }}</div><div class="stat-hint">{{ number_format($summary['invoices']) }} invoices · avg {{ money($summary['avg_invoice']) }}</div></div>
    <div class="stat"><div class="stat-label">Gross profit</div><div class="stat-value text-emerald-700">{{ money($summary['profit']) }}</div><div class="stat-hint">Margin {{ $summary['margin'] }}%</div></div>
    <div class="stat"><div class="stat-label">Cost of goods sold</div><div class="stat-value">{{ money($summary['cost']) }}</div><div class="stat-hint">{{ number_format($summary['items_sold']) }} units sold</div></div>
    <div class="stat"><div class="stat-label">Discounts / Returns</div><div class="stat-value text-xl">{{ money($summary['discount']) }}</div><div class="stat-hint">Returns {{ money($summary['returns']) }} ({{ $summary['returns_count'] }}) · Tax {{ money($summary['tax']) }}</div></div>
</div>
