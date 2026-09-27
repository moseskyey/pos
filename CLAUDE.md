# CLAUDE.md — DukaPOS (Multi-Branch Point of Sale System)

> This file is the single source of truth for this project. Every session MUST read it fully before writing code.
> Build phase by phase (see §14). Never skip ahead. Update the "Phase Status" table at the end of each phase.

---

## 1. Product Summary

DukaPOS is a web-based, multi-branch Point of Sale and inventory system for Tanzanian retail businesses: supermarkets, mini-marts, pharmacies, hardware shops, boutiques, electronics shops, cosmetics shops and wholesalers.

**Goals**
- A cashier can complete a sale in under 10 seconds using barcode scanner + keyboard only.
- The owner sees today's sales, profit, stock alerts and debts from one dashboard, on phone or desktop.
- Stock is always accurate and auditable (every change is a ledger entry).
- Beautiful, modern, consistent Bootstrap 5 UI on every page, responsive from 360px phones to 1920px monitors.

**Locale defaults**
- Currency: TZS (`TSh`), no decimals shown by default (configurable), thousands separator `,`.
- VAT: 18% (configurable per product: standard / zero-rated / exempt).
- Timezone: `Africa/Dar_es_Salaam`.
- Languages: English (default) + Kiswahili. All UI strings through `__()` with `lang/en` and `lang/sw` files.
- Phone format: `2557XXXXXXXX` / `2556XXXXXXXX` (normalize input `07…`, `+255…`, `255…`).

---

## 2. Tech Stack (do not change without updating this file)

| Layer | Choice |
|---|---|
| Backend | Laravel 13 (latest stable), PHP 8.4+ (Symfony 8 / spatie dependencies need 8.4) |
| Database | MySQL 8 or MariaDB 10.11+ (InnoDB, utf8mb4); CI tests MySQL 8, MariaDB verified |
| Frontend | Blade + **Bootstrap 5.3** (SCSS, custom theme) + **Livewire 4** + Alpine.js |
| Icons | Bootstrap Icons |
| Charts | Chart.js 4 |
| Build | Vite |
| Auth | Laravel Breeze (Blade) restyled to Bootstrap, then extended |
| Permissions | spatie/laravel-permission |
| Audit | spatie/laravel-activitylog |
| Exports | maatwebsite/excel (XLSX), barryvdh/laravel-dompdf (PDF) |
| Barcodes | picqer/php-barcode-generator |
| Queue | database driver (Redis optional) |
| Tests | Pest |
| Code style | Laravel Pint (default preset) |
| Multi-tenancy | Database per business (`App\Tenancy`): central DB for businesses, plans, billing and platform admins; the default connection is switched to the business's database per request/job |

**No Tailwind anywhere.** Remove Breeze's Tailwind after scaffolding and restyle every auth view with Bootstrap.

---

## 3. Coding Rules (mandatory)

1. **Money**: store as `DECIMAL(15,2)`. Never use float math. Use `brick/money` or integer-safe helpers in `App\Support\Money`. Round only at line and document totals.
2. **Stock is a ledger**: every quantity change writes a row to `stock_movements`. `product_stocks.quantity` is a cached balance updated in the same DB transaction. Never update quantity directly without a movement.
3. **Transactions & locking**: sale completion, returns, transfers, GRNs and adjustments run inside `DB::transaction()` with `lockForUpdate()` on affected `product_stocks` rows.
4. **Idempotency**: sale checkout and payment callbacks accept an `idempotency_key`. The same key never creates two sales or payments.
5. **Branch scoping**: every branch-owned model uses a `BelongsToBranch` global scope. Users see only their assigned branches unless they hold `view all branches`.
6. **Services, not fat controllers**: business logic lives in `app/Services/*` (e.g. `SaleService`, `StockService`, `PurchaseService`). Controllers and Livewire components stay thin.
7. **Form Requests** for all validation. **Policies** for all authorization.
8. **Document numbers** are sequential per branch per type, generated inside the transaction: `INV-DSM01-000123`, `PO-…`, `GRN-…`, `RET-…`, `TRF-…`, `QT-…`, `EXP-…`.
9. **Soft deletes** on master data (products, customers, suppliers, users). Never hard-delete transactional data. Sales are voided, not deleted.
10. **Audit**: log create/update/delete on master data, plus price changes, voids, discounts above the limit, stock adjustments and settings changes.
11. **Tests**: every service method with business logic gets a Pest test. Each phase ends with `php artisan test` fully green.
12. **Seeders**: realistic Tanzanian demo data (products like "Azam Maji 500ml", "Kilimanjaro Water 1L", "Unga wa Sembe 2kg", "Sukari 1kg", "Mafuta ya Kupikia Korie 1L", "Blueband 250g"), 2 branches (Kariakoo, Mbezi), demo users per role.
13. Run `./vendor/bin/pint` before every commit.
14. Never commit `.env`. Keep `.env.example` complete and commented.

