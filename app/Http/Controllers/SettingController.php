<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('settings.edit', ['settings' => Setting::allSettings()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'store_address' => ['nullable', 'string', 'max:250'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'store_email' => ['nullable', 'email', 'max:100'],
            'store_license_no' => ['nullable', 'string', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'near_expiry_days' => ['required', 'integer', 'min:1', 'max:730'],
            'new_arrival_days' => ['required', 'integer', 'min:1', 'max:90'],
            'invoice_prefix' => ['required', 'string', 'max:10', 'alpha_dash'],
            'receipt_footer' => ['nullable', 'string', 'max:300'],
            'receipt_width' => ['required', 'in:58,80'],
            'cashier_max_discount' => ['required', 'numeric', 'min:0', 'max:100'],
            'backup_keep_days' => ['required', 'integer', 'min:3', 'max:3650'],
        ]);
        $data['auto_print_receipt'] = $request->boolean('auto_print_receipt') ? '1' : '0';

        Setting::putMany(array_map(fn ($v) => $v ?? '', $data));
        ActivityLog::record('settings.updated', 'Updated store settings');

        return back()->with('success', 'Settings saved.');
    }
}
