# TradeMeter

TradeMeter is a multi-tenant inventory and transaction management web app for small-to-medium businesses.

It helps each company manage:
- partners (customers/suppliers),
- inventory and stock movement,
- purchases and sales,
- receivables/payables,
- role-based access and session security,
- operational dashboards and audit logs.

---

## Tech Stack

- **Backend:** PHP (procedural + helper functions)
- **Database:** SQLite (`mysqlitedb.db`)
- **Frontend:** jQuery + Bootstrap + plain JavaScript modules
- **Charts/Exports:** Chart.js, jsPDF, xlsx (vendor assets in `assets/vendor`)

---

## Core Modules

### 1) Authentication & Company Access

- Company-aware login (`Company (Email or Name)` + `User Email` + `Password`)
- Signup flow creates company + owner user
- Email verification required before login
- Remember-me token flow (30 days)
- Forgot-password flow (security question/answer)
- Public company directory page (`companies.php`)

### 2) Dashboard

- Financial and operational KPIs:
  - outstanding balances,
  - advance payments,
  - active debtors/creditors,
  - sales/purchases,
  - transaction counts by date range,
  - inventory value,
  - profit.
- Date-range filtering (Today / 7 days / 30 days / All)
- Top entities widgets:
  - top selling products,
  - top suppliers,
  - top buyers.

### 3) Partners

- Create, edit, delete partner records
- Partner filters:
  - all partners,
  - active debtors,
  - active creditors.
- Partner details view with:
  - transaction history,
  - ledger timeline,
  - debt/credit operations (`Add Debt`, `Pay Debt`).

### 4) Inventory

- Category management
- Product management (with image, unit, reorder level, pricing)
- Restocking and stock updates
- Low-stock detection
- Global stock movement log (ledger)
- Product-level details:
  - transaction history,
  - stock movement history.

### 5) Transactions

- Buy/Sell transaction entry
- Partner and product autocomplete
- Line-item cart with quantity/rate/amount calculations
- Payment handling (`total`, `paying`, `balance`)
- Transaction history with filters and detail views
- Inventory automatically updated from transaction postings

### 6) Settings & Admin Controls

- Company profile update (name, email, logo)
- Security Q&A update
- Password change
- User management:
  - create users,
  - role assignment,
  - status toggle,
  - demo user seeding.
- Security/audit operations:
  - remember-token audit feed,
  - active session list + revoke,
  - logout-all-devices,
  - login activity logs.
- SMTP test email trigger.

---

## Application Flows

## A) New Company Onboarding

1. User signs up with company details and owner credentials.
2. System creates company + owner user.
3. Verification email is sent with time-limited token.
4. User opens verification link (`verify_email.php`).
5. User logs in and lands on dashboard.

## B) Daily Operating Flow

1. Add categories and products in Inventory.
2. Register suppliers/customers in Partners.
3. Record purchases and sales in Transactions.
4. Monitor balances and partner ledgers.
5. Track KPIs from dashboard by date range.

## C) Security & Session Flow

1. User logs in (rate-limited endpoint).
2. Session is tracked in `user_sessions`.
3. Optional remember token is issued and audited.
4. Admin can review sessions and revoke devices.
5. Logout (single or all devices) invalidates sessions/tokens.

---

## Project Structure (High Level)

- `index.php` – dashboard
- `login.php` – login/signup/forgot password UI
- `partners.php` – partner management
- `inventory.php` – inventory & stock movement
- `transactions.php` – buy/sell operations + history
- `settings.php` – profile, user/admin, security tools
- `companies.php` – public company listing
- `verify_email.php` – email verification endpoint/page

- `apiAuthentications.php` – auth/session/email endpoints
- `apiRequest.php` – dashboard/summary endpoints
- `apiPartners.php` – partner endpoints
- `apiInventory.php` – inventory endpoints
- `apiTransactions.php` – transaction endpoints
- `apiSettings.php` – settings/admin endpoints

