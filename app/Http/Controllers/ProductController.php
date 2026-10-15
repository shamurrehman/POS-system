<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleItem;
use App\Services\StockService;
use App\Support\BarcodeParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'stock' => ['nullable', Rule::in(['in', 'low', 'out'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $products = Product::with('category')
            ->search($filters['q'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when(($filters['stock'] ?? null) === 'in', fn ($q) => $q->whereColumn('stock_quantity', '>', 'reorder_level'))
            ->when(($filters['stock'] ?? null) === 'low', fn ($q) => $q->lowStock())
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->outOfStock())
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Product $product)
    {
        $product->load('category');
        $batches = $product->batches()->with('supplier')
            ->orderByRaw('CASE WHEN quantity > 0 THEN 0 ELSE 1 END')->orderBy('expiry_date')->get();

        $movements = $product->movements()->with(['user', 'batch'])->latest('id')->paginate(20);

        $since = now()->subDays(30);
        $soldLast30 = (int) SaleItem::where('product_id', $product->id)
            ->whereHas('sale', fn ($q) => $q->where('created_at', '>=', $since))
            ->sum(DB::raw('quantity - returned_quantity'));

        return view('products.show', compact('product', 'batches', 'movements', 'soldLast30'));
    }

    public function create()
    {
        return view('products.form', ['product' => new Product(['unit' => 'pcs', 'reorder_level' => 10, 'is_active' => true]), 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $this->validated($request);
        $opening = $request->validate([
            'opening_qty' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'opening_batch' => ['nullable', 'string', 'max:100'],
            'opening_expiry' => ['nullable', 'date'],
        ]);

        $product = DB::transaction(function () use ($data, $opening, $stock) {
            $product = Product::create($data);
            if (! $product->barcode) {
                $product->update(['barcode' => $this->internalBarcode($product->id)]);
            }
            if (! empty($opening['opening_qty'])) {
                $stock->receive($product, [
                    'batch_no' => $opening['opening_batch'] ?? null,
                    'expiry_date' => $opening['opening_expiry'] ?? null,
                    'quantity' => $opening['opening_qty'],
                ], 'opening', null, null, 'Opening stock');
            }
            ActivityLog::record('product.created', "Added product {$product->name}", $product);

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Product added.');
    }

    public function edit(Product $product)
    {
        return view('products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $old = $product->only(['sale_price', 'purchase_price']);
        $product->update($data);
        if (! $product->barcode) {
            $product->update(['barcode' => $this->internalBarcode($product->id)]);
        }

        $priceNote = $old['sale_price'] != $product->sale_price ? " (price {$old['sale_price']} → {$product->sale_price})" : '';
        ActivityLog::record('product.updated', "Updated product {$product->name}{$priceNote}", $product);

        return redirect()->route('products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        if ($product->stock_quantity > 0) {
            return back()->with('error', 'This product still has stock. Write off or adjust the stock first, or mark it inactive instead.');
        }
        $product->delete();
        ActivityLog::record('product.deleted', "Deleted product {$product->name}", $product);

        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    /** Printable barcode labels (for shelf tags or products without a manufacturer barcode). */
    public function labels(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'copies' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $product = isset($data['product_id']) ? Product::find($data['product_id']) : null;
        // Labels are for medicines without a scannable pack code: give them an in-store one to print.
        if ($product && ! $product->barcode) {
            $product->update(['barcode' => $this->internalBarcode($product->id)]);
            ActivityLog::record('product.updated', "Generated in-store barcode {$product->barcode} for {$product->name}", $product);
        }

        return view('products.labels', [
            'product' => $product,
            'copies' => $data['copies'] ?? 12,
        ]);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        // A scanned GS1 pack code becomes its GTIN before the uniqueness check, see BarcodeParser::normalize().
        if (filled($request->input('barcode'))) {
            $request->merge(['barcode' => BarcodeParser::normalize((string) $request->input('barcode'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'generic_name' => ['nullable', 'string', 'max:150'],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product?->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($product) {
                    // The same code written with or without leading zeros (UPC-A / EAN-13 / GTIN-14).
                    $other = Product::withTrashed()->whereIn('barcode', BarcodeParser::numericVariants((string) $value))
                        ->when($product, fn ($q) => $q->whereKeyNot($product->id))->first();
                    if ($other) {
                        $fail("This barcode already belongs to {$other->display_name}.");
                    }
                }],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'dosage_form' => ['nullable', 'string', 'max:50'],
            'strength' => ['nullable', 'string', 'max:50'],
            'unit' => ['required', 'string', 'max:30'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rack_location' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['requires_prescription'] = $request->boolean('requires_prescription');
        $data['is_active'] = $request->boolean('is_active');
        $data['barcode'] = ! empty($data['barcode']) ? trim($data['barcode']) : null;

        return $data;
    }

    /** EAN-13 in the 200–299 "in-store use" range so it never clashes with real manufacturer codes. */
    private function internalBarcode(int $id): string
    {
        $base = '200'.str_pad((string) $id, 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($base) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $base.((10 - $sum % 10) % 10);
    }
}
