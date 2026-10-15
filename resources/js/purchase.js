import axios from 'axios';
import { beep } from './sound';

/** Stock-in (purchase / GRN) form: scan or search medicines, enter batch, expiry, qty and prices. */
export default (config) => ({
    form: {
        supplier_id: '',
        supplier_invoice_no: '',
        purchase_date: config.today,
        discount: 0,
        tax: 0,
        paid_amount: '',
        notes: '',
        update_prices: true,
    },
    lines: [],
    query: '',
    results: [],
    highlighted: 0,
    errors: {},
    message: '',
    busy: false,
    timer: null,
    queue: Promise.resolve(),

    init() {
        this.$nextTick(() => this.$refs.scan?.focus());
    },

    onInput() {
        clearTimeout(this.timer);
        const q = this.query.trim();
        if (q.length < 2 || /^\d{8,}$/.test(q)) { this.results = []; return; }
        this.timer = setTimeout(async () => {
            const { data } = await axios.get(config.routes.search, { params: { q } });
            if (this.query.trim() === q) { this.results = data; this.highlighted = 0; }
        }, 250);
    },

    onKeydown(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); this.highlighted = Math.min(this.highlighted + 1, this.results.length - 1); }
        if (e.key === 'ArrowUp') { e.preventDefault(); this.highlighted = Math.max(this.highlighted - 1, 0); }
        if (e.key === 'Enter') { e.preventDefault(); this.submit(); }
    },

    submit() {
        const code = this.query.trim();
        clearTimeout(this.timer);
        if (!code) return;
        if (this.results.length && !/^\d{8,}$/.test(code) && !code.startsWith('01')) {
            this.pick(this.results[this.highlighted]);
            return;
        }
        // Empty the box now so a quick next scan isn't glued onto this code; look codes up in order.
        this.query = '';
        this.results = [];
        this.queue = this.queue.then(() => this.lookupCode(code));
    },

    pick(p) {
        this.query = '';
        this.results = [];
        this.add(p);
    },

    async lookupCode(code) {
        try {
            const { data } = await axios.get(config.routes.lookup, { params: { code } });
            this.add(data.product, data.scanned_batch, data.scanned_expiry);
        } catch (err) {
            if (err.response?.status === 404) {
                this.message = `No product with code "${code}". Add it under Products first.`;
            } else {
                this.message = err.response?.data?.message || 'Could not reach the server. Check that the POS service is running.';
            }
            beep(false);
        }
    },

    add(p, batch = null, expiry = null) {
        if (!p) return;
        this.lines.push({
            key: Date.now() + Math.random(),
            product_id: p.id,
            name: p.name,
            unit: p.unit,
            batch_no: batch || '',
            expiry_date: expiry || '',
            quantity: 1,
            unit_cost: p.cost ?? 0,
            sale_price: p.price,
        });
        beep(true);
        this.message = '';
        // Jump to the new row's batch box, unless the QR filled it or the next code is already arriving.
        this.$nextTick(() => {
            const rows = this.$refs.lines?.querySelectorAll('input[data-batch]');
            const target = rows?.[rows.length - 1];
            if (target && !batch && !this.query) target.focus(); else this.$refs.scan?.focus();
        });
    },

    remove(i) { this.lines.splice(i, 1); },

    round(n) { return Math.round((n + Number.EPSILON) * 100) / 100; },
    lineTotal(l) { return this.round((parseFloat(l.quantity) || 0) * (parseFloat(l.unit_cost) || 0)); },
    get subtotal() { return this.round(this.lines.reduce((s, l) => s + this.lineTotal(l), 0)); },
    get total() { return this.round(Math.max(0, this.subtotal - (parseFloat(this.form.discount) || 0) + (parseFloat(this.form.tax) || 0))); },
    get units() { return this.lines.reduce((s, l) => s + (parseInt(l.quantity, 10) || 0), 0); },

    margin(l) {
        const c = parseFloat(l.unit_cost) || 0;
        const s = parseFloat(l.sale_price) || 0;
        return s > 0 ? Math.round((s - c) / s * 100) : 0;
    },

    money(n) {
        return `${config.currency} ${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },

    err(key) { return this.errors[key]?.[0]; },

    async save() {
        if (!this.lines.length) { this.message = 'Add at least one medicine.'; return; }
        this.busy = true;
        this.errors = {};
        this.message = '';
        try {
            const payload = {
                ...this.form,
                supplier_id: this.form.supplier_id || null,
                paid_amount: this.form.paid_amount === '' ? null : this.form.paid_amount,
                items: this.lines.map(({ key, name, unit, ...rest }) => ({ ...rest, expiry_date: rest.expiry_date || null })),
            };
            const { data } = await axios.post(config.routes.store, payload, { headers: { Accept: 'application/json' } });
            this.lines = [];
            window.location = data.redirect;
        } catch (err) {
            if (err.response?.status === 422) {
                this.errors = err.response.data.errors || {};
                this.message = 'Please fix the highlighted fields.';
            } else {
                this.message = 'Could not save. Please try again.';
            }
        } finally {
            this.busy = false;
        }
    },
});
