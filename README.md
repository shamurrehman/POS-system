# Pharmacy POS

A complete point-of-sale and inventory system for a pharmacy. It runs **entirely on one Windows PC** (no internet needed) and works with a **barcode/QR scanner** and a **receipt printer**.

Built with Laravel 12 (PHP 8.2+), SQLite (or MySQL), Tailwind CSS and Alpine.js. All assets are bundled locally.

---

## What it does

| Area | Features |
|---|---|
| **Point of Sale** | Scan barcodes / QR codes or search by brand or generic name. Keyboard shortcuts (F2 scan, F4 cash, F8 clear, F9 pay). Cash / card / mobile payment with change calculation. Bill discount (cashiers capped at a configurable %). Sales tax. Prescription (Rx) warnings. Auto-printed 80 mm or 58 mm receipt. |
| **Batch & expiry tracking** | Every delivery is stored as a batch with its own expiry and cost. Sales automatically take stock **first-expiry-first-out**, and **expired batches can never be sold**. Scanning a GS1 pharma QR/DataMatrix code picks that exact batch. |
| **Inventory** | Full stock list by batch, stock valuation at cost and retail, **complete stock history** (every unit in and out, with who and why). Adjustments and write-offs (expired, damaged, returned to supplier, count corrections) require a reason. |
| **Alerts** | Near expiry, expired on shelf, low stock (per-medicine reorder level), out of stock, **new arrivals**. Count shown on the bell icon. Printable lists. |
| **Stock In / Purchases** | Receive stock from suppliers by scanning (QR fills batch and expiry automatically). Supplier invoice no, discount, tax, partial payments and amounts owed. Printable goods-received note. |
| **Returns** | Partial or full customer returns. Refund is proportional to what was actually paid. Choose whether goods go back on the shelf. |
| **Reports** (print on A4 or export to Excel/CSV) | Business overview · **Daily report** (every invoice, cash per payment method, per cashier) · **Monthly sales history** (day by day) · Yearly (month by month) · Product-wise sales and profit · Stock valuation · Expiry report · Purchase / supplier dues report. |
| **Dashboard** | Today vs yesterday, this month vs last month, profit and margin, stock value, 30-day and 12-month charts, top sellers, expiring soon, low stock. |
| **Security** | Logins with roles (Admin / Manager / Cashier). Brute-force lockout. Encrypted sessions. Server-side price calculation (the browser can't change prices). Full activity/audit log. Daily automatic backups. Server only listens on this PC (127.0.0.1). |

### Roles

| | Cashier | Manager | Admin |
|---|:-:|:-:|:-:|
| POS, own sales, reprint receipts, view medicines and alerts | ✓ | ✓ | ✓ |
| Medicines, stock in, adjustments, returns, suppliers, all reports, profit figures | | ✓ | ✓ |
| Users, settings, activity log, backups | | | ✓ |

---

## Installation (one time)

1. Install **XAMPP** (PHP 8.2 or newer) to `C:\xampp`. Only PHP is needed. You don't have to start Apache or MySQL.
2. Copy this `pharmacy-pos` folder to the PC (e.g. `C:\PharmacyPOS`).
3. Double-click **`install.bat`**. It creates the database, asks you to create the **administrator** account, and puts a **Pharmacy POS** shortcut on the desktop.
4. Double-click **Pharmacy POS** on the desktop. Log in, open **Settings**, and enter the pharmacy name, address, phone, license no, currency, tax %, and so on.

> The time zone is set in `.env` (`APP_TIMEZONE=Asia/Karachi`). Change it if you are elsewhere.

**Start automatically when Windows starts:** press `Win + R`, type `shell:startup`, and copy the *Pharmacy POS* desktop shortcut into that folder.

---

## Recommended: run as a Windows service (most reliable)

Right-click **`setup-xampp-service.bat` → Run as administrator** (one time). It:
* runs the POS on XAMPP's Apache as a **Windows service** that starts with Windows and is **restarted automatically** if it ever stops,
* serves the POS at `http://127.0.0.1:8000` to this PC only,
* checks for port conflicts (the usual reason XAMPP fails to start) and enables PHP OPcache.

After that, don't use the XAMPP Control Panel, and **don't start XAMPP's MySQL**. The POS uses SQLite and doesn't need it. The desktop shortcut detects the service and just opens the POS window.
To undo: `sc stop Apache2.4` then `C:\xampp\apache\bin\httpd.exe -k uninstall -n Apache2.4`. The script keeps backups of `httpd.conf` and `php.ini` (`*.bak-<date>`).

## Daily use

* **Open:** double-click *Pharmacy POS*. It starts the local server (a minimised window you should leave open), makes the day's backup, and opens the POS in its own window.
* **Close:** close the POS window. Run `stop-pos.bat` (or close the minimised server window) to stop the server.
* **First steps:** add *Categories*, then *Suppliers*, then *Medicines* (scan each pack's barcode into the barcode field). Then receive stock with **Stock In**.

---

## Hardware

### Barcode / QR scanner
* Any **USB or Bluetooth scanner in keyboard (HID) mode** works. No driver is needed.
* Make sure it sends **Enter** after each scan. This is the factory default on almost all scanners.
* For the **QR / DataMatrix codes on medicine packs** you need a **2D scanner** (1D laser scanners only read normal barcodes). GS1 codes are decoded automatically: GTIN finds the product, and batch and expiry are filled in during Stock In.
* Products without a barcode get an in-store code automatically. Print labels from *Medicines → Barcode labels*.
* On the POS screen you can scan at any time. Focus jumps to the scan box automatically.

### Receipt printer
* Install the thermal printer's Windows driver so it shows up under *Printers & scanners*.
* In *Settings*, choose **80 mm** or **58 mm** paper.
* The first time the print dialog opens, choose the receipt printer, set **Margins: None** and untick **Headers and footers**. The browser remembers this.
* **Silent printing (no dialog):** make the receipt printer the Windows **default printer** and set `SILENT_PRINT=1` at the top of `start-pos.bat`. With silent printing, A4 reports also go to the default printer, so leave it off if you print reports on another printer.
* **Monthly history:** *Reports → Monthly Report → Print* (A4, with your letterhead).

> The launcher opens the POS in **Microsoft Edge** (built into Windows) with its own profile. In testing, Chrome's normal profile sometimes stopped passing scanner keystrokes to the page after the first scan, while Edge was unaffected. If you open the POS by hand, use Edge or an incognito window.

---

## Backups (important)

* Backups are made **automatically every 7 days (weekly)**, on the first page anyone opens once a backup is due. This works however the POS was started (XAMPP Control Panel, Apache service or `start-pos.bat`).
* **Change how often:** set `BACKUP_EVERY_DAYS` in the `.env` file (1 = daily, 7 = weekly, 30 = monthly).
* They are saved in the folder set by `BACKUP_PATH` in `.env`. On this PC that is **`C:\PharmacyBackups`**, which is on a different physical disk from the database (E:). Without `BACKUP_PATH`, the folder is `storage\app\backups`.
* Old backups are deleted after the number of days set in *Settings*. Admins can also click **Backups → Back up now** and download a copy.
* **Also keep a copy off this PC**, e.g. copy the folder to a USB drive or Google Drive weekly.
* Write `BACKUP_PATH` with forward slashes and no quotes, e.g. `BACKUP_PATH=C:/PharmacyBackups`.

**Restore a backup:** stop Apache (or run `stop-pos.bat`), then copy the chosen `pharmacy-backup-....sqlite` file over `database\database.sqlite` (delete `database.sqlite-wal` / `-shm` if present), then start again.

---

## Security notes

* The server binds to **127.0.0.1**, so other computers on the network cannot reach it.
* Give every staff member their **own login**. The activity log records who did what (logins, failed logins, sales, returns, price changes, stock changes, user changes).
* Passwords must be 8+ characters with letters and numbers. 5 wrong passwords lock that username for 5 minutes.
* The last active admin cannot be demoted or disabled. If you forget the admin password, run this in the folder:
  `C:\xampp\php\php.exe artisan pos:reset-password <username>`
* Use a Windows login password on the PC, and enable BitLocker if you can. The database is a file on disk.
* Keep `APP_DEBUG=false` in `.env`.

---

## More than one counter / MySQL (optional)

SQLite (the default) runs in WAL mode and is reliable for a single PC and years of data. For **several counters on a LAN**:

1. Run the app under XAMPP **Apache** (point a virtual host at the `public` folder) instead of `start-pos.bat`, and restrict it to your LAN in the Apache config.
2. Optionally switch to MySQL. Create a database in XAMPP, then in `.env` set `DB_CONNECTION=mysql`, `DB_DATABASE=pharmacy`, `DB_USERNAME`, `DB_PASSWORD`, and run `php artisan migrate --force`. Backups then use `mysqldump` (`MYSQLDUMP_PATH`).

---

## Useful commands

Run these inside the folder with `C:\xampp\php\php.exe artisan ...`:

| Command | Purpose |
|---|---|
| `pos:create-admin <username>` | Create another administrator |
| `pos:reset-password <username>` | Reset any user's password |
| `pos:backup` | Make a backup now |
| `db:seed --class=CardiacMedicinesSeeder` | Add the Pakistani cardiac medicine catalogue (brands and strengths, no prices). Safe to run again |
| `optimize:clear` then `optimize` | Apply `.env` changes |

### For developers
* `composer install`, `npm install`, `npm run build` (the compiled `public/build` is shipped, so Node is not needed on the shop PC).
* Tests: `php artisan test` (covers FEFO selling, expired-stock blocking, server-side pricing, discounts and tax, returns, purchases, reports, roles, login lockout and backup safety).
* Business logic lives in `app/Services` (`StockService` is the only code that changes stock; every change is written to `stock_movements`).
