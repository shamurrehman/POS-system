<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Production seed: only reference data. Create the first admin with `php artisan pos:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    public const CATEGORIES = [
        'Analgesics & Pain Relief', 'Antibiotics', 'Antacids & GI', 'Antihistamines & Allergy', 'Cough & Cold',
        'Cardiovascular', 'Diabetes', 'Vitamins & Supplements', 'Dermatology', 'Eye & Ear Care',
        'Respiratory', 'Baby Care', 'Women\'s Health', 'Surgical & First Aid', 'Personal Care', 'Medical Devices',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
