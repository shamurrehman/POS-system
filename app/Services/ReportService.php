<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All figures are calculated in SQL that works on both SQLite and MySQL.
 * Revenue/profit always exclude tax and are net of customer returns.
 */
class ReportService
{
    /** @return array<string, float|int> */
    public function summary(Carbon $from, Carbon $to): array
    {
        [$start, $end] = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $s = Sale::whereBetween('created_at', [$start, $end])->selectRaw('
            COUNT(*) as invoices,
            COALESCE(SUM(subtotal),0) as gross,
            COALESCE(SUM(discount),0) as discount,
            COALESCE(SUM(tax),0) as tax,
            COALESCE(SUM(total),0) as total,
            COALESCE(SUM(cost_total),0) as cost
        ')->first();

        $r = SaleReturn::whereBetween('created_at', [$start, $end])->selectRaw('
            COUNT(*) as returns_count,
            COALESCE(SUM(total),0) as total,
            COALESCE(SUM(tax),0) as tax,
            COALESCE(SUM(cost_total),0) as cost
        ')->first();

        $itemsSold = (int) SaleItem::whereHas('sale', fn ($q) => $q->whereBetween('created_at', [$start, $end]))->sum('quantity');

        $netSales = (float) $s->total - (float) $r->total;               // money actually kept, incl. tax
        $netRevenue = $netSales - ((float) $s->tax - (float) $r->tax);   // excl. tax
        $netCost = (float) $s->cost - (float) $r->cost;
        $profit = $netRevenue - $netCost;

        return [
            'invoices' => (int) $s->invoices,
            'items_sold' => $itemsSold,
            'gross' => (float) $s->gross,
            'discount' => (float) $s->discount,
            'tax' => (float) $s->tax - (float) $r->tax,
            'sales_total' => (float) $s->total,
            'returns_count' => (int) $r->returns_count,
            'returns' => (float) $r->total,
            'net_sales' => $netSales,
            'net_revenue' => $netRevenue,
            'cost' => $netCost,
            'profit' => $profit,
            'margin' => $netRevenue > 0 ? round($profit / $netRevenue * 100, 1) : 0,
            'avg_invoice' => $s->invoices > 0 ? (float) $s->total / $s->invoices : 0,
        ];
    }

    /** Sales split by payment method (before returns). */
    public function byPaymentMethod(Carbon $from, Carbon $to): Collection
    {
        return Sale::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('payment_method, COUNT(*) as invoices, SUM(total) as total')
            ->groupBy('payment_method')
            ->get();
    }

    /** One row per day between two dates (days with no sales are included as zero). */
    public function daily(Carbon $from, Carbon $to): Collection
    {
        [$start, $end] = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $sales = Sale::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as invoices, SUM(total) as total, SUM(tax) as tax, SUM(cost_total) as cost, SUM(discount) as discount')
            ->groupBy(DB::raw('DATE(created_at)'))->get()->keyBy('day');

        $returns = SaleReturn::whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(total) as total, SUM(tax) as tax, SUM(cost_total) as cost')
            ->groupBy(DB::raw('DATE(created_at)'))->get()->keyBy('day');

        return collect(CarbonPeriod::create($start, $end->copy()->startOfDay()))->map(function (Carbon $d) use ($sales, $returns) {
            $key = $d->toDateString();
            $s = $sales->get($key);
            $r = $returns->get($key);

            return $this->row($d, $s, $r);
        });
    }

    /** One row per month of a year. */
    public function monthly(int $year): Collection
    {
        $start = Carbon::create($year)->startOfYear();
        $end = $start->copy()->endOfYear();
        $monthExpr = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%m', created_at)" : 'MONTH(created_at)';

        $sales = Sale::whereBetween('created_at', [$start, $end])
            ->selectRaw("CAST({$monthExpr} AS INTEGER) as m, COUNT(*) as invoices, SUM(total) as total, SUM(tax) as tax, SUM(cost_total) as cost, SUM(discount) as discount")
            ->groupBy(DB::raw($monthExpr))->get()->keyBy('m');
        $returns = SaleReturn::whereBetween('created_at', [$start, $end])
            ->selectRaw("CAST({$monthExpr} AS INTEGER) as m, SUM(total) as total, SUM(tax) as tax, SUM(cost_total) as cost")
            ->groupBy(DB::raw($monthExpr))->get()->keyBy('m');

        return collect(range(1, 12))->map(fn (int $m) => $this->row(
            Carbon::create($year, $m), $sales->get($m), $returns->get($m)
        ));
    }

    /** Best sellers by revenue. */
    public function topProducts(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('sale_items.product_id, MAX(sale_items.product_name) as product_name,
                SUM(sale_items.quantity - sale_items.returned_quantity) as qty,
                SUM((sale_items.quantity - sale_items.returned_quantity) * sale_items.unit_price) as revenue,
                SUM((sale_items.quantity - sale_items.returned_quantity) * (sale_items.unit_price - sale_items.unit_cost)) as profit')
            ->groupBy('sale_items.product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }

    /** Sales grouped by product category. */
    public function byCategory(Carbon $from, Carbon $to): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as category,
                SUM(sale_items.quantity - sale_items.returned_quantity) as qty,
                SUM((sale_items.quantity - sale_items.returned_quantity) * sale_items.unit_price) as revenue")
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get();
    }

    /** Current stock value at cost and at retail price. */
    public function inventoryValue(): array
    {
        $row = Batch::query()
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->whereNull('products.deleted_at')
            ->where('batches.quantity', '>', 0)
            ->selectRaw('COALESCE(SUM(batches.quantity * batches.purchase_price),0) as cost,
                COALESCE(SUM(batches.quantity * products.sale_price),0) as retail,
                COALESCE(SUM(batches.quantity),0) as units')
            ->first();

        $expired = Batch::expired()->inStock()->selectRaw('COALESCE(SUM(quantity * purchase_price),0) as v')->value('v');

        return [
            'cost' => (float) $row->cost,
            'retail' => (float) $row->retail,
            'units' => (int) $row->units,
            'potential_profit' => (float) $row->retail - (float) $row->cost,
            'expired_value' => (float) $expired,
            'products' => Product::count(),
            'in_stock_products' => Product::where('stock_quantity', '>', 0)->count(),
        ];
    }

    public function purchasesTotal(Carbon $from, Carbon $to): array
    {
        $row = Purchase::whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total),0) as total, COALESCE(SUM(paid_amount),0) as paid')->first();

        return ['count' => (int) $row->n, 'total' => (float) $row->total, 'paid' => (float) $row->paid, 'due' => (float) $row->total - (float) $row->paid];
    }

    private function row(Carbon $period, $s, $r): array
    {
        $total = (float) ($s->total ?? 0);
        $returns = (float) ($r->total ?? 0);
        $net = $total - $returns;
        $netRevenue = $net - ((float) ($s->tax ?? 0) - (float) ($r->tax ?? 0));
        $cost = (float) ($s->cost ?? 0) - (float) ($r->cost ?? 0);

        return [
            'date' => $period,
            'invoices' => (int) ($s->invoices ?? 0),
            'discount' => (float) ($s->discount ?? 0),
            'sales' => $total,
            'returns' => $returns,
            'net' => $net,
            'tax' => (float) ($s->tax ?? 0) - (float) ($r->tax ?? 0),
            'cost' => $cost,
            'profit' => $netRevenue - $cost,
        ];
    }
}
