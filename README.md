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

## Notes

- The frontend is intentionally modular (especially transactions) to simplify maintenance.
- Most APIs return JSON with a standard `status` + `text` response shape.
- The app is designed for one database file per deployment and company-level data isolation by `cid`.
