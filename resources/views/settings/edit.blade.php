@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<h2 class="page-title mb-4">Store settings</h2>

<form method="POST" action="{{ route('settings.update') }}" class="space-y-4 max-w-4xl">
    @csrf @method('PUT')
    <div class="card">
        <div class="card-header"><span class="card-title">Pharmacy details (printed on receipts and reports)</span></div>
        <div class="card-body grid md:grid-cols-2 gap-4">
            <div><label class="label">Pharmacy name *</label><input name="store_name" value="{{ old('store_name', $settings['store_name']) }}" class="input" required maxlength="100"></div>
            <div><label class="label">Drug license no</label><input name="store_license_no" value="{{ old('store_license_no', $settings['store_license_no']) }}" class="input" maxlength="100"></div>
            <div class="md:col-span-2"><label class="label">Address</label><input name="store_address" value="{{ old('store_address', $settings['store_address']) }}" class="input" maxlength="250"></div>
            <div><label class="label">Phone</label><input name="store_phone" value="{{ old('store_phone', $settings['store_phone']) }}" class="input" maxlength="50"></div>
            <div><label class="label">Email</label><input type="email" name="store_email" value="{{ old('store_email', $settings['store_email']) }}" class="input" maxlength="100"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Sales & receipts</span></div>
        <div class="card-body grid md:grid-cols-3 gap-4">
            <div><label class="label">Currency symbol *</label><input name="currency_symbol" value="{{ old('currency_symbol', $settings['currency_symbol']) }}" class="input" required maxlength="10"></div>
            <div><label class="label">Sales tax % *</label><input type="number" step="0.01" min="0" max="100" name="tax_rate" value="{{ old('tax_rate', $settings['tax_rate']) }}" class="input" required></div>
            <div><label class="label">Invoice prefix *</label><input name="invoice_prefix" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" class="input" required maxlength="10"></div>
            <div><label class="label">Max cashier discount % *</label><input type="number" step="0.01" min="0" max="100" name="cashier_max_discount" value="{{ old('cashier_max_discount', $settings['cashier_max_discount']) }}" class="input" required><p class="text-xs text-slate-500 mt-1">Managers and admins have no limit.</p></div>
            <div><label class="label">Receipt paper width *</label>
                <select name="receipt_width" class="input">
                    <option value="80" @selected(old('receipt_width', $settings['receipt_width']) == '80')>80 mm (standard thermal)</option>
                    <option value="58" @selected(old('receipt_width', $settings['receipt_width']) == '58')>58 mm (small thermal)</option>
                </select>
            </div>
            <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_print_receipt" value="1" class="checkbox" @checked(old('auto_print_receipt', $settings['auto_print_receipt']) == '1')> Print receipt automatically after each sale</label></div>
            <div class="md:col-span-3"><label class="label">Receipt footer message</label><input name="receipt_footer" value="{{ old('receipt_footer', $settings['receipt_footer']) }}" class="input" maxlength="300"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Alerts & backups</span></div>
        <div class="card-body grid md:grid-cols-3 gap-4">
            <div><label class="label">Near-expiry warning (days) *</label><input type="number" min="1" max="730" name="near_expiry_days" value="{{ old('near_expiry_days', $settings['near_expiry_days']) }}" class="input" required></div>
            <div><label class="label">"New arrival" period (days) *</label><input type="number" min="1" max="90" name="new_arrival_days" value="{{ old('new_arrival_days', $settings['new_arrival_days']) }}" class="input" required></div>
            <div><label class="label">Keep backups for (days) *</label><input type="number" min="3" max="3650" name="backup_keep_days" value="{{ old('backup_keep_days', $settings['backup_keep_days']) }}" class="input" required></div>
        </div>
    </div>

    <div class="flex justify-end"><button class="btn btn-primary btn-lg">Save settings</button></div>
</form>
@endsection
