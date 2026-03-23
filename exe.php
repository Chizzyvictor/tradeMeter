<?php

// ============================
//  FRESH SQLITE INITIALIZER
// ============================

class MyDB extends SQLite3 {
    public function __construct() {
        $this->open(__DIR__ . '/mysqlitedb.db');
    }
}

try {

    $db = new MyDB();
    $db->enableExceptions(true);
    $db->exec('PRAGMA foreign_keys = ON;');

    // ============================
    // CREATE TABLES
    // ============================

    $schema = [

        // -------------------------
        // COMPANY
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS company (
            cid INTEGER PRIMARY KEY AUTOINCREMENT,
            cName TEXT NOT NULL UNIQUE,
            cEmail TEXT NOT NULL UNIQUE,
            cPass TEXT NOT NULL,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            cLogo TEXT DEFAULT 'logo.jpg',
            regDate INTEGER DEFAULT (strftime('%s','now')),
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        ",

        // -------------------------
        // USERS
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            is_active INTEGER DEFAULT 1 CHECK (is_active IN (0,1)),
            created_at INTEGER DEFAULT (strftime('%s','now')),
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (cid, email)
        );
        ",

        // -------------------------
        // ROLES
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS roles (
            role_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            role_name TEXT NOT NULL,
            is_system INTEGER DEFAULT 0,
            created_at INTEGER DEFAULT (strftime('%s','now')),
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (cid, role_name)
        );
        ",

        // -------------------------
        // USER ROLES
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS user_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            role_id INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
            UNIQUE (user_id, role_id)
        );
        ",

        // -------------------------
        // PERMISSIONS
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS permissions (
            permission_id INTEGER PRIMARY KEY AUTOINCREMENT,
            permission_key TEXT NOT NULL UNIQUE
        );
        ",

        // -------------------------
        // ROLE PERMISSIONS
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            role_id INTEGER NOT NULL,
            permission_id INTEGER NOT NULL,
            FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE,
            UNIQUE (role_id, permission_id)
        );
        ",

        // -------------------------
        // PARTNER (SUPPLIER/CUSTOMER)
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS partner (
            sid INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            sName TEXT NOT NULL,
            sEmail TEXT,
            sPhone TEXT,
            sAddress TEXT,
            outstanding REAL DEFAULT 0.00,
            advancePayment REAL DEFAULT 0.00,
            sLogo TEXT DEFAULT 'user.jpg',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at INTEGER DEFAULT (strftime('%s','now')),
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (cid, sName)
        );
        ",

        // -------------------------
        // PRODUCT CATEGORIES
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS product_categories (
            category_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            category_name TEXT NOT NULL,
            category_description TEXT NOT NULL,
            is_active INTEGER DEFAULT 1 CHECK (is_active IN (0,1)),
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (cid, category_name)
        );
        ",

        // -------------------------
        // PRODUCTS
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS products (
            product_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            category_id INTEGER,
            product_name TEXT NOT NULL,
            product_image TEXT DEFAULT 'product.jpg',
            product_unit TEXT DEFAULT 'pcs',
            cost_price REAL DEFAULT 0.00,
            selling_price REAL DEFAULT 0.00,
            reorder_level INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1 CHECK (is_active IN (0,1)),
            timestamp INTEGER DEFAULT (strftime('%s','now')),
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES product_categories(category_id),
            UNIQUE (cid, product_name)
        );
        ",

        // -------------------------
        // INVENTORY
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS inventory (
            inventory_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            last_updated TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (product_id, cid)
        );
        ",

        // -------------------------
        // STOCK LEDGER
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS stock_ledger (
            ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            reference_type TEXT NOT NULL CHECK(reference_type IN ('purchase','sale','adjustment')),
            reference_id INTEGER,
            qty_in INTEGER DEFAULT 0,
            qty_out INTEGER DEFAULT 0,
            balance_after INTEGER NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
        );
        ",

        // -------------------------
        // PURCHASES
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS purchases (
            purchase_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            partner_id INTEGER NOT NULL,
            totalAmount REAL DEFAULT 0.00,
            amountPaid REAL DEFAULT 0.00,
            status TEXT NOT NULL CHECK(status IN ('pending','partial','paid')),
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (partner_id) REFERENCES partner(sid) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
        );
        ",

        // -------------------------
        // PURCHASE ITEMS
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS purchases_items (
            item_id INTEGER PRIMARY KEY AUTOINCREMENT,
            purchase_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            qty INTEGER NOT NULL,
            costPrice REAL NOT NULL,
            total REAL GENERATED ALWAYS AS (qty * costPrice) STORED,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_id) REFERENCES purchases(purchase_id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
        );
        ",

        // -------------------------
        // SALES
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS sales (
            sale_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            partner_id INTEGER NOT NULL,
            totalAmount REAL DEFAULT 0.00,
            amountPaid REAL DEFAULT 0.00,
            status TEXT NOT NULL CHECK(status IN ('pending','partial','paid')),
            createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (partner_id) REFERENCES partner(sid) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
        );
        ",

        // -------------------------
        // SALES ITEMS
        // -------------------------
        "
        CREATE TABLE IF NOT EXISTS sales_items (
            item_id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            qty INTEGER NOT NULL,
            costPrice REAL NOT NULL,
            total REAL GENERATED ALWAYS AS (qty * costPrice) STORED,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
        );
        "
        // -------------------------
        // PARTNER LEDGER
        // -------------------------
        ,
        "
        CREATE TABLE IF NOT EXISTS partner_ledger (
            ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER,
            sid INTEGER,
            type TEXT NOT NULL CHECK(type IN ('sell','buy','addDebt','payDebt')),
            debit REAL DEFAULT 0,
            credit REAL DEFAULT 0,
            outstanding REAL DEFAULT 0,
            advancePayment REAL DEFAULT 0,
            note TEXT,
            reference_id INTEGER,
            reference_type TEXT,
            createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            FOREIGN KEY (sid) REFERENCES partner(sid) ON DELETE CASCADE
        );
 "

    ];

    foreach ($schema as $sql) {
        $db->exec($sql);
    }

    // ============================
    // LEGACY MIGRATION (purchase -> purchases + sales)
    // ============================
    $hasLegacyPurchase = false;
    $legacyTables = [];
    $tableRes = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($tableRes && ($row = $tableRes->fetchArray(SQLITE3_ASSOC))) {
        $legacyTables[] = $row['name'] ?? '';
    }
    $hasLegacyPurchase = in_array('purchase', $legacyTables, true);
    $hasLegacyPurchaseItems = in_array('purchase_items', $legacyTables, true);

    if ($hasLegacyPurchase && $hasLegacyPurchaseItems) {
        $purchaseCount = intval($db->querySingle("SELECT COUNT(1) FROM purchases"));
        $saleCount = intval($db->querySingle("SELECT COUNT(1) FROM sales"));

        if ($purchaseCount === 0 && $saleCount === 0) {
            $db->exec("INSERT INTO purchases (cid, partner_id, totalAmount, amountPaid, status, createdAt)
                       SELECT cid, partner_id, totalAmount, amountPaid, status, createdAt
                       FROM purchase
                       WHERE LOWER(COALESCE(transaction_type,'buy')) IN ('buy','purchase')");

            $db->exec("INSERT INTO purchases_items (purchase_id, product_id, qty, costPrice, created_at)
                       SELECT p_new.purchase_id, pi.product_id, pi.qty, pi.costPrice, pi.created_at
                       FROM purchase_items pi
                       INNER JOIN purchase p_old ON p_old.purchase_id = pi.purchase_id
                       INNER JOIN purchases p_new
                           ON p_new.cid = p_old.cid
                          AND p_new.partner_id = p_old.partner_id
                          AND p_new.totalAmount = p_old.totalAmount
                          AND p_new.amountPaid = p_old.amountPaid
                          AND p_new.status = p_old.status
                          AND p_new.createdAt = p_old.createdAt
                       WHERE LOWER(COALESCE(p_old.transaction_type,'buy')) IN ('buy','purchase')");

            $db->exec("INSERT INTO sales (cid, partner_id, totalAmount, amountPaid, status, createdAt)
                       SELECT cid, partner_id, totalAmount, amountPaid, status, createdAt
                       FROM purchase
                       WHERE LOWER(COALESCE(transaction_type,'buy')) IN ('sell','sale')");

            $db->exec("INSERT INTO sales_items (sale_id, product_id, qty, costPrice, created_at)
                       SELECT s_new.sale_id, pi.product_id, pi.qty, pi.costPrice, pi.created_at
                       FROM purchase_items pi
                       INNER JOIN purchase p_old ON p_old.purchase_id = pi.purchase_id
                       INNER JOIN sales s_new
                           ON s_new.cid = p_old.cid
                          AND s_new.partner_id = p_old.partner_id
                          AND s_new.totalAmount = p_old.totalAmount
                          AND s_new.amountPaid = p_old.amountPaid
                          AND s_new.status = p_old.status
                          AND s_new.createdAt = p_old.createdAt
                       WHERE LOWER(COALESCE(p_old.transaction_type,'buy')) IN ('sell','sale')");
        }
    }

    // ============================
    // INDEXES
    // ============================

    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_partner_company ON partner(cid);",
        "CREATE INDEX IF NOT EXISTS idx_partner_company_sid ON partner(cid, sid);",
        "CREATE INDEX IF NOT EXISTS idx_product_company ON products(cid);",
        "CREATE INDEX IF NOT EXISTS idx_product_category ON products(category_id);",
        "CREATE INDEX IF NOT EXISTS idx_inventory_company ON inventory(cid);",
        "CREATE INDEX IF NOT EXISTS idx_stock_ledger_product ON stock_ledger(product_id);",
        "CREATE INDEX IF NOT EXISTS idx_purchase_company ON purchases(cid);",
        "CREATE INDEX IF NOT EXISTS idx_purchase_partner ON purchases(partner_id);",
        "CREATE INDEX IF NOT EXISTS idx_purchase_items_purchase ON purchases_items(purchase_id);",
        "CREATE INDEX IF NOT EXISTS idx_purchase_items_product ON purchases_items(product_id);",
        "CREATE INDEX IF NOT EXISTS idx_purchase_items_purchase_product ON purchases_items(purchase_id, product_id);",
        "CREATE INDEX IF NOT EXISTS idx_sales_company ON sales(cid);",
        "CREATE INDEX IF NOT EXISTS idx_sales_partner ON sales(partner_id);",
        "CREATE INDEX IF NOT EXISTS idx_sales_items_sale ON sales_items(sale_id);",
        "CREATE INDEX IF NOT EXISTS idx_sales_items_product ON sales_items(product_id);",
        "CREATE INDEX IF NOT EXISTS idx_sales_items_sale_product ON sales_items(sale_id, product_id);",
        "CREATE INDEX IF NOT EXISTS idx_partner_ledger_company ON partner_ledger(cid);",
        "CREATE INDEX IF NOT EXISTS idx_partner_ledger_partner ON partner_ledger(sid);",
        "CREATE INDEX IF NOT EXISTS idx_users_cid ON users(cid);",
        "CREATE INDEX IF NOT EXISTS idx_user_roles_user ON user_roles(user_id);",
        "CREATE INDEX IF NOT EXISTS idx_role_permissions_role ON role_permissions(role_id);"
    ];

    foreach ($indexes as $index) {
        $db->exec($index);
    }

    echo "✅ Fresh SQLite schema created successfully.";

} catch (Throwable $e) {
    http_response_code(500);
    echo "❌ Initialization failed: " . $e->getMessage();
}

  $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                cid INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at INTEGER NOT NULL,
                created_at INTEGER DEFAULT (strftime('%s','now'))
            )");
