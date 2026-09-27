# DukaPOS

**Multi-branch Point of Sale & Inventory for Tanzanian retail.** Supermarkets, mini-marts, pharmacies,
hardware shops and boutiques. It is built with Laravel, Livewire and Bootstrap 5, in English and Kiswahili, and
priced in TZS with 18% VAT, M-Pesa / Mixx by Yas / Airtel Money / HaloPesa, and TRA-ready receipts.

- Runs any number of branches from one install, each with its own stock, tills, shifts and document numbers.
- A fast, keyboard-first POS screen that works with barcode scanners, split payments, hold/resume and manager PINs.
- A full stock ledger: every quantity change is recorded, with FEFO batches and expiry, transfers and stock takes.
- The numbers an owner needs: dashboard, 18 reports, VAT return helper, profit & loss, and debt aging.
- Runs as a subscription service for many businesses: each shop signs up, gets its own database and a free
  trial, and pays for a plan by mobile money. A platform admin panel manages every business and subscription.

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

Open <http://localhost:8000> and sign in with one of the demo shop accounts (password **`password`**):

| Role | Email | PIN |
|---|---|---|
| Owner | `owner@dukapos.test` | 1234 |
| Manager (Kariakoo) | `manager@dukapos.test` | 4321 |
| Manager (Mbezi) | `manager.mbezi@dukapos.test` | 5678 |
| Cashier (Kariakoo) | `cashier@dukapos.test` | 1111 |
| Cashier (Mbezi) | `cashier.mbezi@dukapos.test` | 2222 |
| Storekeeper | `store@dukapos.test` | – |
| Accountant | `accounts@dukapos.test` | – |

The platform admin panel is at <http://localhost:8000/admin>: sign in as **`admin@dukapos.test`** / `password`.
New businesses can sign up at <http://localhost:8000/register>.

The demo seeder creates the admin, four subscription plans and a demo business ("DukaPOS Demo Store", in its own
database) with two branches (DSM01 Kariakoo, DSM02 Mbezi), about 60 Tanzanian products, customers with debts,
suppliers, purchase orders and 30 days of sales. Demo data is only seeded when `APP_ENV` is `local`, `testing` or
`demo`.

