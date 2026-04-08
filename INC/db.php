<?php

if (!defined('SQLITE3_ASSOC')) {
    define('SQLITE3_ASSOC', 1);
}
if (!defined('SQLITE3_NUM')) {
    define('SQLITE3_NUM', 2);
}
if (!defined('SQLITE3_BOTH')) {
    define('SQLITE3_BOTH', 3);
}
if (!defined('SQLITE3_INTEGER')) {
    define('SQLITE3_INTEGER', 1);
}
if (!defined('SQLITE3_FLOAT')) {
    define('SQLITE3_FLOAT', 2);
}
if (!defined('SQLITE3_TEXT')) {
    define('SQLITE3_TEXT', 3);
}
if (!defined('SQLITE3_BLOB')) {
    define('SQLITE3_BLOB', 4);
}
if (!defined('SQLITE3_NULL')) {
    define('SQLITE3_NULL', 5);
}

function appEnv(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value !== false && $value !== null && $value !== '') {
        return (string)$value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string)$_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return (string)$_SERVER[$key];
    }

    return $default;
}

function appDbDriver(): string {
    $databaseUrl = trim((string)appEnv('DATABASE_URL', ''));
    if ($databaseUrl !== '') {
        return 'pgsql';
    }

    return 'sqlite';
}

function appSqlitePath(): string {
    $configured = trim((string)appEnv('SQLITE_DB_PATH', ''));
    if ($configured !== '') {
        return $configured;
    }

    return __DIR__ . '/../mysqlitedb.db';
}

function appPostgresConfig(): array {
    $databaseUrl = trim((string)appEnv('DATABASE_URL', ''));
    if ($databaseUrl === '') {
        return [];
    }

    $parts = parse_url($databaseUrl);
    if (!is_array($parts)) {
        return [];
    }

    return [
        'scheme' => strtolower((string)($parts['scheme'] ?? '')),
        'host' => (string)($parts['host'] ?? ''),
        'port' => intval($parts['port'] ?? 5432),
        'user' => (string)($parts['user'] ?? ''),
        'pass' => (string)($parts['pass'] ?? ''),
        'dbname' => ltrim((string)($parts['path'] ?? ''), '/'),
        'query' => (string)($parts['query'] ?? ''),
        'sslmode' => trim((string)appEnv('PGSSLMODE', 'require')),
    ];
}

function appDbConnect(): SQLite3 {
    $db = new SQLite3(appSqlitePath());
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA foreign_keys = ON;');
    return $db;
}

function appPgLegacyColumnAliases(): array {
    static $aliases = null;
    if ($aliases !== null) {
        return $aliases;
    }

    $aliases = [
        'cname' => 'cName',
        'cemail' => 'cEmail',
        'cpass' => 'cPass',
        'clogo' => 'cLogo',
        'regdate' => 'regDate',
        'sname' => 'sName',
        'semail' => 'sEmail',
        'sphone' => 'sPhone',
        'saddress' => 'sAddress',
        'slogo' => 'sLogo',
        'advancepayment' => 'advancePayment',
        'createdat' => 'createdAt',
        'totalamount' => 'totalAmount',
        'amountpaid' => 'amountPaid',
        'costprice' => 'costPrice',
    ];

    return $aliases;
}

function appNormalizePgAssocRow($row) {
    if (!is_array($row)) {
        return $row;
    }

    foreach (appPgLegacyColumnAliases() as $lowercaseKey => $legacyKey) {
        if (!array_key_exists($legacyKey, $row) && array_key_exists($lowercaseKey, $row)) {
            $row[$legacyKey] = $row[$lowercaseKey];
        }
    }

    return $row;
}