---

## 4. Roles & Permissions

| Role | Summary |
|---|---|
| **Owner / Super Admin** | Everything, all branches, settings, users, reports, profit figures |
| **Manager** | Their branch(es): products, stock, purchases, reports, approve voids, returns and large discounts, close any shift |
| **Cashier** | POS screen, own shift open/close, hold/resume, customer quick-add, print receipts, view own sales today |
| **Storekeeper** | Products (no prices unless granted), stock receiving (GRN), transfers, stock takes |
| **Accountant** | Read-only reports, expenses, customer and supplier payments, exports. No POS |

Permissions are granular (e.g. `sales.create`, `sales.void`, `sales.discount.above_limit`, `products.edit_price`, `reports.profit.view`, `stock.adjust`, `settings.manage`). Roles are editable from the UI. Cost price and profit are hidden from anyone without `reports.profit.view`.

**Manager override**: when a cashier attempts a restricted action (void, price override, discount above limit, return), show a modal for a manager PIN (4–6 digits, hashed, stored on user). Log who approved.

---

## 5. Feature Modules

### 5.1 Dashboard
- KPI cards: Today's Sales, Transactions, Avg Basket, Gross Profit*, Cash in Drawer, Customer Debts Outstanding.
- Charts: sales last 30 days (line), sales by payment method (doughnut), top 10 products (horizontal bar), sales by hour today (bar).
- Widgets: low-stock list, expiring within 30 days, top customers, recent sales, open shifts.
- Branch selector plus date range filter (Today / Yesterday / This Week / This Month / Custom).
- \*Profit widgets require `reports.profit.view`.

### 5.2 POS / Sales Screen (the most important page)
- Full-screen layout that collapses the sidebar. Optimized for 1366×768 and up; usable on tablet.
- **Left (≈65%)**: search bar (autofocus, barcode scanner friendly, searches name/SKU/barcode), category chips, product grid cards (image, name, price, stock badge) with grid/list toggle.
- **Right (≈35%)**: customer selector (walk-in default, quick-add modal), cart lines (qty +/- and editable input, unit price, line discount, remove), totals panel (subtotal, discount, VAT, **grand total** large and bold), action buttons.
- **Keyboard shortcuts** (shown in a help modal on `?`):
  - `F2` focus search · `F4` customer · `F6` discount · `F8` hold sale · `F9` held sales · `F10` / `Ctrl+Enter` pay · `Esc` close modal · `Del` remove selected line · `+` / `-` change qty.
- Barcode scan adds qty 1, or increments if the product is already in the cart. Weighted/price-embedded barcodes (prefix `2`) are parsed for weight/price items.
- Units: sell by piece, dozen, carton, kg, litre, with **unit conversions** (e.g. 1 carton = 24 pcs) and price per unit.
- Price tiers: retail, wholesale (auto-applies at qty ≥ threshold or for wholesale customers).
- Discounts: line or whole cart, % or fixed. Above the configured limit requires manager PIN.
- **Hold / Resume** sales (multiple held carts per cashier, with a note).
- **Payment modal**: split payments across Cash, Mobile Money (M-Pesa, Tigo Pesa/Mixx by Yas, Airtel Money, HaloPesa), Card, Bank Transfer, Credit (on account), Store Credit. Quick-cash buttons (exact, 1,000, 2,000, 5,000, 10,000, 20,000, 50,000). Change is calculated live.
- Mobile money: manual reference entry (always available) plus optional STK push through a pluggable `PaymentGateway` interface. First driver: FastLipa. Include a `Manual` driver.
- Credit sale: requires a registered customer, checks the credit limit, needs manager PIN if exceeded.
- After payment: success screen showing change due (huge font), buttons Print Receipt / SMS Receipt / New Sale (`Enter`). Auto-print is configurable.
- Offline resilience: if a request fails, keep the cart in Alpine state plus `localStorage` so it isn't lost; retry with the same idempotency key.
- Blocks selling below cost (warning, manager PIN) and selling beyond stock (configurable: block / warn / allow negative).