- `scripts/` – frontend JS controllers by page
- `styles/` – app styling
- `INC/` – shared layout/auth includes
- `assets/vendor/` – third-party frontend libraries

---

## API Action Reference

Use `POST` requests with an `action` parameter to call backend handlers.

### `apiAuthentications.php`

- `login`
- `signup`
- `logout`
- `logoutAllDevices`
- `cLogo`
- `loadCompanies`
- `requestPasswordReset`
- `forgotQandA`
- `resetPassword`
- `getUserPermissions`
- `getCurrentUserContext`
- `sendSmtpTestEmail`

### `apiRequest.php`

- `loadDashboard`
- `loadPartners`

### `apiPartners.php`

- `addPartner`
- `editPartner`
- `deletePartner`
- `loadAllPartners`
- `loadActivePartnerDebtors`
- `loadActivePartnerCreditors`
- `loadPartnerDetails`
- `payDebt`
- `addDebt`

### `apiInventory.php`

- `createCategory`
- `loadCategories`
- `createProduct`
- `loadInventory`
- `loadStockLedger`
- `editCategory`
- `deleteCategory`
- `loadProductDetails`
- `editProduct`
- `restockProduct`
- `deleteProduct`
- `loadLowStock`

### `apiTransactions.php`

- `loadProducts`
- `createTransaction`
- `createPurchase`
- `loadPurchases`
- `loadPurchaseDetails`
- `payPurchase`

### `apiSettings.php`

- `loadSettings`
- `updateProfile`
- `updateSecurity`
- `changePassword`
- `loadRoles`
- `loadUsers`
- `createUser`
- `updateUserRole`
- `toggleUserStatus`
- `seedDemoUsers`
- `loadRememberAudit`
- `loadActiveSessions`
- `revokeSession`
- `loadLoginLogs`

---

## Security Features Implemented

- RBAC foundation (users, roles, permissions)
- Permission-aware APIs and UI context
- Login rate limiting
- Session fixation mitigation (`session_regenerate_id`)
- Device/session tracking and revocation
- Remember-token hashing + rotation + audit
- Login activity logging
- CSRF token session usage
- Email verification gating
- Secure cookie options (`httponly`, `samesite`, HTTPS-aware)

---

## Local Setup

### Prerequisites

- PHP 7.4+ (or PHP 8.x)
- SQLite extension enabled in PHP
- Web server (Laragon / Apache / Nginx + PHP)

### Run

1. Place project in your web root (example: `C:\laragon\www\TradeMeter`).
2. Start your local web server.
3. Open:
   - `http://localhost/TradeMeter/login.php` (main entry)
   - `http://localhost/TradeMeter/companies.php` (public companies list)

> Database file (`mysqlitedb.db`) is used directly by the app and evolves at runtime (schema guards and migrations are embedded in APIs).

---

## SMTP / Email Configuration (Optional)

Email sending supports SMTP via environment variables (with optional PHPMailer autoload), with fallback to native `mail()`.

Set as needed:

