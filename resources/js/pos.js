import axios from 'axios';
import { beep } from './sound';

/**
 * Point-of-sale screen.
 * USB / Bluetooth barcode and QR scanners act like a keyboard: they type the code and press Enter.
 * The scan box keeps focus, and any typing outside a form field is redirected into it.
 */
export default (config) => ({
    cart: [],
    query: '',
    results: [],
    highlighted: 0,
    searching: false,
    discountValue: 0,
    discountType: 'amount',
    paymentMethod: 'cash',
    paid: '',
    customer: { name: '', phone: '', doctor: '' },
    notes: '',
    busy: false,
    error: '',
    toast: '',
    lastSale: null,
    searchTimer: null,
    scanQueue: Promise.resolve(),

    init() {
        this.$nextTick(() => this.focusScan());
        window.addEventListener('keydown', (e) => this.globalKeys(e));
        window.addEventListener('beforeunload', (e) => {
            if (this.cart.length) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    },

    // ---------- keyboard ----------
    globalKeys(e) {
        if (e.key === 'F2') { e.preventDefault(); this.focusScan(); return; }
        if (e.key === 'F4') { e.preventDefault(); this.$refs.paid?.focus(); this.$refs.paid?.select(); return; }
        if (e.key === 'F8') { e.preventDefault(); this.clearCart(); return; }
        if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) { e.preventDefault(); this.checkout(); return; }
        if (e.key === 'Escape') { this.results = []; this.lastSale = null; this.focusScan(); return; }

        const tag = (e.target.tagName || '').toLowerCase();
        const typing = ['input', 'textarea', 'select'].includes(tag);
        if (!typing && e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
            this.focusScan();
        }
    },

    focusScan() {
        this.$refs.scan?.focus();
    },

    onQueryInput() {
        clearTimeout(this.searchTimer);
        const q = this.query.trim();
        // Scanners type very fast and finish with Enter; only search for human typing.
        if (q.length < 2 || /^\d{8,}$/.test(q)) {
            this.results = [];
            return;
        }
        this.searchTimer = setTimeout(() => this.search(q), 250);
    },

    async search(q) {
        this.searching = true;
        try {
            const { data } = await axios.get(config.routes.search, { params: { q } });
            if (this.query.trim() === q) {
                this.results = data;
                this.highlighted = 0;
            }
        } finally {
            this.searching = false;
        }
    },

    onScanKeydown(e) {
        if (e.key === 'ArrowDown' && this.results.length) {
            e.preventDefault();
            this.highlighted = Math.min(this.highlighted + 1, this.results.length - 1);
        } else if (e.key === 'ArrowUp' && this.results.length) {
            e.preventDefault();
            this.highlighted = Math.max(this.highlighted - 1, 0);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            this.submitScan();
        }
    },

    submitScan() {
        const code = this.query.trim();
        clearTimeout(this.searchTimer);

        if (!code) {
            return;
        }
        if (this.results.length && !/^\d{8,}$/.test(code) && !code.startsWith('01')) {
            this.pick(this.results[this.highlighted]);
            return;
        }

        // Empty the box at once so the next scan starts clean even while the server is still
        // answering this one (on a slow PC the cashier often scans faster than the lookup).
        // Codes are then looked up one at a time, in the order they were scanned.
        this.query = '';
        this.results = [];
        this.scanQueue = this.scanQueue.then(() => this.lookupCode(code));
    },

    async lookupCode(code) {
        try {
            const { data } = await axios.get(config.routes.lookup, { params: { code } });
            this.addProduct(data.product, data.batch_id);
        } catch (err) {
            if (err.response?.status === 404) {
                // Not a barcode: fall back to a name search.
                const matches = await axios.get(config.routes.search, { params: { q: code } })
                    .then((r) => r.data).catch(() => []);
                if (matches.length === 1) {
                    this.addProduct(matches[0]);
                } else if (!matches.length) {
                    this.flashError(`No product found for "${code}"`);
                } else if (!this.query) {
                    this.query = code;
                    this.results = matches;
                    this.highlighted = 0;
                } else {
                    this.flashError(`More than one medicine matches "${code}". Pick it from the list.`);
                }
            } else if ([401, 419].includes(err.response?.status)) {
                this.flashError('Your session expired. Please reload the page and log in again.');
            } else if (err.response?.data?.message) {
                this.flashError(err.response.data.message);
            } else {
                this.flashError('Could not reach the server. Check that the POS service is running.');
            }
        }
    },

    // ---------- cart ----------
    /** A product chosen from the search list. */
    pick(p) {
        this.query = '';
        this.results = [];
        this.addProduct(p);
    },

    /** Never touches the scan box: the next code may already be arriving in it. */
    addProduct(p, batchId = null) {
        if (!p) return;
        if (p.stock <= 0) {
            this.flashError(`${p.name} has no stock. Receive it with Stock In first (expired stock cannot be sold).`);
            return;
        }

        const line = this.cart.find((l) => l.product_id === p.id);
        if (line) {
            if (line.quantity + 1 > line.stock) {
                this.flashError(`Only ${line.stock} ${p.unit} of ${p.name} in stock.`);
            } else {
                line.quantity++;
                beep(true);
            }
        } else {
            this.cart.unshift({
                product_id: p.id,
                batch_id: batchId,
                name: p.name,
                generic: p.generic_name,
                unit: p.unit,
                price: p.price,
                stock: p.stock,
                rx: p.rx,
                rack: p.rack,
                expiry: p.next_expiry,
                quantity: 1,
            });
            beep(true);
            if (p.rx) this.showToast(`${p.name} requires a prescription.`);
        }

        this.error = '';
        this.focusScan();
    },

    setQty(line, value) {
        let q = parseInt(value, 10);
        if (Number.isNaN(q) || q < 1) q = 1;
        if (q > line.stock) {
            this.flashError(`Only ${line.stock} ${line.unit} of ${line.name} in stock.`);
            q = line.stock;
        }
        line.quantity = q;
    },

    inc(line) { this.setQty(line, line.quantity + 1); },
    dec(line) { if (line.quantity > 1) line.quantity--; },
    remove(index) { this.cart.splice(index, 1); this.focusScan(); },

    clearCart() {
        if (this.cart.length && !window.confirm('Clear the current bill?')) return;
        this.resetSale();
    },

    resetSale() {
        this.cart = [];
        this.discountValue = 0;
        this.discountType = 'amount';
        this.paid = '';
        this.paymentMethod = 'cash';
        this.customer = { name: '', phone: '', doctor: '' };
        this.notes = '';
        this.error = '';
        this.focusScan();
    },

    // ---------- totals (display only; the server recalculates everything) ----------
    round(n) { return Math.round((n + Number.EPSILON) * 100) / 100; },
    get itemCount() { return this.cart.reduce((s, l) => s + l.quantity, 0); },
    get subtotal() { return this.round(this.cart.reduce((s, l) => s + this.round(l.price * l.quantity), 0)); },
    get discount() {
        const v = parseFloat(this.discountValue) || 0;
        const d = this.discountType === 'percent' ? this.subtotal * v / 100 : v;
        return this.round(Math.min(Math.max(d, 0), this.subtotal));
    },
    get tax() { return this.round((this.subtotal - this.discount) * config.taxRate / 100); },
    get total() { return this.round(this.subtotal - this.discount + this.tax); },
    get paidAmount() { return this.paymentMethod === 'cash' ? (parseFloat(this.paid) || 0) : this.total; },
    get change() { return this.round(Math.max(0, this.paidAmount - this.total)); },
    get discountTooHigh() {
        return !config.isManager && this.subtotal > 0 && (this.discount / this.subtotal * 100) > config.maxDiscount + 0.001;
    },
    get canCheckout() {
        return this.cart.length > 0 && !this.busy && !this.discountTooHigh
            && (this.paymentMethod !== 'cash' || this.paid === '' || this.paidAmount + 0.001 >= this.total);
    },

    quickCash(amount) {
        this.paid = amount === 'exact' ? this.total.toFixed(2) : String(amount);
    },

    money(n) {
        return `${config.currency} ${Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },

    // ---------- checkout ----------
    async checkout() {
        if (!this.canCheckout) {
            if (!this.cart.length) this.flashError('The bill is empty. Scan a product first.');
            return;
        }
        this.busy = true;
        this.error = '';
        try {
            const { data } = await axios.post(config.routes.store, {
                items: this.cart.map((l) => ({ product_id: l.product_id, quantity: l.quantity, batch_id: l.batch_id })),
                discount: this.discount,
                payment_method: this.paymentMethod,
                paid_amount: this.paymentMethod === 'cash' ? (this.paid === '' ? this.total : this.paidAmount) : null,
                customer_name: this.customer.name || null,
                customer_phone: this.customer.phone || null,
                doctor_name: this.customer.doctor || null,
                notes: this.notes || null,
            });
            this.lastSale = data;
            beep(true);
            if (config.autoPrint) this.printReceipt(data.receipt_url);
            this.resetSale();
        } catch (err) {
            const errors = err.response?.data?.errors;
            this.flashError(errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || 'Sale failed. Please try again.'));
            if (err.response?.status === 419) this.flashError('Your session expired. Please reload the page and log in again.');
        } finally {
            this.busy = false;
        }
    },

    printReceipt(url) {
        const frame = this.$refs.printFrame;
        frame.onload = () => {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch { window.open(url, '_blank'); }
            this.focusScan();
        };
        frame.src = url + (url.includes('?') ? '&' : '?') + 'embed=1&t=' + Date.now();
    },

    flashError(msg) {
        this.error = msg;
        beep(false);
        clearTimeout(this._errTimer);
        this._errTimer = setTimeout(() => { this.error = ''; }, 6000);
    },

    showToast(msg) {
        this.toast = msg;
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => { this.toast = ''; }, 3500);
    },
});
