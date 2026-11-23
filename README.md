```bash
composer install
```


# SuperShop — POS & Supermarket Management System

A complete point-of-sale and store-management platform for supermarkets and retail
counters, built with **Laravel 13** and **MySQL**. Hand-crafted blue-and-white UI (no
CSS framework), barcode-scanner checkout, cash-drawer control, role-based access, and a
built-in database backup/restore system.

---

## Features

### Point of Sale

- **Barcode scanner support** — any USB/Bluetooth HID scanner works out of the box (it
  types the code and presses Enter; the terminal adds the item instantly).
- Live cart with quantity steppers, per-item tax, line and order discounts.
- Cash / card / mobile payments, cash-tendered + automatic change calculation, quick-cash buttons.
- **Cash-drawer kick** via ESC/POS network receipt printer (`POS_PRINTER_HOST`).
- Thermal-style **receipt printing** (auto-prints in a popup after each sale).
- **Hold & resume** sales (park a cart, serve another customer, resume later).
- Add walk-in or new customers on the fly; keyboard shortcuts (`F2` pay, `F4` focus scan).

### Catalog & Inventory

- Products with SKU, barcode, cost/sale price, tax rate, category, unit, supplier, images.
- Categories (color-coded), units (with decimal support for weighed goods), suppliers.
- Stock tracking with **per-branch low-stock thresholds**, dashboard alerts, and manual
  stock adjustments (with full audit history).
- **Branch-specific inventory** — each location has its own stock and low-stock threshold.
- **Stock transfers** — request stock between branches, require Super Admin approval, deduct
  source stock at dispatch, and record one or more destination receipts with shortage/damage
  reasons. Branch users can only act for their assigned branch.
- **Purchases / stock receiving** — recording a received purchase increases stock and
  updates cost price. Supplier payments are recorded individually; partial payments and
  outstanding balances are visible in purchase and supplier records.

### Expenses & Finance

- Daily expenses with categories, payment methods, paid/unpaid status, and branch-aware
  expense totals. Each expense stores AFN or USD independently; totals and trend charts
  remain separated by currency with no implicit conversion.
- Supplier outstanding balances include opening balances and purchase amounts less
  recorded payments.

### Sales & Customers

- Full sales history with filters (date, payment, status), invoice detail pages.
- **Refunds** — restores stock, reverses loyalty & cash-session totals.
- Customer directory with **loyalty points** and lifetime spend tracking.

### Cash Register (shifts)

- Open a register with a starting float, take sales against the session, then close with a
  cash count → generates an **X/Z-report** showing expected vs. counted cash and any variance.

### Reports

- Sales report (by day / payment method / cashier), purchase and expense reports, profit
  report (per-product margins), stock valuation report, and top-selling products.
- CSV and Excel-compatible `.xls` exports for sales, purchases, expenses, inventory, and
  profit reports. Transaction reports support date ranges and branch filters; inventory is
  exported as a current stock snapshot for the selected branch(es).

### Administration

- **Multi-branch access control** — Super Admin manages branches and sees all locations;
  Admin, Manager, and Cashier accounts are scoped to one assigned branch.
- **Operational notifications** — branch-aware in-app alerts for low-stock threshold
  crossings and stock-transfer requests, approvals, dispatches, receipts, and discrepancies.
  Users can filter unread alerts and mark them read from the notification center.
- **Internal messaging** — Messenger-style conversation list and chat view with live
  delivery via Laravel Reverb, read receipts, and private direct conversations. Branch or
  role announcements remain read-only and messages appear in the notification center.
- Branch Admins and Managers can request, dispatch, or receive transfers for their branch.
  Super Admin approves or rejects requests; partial receipts are supported and discrepancies
  must be explained before closing a transfer.
- Branch names are stored as entered, branch numbers are unique, and new branches start with
  zero stock. Existing users, transactions, and product stock are migrated into **Main Branch (001)**.
- **Role-based access control** (Super Admin / Admin / Manager / Cashier + custom roles) with a granular
  permission matrix — 25+ permissions across every module.