### Manual setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # the central database; or configure MySQL in .env
php artisan migrate --seed            # central tables + plans; outside production also the demo business
php artisan storage:link
npm install && npm run build         # compiled assets are not committed
php artisan serve
php artisan queue:work               # exports, SMS and notifications run on the queue
```

To use MySQL 8 / MariaDB instead of SQLite, set `DB_CONNECTION=mysql` and the `DB_*` values in `.env`. The
database user must be allowed to create one database per business (see [DEPLOY.md](DEPLOY.md#2-database)).

## Features

**Subscriptions & platform admin (SaaS).**
- One install serves many businesses. Each business has **its own database** and its own files, backups, settings
  and users; nothing is shared between shops.
- Self-service sign-up at `/register` with a free trial (length set by the admin). Users sign in with their email or
  phone, and the system finds their business.
- Plans with a price, billing interval and limits on branches, active users and products.
- Owners pay from **Settings → Subscription** by mobile money (FastLipa STK push to the platform's account). Payments
  are confirmed with FastLipa's status API before the subscription is extended, webhooks are idempotent, and a
  sweep catches missed ones. Invoices as PDF.
- Trial → active → grace period → expired: an expired business is sent to its billing page (its data is kept). Owners
  get reminders in the app and by email 7, 3 and 1 days before access ends.
- **Platform admin panel** (`/admin`, separate login):
  - dashboard with MRR, revenue and sign-up charts, businesses ending soon and recent payments
  - businesses: create, edit, extend, change plan, record cash/bank payments, suspend, delete/restore/purge,
    **login as owner**, reset a user's password, deactivate users, usage against plan limits
  - payments (verify with FastLipa, refund), plans, announcements shown to all or one business, a directory of
    every user, admins (super and support), platform settings and an audit log of every admin action
- An existing single-shop install is adopted as business #1 in place on the first `./deploy.sh`.

**Foundation.**
- Branches with tills/registers.
- Users can sign in with an email or a phone number, set a PIN, and enable TOTP two-factor auth.
- Roles & permissions come with editable defaults for Owner, Manager, Cashier, Storekeeper and Accountant.
- Manager PIN overrides.
- An activity log records every sensitive action.
- A settings module, dark mode, and an English/Kiswahili switcher.
- **Feature switches per business** (Settings → Features): quotations, layaway, credit terms, loyalty, variants,
  batches & expiry, weighed items, transfers, stock takes, expenses, WhatsApp sharing and email documents. Switched-off
  modules disappear from menus and screens, and their pages return 404. One-click presets set them up for a
  supermarket, pharmacy, hardware shop, boutique, electronics shop, cosmetics shop or wholesaler.

**Catalog.**
- Categories (two levels), brands, and units with conversions (carton ↔ piece).
- Retail and wholesale prices, with a wholesale price applied from a set quantity.
- Multiple barcodes per product, EAN-13 generation, scale (weighed) barcodes, and variants (size/colour).
- Price history, bulk price updates, Excel import/export with preview and a downloadable error report, and barcode label printing.

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
- Direct printing to USB or serial thermal printers (ESC/POS) from Chrome or Edge, with the cash drawer
  opening automatically on cash sales. "No sale" drawer openings are logged.
- US dollar cash at a configurable rate. Change is given in shillings, and shift reports show the dollars expected in the drawer.
- WhatsApp buttons for receipts, invoices, quotations and customer statements. The customer receives a link
  to a PDF that expires after 30 days.
- **Offline till:** if the internet drops, cashiers keep selling from the offline till.
  - The page works without a connection, and sales are stored on the device.
  - Sales sync automatically when the connection returns, dated to when they happened.
  - A sale is never recorded twice.
  - Anything that would normally have needed a manager is flagged for review.
- The cart is backed up locally in the browser.

**Sales & customers.**
- Sales list and detail, same-day voids, and returns/refunds with restock or damaged handling.
- Quotations that convert to sales in one click, and can be emailed to the customer as a PDF.
- Invoices and receipts can be emailed as a PDF from the sale page or the POS success screen.
- Layaway with deposits.
- Customer accounts:
  - credit ledger (deni), with FIFO payment allocation
  - payment terms per customer (or a business default); every credit sale gets a due date, and aging, statements
    and overdue alerts count days past the due date
  - statements as PDF
  - SMS reminders
  - loyalty points
  - store credit

**Purchases & expenses.**
- Suppliers and purchase orders (PDF), which can be emailed to the supplier from the order page.
- Supplier statements as PDF.
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
- Policies authorise every record by role and branch, and Form Requests validate every controller input.
- Lazy loading is blocked outside production.

## Configuration

Most configuration is in the app under **Settings**: business profile, TIN/VRN, currency, VAT, receipts, POS
rules (negative stock, maximum discount, below-cost sales, rounding, lock timeout), payment methods, SMS and
document number prefixes.

| What | Where |
|---|---|
| FastLipa API key, URL, webhook secret | Settings → Payment methods (stored encrypted). Callback URL: `https://your-domain/api/payments/callback/fastlipa/<business id>` (shown on that page) |
| Plans, trial and grace days, sign-ups, support contacts | Admin → Plans, Admin → Settings |
| Subscription payments (platform FastLipa account) | Admin → Settings. Callback URL: `https://your-domain/api/billing/callback/fastlipa` |
| Database per business | `.env`: `TENANT_DB_PREFIX` (MySQL/MariaDB) or `TENANT_SQLITE_PATH` (SQLite) |
| Beem SMS key, secret, sender ID | Settings → SMS, alerts & fiscal |
| Backups (disk, retention, alert email) | `.env`: `BACKUP_DISKS`, `BACKUP_KEEP_DAYS`, `BACKUP_NOTIFY_EMAIL` |
| USD cash payments | Settings → Currency & tax (rate), Settings → Payment methods (turn on "Cash (USD)") |
| Direct receipt printing / cash drawer | Settings → Receipts → Printing: *Direct to thermal printer*. Then use **Connect printer** on the POS screen once per computer (Chrome or Edge) |
| Offline till | Works automatically over HTTPS. Open the POS once while online so the device downloads the product list |
| Scheduled jobs | `routes/console.php`. Run `php artisan schedule:run` every minute from cron; per-business jobs run through `tenants:run` |

## Tests

```bash
php artisan test                     # SQLite in memory
```

The suite has about 250 Pest tests. Each test runs inside a test business with its own database, next to the
central one. They cover:
- money math, checkout, stock and purchasing
- reports and payment callbacks
- offline sync, ESC/POS output and authorisation
- tenancy isolation (data, files, queue jobs, sign-in), sign-up, subscription billing and the admin panel

A smoke test renders every page against the full demo data, and another renders every admin page. CI runs the
suite on SQLite and MySQL 8.

## Deployment

See **[DEPLOY.md](DEPLOY.md)**. It covers an Ubuntu VPS with Nginx, PHP-FPM, MySQL, Supervisor, cron, SSL and
backups. Updates after the first install use `./deploy.sh`.

## Tech stack

Laravel 13 · PHP 8.4+ · Livewire 4 + Alpine.js · Bootstrap 5.3 (custom SCSS theme, Inter, Bootstrap Icons) ·
Chart.js · Tom Select · Vite · MySQL 8 / MariaDB 10.11+ (SQLite for local use) · spatie/laravel-permission ·
spatie/laravel-activitylog · spatie/laravel-backup · maatwebsite/excel · barryvdh/laravel-dompdf · Pest.

The full specification is in [CLAUDE.md](CLAUDE.md).