### 5.3 Receipts & Invoices
- Thermal receipt view for **58mm and 80mm** (CSS `@page` sized, monospace-friendly): logo, business name, TIN, VRN, branch address and phone, receipt no, date/time, cashier, customer, items (name, qty × price, total), subtotal, discount, VAT breakdown, total, payments, change, footer message, QR code with receipt number/verification URL.
- A4 **Tax Invoice** PDF, **Delivery Note**, and **Quotation** PDF with a professional Bootstrap-styled template.
- Reprint any receipt (logged). "COPY" watermark on reprints.
- Optional SMS receipt summary via a pluggable `SmsGateway` interface.

### 5.4 Sales Management
- Sales list: filters (date, branch, cashier, customer, payment method, status), totals row, export XLSX/PDF.
- Sale detail page: items, payments, returns, audit trail, reprint.
- **Void** sale (same day, manager PIN, reason required; stock restored via movements).
- **Returns / Refunds**: select a sale, pick lines and quantities, give a reason. Refund to cash, mobile money or store credit. Choose restock or damaged. Generates `RET-` document.
- **Quotations**: create, send (PDF), convert to sale in one click, set expiry date.
- **Layaway / deposits** (optional flag): partial payment, stock reserved, completed on full payment.

### 5.5 Products & Catalog
- Product fields: name, SKU (auto or manual), barcode(s) (multiple allowed), category, brand, unit, secondary units with conversion, cost price, retail price, wholesale price + min qty, tax type, reorder level, track stock (yes/no, no for services), track batches/expiry (yes/no), image, description, active flag.
- **Variants** (size/color) for boutiques: parent product with child SKUs, each with its own stock and price.
- Categories (nested, 2 levels), Brands, Units.
- **Bulk import** from XLSX with a downloadable template, validation preview and error report. **Bulk export**.
- **Bulk price update** (by category/brand, % or fixed), logged.
- **Barcode label printing**: select products + quantity and choose a label size (A4 sheets of 30/40/65 labels, or 50×25mm roll). Label shows name, price, barcode.
- Product detail page: stock per branch, movement history, sales history chart, batches with expiry.

### 5.6 Inventory
- Stock per branch overview with value at cost/retail (value needs permission), plus filters for low / out / overstock.
- **Stock movements ledger** (read-only) filtered by product, type, date and user. Types: `sale`, `return`, `purchase`, `adjustment_in`, `adjustment_out`, `transfer_out`, `transfer_in`, `stock_take`, `void`, `opening`.
- **Stock adjustments** with a reason (damaged, expired, theft, found, correction) and approval workflow for managers.
- **Stock transfers** between branches: request → approve → dispatch (stock out) → receive (stock in, with discrepancy handling).
- **Stock take / cycle count**: create a count sheet (full or by category), freeze snapshot, count via scanner or manual entry, variance report, post adjustments on approval.
- **Batches & expiry** (pharmacy/food): FEFO selection on sale, expiry alerts at 30/60/90 days, expired stock report.
- Low-stock alerts on the dashboard, plus an optional daily email/SMS to the manager.

### 5.7 Purchases & Suppliers
- Suppliers: name, contact person, phone, email, TIN, address, payment terms, opening balance.
- **Purchase Orders**: draft → sent (PDF/email) → partially received → received / cancelled.
- **Goods Received Note (GRN)**: against a PO or direct; enter batch/expiry, update cost price (moving average cost, configurable to last cost).
- **Supplier invoices & payments**: record bills and payments; supplier statement and aging (0–30, 31–60, 61–90, 90+).
- Purchase returns to supplier.
- **Reorder suggestions**: products below the reorder level, grouped by last supplier, one click to create a draft PO.

### 5.8 Customers & Credit (Deni)
- Customers: name, phone, email, TIN, address, type (retail/wholesale), credit limit, opening balance, notes.
- **Customer account ledger**: credit sales, payments, returns, running balance.
- Receive customer payments (allocate to invoices, FIFO default).
- **Customer statement** PDF + SMS reminder for overdue balances.
- Debtors aging report.
- **Loyalty points** (optional): earn X points per TSh Y, redeem as discount at POS.
- Purchase history and favorite products on the customer page.

