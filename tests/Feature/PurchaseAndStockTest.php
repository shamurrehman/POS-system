<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseAndStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_creates_batches_updates_prices_and_tracks_dues(): void
    {
        $manager = User::factory()->role('manager')->create();
        $supplier = Supplier::create(['name' => 'Dist', 'is_active' => true]);
        $product = Product::factory()->create(['purchase_price' => 10, 'sale_price' => 15]);

        $this->actingAs($manager)->postJson(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => today()->toDateString(),
            'paid_amount' => 100,
            'items' => [
                ['product_id' => $product->id, 'batch_no' => 'X1', 'expiry_date' => today()->addYear()->toDateString(), 'quantity' => 20, 'unit_cost' => 12, 'sale_price' => 18],
                ['product_id' => $product->id, 'batch_no' => 'X1', 'expiry_date' => today()->addYear()->toDateString(), 'quantity' => 5, 'unit_cost' => 12, 'sale_price' => 18],
            ],
        ])->assertOk()->assertJsonStructure(['redirect']);

        $purchase = Purchase::sole();
        $this->assertEquals(300, (float) $purchase->total);
        $this->assertSame('partial', $purchase->payment_status);
        $this->assertSame(1, Batch::count(), 'same batch number is merged into one batch');
        $this->assertSame(25, $product->fresh()->stock_quantity);
        $this->assertEquals(18, (float) $product->fresh()->sale_price);

        $this->actingAs($manager)->post(route('purchases.payment', $purchase), ['amount' => 500])->assertRedirect();
        $this->assertSame('paid', $purchase->fresh()->payment_status);
        $this->assertEquals(300, (float) $purchase->fresh()->paid_amount, 'overpayment is capped at the total');
    }

    public function test_items_without_batch_number_or_expiry_can_be_stocked_and_sold(): void
    {
        $manager = User::factory()->role('manager')->create();
        $product = Product::factory()->create(['sale_price' => 50]);

        $this->actingAs($manager)->postJson(route('purchases.store'), [
            'purchase_date' => today()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'batch_no' => '', 'expiry_date' => null, 'quantity' => 5, 'unit_cost' => 30, 'sale_price' => 50],
                ['product_id' => $product->id, 'batch_no' => null, 'expiry_date' => null, 'quantity' => 3, 'unit_cost' => 30, 'sale_price' => 50],
                ['product_id' => $product->id, 'expiry_date' => today()->addYear()->toDateString(), 'quantity' => 2, 'unit_cost' => 30, 'sale_price' => 50],
            ],
        ])->assertOk();

        $this->assertSame(['NB-EXP'.today()->addYear()->format('Ymd') => 2, 'NB-NOEXP' => 8],
            Batch::orderBy('batch_no')->pluck('quantity', 'batch_no')->all());
        $this->assertSame(10, $product->fresh()->stock_quantity);

        // Opening stock on a new product without batch/expiry
        $this->actingAs($manager)->post(route('products.store'), [
            'name' => 'Cotton Roll', 'unit' => 'pcs', 'purchase_price' => 10, 'sale_price' => 15, 'reorder_level' => 5,
            'opening_qty' => 7,
        ])->assertRedirect();
        $this->assertSame(7, Product::where('name', 'Cotton Roll')->value('stock_quantity'));

        // Batches that expire are sold before the no-expiry stock
        $this->actingAs($manager)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]], 'payment_method' => 'card',
        ])->assertOk();
        $this->assertSame(0, Batch::where('batch_no', 'like', 'NB-EXP%')->value('quantity'));
        $this->assertSame(7, Batch::where('batch_no', 'NB-NOEXP')->value('quantity'));
    }

    public function test_write_off_requires_a_reason_and_cannot_exceed_batch_stock(): void
    {
        $manager = User::factory()->role('manager')->create();
        $product = Product::factory()->create();
        $this->actingAs($manager)->postJson(route('purchases.store'), [
            'purchase_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'batch_no' => 'B', 'quantity' => 5, 'unit_cost' => 1, 'sale_price' => 2]],
        ])->assertOk();
        $batch = Batch::sole();

        $this->actingAs($manager)->post(route('adjustments.store'), ['batch_id' => $batch->id, 'type' => 'damaged', 'quantity' => 2])
            ->assertSessionHasErrors('note');
        $this->actingAs($manager)->post(route('adjustments.store'), ['batch_id' => $batch->id, 'type' => 'damaged', 'quantity' => 9, 'note' => 'x'])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($manager)->post(route('adjustments.store'), ['batch_id' => $batch->id, 'type' => 'expired', 'quantity' => 2, 'note' => 'destroyed'])
            ->assertRedirect();

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertSame([5, -2], $product->movements()->orderBy('id')->pluck('quantity')->all());
        $this->assertSame([5, 3], $product->movements()->orderBy('id')->pluck('balance_after')->all());
    }

    public function test_reports_compute_profit_net_of_returns_and_tax(): void
    {
        $manager = User::factory()->role('manager')->create();
        $product = Product::factory()->create(['sale_price' => 100]);
        $this->actingAs($manager)->postJson(route('purchases.store'), [
            'purchase_date' => today()->toDateString(),
            'items' => [['product_id' => $product->id, 'batch_no' => 'B', 'quantity' => 10, 'unit_cost' => 60, 'sale_price' => 100]],
        ])->assertOk();
        $this->actingAs($manager)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]], 'payment_method' => 'card',
        ])->assertOk();

        $s = app(ReportService::class)->summary(today(), today());
        $this->assertEquals(200, $s['net_sales']);
        $this->assertEquals(120, $s['cost']);
        $this->assertEquals(80, $s['profit']);

        $inv = app(ReportService::class)->inventoryValue();
        $this->assertEquals(480, $inv['cost']);
        $this->assertEquals(800, $inv['retail']);
    }
}
