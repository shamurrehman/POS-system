<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Catalogue of common cardiovascular brands sold in Pakistan.
 *
 *   php artisan db:seed --class=CardiacMedicinesSeeder
 *
 * Prices and stock are intentionally left at 0: set them on the first Stock In
 * (with "Update product cost & selling prices" ticked). A product with no price
 * cannot be sold. Barcodes are left empty: scan the pack into the product when
 * you first receive it. Safe to run more than once; existing products are skipped.
 */
class CardiacMedicinesSeeder extends Seeder
{
    /** brand => [generic, drug class, form, manufacturer, rx, unit, [strengths]] */
    private const CATALOG = [
        // Antiplatelets
        'Loprin' => ['Aspirin', 'Antiplatelet', 'Tablet', 'Highnoon Laboratories', false, 'strip', ['75mg', '150mg']],
        'Disprin CV' => ['Aspirin', 'Antiplatelet', 'Tablet', 'Reckitt', false, 'strip', ['100mg']],
        'Plavix' => ['Clopidogrel', 'Antiplatelet', 'Tablet', 'Sanofi', true, 'strip', ['75mg']],
        'Brilinta' => ['Ticagrelor', 'Antiplatelet', 'Tablet', 'AstraZeneca', true, 'strip', ['90mg']],

        // Statins / lipid lowering
        'Lipitor' => ['Atorvastatin', 'Statin', 'Tablet', 'Pfizer', true, 'strip', ['10mg', '20mg', '40mg']],
        'Lipiget' => ['Atorvastatin', 'Statin', 'Tablet', 'Getz Pharma', true, 'strip', ['10mg', '20mg', '40mg']],
        'Crestor' => ['Rosuvastatin', 'Statin', 'Tablet', 'AstraZeneca', true, 'strip', ['5mg', '10mg', '20mg']],
        'Rovista' => ['Rosuvastatin', 'Statin', 'Tablet', 'Getz Pharma', true, 'strip', ['5mg', '10mg', '20mg']],
        'Caduet' => ['Amlodipine + Atorvastatin', 'CCB + Statin', 'Tablet', 'Pfizer', true, 'strip', ['5mg/10mg', '10mg/10mg']],

        // Beta blockers
        'Concor' => ['Bisoprolol', 'Beta blocker', 'Tablet', 'Merck', true, 'strip', ['2.5mg', '5mg', '10mg']],
        'Concor Plus' => ['Bisoprolol + Hydrochlorothiazide', 'Beta blocker + Diuretic', 'Tablet', 'Merck', true, 'strip', ['5mg/6.25mg']],
        'Tenormin' => ['Atenolol', 'Beta blocker', 'Tablet', 'AstraZeneca', true, 'strip', ['50mg', '100mg']],
        'Inderal' => ['Propranolol', 'Beta blocker', 'Tablet', 'AstraZeneca', true, 'strip', ['10mg', '40mg']],
        'Dilatrend' => ['Carvedilol', 'Beta blocker', 'Tablet', 'Roche', true, 'strip', ['6.25mg', '25mg']],

        // ACE inhibitors
        'Capoten' => ['Captopril', 'ACE inhibitor', 'Tablet', null, true, 'strip', ['25mg']],
        'Renitec' => ['Enalapril', 'ACE inhibitor', 'Tablet', 'MSD', true, 'strip', ['5mg', '10mg', '20mg']],
        'Zestril' => ['Lisinopril', 'ACE inhibitor', 'Tablet', 'AstraZeneca', true, 'strip', ['5mg', '10mg', '20mg']],
        'Tritace' => ['Ramipril', 'ACE inhibitor', 'Tablet', 'Sanofi', true, 'strip', ['2.5mg', '5mg', '10mg']],
        'Coversyl' => ['Perindopril', 'ACE inhibitor', 'Tablet', 'Servier', true, 'strip', ['5mg', '10mg']],

        // ARBs / ARNI
        'Cozaar' => ['Losartan', 'ARB', 'Tablet', 'MSD', true, 'strip', ['50mg', '100mg']],
        'Hyzaar' => ['Losartan + Hydrochlorothiazide', 'ARB + Diuretic', 'Tablet', 'MSD', true, 'strip', ['50mg/12.5mg']],
        'Diovan' => ['Valsartan', 'ARB', 'Tablet', 'Novartis', true, 'strip', ['80mg', '160mg']],
        'Co-Diovan' => ['Valsartan + Hydrochlorothiazide', 'ARB + Diuretic', 'Tablet', 'Novartis', true, 'strip', ['80mg/12.5mg', '160mg/12.5mg']],
        'Exforge' => ['Amlodipine + Valsartan', 'CCB + ARB', 'Tablet', 'Novartis', true, 'strip', ['5mg/80mg', '5mg/160mg', '10mg/160mg']],
        'Micardis' => ['Telmisartan', 'ARB', 'Tablet', 'Boehringer Ingelheim', true, 'strip', ['40mg', '80mg']],
        'Micardis Plus' => ['Telmisartan + Hydrochlorothiazide', 'ARB + Diuretic', 'Tablet', 'Boehringer Ingelheim', true, 'strip', ['40mg/12.5mg', '80mg/12.5mg']],
        'Aprovel' => ['Irbesartan', 'ARB', 'Tablet', 'Sanofi', true, 'strip', ['150mg', '300mg']],
        'Entresto' => ['Sacubitril + Valsartan', 'ARNI (heart failure)', 'Tablet', 'Novartis', true, 'strip', ['50mg', '100mg', '200mg']],

        // Calcium channel blockers
        'Norvasc' => ['Amlodipine', 'Calcium channel blocker', 'Tablet', 'Pfizer', true, 'strip', ['5mg', '10mg']],
        'Adalat Retard' => ['Nifedipine', 'Calcium channel blocker', 'Tablet', 'Bayer', true, 'strip', ['20mg']],
        'Dilzem' => ['Diltiazem', 'Calcium channel blocker', 'Tablet', null, true, 'strip', ['60mg']],

        // Diuretics / aldosterone antagonists
        'Lasix' => ['Furosemide', 'Loop diuretic', 'Tablet', 'Sanofi', true, 'strip', ['40mg']],
        'Lasix Injection' => ['Furosemide', 'Loop diuretic', 'Injection', 'Sanofi', true, 'pcs', ['20mg/2ml']],
        'Aldactone' => ['Spironolactone', 'Aldosterone antagonist', 'Tablet', 'Pfizer', true, 'strip', ['25mg', '100mg']],
        'Inspra' => ['Eplerenone', 'Aldosterone antagonist', 'Tablet', 'Pfizer', true, 'strip', ['25mg', '50mg']],
        'Natrilix SR' => ['Indapamide', 'Thiazide-like diuretic', 'Tablet', 'Servier', true, 'strip', ['1.5mg']],

        // Anti-anginal
        'Angised' => ['Glyceryl trinitrate', 'Nitrate (anti-anginal)', 'Tablet', 'GSK', true, 'bottle', ['0.5mg']],
        'Isordil' => ['Isosorbide dinitrate', 'Nitrate (anti-anginal)', 'Tablet', null, true, 'strip', ['5mg', '10mg']],
        'Vastarel MR' => ['Trimetazidine', 'Anti-anginal', 'Tablet', 'Servier', true, 'strip', ['35mg']],
        'Procoralan' => ['Ivabradine', 'Anti-anginal / heart failure', 'Tablet', 'Servier', true, 'strip', ['5mg', '7.5mg']],

        // Anticoagulants
        'Xarelto' => ['Rivaroxaban', 'Anticoagulant', 'Tablet', 'Bayer', true, 'strip', ['10mg', '15mg', '20mg']],
        'Pradaxa' => ['Dabigatran', 'Anticoagulant', 'Capsule', 'Boehringer Ingelheim', true, 'strip', ['110mg', '150mg']],
        'Eliquis' => ['Apixaban', 'Anticoagulant', 'Tablet', 'Pfizer', true, 'strip', ['2.5mg', '5mg']],
        'Clexane' => ['Enoxaparin', 'Anticoagulant (LMWH)', 'Injection', 'Sanofi', true, 'pcs', ['40mg/0.4ml', '60mg/0.6ml']],

        // Anti-arrhythmic / heart failure
        'Cordarone' => ['Amiodarone', 'Anti-arrhythmic', 'Tablet', 'Sanofi', true, 'strip', ['200mg']],
        'Lanoxin' => ['Digoxin', 'Cardiac glycoside', 'Tablet', 'GSK', true, 'strip', ['0.25mg']],
        'Forxiga' => ['Dapagliflozin', 'SGLT2 inhibitor (heart failure / diabetes)', 'Tablet', 'AstraZeneca', true, 'strip', ['10mg']],
        'Jardiance' => ['Empagliflozin', 'SGLT2 inhibitor (heart failure / diabetes)', 'Tablet', 'Boehringer Ingelheim', true, 'strip', ['10mg', '25mg']],
    ];

    public function run(): void
    {
        $categoryId = Category::firstOrCreate(['name' => 'Cardiovascular'])->id;
        $added = 0;

        foreach (self::CATALOG as $brand => [$generic, $class, $form, $maker, $rx, $unit, $strengths]) {
            foreach ($strengths as $strength) {
                $exists = Product::withTrashed()->where('name', $brand)
                    ->where(fn ($q) => $q->where('strength', $strength)->orWhereNull('strength'))
                    ->exists();
                if ($exists) {
                    continue;
                }

                Product::create([
                    'name' => $brand,
                    'generic_name' => $generic,
                    'strength' => $strength,
                    'dosage_form' => $form,
                    'category_id' => $categoryId,
                    'manufacturer' => $maker,
                    'unit' => $unit,
                    'purchase_price' => 0,
                    'sale_price' => 0,
                    'reorder_level' => 10,
                    'requires_prescription' => $rx,
                    'is_active' => true,
                    'description' => $class,
                ]);
                $added++;
            }
        }

        $this->command?->info("Cardiac catalogue: {$added} medicines added (prices are set on first Stock In).");
    }
}