### 5.9 Cash Register / Shifts
- A cashier must **open a shift** before selling: register/till, opening float.
- Cash In / Cash Out (paid-outs) during a shift, with reason.
- **Close shift**: enter counted cash (optional denomination counter: 10,000 / 5,000 / 2,000 / 1,000 / 500 / 200 / 100 / 50). The system shows expected vs counted and the over/short amount.
- **X report** (mid-shift snapshot) and **Z report** (end of shift, printable on thermal).
- A manager can force-close shifts. The shift history page shows over/short trends per cashier.

### 5.10 Expenses
- Expense categories (rent, electricity/LUKU, salaries, transport, water, internet, other).
- Record expenses per branch with an attachment (receipt photo/PDF) and payment method; cash expenses can come from the drawer.
- Recurring expense templates (monthly rent, etc.).

### 5.11 Reports (all with filters, charts where useful, XLSX + PDF export, print)
1. Sales summary (by day/week/month)
2. Sales by product / category / brand
3. Sales by cashier / branch / payment method
4. Sales by hour (peak hours)
5. **Profit & Loss** (sales − COGS − expenses)
6. Gross profit by product / category
7. Stock valuation (cost & retail)
8. Stock movement report
9. Low stock / out of stock / dead stock (no sales in N days)
10. Expiry report
11. Purchases by supplier
12. Supplier aging & statement
13. Debtors aging & customer statement
14. Returns & voids report (loss prevention)
15. Discounts given report (by cashier)
16. Shift / cash reconciliation report
17. VAT report (output VAT from sales, input VAT from purchases) for monthly TRA filing
18. Expenses report

### 5.12 Multi-Branch
- Branches: name, code (e.g. `DSM01`), address, phone, receipt header/footer overrides.
- Registers/tills per branch.
- Branch switcher in the top navbar for multi-branch users. Owner sees a consolidated or per-branch view.

### 5.13 Settings
- Business profile: name, logo, TIN, VRN, address, phone, email.
- Currency format, decimal places, VAT rate, prices inclusive/exclusive of VAT.
- Receipt: paper size, header, footer, show logo, auto-print, show QR.
- POS: negative stock policy, max cashier discount %, below-cost selling policy, default customer, rounding (to nearest 50/100 TZS optional).
- Payment methods on/off, plus gateway credentials (encrypted with `Crypt`).
- SMS gateway credentials, sender ID, notification toggles.
- Document number prefixes.
- Language, timezone, date format.
- **TRA VFD/EFD integration hook**: `FiscalDevice` interface with a `Null` driver now; leave a clear extension point to send receipts to TRA and print the verification code/QR. Do not implement the live TRA API unless instructed.
- Backup: button to trigger `spatie/laravel-backup` (DB dump), plus a list of backups to download.

### 5.14 Users & Security
- User management: name, phone, email, role(s), branches, manager PIN, active flag, avatar.
- Login with email or phone plus password. Throttle attempts. Optional 2FA (TOTP) for owner/manager.
- Session timeout on the POS screen (configurable) with lock screen (re-enter PIN to resume).
- Activity log viewer with filters.
- "Login as" (impersonate) for owner, logged.

### 5.15 Notifications
- In-app bell with low stock, expiring batches, transfer awaiting approval, shift over/short above threshold, overdue customer debts.
- Optional email and SMS channels (queued).

---

## 6. Database Overview (core tables)

`branches`, `registers`, `users`, `branch_user`, roles/permissions tables (spatie), `settings` (key/value, cached),
`categories`, `brands`, `units`, `unit_conversions`, `products`, `product_variants`, `product_barcodes`, `product_prices`, `product_stocks` (branch_id, product_id, quantity; unique pair), `product_batches` (batch_no, expiry_date, quantity, branch_id),
`stock_movements` (branch_id, product_id, batch_id nullable, type, quantity signed, unit_cost, reference_type, reference_id, user_id, note),
`stock_adjustments` + items, `stock_transfers` + items, `stock_takes` + items,
`suppliers`, `purchase_orders` + items, `goods_receipts` + items, `supplier_bills`, `supplier_payments`, `purchase_returns` + items,
`customers`, `customer_ledger_entries`, `customer_payments`, `loyalty_transactions`,
`shifts`, `cash_movements`, `sales` (status: completed/voided/held/quotation/layaway; idempotency_key unique), `sale_items` (snapshot name, sku, qty, unit, unit_price, cost_price, discount, tax_rate, tax_amount, line_total), `sale_payments` (method, amount, reference, gateway_status), `sale_returns` + items,
`expense_categories`, `expenses`, `document_sequences` (branch_id, type, next_number), `activity_log`, `notifications`, `jobs`, `failed_jobs`.

