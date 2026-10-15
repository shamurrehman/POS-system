<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\ReportService;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request, today()->startOfMonth(), today());

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->reports->summary($from, $to),
            'payments' => $this->reports->byPaymentMethod($from, $to),
            'categories' => $this->reports->byCategory($from, $to),
            'purchases' => $this->reports->purchasesTotal($from, $to),
            'inventory' => $this->reports->inventoryValue(),
        ]);
    }

    /** Daily report: every invoice of one day, totals per payment method and per cashier. */
    public function daily(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date']]);
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : today();
        [$start, $end] = [$date->copy()->startOfDay(), $date->copy()->endOfDay()];

        $sales = Sale::with('user')->whereBetween('created_at', [$start, $end])->orderBy('id')->get();
        $returns = SaleReturn::with(['sale', 'user'])->whereBetween('created_at', [$start, $end])->orderBy('id')->get();

        if ($request->input('export') === 'csv') {
            return $this->csv("daily-sales-{$date->toDateString()}.csv",
                ['Invoice', 'Time', 'Customer', 'Cashier', 'Payment', 'Subtotal', 'Discount', 'Tax', 'Total', 'Returned', 'Status'],
                $sales->map(fn (Sale $s) => [
                    $s->invoice_no, $s->created_at->format('H:i'), $s->customer_name, $s->user?->name, $s->payment_label,
                    $s->subtotal, $s->discount, $s->tax, $s->total, $s->returned_amount, $s->status,
                ]));
        }

        return view('reports.daily', [
            'date' => $date,
            'sales' => $sales,
            'returns' => $returns,
            'summary' => $this->reports->summary($date, $date),
            'payments' => $this->reports->byPaymentMethod($date, $date),
            'byCashier' => $sales->groupBy(fn ($s) => $s->user?->name ?? '—')->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum('total')]),
            'topProducts' => $this->reports->topProducts($date, $date, 15),
        ]);
    }

    /** Monthly report: one row per day plus month totals. Printable as a monthly history. */
    public function monthly(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth() : today()->startOfMonth();
        $end = $month->copy()->endOfMonth()->min(today()->endOfDay());
        if ($end->lt($month)) {
            $end = $month->copy()->endOfMonth();
        }

        $days = $this->reports->daily($month, $end);

        if ($request->input('export') === 'csv') {
            return $this->csv("monthly-sales-{$month->format('Y-m')}.csv",
                ['Date', 'Invoices', 'Discount', 'Sales', 'Returns', 'Net Sales', 'Tax', 'Cost', 'Profit'],
                $days->map(fn ($d) => [$d['date']->toDateString(), $d['invoices'], $d['discount'], $d['sales'], $d['returns'], $d['net'], $d['tax'], round($d['cost'], 2), round($d['profit'], 2)]));
        }

        return view('reports.monthly', [
            'month' => $month,
            'days' => $days,
            'summary' => $this->reports->summary($month, $month->copy()->endOfMonth()),
            'payments' => $this->reports->byPaymentMethod($month, $month->copy()->endOfMonth()),
            'topProducts' => $this->reports->topProducts($month, $month->copy()->endOfMonth(), 15),
            'categories' => $this->reports->byCategory($month, $month->copy()->endOfMonth()),
            'purchases' => $this->reports->purchasesTotal($month, $month->copy()->endOfMonth()),
        ]);
    }

    public function yearly(Request $request)
    {
        $year = (int) $request->input('year', today()->year);
        $year = max(2000, min(2100, $year));
        $months = $this->reports->monthly($year);

        if ($request->input('export') === 'csv') {
            return $this->csv("yearly-sales-{$year}.csv",
                ['Month', 'Invoices', 'Sales', 'Returns', 'Net Sales', 'Cost', 'Profit'],
                $months->map(fn ($m) => [$m['date']->format('F Y'), $m['invoices'], $m['sales'], $m['returns'], $m['net'], round($m['cost'], 2), round($m['profit'], 2)]));
        }

        return view('reports.yearly', [
            'year' => $year,
            'months' => $months,
            'summary' => $this->reports->summary(Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()),
        ]);
    }

    /** Product-wise sales for any date range. */
    public function products(Request $request)
    {
        [$from, $to] = $this->range($request, today()->startOfMonth(), today());
        $rows = $this->reports->topProducts($from, $to, 1000);

        if ($request->input('export') === 'csv') {
            return $this->csv("product-sales-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Product', 'Qty Sold', 'Revenue', 'Gross Profit'],
                $rows->map(fn ($r) => [$r->product_name, $r->qty, round($r->revenue, 2), round($r->profit, 2)]));
        }

        return view('reports.products', compact('from', 'to', 'rows'));
    }

    /** Stock valuation per product. */
    public function inventory(Request $request)
    {
        $rows = Product::with('category')
            ->withSum(['batches as cost_value' => fn ($q) => $q->where('quantity', '>', 0)], \DB::raw('quantity * purchase_price'))
            ->orderBy('name')
            ->get();

        if ($request->input('export') === 'csv') {
            return $this->csv('inventory-valuation-'.today()->toDateString().'.csv',
                ['Product', 'Generic', 'Category', 'Barcode', 'Stock', 'Unit', 'Reorder Level', 'Cost Value', 'Sale Price', 'Retail Value'],
                $rows->map(fn ($p) => [$p->name, $p->generic_name, $p->category?->name, $p->barcode, $p->stock_quantity, $p->unit, $p->reorder_level,
                    round((float) $p->cost_value, 2), $p->sale_price, round($p->stock_quantity * (float) $p->sale_price, 2)]));
        }

        return view('reports.inventory', [
            'rows' => $rows,
            'valuation' => $this->reports->inventoryValue(),
        ]);
    }

    public function expiry(Request $request)
    {
        $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:730']]);
        $days = (int) $request->input('days', Settings::int('near_expiry_days'));

        $batches = Batch::with(['product', 'supplier'])->inStock()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', today()->addDays($days)->toDateString())
            ->orderBy('expiry_date')->get();

        if ($request->input('export') === 'csv') {
            return $this->csv('expiry-report-'.today()->toDateString().'.csv',
                ['Product', 'Batch', 'Expiry', 'Days Left', 'Qty', 'Cost Value', 'Supplier'],
                $batches->map(fn ($b) => [$b->product?->name, $b->batch_no, $b->expiry_date->toDateString(), $b->daysToExpiry(), $b->quantity,
                    round($b->quantity * (float) $b->purchase_price, 2), $b->supplier?->name]));
        }

        return view('reports.expiry', compact('batches', 'days'));
    }

    public function purchases(Request $request)
    {
        [$from, $to] = $this->range($request, today()->startOfMonth(), today());

        $purchases = Purchase::with('supplier')->withCount('items')
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('purchase_date')->get();

        $bySupplier = $purchases->groupBy(fn ($p) => $p->supplier?->name ?? 'No supplier')
            ->map(fn ($g) => ['count' => $g->count(), 'total' => $g->sum('total'), 'due' => $g->sum(fn ($p) => $p->due_amount)])
            ->sortByDesc('total');

        if ($request->input('export') === 'csv') {
            return $this->csv("purchases-{$from->toDateString()}-to-{$to->toDateString()}.csv",
                ['Reference', 'Date', 'Supplier', 'Supplier Invoice', 'Items', 'Total', 'Paid', 'Due', 'Status'],
                $purchases->map(fn ($p) => [$p->reference_no, $p->purchase_date->toDateString(), $p->supplier?->name, $p->supplier_invoice_no,
                    $p->items_count, $p->total, $p->paid_amount, $p->due_amount, $p->payment_status]));
        }

        return view('reports.purchases', compact('from', 'to', 'purchases', 'bySupplier'));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request, Carbon $defaultFrom, Carbon $defaultTo): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : $defaultFrom;
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : $defaultTo;

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
            fputcsv($out, $header);
            foreach ($rows as $row) {
                // Neutralise spreadsheet formula injection from user-entered text.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, (array) $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
