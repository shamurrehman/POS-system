<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Demo data for trying the system out: `php artisan db:seed --class=DemoSeeder`.
 * Never run this on the live database. It creates users with a known password.
 */
class DemoSeeder extends Seeder
{
    public function run(PurchaseService $purchases, SaleService $sales): void
    {
        $this->call(DatabaseSeeder::class);

        Setting::putMany(['store_name' => 'Dr. Roohul Amin Pharmacy', 'store_address' => 'Opposite Telenor Tower, Main Bazar, Buner Road, Rustam', 'store_phone' => '03125501651']);

        $admin = User::firstOrCreate(['username' => 'admin'], ['name' => 'Admin', 'password' => 'Admin12345', 'role' => 'admin']);
        $manager = User::firstOrCreate(['username' => 'manager'], ['name' => 'Store Manager', 'password' => 'Manager12345', 'role' => 'manager']);
        $cashier = User::firstOrCreate(['username' => 'cashier'], ['name' => 'Counter Cashier', 'password' => 'Cashier12345', 'role' => 'cashier']);

        $suppliers = collect([
            ['name' => 'Al-Shifa Distributors', 'phone' => '042-1111111', 'contact_person' => 'Imran'],
            ['name' => 'MediLink Pharma Traders', 'phone' => '042-2222222', 'contact_person' => 'Sana'],
            ['name' => 'HealthLine Wholesale', 'phone' => '042-3333333', 'contact_person' => 'Bilal'],
        ])->map(fn ($s) => Supplier::firstOrCreate(['name' => $s['name']], $s + ['is_active' => true]));

        $cat = fn (string $name) => Category::where('name', $name)->value('id');
        $catalog = [
            // name, generic, strength, form, category, unit, cost, price, reorder, rx
            ['Panadol', 'Paracetamol', '500mg', 'Tablet', 'Analgesics & Pain Relief', 'strip', 28, 35, 40, false],
            ['Panadol Extra', 'Paracetamol + Caffeine', '500mg/65mg', 'Tablet', 'Analgesics & Pain Relief', 'strip', 45, 56, 30, false],
            ['Brufen', 'Ibuprofen', '400mg', 'Tablet', 'Analgesics & Pain Relief', 'strip', 52, 65, 25, false],
            ['Ponstan', 'Mefenamic Acid', '250mg', 'Capsule', 'Analgesics & Pain Relief', 'strip', 60, 76, 20, false],
            ['Disprin', 'Aspirin', '300mg', 'Tablet', 'Analgesics & Pain Relief', 'strip', 18, 24, 30, false],
            ['Augmentin', 'Amoxicillin + Clavulanate', '625mg', 'Tablet', 'Antibiotics', 'strip', 310, 380, 10, true],
            ['Amoxil', 'Amoxicillin', '500mg', 'Capsule', 'Antibiotics', 'strip', 120, 150, 12, true],
            ['Flagyl', 'Metronidazole', '400mg', 'Tablet', 'Antibiotics', 'strip', 40, 52, 15, true],
            ['Ciproxin', 'Ciprofloxacin', '500mg', 'Tablet', 'Antibiotics', 'strip', 190, 240, 10, true],
            ['Azomax', 'Azithromycin', '500mg', 'Tablet', 'Antibiotics', 'strip', 250, 315, 8, true],
            ['Risek', 'Omeprazole', '20mg', 'Capsule', 'Antacids & GI', 'strip', 140, 175, 15, false],
            ['Gaviscon', 'Sodium Alginate', '150ml', 'Suspension', 'Antacids & GI', 'bottle', 210, 265, 8, false],
            ['ORS Sachet', 'Oral Rehydration Salts', '', 'Sachet', 'Antacids & GI', 'pcs', 12, 18, 50, false],
            ['Motilium', 'Domperidone', '10mg', 'Tablet', 'Antacids & GI', 'strip', 70, 90, 10, false],
            ['Softin', 'Loratadine', '10mg', 'Tablet', 'Antihistamines & Allergy', 'strip', 85, 110, 12, false],
            ['Avil', 'Pheniramine', '25mg', 'Tablet', 'Antihistamines & Allergy', 'strip', 22, 30, 15, false],
            ['Hydryllin', 'Diphenhydramine', '120ml', 'Syrup', 'Cough & Cold', 'bottle', 95, 120, 10, false],
            ['Arinac', 'Ibuprofen + Pseudoephedrine', '', 'Tablet', 'Cough & Cold', 'strip', 75, 95, 15, false],
            ['Ventolin Inhaler', 'Salbutamol', '100mcg', 'Inhaler', 'Respiratory', 'pcs', 420, 520, 5, true],
            ['Concor', 'Bisoprolol', '5mg', 'Tablet', 'Cardiovascular', 'strip', 230, 290, 8, true],
            ['Norvasc', 'Amlodipine', '5mg', 'Tablet', 'Cardiovascular', 'strip', 260, 330, 8, true],
            ['Glucophage', 'Metformin', '500mg', 'Tablet', 'Diabetes', 'strip', 90, 115, 12, true],
            ['Surbex Z', 'Multivitamin + Zinc', '', 'Tablet', 'Vitamins & Supplements', 'bottle', 380, 470, 6, false],
            ['Cac-1000 Plus', 'Calcium + Vitamin C', '', 'Tablet', 'Vitamins & Supplements', 'tube', 300, 375, 6, false],
            ['Polyfax', 'Polymyxin B + Bacitracin', '20g', 'Ointment', 'Dermatology', 'tube', 160, 200, 6, false],
            ['Dettol Antiseptic', 'Chloroxylenol', '250ml', 'Solution', 'Surgical & First Aid', 'bottle', 230, 285, 6, false],
            ['Crepe Bandage', '', '4 inch', 'Device', 'Surgical & First Aid', 'pcs', 70, 95, 10, false],
            ['Johnson Baby Powder', '', '200g', 'Powder', 'Baby Care', 'pcs', 310, 380, 5, false],
            ['Digital Thermometer', '', '', 'Device', 'Medical Devices', 'pcs', 350, 480, 3, false],
            ['Glucometer Strips', '', '50 strips', 'Device', 'Medical Devices', 'box', 1900, 2350, 3, false],
        ];

        $products = collect($catalog)->map(function ($p, $i) use ($cat) {
            return Product::firstOrCreate(['name' => $p[0]], [
                'generic_name' => $p[1] ?: null, 'strength' => $p[2] ?: null, 'dosage_form' => $p[3],
                'category_id' => $cat($p[4]), 'unit' => $p[5], 'purchase_price' => $p[6], 'sale_price' => $p[7],
                'reorder_level' => $p[8], 'requires_prescription' => $p[9], 'is_active' => true,
                'manufacturer' => ['GSK', 'Abbott', 'Getz Pharma', 'Searle', 'Sami Pharma', 'Martin Dow'][$i % 6],
                'barcode' => '89'.str_pad((string) (100000000 + $i * 7919), 11, '0', STR_PAD_LEFT),
                'rack_location' => chr(65 + intdiv($i, 6)).'-'.($i % 6 + 1),
            ]);
        });

        mt_srand(42);
        $realToday = today();
        $start = $realToday->copy()->subDays(75);
        $names = ['Ahmed', 'Fatima', 'Ali', 'Ayesha', 'Usman', 'Zainab', 'Hassan', 'Maryam', 'Omar', 'Hira'];

        // Opening deliveries 75 days ago, then restocks every ~2 weeks.
        Auth::setUser($manager);
        foreach ([75, 60, 45, 30, 15, 3] as $daysAgo) {
            Carbon::setTestNow($realToday->copy()->subDays($daysAgo)->setTime(10, 0));
            $lines = $products->shuffle()->take($daysAgo === 75 ? $products->count() : 12)->map(function (Product $p) use ($daysAgo, $realToday) {
                // A few batches expire soon or have already expired so the alerts have something to show.
                $expiry = match (true) {
                    $p->id % 11 === 0 => $realToday->copy()->addDays(20),
                    $p->id % 7 === 0 => $realToday->copy()->addDays(60),
                    $p->id % 13 === 0 && $daysAgo === 75 => $realToday->copy()->subDays(5),
                    default => $realToday->copy()->addMonths(mt_rand(8, 30)),
                };

                return [
                    'product_id' => $p->id,
                    'batch_no' => strtoupper(substr($p->name, 0, 3)).'-'.today()->format('ymd').mt_rand(10, 99),
                    'expiry_date' => $expiry->toDateString(),
                    'quantity' => $daysAgo === 75 ? $p->reorder_level * mt_rand(3, 6) : $p->reorder_level * mt_rand(1, 3),
                    'unit_cost' => (float) $p->purchase_price,
                    'sale_price' => (float) $p->sale_price,
                ];
            })->values()->all();

            $purchases->create([
                'supplier_id' => $suppliers->random()->id,
                'supplier_invoice_no' => 'SI-'.mt_rand(1000, 9999),
                'purchase_date' => today()->toDateString(),
                'items' => $lines,
                'paid_amount' => $daysAgo === 3 ? 0 : null, // latest delivery still unpaid
            ], $manager);
        }

        // Daily sales.
        for ($d = $start->copy()->addDay(); $d->lte($realToday); $d->addDay()) {
            $count = mt_rand(8, 22) + ($d->isWeekend() ? 5 : 0);
            for ($n = 0; $n < $count; $n++) {
                Carbon::setTestNow($d->copy()->setTime(mt_rand(9, 21), mt_rand(0, 59), mt_rand(0, 59)));
                $seller = mt_rand(0, 3) ? $cashier : $manager;
                Auth::setUser($seller);
                $items = $products->random(mt_rand(1, 4))->map(fn ($p) => ['product_id' => $p->id, 'quantity' => mt_rand(1, 3)])->values()->all();
                try {
                    $sale = $sales->create([
                        'items' => $items,
                        'payment_method' => ['cash', 'cash', 'cash', 'card', 'mobile'][mt_rand(0, 4)],
                        'discount' => mt_rand(0, 6) === 0 ? 10 : 0,
                        'customer_name' => mt_rand(0, 3) === 0 ? $names[mt_rand(0, count($names) - 1)] : null,
                    ], $seller);
                    if (mt_rand(0, 60) === 0) {
                        Auth::setUser($manager);
                        $item = $sale->items()->first();
                        $sales->processReturn($sale, [$item->id => 1], true, 'Customer changed mind', $manager);
                    }
                } catch (ValidationException) {
                    // Out of stock: skip, just like a real counter would.
                }
            }
        }

        Carbon::setTestNow();
        Auth::logout();

        $this->command?->info('Demo data ready: '.Sale::count().' sales. Logins: admin / Admin12345, manager / Manager12345, cashier / Cashier12345');
    }
}
