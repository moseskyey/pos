# DukaPOS

**Multi-branch Point of Sale & Inventory for Tanzanian retail.** Supermarkets, mini-marts, pharmacies,
hardware shops and boutiques. It is built with Laravel, Livewire and Bootstrap 5, in English and Kiswahili, and
priced in TZS with 18% VAT, M-Pesa / Mixx by Yas / Airtel Money / HaloPesa, and TRA-ready receipts.

- Runs any number of branches from one install, each with its own stock, tills, shifts and document numbers.
- A fast, keyboard-first POS screen that works with barcode scanners, split payments, hold/resume and manager PINs.
- A full stock ledger: every quantity change is recorded, with FEFO batches and expiry, transfers and stock takes.
- The numbers an owner needs: dashboard, 18 reports, VAT return helper, profit & loss, and debt aging.

---

## Download

```bash
git clone https://github.com/moseskyey/pos.git dukapos
cd dukapos
```

Or download the ZIP from GitHub (**Code → Download ZIP**) and extract it.

## Quick start (local, SQLite: no database server needed)

Requirements: **PHP 8.4+** (extensions: `pdo_sqlite` or `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`
optional), **Composer 2**, **Node.js 22+** (or 20.19+).

```bash
composer run setup      # install PHP + JS deps, create .env, key, SQLite DB, migrate + demo data, build assets
composer run dev        # starts the web server, queue worker, logs and Vite together
```

Open <http://localhost:8000> and sign in with one of the demo accounts (password **`password`**):

| Role | Email | PIN |
|---|---|---|
| Owner | `owner@dukapos.test` | 1234 |
| Manager (Kariakoo) | `manager@dukapos.test` | 4321 |
| Manager (Mbezi) | `manager.mbezi@dukapos.test` | 5678 |
| Cashier (Kariakoo) | `cashier@dukapos.test` | 1111 |
| Cashier (Mbezi) | `cashier.mbezi@dukapos.test` | 2222 |
| Storekeeper | `store@dukapos.test` | – |
| Accountant | `accounts@dukapos.test` | – |

The demo seeder creates two branches (DSM01 Kariakoo, DSM02 Mbezi), about 60 Tanzanian products, customers
with debts, suppliers, purchase orders and 30 days of sales. Demo data is only seeded when `APP_ENV` is
`local`, `testing` or `demo`.