Sale items **snapshot** name, price, cost and tax at the time of sale, so later product edits never change history.

Add indexes on foreign keys, `(branch_id, created_at)` on sales/movements/expenses, `barcode`, `sku`, `phone`.

---

## 7. UI / UX Design System (apply to EVERY page)

### 7.1 Look & Feel
- Modern, clean admin style: **Bootstrap 5.3 with a custom SCSS theme**, not default-looking Bootstrap.
- Font: **Inter** (Google Fonts) with system fallback.
- Theme tokens (SCSS variables, override Bootstrap before import):
  - `$primary: #4F46E5` (indigo) · `$success: #16A34A` · `$danger: #DC2626` · `$warning: #F59E0B` · `$info: #0EA5E9`
  - Body bg light `#F5F7FB`, cards white, border `#E5E7EB`, text `#111827`, muted `#6B7280`
  - `$border-radius: .75rem`, `$border-radius-lg: 1rem`, soft shadows (`0 1px 3px rgba(16,24,40,.08)`)
- **Dark mode** via `data-bs-theme="dark"`, toggle in the navbar, preference saved per user.
- Consistent spacing: page padding `1.5rem` desktop / `1rem` mobile; card padding `1.25rem`.

### 7.2 Layout
- **Sidebar** (fixed, 260px, collapsible to 72px icon-only, offcanvas on mobile): logo, grouped nav with Bootstrap Icons. Groups: Dashboard · POS · Sales · Products · Inventory · Purchases · Customers · Expenses · Reports · Settings. Active item highlighted with a primary pill.
- **Top navbar**: page title + breadcrumb, global search (products/customers/invoices, `Ctrl+K`), branch switcher, shift status badge (Open/Closed), notifications bell with count, language toggle EN/SW, dark mode toggle, user dropdown.
- **Page header pattern**: title, subtitle, action buttons on the right (primary action solid, secondary outline).
- Footer: small, muted, app version.

### 7.3 Components (build once as Blade components in `resources/views/components/`)
- `x-stat-card` (icon in tinted circle, label, big value, trend % up/down badge)
- `x-card`, `x-page-header`, `x-empty-state` (illustration icon, message, CTA button)
- `x-data-table` (Livewire: search, column sort, filters dropdown, per-page, pagination, bulk select, export, sticky header, responsive → stacked cards on mobile)
- `x-modal`, `x-confirm-modal` (danger styling for destructive actions), `x-manager-pin-modal`
- `x-status-badge` (soft-colored pill badges: Completed=success, Voided=danger, Held=warning, Draft=secondary, etc.)
- `x-money` (formats TZS consistently), `x-avatar`, `x-date-range-picker`
- Form components: `x-input`, `x-select` (Tom Select for searchable selects), `x-textarea`, `x-toggle`, `x-file-upload` (drag & drop with preview). All show validation errors inline with `is-invalid`.
- **Toasts** (top-right) for success/error, triggered from Livewire events and session flash.
- **Loading states**: skeleton placeholders on tables/cards, spinner on buttons (`wire:loading`), buttons disabled on submit to prevent double-clicks.

### 7.4 Page-level UI requirements
- **Every list page**: page header with "Add" button, filter bar, data table, empty state, export buttons, responsive.
- **Every form page**: two-column layout on desktop (main form left, summary/help card right), sticky bottom action bar (Cancel / Save / Save & New), unsaved-changes warning.
- **Every detail page**: header with status badge + actions, summary cards on top, tabs for related data (e.g. Product: Overview · Stock · Movements · Sales · Batches).
- **Login page**: split screen (brand panel with gradient and illustration on the left, form on the right), language toggle, "remember me", polished on mobile.
- **POS screen**: large touch-friendly buttons (min 44px), high-contrast grand total, green Pay button, visible keyboard shortcut hints, sound on scan (short beep, toggleable), no page reloads (Livewire).
- **Reports**: filter card on top, KPI summary row, chart, then table; print stylesheet hides nav.
- **Accessibility**: labels on all inputs, focus states visible, color never the only signal, contrast AA.

