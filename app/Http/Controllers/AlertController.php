<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __invoke(Request $request)
    {
        $tab = in_array($request->input('tab'), ['low', 'out', 'near_expiry', 'expired', 'new'], true) ? $request->input('tab') : 'near_expiry';
        $newDays = Settings::int('new_arrival_days');

        $counts = [
            'near_expiry' => Batch::inStock()->nearExpiry()->count(),
            'expired' => Batch::inStock()->expired()->count(),
            'low' => Product::lowStock()->count(),
            'out' => Product::where('is_active', true)->outOfStock()->count(),
            'new' => Batch::where('received_at', '>=', now()->subDays($newDays))->count(),
        ];

        $items = match ($tab) {
            'near_expiry' => Batch::with(['product', 'supplier'])->inStock()->nearExpiry()->orderBy('expiry_date')->paginate(40),
            'expired' => Batch::with(['product', 'supplier'])->inStock()->expired()->orderBy('expiry_date')->paginate(40),
            'low' => Product::with('category')->lowStock()->orderByRaw('stock_quantity * 1.0 / CASE WHEN reorder_level = 0 THEN 1 ELSE reorder_level END')->paginate(40),
            'out' => Product::with('category')->where('is_active', true)->outOfStock()->orderBy('name')->paginate(40),
            'new' => Batch::with(['product', 'supplier', 'purchase'])->where('received_at', '>=', now()->subDays($newDays))->latest('received_at')->paginate(40),
        };

        return view('alerts.index', [
            'tab' => $tab,
            'counts' => $counts,
            'items' => $items->withQueryString(),
            'nearDays' => Settings::int('near_expiry_days'),
            'newDays' => $newDays,
        ]);
    }
}