### Manual setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # or configure MySQL in .env
php artisan migrate --seed
php artisan storage:link
npm install && npm run build         # compiled assets are not committed
php artisan serve
php artisan queue:work               # exports, SMS and notifications run on the queue
```

To use MySQL 8 / MariaDB instead of SQLite, set `DB_CONNECTION=mysql` and the `DB_*` values in `.env`.

## Features

**Foundation.**
- Branches with tills/registers.
- Users can sign in with an email or a phone number, set a PIN, and enable TOTP two-factor auth.
- Roles & permissions come with editable defaults for Owner, Manager, Cashier, Storekeeper and Accountant.
- Manager PIN overrides.
- An activity log records every sensitive action.
- A settings module, dark mode, and an English/Kiswahili switcher.

**Catalog.**
- Categories (two levels), brands, and units with conversions (carton ↔ piece).
- Retail and wholesale prices, with a wholesale price applied from a set quantity.
- Multiple barcodes per product, EAN-13 generation, scale (weighed) barcodes, and variants (size/colour).
- Price history, bulk price updates, Excel import/export with preview, and barcode label printing.

**Inventory.**
- An append-only stock ledger and per-branch stock levels.
- Opening stock, and adjustments with approval.
- Branch transfers: request → approve → dispatch → receive, with discrepancies recorded.
- Stock takes with frozen expected quantities.
- Batches and expiry, sold first-expiry-first-out (FEFO).
- Low-stock and expiry alerts.

**POS & shifts.**
- Shifts with an opening float, cash in/out, X/Z reports, and a denomination counter.
- Keyboard shortcuts (F2–F10, Ctrl+Enter, Esc, +/−, Del, ?) and a scanner-friendly search.
- Hold and resume sales.
- Line and cart discounts, split payments, change calculation, and credit sales within limits.
- Mobile money STK push, and an idle lock screen that needs a PIN to unlock.
- Receipts on 58/80 mm thermal paper with a QR verification code, plus A4 tax invoices and delivery notes.
- The cart is backed up locally in the browser.

**Sales & customers.**
- Sales list and detail, same-day voids, and returns/refunds with restock or damaged handling.
- Quotations that convert to sales in one click.
- Layaway with deposits.
- Customer accounts:
  - credit ledger (deni), with FIFO payment allocation
  - statements as PDF
  - SMS reminders
  - loyalty points
  - store credit

**Purchases & expenses.**
- Suppliers and purchase orders (PDF).
- Goods received notes that update the moving average cost.
- Supplier bills, payments and aging.
- Returns to supplier.
- Reorder suggestions that turn into draft POs.
- Expenses with receipts, plus recurring expenses such as rent, salaries and LUKU.

**Dashboard & reports.**
- A live dashboard with KPIs, charts, top products, low stock and debtors.
- 18 reports:
  - sales summary; by product/category/brand; by cashier/branch/method; by hour
  - gross profit; P&L; VAT (TRA); expenses
  - stock valuation, movement, low/dead stock and expiry
  - debtors aging; supplier aging; purchases by supplier
  - shift reconciliation; discounts; returns & voids
- Every report can be exported to Excel or PDF as a queued job.

**Integrations & hardening.**
- FastLipa mobile money uses two-phase initiation and idempotent references. Every webhook is confirmed against FastLipa's status API before it counts, and callbacks are logged and replayable. A sweep checks stuck payments every minute and keeps re-checking "failed" ones, because FastLipa can report a payment failed and then completed; late payments alert the cashier and managers.
- Beem SMS goes through a queued, retrying job.
- A TRA VFD/EFD `FiscalDevice` extension point.
- Backups through spatie/laravel-backup: nightly database backups, plus download and delete from the UI.
- Security headers, rate-limited logins and PINs, and encrypted API keys.
- Lazy loading is blocked outside production.

## Configuration

Most configuration is in the app under **Settings**: business profile, TIN/VRN, currency, VAT, receipts, POS
rules (negative stock, maximum discount, below-cost sales, rounding, lock timeout), payment methods, SMS and
document number prefixes.

| What | Where |
|---|---|
| FastLipa API key, URL, webhook secret | Settings → Payment methods (stored encrypted). Callback URL: `https://your-domain/api/payments/callback/fastlipa` |
| Beem SMS key, secret, sender ID | Settings → SMS, alerts & fiscal |
| Backups (disk, retention, alert email) | `.env`: `BACKUP_DISKS`, `BACKUP_KEEP_DAYS`, `BACKUP_NOTIFY_EMAIL` |
| Scheduled jobs | `routes/console.php`. Run `php artisan schedule:run` every minute from cron |

## Tests

```bash
php artisan test                     # SQLite in memory
```

The suite has about 175 Pest tests. They cover money math, checkout, stock, purchasing, reports and payment
callbacks, and include a smoke test that renders every page against the full demo data.

## Deployment

See **[DEPLOY.md](DEPLOY.md)**. It covers an Ubuntu VPS with Nginx, PHP-FPM, MySQL, Supervisor, cron, SSL and
backups. Updates after the first install use `./deploy.sh`.

## Tech stack

Laravel 13 · PHP 8.4+ · Livewire 4 + Alpine.js · Bootstrap 5.3 (custom SCSS theme, Inter, Bootstrap Icons) ·
Chart.js · Tom Select · Vite · MySQL 8 / MariaDB (SQLite for local use) · spatie/laravel-permission ·
spatie/laravel-activitylog · spatie/laravel-backup · maatwebsite/excel · barryvdh/laravel-dompdf · Pest.

The full specification is in [CLAUDE.md](CLAUDE.md).