- User management, activity/audit log, store settings (currency, tax, receipt footer, loyalty).
- **Database backups** — one-click `mysqldump` backup, download, restore, delete, plus a
  scheduled daily backup at 2:00 AM.

---

## Requirements

- PHP 8.3+
- Composer 2
- MySQL 8+ (with `mysqldump` / `mysql` binaries on `PATH` for the backup feature)
- Node.js 20.19+ (or 22.12+) for building the live chat client with Vite.

---

## Languages

The interface supports English (left-to-right), Dari (right-to-left), and Pashto
(right-to-left). Use the language selector on the sign-in page or in the app header.
User-entered shop, product, customer, and receipt text is not automatically translated.

---

## Installation

```bash
# 1. Install dependencies
composer install

# 2. Create .env from .env.example, configure the database, and generate the app key
php artisan key:generate

# 3. Install and build the frontend
npm install
npm run build

# 4. Create the database
mysql -uroot -e "CREATE DATABASE super_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Run migrations + seed demo data
php artisan migrate --seed
php artisan db:seed --class=DemoSalesSeeder   # optional: sample sales for the dashboard

# 6. Start the app, queue worker, Vite, and Reverb for local development
composer run dev
```

Then open [http://127.0.0.1:8000](http://127.0.0.1:8000). Reverb is configured by the
`REVERB_*` settings in `.env.example`; keep a Reverb server running in each environment
that needs live chat. Messaging pages and saved messages remain available if Reverb is
temporarily offline; real-time updates resume when Reverb and the queue worker are running.

### Environment settings (`.env`)

```env
APP_VERSION=1.0.0
DB_DATABASE=super_shop
DB_USERNAME=root
DB_PASSWORD=

# Cash-drawer / receipt printer (leave POS_PRINTER_HOST blank to disable the hardware kick)
POS_PRINTER_HOST=
POS_PRINTER_PORT=9100
```

`APP_VERSION` is displayed in the application footer and profile page. Set it to the
release's semantic version when deploying an update; if configuration is cached, run
`php artisan config:clear` after changing it.

---

## Demo accounts

| Role        | Email                  | Password     | Access                                           |
| ----------- | ---------------------- | ------------ | ------------------------------------------------ |
| Super Admin | admin@supershop.test   | `password` | All branches and system administration           |
| Manager | manager@supershop.test | `password` | Operations, reports (no users/roles/backups)     |
| Cashier | cashier@supershop.test | `password` | POS, register, view products/customers/own sales |

Change seeded passwords before exposing a deployment to users. Branch staff are assigned
to one branch in **Users**; Super Admin can select the active branch from the app header.

---

## Hardware notes

- **Barcode scanner:** any HID (keyboard-emulation) scanner. Focus the scan box on the POS
  screen (it auto-focuses) and scan — no drivers or config needed.
- **Cash drawer:** connect the drawer to your ESC/POS receipt printer's RJ11 port and set
  `POS_PRINTER_HOST`/`POS_PRINTER_PORT`. The app sends the standard drawer-kick pulse on cash
  sales (and via the "Open cash drawer after sale" toggle). If no printer is configured the
  sale still completes — the drawer step is simply skipped.

---

## Scheduled tasks

Enable Laravel's scheduler for automatic daily backups:

```bash
* * * * * cd /path/to/super-shop && php artisan schedule:run >> /dev/null 2>&1
```

You can also back up on demand: `php artisan supershop:backup`.

---

## Tech & architecture

- **Laravel 13**, MySQL, Blade + vanilla-JS interactivity (no build step).
- `spatie/laravel-permission` for roles & permissions.
- Business logic in service classes: `SaleService` (transactional checkout/refund),
  `BackupService` (mysqldump backup/restore), `DrawerService` (ESC/POS kick).
- Bespoke stylesheet in `public/css/app.css` + `public/css/pos.css` — blue/white theme,
  no Tailwind/Bootstrap.
