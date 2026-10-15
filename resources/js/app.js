import './bootstrap';
import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import JsBarcode from 'jsbarcode';
import pos from './pos';
import purchaseForm from './purchase';

window.Chart = Chart;
window.JsBarcode = JsBarcode;
window.Alpine = Alpine;

Alpine.data('pos', pos);
Alpine.data('purchaseForm', purchaseForm);

// Confirm helper for destructive forms: <form data-confirm="Are you sure?">
document.addEventListener('submit', (e) => {
    const msg = e.target?.dataset?.confirm;
    if (msg && !window.confirm(msg)) {
        e.preventDefault();
    }
});

Alpine.start();