---

## 8. Integrations (interfaces first, drivers pluggable)

```php
interface PaymentGateway { public function initiate(PaymentRequest $r): PaymentResult; public function status(string $ref): PaymentResult; public function handleCallback(Request $r): PaymentResult; }
interface SmsGateway { public function send(string $to, string $message): SmsResult; }
interface FiscalDevice { public function submit(Sale $sale): FiscalResult; }
```
- Drivers selected in Settings, credentials encrypted.
- Callbacks: verify signature, idempotent, log raw payload, never trust amount from the client.
- Provide `Manual`/`Log`/`Null` drivers so the system works fully without external services.

---

## 9. Security Checklist
- CSRF on all forms, validation on all input, mass-assignment protection (`$fillable`).
- Authorization via policies on every route and Livewire action (not just hidden buttons).
- Rate-limit login, PIN attempts (lock after 5 failed), and payment endpoints.
- Encrypt gateway secrets. Hash manager PINs.
- File uploads: validate mime/size, store outside public, serve through controller.
- Security headers middleware (X-Frame-Options, X-Content-Type-Options, Referrer-Policy).
- `APP_DEBUG=false` guidance in deployment docs.

---

## 10. Performance
- Eager-load relationships (no N+1; enable `Model::preventLazyLoading()` in non-production).
- Cache settings and permissions. Paginate all lists.
- POS product search: indexed columns, limit 30 results, debounce 250ms.
- Heavy reports and exports run as queued jobs, with a notification when ready to download.

---

## 11. Folder Structure (key parts)

```
app/
  Enums/            (SaleStatus, PaymentMethod, MovementType, ...)
  Http/Controllers/  Http/Requests/  Policies/
  Livewire/         (Pos/, Products/, Inventory/, Sales/, Reports/, ...)
  Models/
  Services/         (SaleService, StockService, PurchaseService, ShiftService, ReportService, DocumentNumberService)
  Contracts/        (PaymentGateway, SmsGateway, FiscalDevice)
  Gateways/         (Payment/FastLipa, Payment/Manual, Sms/Log, Fiscal/Null)
  Support/          (Money, PhoneNumber, BarcodeParser)
resources/
  scss/app.scss     (theme variables → bootstrap import → custom components)
  views/components/ views/layouts/ views/pos/ views/receipts/ views/pdf/
lang/en  lang/sw
tests/Feature  tests/Unit
```

---

## 12. Deployment Target (for docs in Phase 8)
- Ubuntu 22.04/24.04 VPS, Nginx, PHP 8.4-FPM, MySQL 8, Supervisor (queue worker), cron (`schedule:run` every minute), Let's Encrypt SSL.
- Provide `DEPLOY.md` with exact commands, an Nginx server block, Supervisor config, cron line, backup schedule, and an update script (`deploy.sh`: pull, composer, migrate --force, build, cache, restart queue).

---

## 13. Definition of Done (every phase)
- Features in the phase are complete, and the UI matches §7 on desktop and mobile.
- Migrations, factories and seeders updated. Demo data shows the new features.
- Pest tests written and `php artisan test` passes.
- Pint clean. No debug code. `.env.example` updated.
- README "Features" section and the Phase Status table below updated.
- One PR per phase with a clear summary and screenshots list of new pages.

---

## 14. Build Phases

| # | Phase | Scope |
|---|---|---|
| 1 | Foundation & UI Shell | Laravel install, Bootstrap theme (SCSS), layout (sidebar/navbar/dark mode/lang), all Blade components (§7.3), auth restyled (login split-screen), roles/permissions, branches, registers, users CRUD, settings module, activity log, seeders |
| 2 | Catalog | Categories, brands, units + conversions, products, variants, barcodes, prices, images, XLSX import/export, bulk price update, barcode label printing |
| 3 | Inventory | Stock ledger, product_stocks, opening stock, adjustments + approval, transfers, stock take, batches/expiry (FEFO), low stock & expiry alerts |
| 4 | POS & Shifts | Shift open/close/X/Z, cash in/out, full POS screen (§5.2), hold/resume, split payments, manager PIN, receipts 58/80mm + A4 invoice, reprint |
| 5 | Sales Ops & Customers | Sales list/detail, void, returns/refunds, quotations, layaway, customers, credit ledger, customer payments, statements, loyalty points |
| 6 | Purchases & Expenses | Suppliers, POs, GRN (moving average cost), supplier bills/payments/aging, purchase returns, reorder suggestions, expenses + recurring |
| 7 | Dashboard & Reports | Dashboard (§5.1), all 18 reports (§5.11), queued exports, notifications center |
| 8 | Integrations & Hardening | PaymentGateway (FastLipa + Manual), SmsGateway, FiscalDevice hook, 2FA, POS lock screen, backups, security headers, performance pass, Kiswahili translations complete, DEPLOY.md + deploy.sh |