function appEnsureUserVerificationColumns(AppDbConnection $db): void {
    $columns = [];
    $res = $db->query("PRAGMA table_info(users)");
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $columns[] = strtolower((string)($row['name'] ?? ''));
    }

    $addedVerifiedColumn = false;
    if (!in_array('email_verified_at', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verified_at INTEGER");
        $addedVerifiedColumn = true;
    }
    if (!in_array('email_verification_token_hash', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verification_token_hash TEXT");
    }
    if (!in_array('email_verification_expires_at', $columns, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN email_verification_expires_at INTEGER");
    }

    if ($addedVerifiedColumn) {
        $db->exec("UPDATE users SET email_verified_at = strftime('%s','now') WHERE email_verified_at IS NULL");
    }
}

function appEnsureSecurityTables(AppDbConnection $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS remember_token_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT NOT NULL,
        user_id INTEGER,
        cid INTEGER,
        ip_address TEXT,
        user_agent TEXT,
        details TEXT,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        cid INTEGER,
        session_id TEXT NOT NULL UNIQUE,
        ip_address TEXT,
        user_agent TEXT,
        last_activity INTEGER,
        created_at INTEGER
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        cid INTEGER,
        ip_address TEXT,
        user_agent TEXT,
        login_time INTEGER,
        status TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL UNIQUE,
        attempts INTEGER NOT NULL DEFAULT 0,
        last_attempt INTEGER NOT NULL
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_remember_audit_created_at ON remember_token_audit(created_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id, cid)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_login_logs_time ON login_logs(login_time)");
}

function appEnsureRbacSchema(AppDbConnection $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        user_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        full_name TEXT NOT NULL,
        email TEXT NOT NULL,
        password TEXT NOT NULL,
        is_active INTEGER DEFAULT 1 CHECK (is_active IN (0,1)),
        created_at INTEGER DEFAULT (strftime('%s','now')),
        UNIQUE (cid, email)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS roles (
        role_id INTEGER PRIMARY KEY AUTOINCREMENT,
        cid INTEGER NOT NULL,
        role_name TEXT NOT NULL,
        is_system INTEGER DEFAULT 0,
        created_at INTEGER DEFAULT (strftime('%s','now')),
        UNIQUE (cid, role_name)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role_id INTEGER NOT NULL,
        UNIQUE (user_id, role_id)
    )");

    appEnsureUserVerificationColumns($db);

    $db->exec("CREATE TABLE IF NOT EXISTS permissions (
        permission_id INTEGER PRIMARY KEY AUTOINCREMENT,
        permission_key TEXT NOT NULL UNIQUE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL,
        UNIQUE (role_id, permission_id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        cid INTEGER NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        created_at INTEGER DEFAULT (strftime('%s','now'))
    )");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_token_hash ON remember_tokens(token_hash)");

    appEnsureSecurityTables($db);
}

function appEnsureCoreBusinessSchema(AppDbConnection $db): void {
    $schema = [
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
        "
        CREATE TABLE IF NOT EXISTS inventory (
            inventory_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            quantity INTEGER NOT NULL DEFAULT 0,
            fraction_qty REAL NOT NULL DEFAULT 0,
            last_updated TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            UNIQUE (product_id, cid)
        );
        ",
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
        "
        CREATE TABLE IF NOT EXISTS sales_items (
            item_id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            qty INTEGER NOT NULL,
            costPrice REAL NOT NULL,
            sale_unit TEXT,
            fraction_length REAL,
            fraction_width REAL,
            fraction_qty REAL,
            display_label TEXT,
            total REAL GENERATED ALWAYS AS (qty * costPrice) STORED,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
        );
        ",
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
        ",
        "
        CREATE TABLE IF NOT EXISTS attendance_policies (
            policy_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            resumption_time TEXT NOT NULL DEFAULT '09:00',
            fine_0_15 REAL NOT NULL DEFAULT 200,
            fine_15_60 REAL NOT NULL DEFAULT 500,
            fine_60_plus REAL NOT NULL DEFAULT 1000,
            updated_by INTEGER,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL,
            UNIQUE (cid)
        );
        ",
        "
        CREATE TABLE IF NOT EXISTS employee_shift_rules (
            shift_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            shift_start TEXT NOT NULL DEFAULT '09:00',
            shift_end TEXT NOT NULL DEFAULT '17:00',
            grace_minutes INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (cid, user_id)
        );
        ",
        "
        CREATE TABLE IF NOT EXISTS employee_attendance_logs (
            attendance_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            attendance_date TEXT NOT NULL,
            signin_at TEXT,
            signout_at TEXT,
            signin_method TEXT NOT NULL DEFAULT 'pin',
            minutes_late INTEGER DEFAULT 0,
            late_grade TEXT DEFAULT 'on_time',
            fine_amount REAL DEFAULT 0,
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE (cid, user_id, attendance_date)
        );
        ",
        "
        CREATE TABLE IF NOT EXISTS attendance_corrections (
            correction_id INTEGER PRIMARY KEY AUTOINCREMENT,
            cid INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            attendance_date TEXT NOT NULL,
            requested_by INTEGER,
            current_signin_at TEXT,
            current_signout_at TEXT,
            proposed_signin_at TEXT,
            proposed_signout_at TEXT,
            reason TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            reviewed_by INTEGER,
            review_note TEXT,
            reviewed_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        ",
    ];

    foreach ($schema as $sql) {
        $db->exec($sql);
    }
}

function appEnsureCoreBusinessIndexes(AppDbConnection $db): void {
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
        "CREATE INDEX IF NOT EXISTS idx_role_permissions_role ON role_permissions(role_id);",
        "CREATE INDEX IF NOT EXISTS idx_sales_cid_createdAt ON sales(cid, createdAt);",
        "CREATE INDEX IF NOT EXISTS idx_purchases_cid_createdAt ON purchases(cid, createdAt);",
        "CREATE INDEX IF NOT EXISTS idx_purchases_partner_cid ON purchases(partner_id, cid);",
        "CREATE INDEX IF NOT EXISTS idx_sales_partner_cid ON sales(partner_id, cid);",
        "CREATE INDEX IF NOT EXISTS idx_inventory_cid_product_id ON inventory(cid, product_id);",
        "CREATE INDEX IF NOT EXISTS idx_attendance_logs_cid_date ON employee_attendance_logs(cid, attendance_date);",
        "CREATE INDEX IF NOT EXISTS idx_attendance_logs_user_date ON employee_attendance_logs(user_id, attendance_date);",
        "CREATE INDEX IF NOT EXISTS idx_attendance_policy_cid ON attendance_policies(cid);",
        "CREATE INDEX IF NOT EXISTS idx_shift_rules_user ON employee_shift_rules(user_id, cid);",
        "CREATE INDEX IF NOT EXISTS idx_attendance_corrections_cid_status ON attendance_corrections(cid, status);",
        "CREATE INDEX IF NOT EXISTS idx_attendance_corrections_user_date ON attendance_corrections(user_id, attendance_date);"
    ];

    foreach ($indexes as $index) {
        $db->exec($index);
    }
}

function appNormalizeLegacyBusinessDateTimeValue($value): ?string {
    if ($value === null) {
        return null;
    }

    $trimmed = trim((string)$value);
    if ($trimmed === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $trimmed) === 1) {
        return $trimmed;
    }

    if (preg_match('/^\d{10}$/', $trimmed) === 1) {
        $seconds = intval($trimmed);
        return $seconds > 0 ? date('Y-m-d H:i:s', $seconds) : null;
    }

    if (preg_match('/^\d{13}$/', $trimmed) === 1) {
        $millis = intval($trimmed);
        return $millis > 0 ? date('Y-m-d H:i:s', intval(round($millis / 1000))) : null;
    }

    if (preg_match('/^\d{14}$/', $trimmed, $matches) === 1) {
        $year = substr($trimmed, 0, 4);
        $month = substr($trimmed, 4, 2);
        $day = substr($trimmed, 6, 2);
        $hour = substr($trimmed, 8, 2);
        $minute = substr($trimmed, 10, 2);
        $second = substr($trimmed, 12, 2);
        $composed = "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
        $parsed = strtotime($composed);
        return $parsed === false ? null : date('Y-m-d H:i:s', $parsed);
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
        return $trimmed . ' 00:00:00';
    }

    $parsed = strtotime($trimmed);
    if ($parsed !== false) {
        return date('Y-m-d H:i:s', $parsed);
    }

    return null;
}

function appNormalizeExistingBusinessDateTimes(AppDbConnection $db): void {
    $columns = [
        ['table' => 'company', 'pk' => 'cid', 'column' => 'created_at'],
        ['table' => 'partner', 'pk' => 'sid', 'column' => 'created_at'],
        ['table' => 'product_categories', 'pk' => 'category_id', 'column' => 'created_at'],
        ['table' => 'products', 'pk' => 'product_id', 'column' => 'created_at'],
        ['table' => 'inventory', 'pk' => 'inventory_id', 'column' => 'last_updated'],
        ['table' => 'stock_ledger', 'pk' => 'ledger_id', 'column' => 'created_at'],
        ['table' => 'purchases', 'pk' => 'purchase_id', 'column' => 'createdAt'],
        ['table' => 'purchases_items', 'pk' => 'item_id', 'column' => 'created_at'],
        ['table' => 'sales', 'pk' => 'sale_id', 'column' => 'createdAt'],
        ['table' => 'sales_items', 'pk' => 'item_id', 'column' => 'created_at'],
        ['table' => 'partner_ledger', 'pk' => 'ledger_id', 'column' => 'createdAt'],
    ];

    $sqlitePattern = "[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]";

    foreach ($columns as $entry) {
        $table = $entry['table'];
        $pk = $entry['pk'];
        $column = $entry['column'];

        if ($db->driver() === 'sqlite') {
            $sql = "SELECT {$pk} AS row_id, {$column} AS raw_value
                    FROM {$table}
                    WHERE {$column} IS NOT NULL
                      AND TRIM(CAST({$column} AS TEXT)) <> ''
                      AND CAST({$column} AS TEXT) NOT GLOB '{$sqlitePattern}'";
        } else {
            $sql = "SELECT {$pk} AS row_id, {$column} AS raw_value
                    FROM {$table}
                    WHERE {$column} IS NOT NULL";
        }

        $res = $db->query($sql);
        if (!$res) {
            continue;
        }

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rowId = intval($row['row_id'] ?? 0);
            $rawValue = $row['raw_value'] ?? null;
            if ($rowId <= 0) {
                continue;
            }

            $normalized = appNormalizeLegacyBusinessDateTimeValue($rawValue);
            if ($normalized === null) {
                continue;
            }

            if (trim((string)$rawValue) === $normalized) {
                continue;
            }

            $stmt = $db->prepare("UPDATE {$table} SET {$column} = :value WHERE {$pk} = :id");
            if (!$stmt) {
                continue;
            }

            $stmt->bindValue(':value', $normalized, SQLITE3_TEXT);
            $stmt->bindValue(':id', $rowId, SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
}

function appEnsureSqliteBusinessDatetimeTriggers(AppDbConnection $db): void {
    if ($db->driver() !== 'sqlite') {
        return;
    }

    $columns = [
        ['table' => 'company', 'column' => 'created_at'],
        ['table' => 'partner', 'column' => 'created_at'],
        ['table' => 'product_categories', 'column' => 'created_at'],
        ['table' => 'products', 'column' => 'created_at'],
        ['table' => 'inventory', 'column' => 'last_updated'],
        ['table' => 'stock_ledger', 'column' => 'created_at'],
        ['table' => 'purchases', 'column' => 'createdAt'],
        ['table' => 'purchases_items', 'column' => 'created_at'],
        ['table' => 'sales', 'column' => 'createdAt'],
        ['table' => 'sales_items', 'column' => 'created_at'],
        ['table' => 'partner_ledger', 'column' => 'createdAt'],
    ];

    $pattern = "[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9] [0-9][0-9]:[0-9][0-9]:[0-9][0-9]";

    foreach ($columns as $entry) {
        $table = $entry['table'];
        $column = $entry['column'];
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';

        if ($safeTable === '' || $safeColumn === '') {
            continue;
        }

        $insertTrigger = "CREATE TRIGGER IF NOT EXISTS trg_{$safeTable}_{$safeColumn}_insert
            BEFORE INSERT ON {$table}
            FOR EACH ROW
            WHEN NEW.{$column} IS NOT NULL AND NEW.{$column} NOT GLOB '{$pattern}'
            BEGIN
                SELECT RAISE(ABORT, 'Invalid datetime format for {$table}.{$column}. Expected YYYY-MM-DD HH:MM:SS');
            END;";

        $updateTrigger = "CREATE TRIGGER IF NOT EXISTS trg_{$safeTable}_{$safeColumn}_update
            BEFORE UPDATE OF {$column} ON {$table}
            FOR EACH ROW
            WHEN NEW.{$column} IS NOT NULL AND NEW.{$column} NOT GLOB '{$pattern}'
            BEGIN
                SELECT RAISE(ABORT, 'Invalid datetime format for {$table}.{$column}. Expected YYYY-MM-DD HH:MM:SS');
            END;";

        $db->exec($insertTrigger);
        $db->exec($updateTrigger);
    }
}

function appRunLegacyPurchaseMigration(AppDbConnection $db): void {
    if ($db->driver() !== 'sqlite') {
        return;
    }

    $legacyTables = [];
    $tableRes = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    while ($tableRes && ($row = $tableRes->fetchArray(SQLITE3_ASSOC))) {
        $legacyTables[] = (string)($row['name'] ?? '');
    }

    $hasLegacyPurchase = in_array('purchase', $legacyTables, true);
    $hasLegacyPurchaseItems = in_array('purchase_items', $legacyTables, true);
    if (!$hasLegacyPurchase || !$hasLegacyPurchaseItems) {
        return;
    }

    $purchaseCount = intval($db->querySingle("SELECT COUNT(1) FROM purchases"));
    $saleCount = intval($db->querySingle("SELECT COUNT(1) FROM sales"));
    if ($purchaseCount !== 0 || $saleCount !== 0) {
        return;
    }

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

function appEnsureInventoryFractionColumns(AppDbConnection $db): void {
    $inventoryColumns = [];
    $invRes = $db->query("PRAGMA table_info(inventory)");
    while ($invRes && ($row = $invRes->fetchArray(SQLITE3_ASSOC))) {
        $inventoryColumns[] = strtolower((string)($row['name'] ?? ''));
    }

    if (!in_array('fraction_qty', $inventoryColumns, true)) {
        $db->exec("ALTER TABLE inventory ADD COLUMN fraction_qty REAL NOT NULL DEFAULT 0");
    }

    $salesItemColumns = [];
    $salesRes = $db->query("PRAGMA table_info(sales_items)");
    while ($salesRes && ($row = $salesRes->fetchArray(SQLITE3_ASSOC))) {
        $salesItemColumns[] = strtolower((string)($row['name'] ?? ''));
    }

    $newColumns = [
        'sale_unit' => 'TEXT',
        'fraction_length' => 'REAL',
        'fraction_width' => 'REAL',
        'fraction_qty' => 'REAL',
        'display_label' => 'TEXT',
    ];

    foreach ($newColumns as $column => $definition) {
        if (!in_array($column, $salesItemColumns, true)) {
            $db->exec("ALTER TABLE sales_items ADD COLUMN {$column} {$definition}");
        }
    }
}

function appInitializeTradeMeterSchema(AppDbConnection $db): void {
    appEnsureCoreBusinessSchema($db);
    appEnsureRbacSchema($db);
    appRunLegacyPurchaseMigration($db);
    appEnsureInventoryFractionColumns($db);
    appEnsureCoreBusinessIndexes($db);
    appNormalizeExistingBusinessDateTimes($db);
    appEnsureSqliteBusinessDatetimeTriggers($db);
}

function appSeedRolesAndPermissions(AppDbConnection $db, int $cid): void {
    $permissions = [
        'view_dashboard',
        'manage_products',
        'manage_inventory',
        'create_sales',
        'create_purchases',
        'view_reports',
        'delete_records',
        'manage_users'
    ];

    foreach ($permissions as $perm) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO permissions (permission_key) VALUES (:perm)");
        $stmt->bindValue(':perm', $perm, SQLITE3_TEXT);
        $stmt->execute();
    }

    $roleNames = [
        ['name' => 'Owner', 'system' => 1],
        ['name' => 'Manager', 'system' => 1],
        ['name' => 'Staff', 'system' => 1],
    ];

    foreach ($roleNames as $role) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO roles (cid, role_name, is_system) VALUES (:cid, :role_name, :is_system)");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $stmt->bindValue(':role_name', $role['name'], SQLITE3_TEXT);
        $stmt->bindValue(':is_system', $role['system'], SQLITE3_INTEGER);
        $stmt->execute();
    }

    $roleMap = [];
    $stmt = $db->prepare("SELECT role_id, role_name FROM roles WHERE cid = :cid");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $roleMap[strtolower((string)($row['role_name'] ?? ''))] = intval($row['role_id'] ?? 0);
    }

    $permMap = [];
    $pres = $db->query("SELECT permission_id, permission_key FROM permissions");
    while ($row = $pres->fetchArray(SQLITE3_ASSOC)) {
        $permMap[(string)($row['permission_key'] ?? '')] = intval($row['permission_id'] ?? 0);
    }

    $assignment = [
        'owner' => $permissions,
        'manager' => ['manage_products', 'manage_inventory', 'create_sales', 'create_purchases', 'view_reports'],
        'staff' => ['create_sales']
    ];

    foreach ($assignment as $roleName => $permList) {
        if (!isset($roleMap[$roleName])) {
            continue;
        }

        foreach ($permList as $permKey) {
            if (!isset($permMap[$permKey])) {
                continue;
            }

            $stmt = $db->prepare("INSERT OR IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
            $stmt->bindValue(':role_id', $roleMap[$roleName], SQLITE3_INTEGER);
            $stmt->bindValue(':permission_id', $permMap[$permKey], SQLITE3_INTEGER);
            $stmt->execute();
        }
    }
}

class AppDbResult {
    private string $driver;
    private $native;

    public function __construct(string $driver, $native) {
        $this->driver = $driver;
        $this->native = $native;
    }

    public function fetchArray(int $mode = SQLITE3_ASSOC) {
        if ($this->driver === 'sqlite') {
            return $this->native->fetchArray($mode);
        }

        if (!($this->native instanceof PDOStatement)) {
            return false;
        }

        if ($mode === SQLITE3_NUM) {
            $row = $this->native->fetch(PDO::FETCH_NUM);
        } elseif ($mode === SQLITE3_BOTH) {
            $row = $this->native->fetch(PDO::FETCH_BOTH);
        } else {
            $row = $this->native->fetch(PDO::FETCH_ASSOC);
        }

        if ($row === false) {
            return false;
        }

        return $mode === SQLITE3_NUM ? $row : appNormalizePgAssocRow($row);
    }
}

class AppDbStatement {
    private string $driver;
    private $native;
    private array $bound = [];

    public function __construct(string $driver, $native) {
        $this->driver = $driver;
        $this->native = $native;
    }

    public function bindValue(string $param, $value, int $type = SQLITE3_TEXT): bool {
        if ($this->driver === 'sqlite') {
            return $this->native->bindValue($param, $value, $type);
        }

        if ($type === SQLITE3_FLOAT && $value !== null) {
            $value = is_numeric($value) ? (float)$value : $value;
        }

        $this->bound[$param] = $value;
        $pdoType = PDO::PARAM_STR;
        if ($type === SQLITE3_INTEGER) {
            $pdoType = PDO::PARAM_INT;
        } elseif ($type === SQLITE3_NULL) {
            $pdoType = PDO::PARAM_NULL;
        }

        return $this->native->bindValue($param, $value, $pdoType);
    }

    public function execute() {
        if ($this->driver === 'sqlite') {
            $result = $this->native->execute();
            return $result === false ? false : new AppDbResult('sqlite', $result);
        }

        $ok = $this->native->execute();
        if (!$ok) {
            return false;
        }

        return new AppDbResult('pgsql', $this->native);
    }
}

class AppDbConnection {
    private string $driver;
    private $native;

    public function __construct(string $driver, $native) {
        $this->driver = $driver;
        $this->native = $native;
    }

    public function driver(): string {
        return $this->driver;
    }

    public function exec(string $sql): bool {
        if ($this->driver === 'sqlite') {
            return $this->native->exec($sql);
        }

        $normalized = $this->normalizeSqlForPg($sql);
        $this->native->exec($normalized);
        return true;
    }

    public function query(string $sql) {
        if ($this->driver === 'sqlite') {
            $result = $this->native->query($sql);
            return $result === false ? false : new AppDbResult('sqlite', $result);
        }

        $trimmed = trim($sql);
        if (preg_match('/^PRAGMA\s+table_info\(([^)]+)\)/i', $trimmed, $matches)) {
            $table = trim($matches[1], " \t\n\r\0\x0B`\"'");
            $stmt = $this->native->prepare(
                "SELECT column_name AS name
                 FROM information_schema.columns
                 WHERE table_schema = 'public' AND table_name = :table
                 ORDER BY ordinal_position"
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();
            return new AppDbResult('pgsql', $stmt);
        }

        $stmt = $this->native->query($this->normalizeSqlForPg($sql));
        return $stmt === false ? false : new AppDbResult('pgsql', $stmt);
    }

    public function querySingle(string $sql, bool $entireRow = false) {
        if ($this->driver === 'sqlite') {
            return $this->native->querySingle($sql, $entireRow);
        }

        $stmt = $this->native->query($this->normalizeSqlForPg($sql));
        if (!$stmt) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row = appNormalizePgAssocRow($row);

        if ($entireRow) {
            return $row;
        }

        $first = array_values($row);
        return $first[0] ?? null;
    }

    public function prepare(string $sql) {
        if ($this->driver === 'sqlite') {
            $stmt = $this->native->prepare($sql);
            return $stmt === false ? false : new AppDbStatement('sqlite', $stmt);
        }

        $stmt = $this->native->prepare($this->normalizeSqlForPg($sql));
        return $stmt === false ? false : new AppDbStatement('pgsql', $stmt);
    }

    public function lastInsertRowID(): int {
        if ($this->driver === 'sqlite') {
            return intval($this->native->lastInsertRowID());
        }

        $stmt = $this->native->query('SELECT LASTVAL() AS id');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return intval($row['id'] ?? 0);
    }

    public function close(): void {
        if ($this->driver === 'sqlite') {
            $this->native->close();
            return;
        }

        $this->native = null;
    }

    private function normalizeSqlForPg(string $sql): string {
        $normalized = $sql;

        $normalized = preg_replace('/\bINSERT\s+OR\s+IGNORE\b/i', 'INSERT', $normalized) ?? $normalized;

        if (stripos($sql, 'INSERT OR IGNORE') !== false) {
            $normalized = rtrim($normalized);
            if (substr($normalized, -1) === ';') {
                $normalized = rtrim(substr($normalized, 0, -1));
            }
            $normalized .= ' ON CONFLICT DO NOTHING';
        }

        $normalized = preg_replace(
            '/strftime\(\s*[\'"]%s[\'"]\s*,\s*[\'"]now[\'"]\s*\)/i',
            'EXTRACT(EPOCH FROM NOW())::bigint',
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace('/INTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT/i', 'BIGSERIAL PRIMARY KEY', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $normalized) ?? $normalized;

        return $normalized;
    }
}

function appDbConnectCompat(): AppDbConnection {
    if (appDbDriver() === 'sqlite') {
        return new AppDbConnection('sqlite', appDbConnect());
    }

    $config = appPostgresConfig();
    if (($config['host'] ?? '') === '' || ($config['dbname'] ?? '') === '') {
        return new AppDbConnection('sqlite', appDbConnect());
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $config['host'],
        intval($config['port'] ?? 5432),
        $config['dbname'],
        $config['sslmode'] ?? 'require'
    );

    $pdo = new PDO(
        $dsn,
        (string)($config['user'] ?? ''),
        (string)($config['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return new AppDbConnection('pgsql', $pdo);
}
