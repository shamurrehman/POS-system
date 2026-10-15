<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleFlowTest extends TestCase
{
    use RefreshDatabase;

    private function stockUp(Product $product, string $batch, int $qty, ?string $expiry, float $cost = 10): Batch
    {
        return app(StockService::class)->receive($product, [
            'batch_no' => $batch, 'expiry_date' => $expiry, 'quantity' => $qty, 'purchase_price' => $cost,
        ], 'opening');
    }

    public function test_sale_uses_first_expiry_first_out_and_skips_expired_batches(): void
    {
        $cashier = User::factory()->create();
        $product = Product::factory()->create(['sale_price' => 20]);
        $expired = $this->stockUp($product, 'OLD', 50, today()->subDay()->toDateString(), 5);
        $late = $this->stockUp($product, 'LATE', 10, today()->addYear()->toDateString(), 12);
        $soon = $this->stockUp($product, 'SOON', 3, today()->addMonth()->toDateString(), 8);

        $this->actingAs($cashier)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'cash',
            'paid_amount' => 100,
        ])->assertOk()->assertJson(['total' => 100, 'change' => 0]);

        $this->assertSame(50, $expired->fresh()->quantity, 'expired stock must never be sold');
        $this->assertSame(0, $soon->fresh()->quantity);
        $this->assertSame(8, $late->fresh()->quantity);
        $this->assertSame(58, $product->fresh()->stock_quantity);

        $sale = Sale::with('items')->sole();
        $this->assertCount(2, $sale->items);
        $this->assertEquals(3 * 8 + 2 * 12, (float) $sale->cost_total);
        $this->assertSame(-5, (int) StockMovement::where('type', 'sale')->sum('quantity'));
    }

    public function test_prices_come_from_the_server_not_the_browser(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sale_price' => 50]);
        $this->stockUp($product, 'B1', 10, null);

        $this->actingAs($user)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 0.01, 'unit_price' => 0.01]],
            'payment_method' => 'card',
        ])->assertOk();

        $this->assertEquals(100, (float) Sale::sole()->total);
    }

    public function test_products_without_a_price_cannot_be_sold(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sale_price' => 0]);
        $this->stockUp($product, 'B1', 10, null);

        $this->actingAs($user)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'card',
        ])->assertStatus(422)->assertJsonPath('errors.items.0', fn ($m) => str_contains($m, 'No selling price'));
    }

    public function test_cardiac_catalogue_seeds_once(): void
    {
        $this->seed(\Database\Seeders\CardiacMedicinesSeeder::class);
        $count = Product::count();
        $this->assertGreaterThan(80, $count);
        $this->seed(\Database\Seeders\CardiacMedicinesSeeder::class);
        $this->assertSame($count, Product::count(), 'running twice must not duplicate');
        $this->assertTrue(Product::where('name', 'Entresto')->where('strength', '100mg')->exists());
    }

    public function test_cannot_sell_more_than_available_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->stockUp($product, 'B1', 2, null);

        $this->actingAs($user)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_method' => 'card',
        ])->assertStatus(422)->assertJsonPath('errors.items.0', fn ($m) => str_contains($m, 'Not enough stock'));

        $this->assertSame(0, Sale::count());
        $this->assertSame(2, $product->fresh()->stock_quantity, 'failed sale must roll back completely');
    }

    public function test_tax_discount_and_cashier_discount_limit(): void
    {
        Setting::putMany(['tax_rate' => '10', 'cashier_max_discount' => '5']);
        $cashier = User::factory()->create();
        $manager = User::factory()->role('manager')->create();
        $product = Product::factory()->create(['sale_price' => 100]);
        $this->stockUp($product, 'B1', 10, null);

        $this->actingAs($cashier)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount' => 20, 'payment_method' => 'cash', 'paid_amount' => 200,
        ])->assertStatus(422);

        $this->actingAs($manager)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount' => 20, 'payment_method' => 'cash', 'paid_amount' => 100,
        ])->assertOk()->assertJson(['total' => 88, 'change' => 12]); // (100-20) + 10% tax
    }

    public function test_cash_received_must_cover_the_total(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sale_price' => 100]);
        $this->stockUp($product, 'B1', 10, null);

        $this->actingAs($user)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash', 'paid_amount' => 50,
        ])->assertStatus(422);
    }

    public function test_scanning_a_gs1_qr_code_finds_the_product_and_batch(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['barcode' => '8901234567890']);
        $batch = $this->stockUp($product, 'AB123', 5, '2030-01-31');

        $this->actingAs($user)->getJson(route('pos.lookup', ['code' => '0000000000000']))->assertNotFound();
        $this->actingAs($user)->getJson(route('pos.lookup', ['code' => "010890123456789017300131\x1D10AB123"]))
            ->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('batch_id', $batch->id)
            ->assertJsonPath('scanned_expiry', '2030-01-31');
    }

    public function test_scanning_an_expired_pack_is_blocked(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['barcode' => '8901234567890']);
        $this->stockUp($product, 'OLD1', 5, today()->subDay()->toDateString());
        $this->stockUp($product, 'NEW1', 5, today()->addYear()->toDateString());

        $old = today()->subMonth();
        $this->actingAs($user)->getJson(route('pos.lookup', ['code' => '0108901234567890'.'17'.$old->format('ymd')."\x1D10ANY"]))
            ->assertStatus(422)->assertJsonPath('expired', true);
        // Expiry known only from our own batch record (the code carries batch but no expiry).
        $this->actingAs($user)->getJson(route('pos.lookup', ['code' => "0108901234567890\x1D10OLD1"]))
            ->assertStatus(422);
        $this->actingAs($user)->getJson(route('pos.lookup', ['code' => "0108901234567890\x1D10NEW1"]))
            ->assertOk()->assertJsonPath('product.id', $product->id);
    }

    public function test_every_pack_of_a_product_saved_from_a_pack_code_scans(): void
    {
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Packcode', 'is_active' => 1, 'barcode' => "]d2010890123456789017300131\x1D10LOT1\x1D21SERIAL-A",
            'unit' => 'pcs', 'purchase_price' => 1, 'sale_price' => 2, 'reorder_level' => 0,
        ])->assertRedirect();
        $product = Product::where('name', 'Packcode')->sole();
        $this->assertSame('8901234567890', $product->barcode);

        // A different pack: other batch, other serial.
        $this->actingAs($admin)->getJson(route('pos.lookup', ['code' => "010890123456789017310228\x1D10LOT2\x1D21SERIAL-B"]))
            ->assertOk()->assertJsonPath('product.id', $product->id);

        // The same code written as GTIN-14 is a duplicate.
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Dupe', 'barcode' => '08901234567890',
            'unit' => 'pcs', 'purchase_price' => 1, 'sale_price' => 2, 'reorder_level' => 0,
        ])->assertSessionHasErrors('barcode');
    }

    public function test_labels_give_a_product_without_barcode_an_in_store_code(): void
    {
        $admin = User::factory()->role('admin')->create();
        $product = Product::factory()->create(['barcode' => null]);

        $this->actingAs($admin)->get(route('products.labels', ['product_id' => $product->id, 'copies' => 2]))->assertOk();
        $code = $product->fresh()->barcode;
        $this->assertMatchesRegularExpression('/^200\d{10}$/', $code);
        $this->actingAs($admin)->getJson(route('pos.lookup', ['code' => $code]))->assertOk()->assertJsonPath('product.id', $product->id);
    }

    public function test_return_restocks_and_refunds_proportionally(): void
    {
        $manager = User::factory()->role('manager')->create();
        $product = Product::factory()->create(['sale_price' => 100]);
        $this->stockUp($product, 'B1', 10, null, 60);

        $this->actingAs($manager)->postJson(route('pos.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'discount' => 40, 'payment_method' => 'card',
        ])->assertOk();
        $sale = Sale::with('items')->sole();
        $item = $sale->items->first();

        $this->actingAs($manager)->post(route('sales.return.store', $sale), [
            'quantities' => [$item->id => 1], 'restock' => 1, 'reason' => 'test',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertEquals(90, (float) $sale->returned_amount); // paid 360 for 4 → 90 each
        $this->assertSame('partially_returned', $sale->status);
        $this->assertSame(7, $product->fresh()->stock_quantity);

        // Cannot return more than was sold.
        $this->actingAs($manager)->post(route('sales.return.store', $sale), [
            'quantities' => [$item->id => 4], 'restock' => 1,
        ])->assertSessionHasErrors('return');

        $this->actingAs($manager)->post(route('sales.return.store', $sale), [
            'quantities' => [$item->id => 3], 'restock' => 0,
        ]);
        $this->assertSame('returned', $sale->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock_quantity, 'unticked restock keeps damaged goods off the shelf');
    }

    public function test_cashier_cannot_process_returns_or_see_others_invoices(): void
    {
        $cashier = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        $this->stockUp($product, 'B1', 10, null);
        $this->actingAs($other)->postJson(route('pos.store'), ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'card'])->assertOk();
        $sale = Sale::sole();

        $this->actingAs($cashier)->get(route('sales.show', $sale))->assertForbidden();
        $this->actingAs($cashier)->get(route('sales.return', $sale))->assertForbidden();
        $this->actingAs($other)->get(route('sales.receipt', $sale))->assertOk()->assertSee($sale->invoice_no);
    }
}