### Phase Status

| Phase | Status | PR | Notes |
|---|---|---|---|
| 1 | ✅ Done | | Laravel 13 + Livewire 4 (latest stable at build time). Theme, layout, components, auth (email/phone, PIN, 2FA), roles, branches, users, settings, activity log |
| 2 | ✅ Done | | Categories, brands, units/conversions, products, variants, barcodes (incl. scale), XLSX import/export, bulk prices, labels |
| 3 | ✅ Done | | Stock ledger, adjustments + approval, transfers, stock takes, FEFO batches, alerts |
| 4 | ✅ Done | | Shifts X/Z, cash in/out, POS screen + shortcuts, hold/resume, split payments, manager PIN, receipts/invoices |
| 5 | ✅ Done | | Sales, voids, returns, quotations, layaway, customers, credit ledger, payments, statements, loyalty |
| 6 | ✅ Done | | Suppliers, POs, GRN (moving average), bills/payments/aging, purchase returns, reorder, expenses + recurring |
| 7 | ✅ Done | | Dashboard, 18 reports, queued Excel/PDF exports, notifications |
| 8 | ✅ Done | | FastLipa (two-phase, signed idempotent callbacks, reconciliation), Beem SMS, fiscal hook, POS lock screen, backups UI, MySQL-verified, sw translations, DEPLOY.md, deploy.sh, CI |

**Additions after Phase 8:**
- FastLipa aligned with the live API, including failed→completed re-checks.
- Supplier statement PDF, and emailing POs to suppliers.
- USD cash payments, and WhatsApp sharing through signed PDF links.
- Direct ESC/POS printing with the cash drawer.
- Offline till: service worker, local queue, idempotent sync with review flags.
- Model policies and Form Requests across all controllers (§3.7).
- Multi-business SaaS:
  - Each business has its own database, files, cache keys, settings and backups.
  - Sign-up at `/register` with a free trial; sign-in finds the business from the email or phone.
  - Plans with branch, user and product limits, plus a trial → active → grace → expired lifecycle.
  - Subscription payments by FastLipa (platform account) and manual cash/bank payments, with invoices and reminders.
  - Platform admin panel at `/admin`.
  - Existing single-shop installs are adopted as business #1 with `tenants:adopt`.
  - Rules for new code:
    - Central models set `$connection = 'central'`; business code keeps using the default connection.
    - Business-only artisan commands run through `tenants:run`.
    - Tests run inside test business #1 (`tests/RefreshTenantDatabase.php`).
- Feature switches (Batch 1):
  - Optional modules are listed in `config('dukapos.features')`; check them with `feature('key')`, guard routes with
    the `feature:key` middleware, and pass a 6th element to `App\Support\Navigation` items.
  - Business-type presets live in `config('dukapos.business_presets')`.
  - Emailing invoices/quotations (`SaleDocumentMail`), import error report, customer credit terms and due dates.

---

## 15. Session Kickoff Prompts (copy one per cloud session)

**Phase 1**
> Read CLAUDE.md fully. Implement Phase 1 only, following every rule in §3 and the UI design system in §7 exactly. Make the UI polished and modern on every page. Write Pest tests, run them until green, run Pint, update the Phase Status table, and open a PR titled "Phase 1: Foundation & UI Shell".

**Phase N (2–8)**
> Read CLAUDE.md fully and review the existing code to match its conventions and components. Implement Phase N only, as described in §5 and §14. Reuse the existing Blade components and theme; every new page must follow §7.4. Write Pest tests, make them pass, run Pint, update the Phase Status table and README, and open a PR titled "Phase N: <name>".

**Fix / polish session**
> Read CLAUDE.md. Review all pages built so far against §7 (UI) and §13 (Definition of Done). Fix inconsistencies, broken responsive layouts, missing empty/loading states, N+1 queries and failing tests. Open a PR titled "Polish pass".
