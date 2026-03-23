# Transactions Modules

This folder contains the modular JavaScript implementation for the Transactions page.

## Load Order

The files are loaded in this order from `transactions.php`:

1. `transaction-manager.js`
2. `autocomplete.js`
3. `items.js`
4. `transactions-api.js`
5. `history.js`
6. `bootstrap.js`

Keep this order unless you also refactor dependencies.

## Module Responsibilities

- **transaction-manager.js**
  - Declares `TransactionManager` class
  - Holds shared state (partners, products, cart, history)
  - Wires DOM events
  - Provides shared utilities (`debug`, error helpers, tab switching, date defaults, modal focus restore)

- **autocomplete.js**
  - Loads partners/products
  - Renders and resolves partner/product suggestions
  - Stock/rate helpers (`validateStockForSell`, `getProductRateByType`, etc.)

- **items.js**
  - Add/edit/remove cart items
  - Render transaction items table
  - Maintain totals and process-button state

- **transactions-api.js**
  - Form validation before save
  - Build API payload
  - Save transaction (`createTransaction`)

- **history.js**
  - Load/filter/render transaction history
  - Render details modal
  - Handle payment flow for purchases
  - Populate type/unit select options

- **bootstrap.js**
  - Page entrypoint
  - Instantiates `TransactionManager`
  - Exposes `window.TransactionApp`

## Maintenance Rules

- Keep DOM IDs in `transactions.php` synchronized with selectors used in these modules.
- Prefer adding new feature logic to the most specific module instead of `transaction-manager.js`.
- If a feature touches multiple modules, keep shared helpers on `TransactionManager.prototype` and avoid duplicate logic.
- Preserve accessibility behavior:
  - `AppCore.safeHideModal(...)` when closing modals programmatically
  - focus restoration handlers in `transaction-manager.js`

## Debugging

- Toggle debug logs by changing `this.debugMode` in `TransactionManager` constructor.
- Common checks:
  - Missing selector/ID mismatch in `transactions.php`
  - API response shape mismatch in `apiTransactions.php`
  - Module load-order issue in `transactions.php`