- `SMTP_HOST`
- `SMTP_PORT` (default: `587`)
- `SMTP_AUTH` (`true`/`false`)
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_ENCRYPTION` (`tls` or `ssl`)
- `SMTP_FROM_EMAIL`
- `SMTP_FROM_NAME`

---

## Deployment Checklist

Use this checklist before going live:

1. **Server & PHP**
  - Use PHP 8.x (or supported 7.4+) with SQLite enabled.
  - Disable PHP error display in production (`display_errors=0`) and enable error logging.

2. **Environment Variables**
  - Copy values from `.env.example` into your server environment.
  - Set real SMTP credentials if verification and test emails are required.

3. **Filesystem Permissions**
  - Ensure the app can read/write `mysqlitedb.db`.
  - Ensure image upload directories are writable:
    - `Images/companyDP/`
    - `Images/partnersDP/`
    - `Images/productsDP/`

4. **Security Hardening**
  - Serve the app over HTTPS.
  - Keep web root clean from local-only artifacts (`.hopweb/`, local DB snapshots, logs, APK outputs).
  - Do not expose backups or database files publicly.

5. **App Verification**
  - Complete signup and verify email flow.
  - Confirm login/logout, remember-me, and session revocation behavior.
  - Run a smoke test on dashboard, partners, inventory, transactions, and settings pages.

6. **Backup & Recovery**
  - Schedule regular backups of `mysqlitedb.db`.
  - Test restore procedure at least once before production launch.

---

## Deploying to Hostinger (Step-by-Step)

Hostinger shared hosting runs Apache/LiteSpeed with PHP and supports SQLite3 — no extra configuration needed.

### 1 — Buy a plan & point a domain

1. Purchase a **Web Hosting** plan (Business or higher recommended for SQLite write performance).
2. In **hPanel → Domains**, add or connect your domain.
3. In **hPanel → Advanced → PHP Configuration**, select **PHP 8.2** (or 8.1 / 8.0).  
   SQLite3 (PDO) is enabled by default.

### 2 — Upload project files

**Option A — File Manager (easiest)**

1. hPanel → **File Manager** → open `public_html/`.
2. Delete the default placeholder files (`index.html`, etc.) if present.
3. Click **Upload** → select all project files as a ZIP → then **Extract** inside `public_html/`.

**Option B — FTP/SFTP**

1. hPanel → **Files → FTP Accounts** → note the host, user, and password.
2. Use FileZilla (free): connect, upload everything into `public_html/`.

> **Do NOT upload** `.git/`, `.hopweb/`, your local `mysqlitedb.db`, or `TradeMeter.apk`.  
> These are already excluded by `.gitignore` if deploying via Git (see Option C below).

**Option C — Git (cleanest)**

```bash
# SSH into your Hostinger account (hPanel → Advanced → SSH Access)
ssh u123456@srv123.hostinger.com

