<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ReportService;
use App\Support\Settings;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports)
    {
        $user = $request->user();
        $today = today();

        $todaySummary = $reports->summary($today, $today);
        $yesterday = $reports->summary($today->copy()->subDay(), $today->copy()->subDay());

        $data = [
            'today' => $todaySummary,
            'yesterday' => $yesterday,
            'alerts' => [
                'low' => Product::lowStock()->count(),
                'out' => Product::where('is_active', true)->outOfStock()->count(),
                'near_expiry' => Batch::inStock()->nearExpiry()->count(),
                'expired' => Batch::inStock()->expired()->count(),
                'new_arrivals' => Batch::where('received_at', '>=', now()->subDays(Settings::int('new_arrival_days')))->count(),
            ],
            'recentSales' => Sale::with('user')
                ->when(! $user->isManagerOrAdmin(), fn ($q) => $q->where('user_id', $user->id))
                ->latest()->limit(8)->get(),
        ];

        if ($user->isManagerOrAdmin()) {
            $monthStart = $today->copy()->startOfMonth();
            $daily = $reports->daily($today->copy()->subDays(29), $today);

            $data += [
                'month' => $reports->summary($monthStart, $today),
                'lastMonth' => $reports->summary($monthStart->copy()->subMonth(), $monthStart->copy()->subDay()),
                'inventory' => $reports->inventoryValue(),
                'topProducts' => $reports->topProducts($monthStart, $today, 8),
                'chartDaily' => [
                    'labels' => $daily->map(fn ($r) => $r['date']->format('d M'))->values(),
                    'sales' => $daily->pluck('net')->values(),
                    'profit' => $daily->map(fn ($r) => round($r['profit'], 2))->values(),
                ],
                'chartMonthly' => (function () use ($reports, $today) {
                    $rows = $reports->monthly($today->year);

                    return [
                        'labels' => $rows->map(fn ($r) => $r['date']->format('M'))->values(),
                        'sales' => $rows->pluck('net')->values(),
                        'profit' => $rows->map(fn ($r) => round($r['profit'], 2))->values(),
                    ];
                })(),
                'nearExpiryList' => Batch::with('product')->inStock()->nearExpiry()->orderBy('expiry_date')->limit(6)->get(),
                'lowStockList' => Product::lowStock()->orderBy('stock_quantity')->limit(6)->get(),
            ];
        }

        return view('dashboard', $data);
    }
}
