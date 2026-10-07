# Ream Business Manager

Internal tool for the ream business (CR, CB, PR, CC): stock in, distributions to resellers and retail, remittances, expenses, balances and profit. It replaces `Ream_Business_Tracker_v3.xlsx`.

- **Staff app** (`/`): mobile-first React app for daily encoding. Every role can use it.
- **Admin panel** (`/admin`): Filament back-office for admins and super admins.
- One Laravel 12 monolith with one MySQL database (`smoke_app_db`).

Resellers never log in. They exist only as records.

---

## Setup in 3 commands

Requirements: PHP 8.3+ (with `pdo_mysql`, `intl`, `zip`, `gd`), Composer 2, Node 20+, and MySQL 8 with an empty database called `smoke_app_db` (user `root`, empty password).

```bash
composer run setup                                  # install, .env, key, migrate, seed, test DB, build assets
php artisan import:tracker Ream_Business_Tracker_v3.xlsx   # optional: load the Excel tracker
composer run dev                                    # PHP server + queue worker + Vite
```

Open http://127.0.0.1:8000 and log in.

`composer run setup` also creates the separate **`smoke_app_db_test`** database. Tests only ever run against that database, and a guard in `tests/TestCase.php` refuses to run them anywhere else. Run the tests with `composer test` or `php artisan test`.

## Default logins

All accounts use the password **`password`**. Change these passwords before real use.

| Email | Role | What they can do |
|---|---|---|
| `superadmin@example.com` | Super admin | Everything, including managing admins, restoring archived records and app settings |
| `admin@example.com` | Admin | Admin panel, reports, confirm remittances, void, override prices, manage staff |
| `staff@example.com` | Staff | Staff app only: encode entries and see stock quantities and balances (never costs or profit) |

Accounts created inside the app get a temporary password and must change it on first login. There is no public registration.

## How price rules work

Every price is a row in **Price Rules** (Admin → Master Data → Price Rules). To change a price, you **add a new rule** with the date it takes effect. You don't edit the old one.

| Rule | "Applies to" | Used for |
|---|---|---|
| **Default** | blank | Every reseller. Its *retail price* is used for the **Retail** (walk-in) customer. |
| **Special** | a reseller | That reseller only, e.g. Plaza: CR ₱800, CB ₱850, PR ₱400, CC ₱800 |

When a distribution is saved, the app looks up the price for the product, reseller and date:

1. If the customer is **Retail**, it uses the latest default rule's **retail price**.
2. Otherwise, if the reseller has a **special** rule, it uses the latest one.
3. Otherwise, it uses the latest **default** reseller price.
4. If no rule is found, the entry is blocked with a clear message.
5. Admins may **override** the price manually, with a reason.

"Latest" means the newest effective date on or before the distribution's date.

**The price is saved onto the distribution.** Adding or changing rules later never changes past entries by itself. Rules already used by a distribution are locked (🔒) and can't be edited.

**Back-dated changes.** If you add a rule with an effective date in the past, the admin shows a warning. On that rule, click **"Apply to existing transactions"** to see a preview of every affected distribution: date, reseller, qty, old → new price, old → new total, and the difference. Nothing changes until you confirm. The update runs in one database transaction and is written to the Audit Log. Voided and manually priced entries are never re-priced.

## Importing the Excel tracker

```bash
php artisan import:tracker path/to/Ream_Business_Tracker_v3.xlsx          # add rows not imported yet
php artisan import:tracker path/to/Ream_Business_Tracker_v3.xlsx --fresh  # wipe all transactions first, then import
```

- Every sheet has its header on row 4 and data from row 5. Blank rows are skipped.
- Rows whose notes contain "SAMPLE", and anything for "Sample Reseller", are skipped.
- Distribution prices are **recomputed** from the PriceHistory sheet. The Excel formula column is ignored.
- Imported remittances are confirmed.
- Everything runs in one transaction. If anything fails, nothing is saved.
- Re-running is safe: each imported row has a fingerprint and is never imported twice. Use `--fresh` to start the transactions over. It asks for confirmation and deletes distributions, remittances, purchases and expenses (products, resellers, price rules and users are kept).
- When it finishes, it prints counts per table, every skipped row with the reason, and each reseller's balance.

**Results from the Oct 07, 2026 workbook** (they match the Excel dashboard exactly):

| Figure | Value |
|---|---|
| Tito Richie | 60 reams, ₱44,370.00 taken, ₱32,960.00 remitted, **₱11,410.00 balance** |
| CR price | ₱740 before Sep 27, 2026; ₱730 from Sep 27, 2026 |
| Sales / COGS / gross profit | ₱44,370.00 / ₱41,252.35 / ₱3,117.65 |
| Expenses / net profit | ₱1,600.00 / ₱1,517.65 |
| Stock | 68 reams, worth ₱42,347.65 at average cost |

## Backing up the database

```bash
mysqldump -u root smoke_app_db > backup.sql
```

With Laragon, `mysqldump` is not on the PATH, so use its full path:

```bash
"C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump.exe" -u root smoke_app_db > backup.sql
```

To restore: `mysql -u root smoke_app_db < backup.sql`. Uploaded remittance proof photos are stored in `storage/app/public/remittance-proofs`; back up that folder too.

## Business rules

| Rule | How it works |
|---|---|
| Stock | Stock = purchases − distributions, ignoring voided rows. A distribution that would make stock negative is blocked. Low stock means stock ≤ the product's reorder level. |
| Cost and profit | Average cost per product = total purchase cost ÷ total reams purchased. COGS = reams distributed × average cost. Gross profit = sales − COGS. Net profit = gross profit − expenses. Cash position = confirmed remittances − purchases − expenses. |
| Balances | Balance = distributions − **confirmed** remittances. Statuses: *May Balance* (> 0), *Paid* (= 0), *Overpaid* (< 0). Remittances encoded by staff stay *pending* until an admin confirms them. Pending amounts are shown separately and don't reduce the balance. |
| Editing and voiding | Nothing is ever deleted. Admins void with a reason. Staff can edit their own entries only on the same day, and only before they are confirmed or voided. |
| Audit | Creates, updates, voids, re-prices and confirm/reject actions are logged with who, when and before/after values (Admin → Settings → Audit Log). |
| Staff privacy | Staff never receive cost, profit or stock-value data, not even hidden in page data. A test scans every staff response to check this. |

## Project layout

```
app/Services/            business rules: Pricing/PriceResolver, Pricing/RepricingService, StockService,
                         Balances/BalanceService, ProfitService, ReportService, Import/TrackerImporter, …
app/Policies/            access matrix (plus gates in AppServiceProvider)
app/Http/Controllers/    thin controllers (Staff/*, Admin/ReportController)
app/Http/Requests/Staff  form validation
app/Filament/            admin resources, pages (dashboard, reports, settings) and widgets
resources/js/            React staff app (pages/, components/staff/, layouts/staff-layout.tsx)
resources/css/app.css    staff app theme (Tailwind v4)
resources/css/filament/  admin theme (separate Vite entry)
tests/                   Pest tests; tests/fixtures/tracker_fixture.xlsx for the import
```