cd public_html
git clone https://github.com/Chizzyvictor/tradeMeter .
```

The `.gitignore` already excludes all local runtime artifacts.

### 3 — Set file permissions

From SSH or File Manager, set these exact permissions:

| Path | Permission |
|---|---|
| `mysqlitedb.db` | `664` |
| `Images/companyDP/` | `775` |
| `Images/partnersDP/` | `775` |
| `Images/productsDP/` | `775` |

In File Manager: right-click each → **Permissions** → enter the octal value.  
Via SSH:
```bash
chmod 664 mysqlitedb.db
chmod 775 Images/companyDP Images/partnersDP Images/productsDP
```

> If `mysqlitedb.db` doesn't exist yet, the app creates it automatically on first request — just make sure the folder is writable (`public_html/` itself already is).

### 4 — Configure SMTP (email verification)

1. hPanel → **Emails → Email Accounts** → create `no-reply@yourdomain.com`.
2. In hPanel → **File Manager**, open `.htaccess` in `public_html/`.
3. Find the commented SMTP block at the bottom and uncomment + fill in your values:

```apache
SetEnv SMTP_HOST smtp.hostinger.com
SetEnv SMTP_PORT 587
SetEnv SMTP_AUTH true
SetEnv SMTP_USERNAME no-reply@yourdomain.com
SetEnv SMTP_PASSWORD your_email_password
SetEnv SMTP_ENCRYPTION tls
SetEnv SMTP_FROM_EMAIL no-reply@yourdomain.com
SetEnv SMTP_FROM_NAME TradeMeter
```

> Hostinger's SMTP host is always `smtp.hostinger.com` for email accounts on their servers.

### 5 — Install PHPMailer (if not already present)

If `vendor/` is not uploaded (Git clone skips it if it's in `.gitignore`), install via SSH:

```bash
cd ~/public_html
curl -sS https://getcomposer.org/installer | php
php composer.phar require phpmailer/phpmailer
```

If you uploaded the full project including `vendor/`, skip this step.

### 6 — Verify SSL / HTTPS

1. hPanel → **SSL → SSL/TLS** → enable **Hostinger Free SSL** (Let's Encrypt) for your domain.
2. The `.htaccess` already forces HTTP → HTTPS redirect automatically.

### 7 — Smoke test

Open your domain and run through:

- [ ] Sign up → receive verification email → verify account
- [ ] Log in / log out
- [ ] Add a partner
- [ ] Add an inventory item (with image upload)
- [ ] Record a transaction
- [ ] Check the dashboard widget totals
- [ ] Log out and confirm session expires
- [ ] Test remember-me (tick the checkbox at login, close browser, reopen)

### Ongoing backups

Hostinger does weekly automated backups on Business plans. For daily protection, also schedule a manual backup of `mysqlitedb.db`:

```bash
# in a Hostinger Cron Job (hPanel → Advanced → Cron Jobs)
# Run daily at 2 AM
0 2 * * * cp ~/public_html/mysqlitedb.db ~/backups/mysqlitedb_$(date +\%Y\%m\%d).db
```

---

## Notes

- The frontend is intentionally modular (especially transactions) to simplify maintenance.
- Most APIs return JSON with a standard `status` + `text` response shape.
- The app is designed for one database file per deployment and company-level data isolation by `cid`.

---

## Deploying to Heroku

TradeMeter can run on Heroku using the PHP buildpack, but there is a **critical limitation**:

- Heroku dyno filesystem is **ephemeral**.
- `mysqlitedb.db` and uploaded images under `Images/` are lost whenever dynos restart or redeploy.

### Recommended approach

Use Heroku only for short-term demos unless you migrate to persistent storage:

1. Move database from SQLite to a managed DB (for example PostgreSQL).
2. Move image uploads to object storage (for example Cloudinary / S3).

### Quick deploy (current SQLite version)

1. Install prerequisites:
  - Heroku CLI
  - Git
  - Composer (optional locally; Heroku runs Composer during build)

2. Login and create app:

```bash
heroku login
heroku create your-trademeter-app
```

3. Set PHP stack/buildpack (optional but explicit):

```bash
heroku stack:set heroku-22
heroku buildpacks:set heroku/php
```

4. Configure environment variables (replace with your real values):

```bash
heroku config:set SMTP_HOST=smtp.hostinger.com
heroku config:set SMTP_PORT=587
heroku config:set SMTP_AUTH=true
heroku config:set SMTP_USERNAME=no-reply@yourdomain.com
heroku config:set SMTP_PASSWORD=your_email_password
heroku config:set SMTP_ENCRYPTION=tls
heroku config:set SMTP_FROM_EMAIL=no-reply@yourdomain.com
heroku config:set SMTP_FROM_NAME=TradeMeter
```

5. Deploy:

```bash
git push heroku master
```

6. Open app:

```bash
heroku open
```

### Important operational notes on Heroku

- Email features work with config vars, because `envValue()` already reads environment variables.
- `.htaccess` exists in this repo for Apache behavior; Heroku uses Apache via `Procfile`.
- The app now includes a DB connection scaffold in `INC/db.php` with `DATABASE_URL` parsing for the PostgreSQL migration path.
- `apiAuthentications.php` now uses the compatibility connection (`appDbConnectCompat()`), allowing SQLite or PostgreSQL selection via environment.
- Current runtime still executes using SQLite (`appDbConnect()`), with optional `SQLITE_DB_PATH` override.
- Do not rely on `mysqlitedb.db` persistence on Heroku.
- Do not rely on local `Images/` folder persistence on Heroku.

### Optional: one-off data backup commands

You can still pull snapshots during a dyno lifecycle:

```bash
heroku run bash
tar -czf /tmp/trademeter_runtime_backup.tgz mysqlitedb.db Images
exit
heroku ps:copy /tmp/trademeter_runtime_backup.tgz ./trademeter_runtime_backup.tgz
```

If this command is unavailable in your Heroku CLI version, use a custom script or migrate to persistent storage.
