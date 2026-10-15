<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use App\Support\BarcodeParser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function index()
    {
        return view('pos.index', [
            'paymentMethods' => Sale::PAYMENT_METHODS,
        ]);
    }

    /** Barcode / QR scan: exact match only. */
    public function lookup(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:255']]);
        $parsed = BarcodeParser::parse($request->string('code'));

        $product = Product::where('is_active', true)->whereIn('barcode', $parsed['codes'])->first();
        if (! $product) {
            return response()->json(['message' => "No product found for code {$parsed['raw']}"], 404);
        }

        $batch = null;
        if ($parsed['batch']) {
            $batch = Batch::where('product_id', $product->id)->where('batch_no', $parsed['batch'])
                ->where('quantity', '>', 0)->first();
        }

        // The pack in the customer's hand is expired: say so instead of quietly selling another batch.
        $expiry = $parsed['expiry'] ?? $batch?->expiry_date?->toDateString();
        if ($expiry && $expiry < today()->toDateString()) {
            return response()->json([
                'message' => "EXPIRED PACK: {$product->display_name}"
                    .($parsed['batch'] ? " batch {$parsed['batch']}" : '')
                    .' expired on '.\Carbon\Carbon::parse($expiry)->format('d M Y').'. Do not sell it.',
                'expired' => true,
            ], 422);
        }
        $batchId = $batch?->id;

        return response()->json([
            'product' => $this->present($product, $request),
            'batch_id' => $batchId,
            'scanned_batch' => $parsed['batch'],
            'scanned_expiry' => $parsed['expiry'],
        ]);
    }

    /** Type-ahead search by name, generic name or barcode. */
    public function search(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $products = Product::where('is_active', true)->search($term)
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$term.'%'])
            ->orderBy('name')
            ->limit(15)->get();

        return response()->json($products->map(fn ($p) => $this->present($p, $request)));
    }

    public function store(Request $request, SaleService $sales)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.batch_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(array_keys(Sale::PAYMENT_METHODS))],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'doctor_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sale = $sales->create($data, $request->user());

        return response()->json([
            'id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'total' => (float) $sale->total,
            'change' => (float) $sale->change_amount,
            'receipt_url' => route('sales.receipt', $sale),
        ]);
    }

    private function present(Product $p, Request $request): array
    {
        $nextExpiry = $p->sellableBatches()->value('expiry_date');

        return [
            'id' => $p->id,
            'name' => $p->display_name,
            'generic_name' => $p->generic_name,
            'barcode' => $p->barcode,
            'unit' => $p->unit,
            'price' => (float) $p->sale_price,
            'cost' => $request->user()->isManagerOrAdmin() ? (float) $p->purchase_price : null,
            'stock' => (int) $p->sellableBatches()->sum('quantity'),
            'rack' => $p->rack_location,
            'rx' => $p->requires_prescription,
            'next_expiry' => $nextExpiry ? \Carbon\Carbon::parse($nextExpiry)->format('M Y') : null,
        ];
    }
}
