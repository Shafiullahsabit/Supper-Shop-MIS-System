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
- Stock tracking with **low-stock alerts**, manual stock adjustments (with full audit history).
- **Purchases / stock receiving** — recording a received purchase increases stock and
  updates cost price.

### Sales & Customers

- Full sales history with filters (date, payment, status), invoice detail pages.
- **Refunds** — restores stock, reverses loyalty & cash-session totals.
- Customer directory with **loyalty points** and lifetime spend tracking.

### Cash Register (shifts)

- Open a register with a starting float, take sales against the session, then close with a
  cash count → generates an **X/Z-report** showing expected vs. counted cash and any variance.

### Reports

- Sales report (by day / payment method / cashier), profit report (per-product margins),
  stock valuation report, top-selling products.

### Administration

- **Role-based access control** (Admin / Manager / Cashier + custom roles) with a granular
  permission matrix — 25+ permissions across every module.
- User management, activity/audit log, store settings (currency, tax, receipt footer, loyalty).
- **Database backups** — one-click `mysqldump` backup, download, restore, delete, plus a
  scheduled daily backup at 2:00 AM.

---

## Requirements

- PHP 8.3+
- Composer 2
- MySQL 8+ (with `mysqldump` / `mysql` binaries on `PATH` for the backup feature)
- Node is **not** required — the UI uses hand-written CSS/JS served from `public/`.

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

# 2. Generate app key (an .env is already configured for local MySQL as root/no-password)
php artisan key:generate

# 3. Create the database
mysql -uroot -e "CREATE DATABASE super_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Run migrations + seed demo data
php artisan migrate --seed
php artisan db:seed --class=DemoSalesSeeder   # optional: sample sales for the dashboard

# 5. Serve
php artisan serve
```

Then open [http://127.0.0.1:8000](http://127.0.0.1:8000).

### Environment settings (`.env`)

```env
DB_DATABASE=super_shop
DB_USERNAME=root
DB_PASSWORD=

# Cash-drawer / receipt printer (leave POS_PRINTER_HOST blank to disable the hardware kick)
POS_PRINTER_HOST=
POS_PRINTER_PORT=9100
```

---

## Demo accounts

| Role    | Email                  | Password     | Access                                           |
| ------- | ---------------------- | ------------ | ------------------------------------------------ |
| Admin   | admin@supershop.test   | `password` | Everything                                       |
| Manager | manager@supershop.test | `password` | Operations, reports (no users/roles/backups)     |
| Cashier | cashier@supershop.test | `password` | POS, register, view products/customers/own sales |

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
