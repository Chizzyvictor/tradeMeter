<?php
// Run this script ONCE to migrate stock_ledger to allow 'stock_taking' in reference_type.
// This script supports both SQLite and PostgreSQL.

require_once __DIR__ . '/../INC/db.php';

function sqliteHasStockTakingReferenceType(SQLite3 $db): bool {
    $stmt = $db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name='stock_ledger' LIMIT 1");
    $result = $stmt ? $stmt->execute() : false;
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
    $sql = strtolower((string)($row['sql'] ?? ''));
    return strpos($sql, "stock_taking") !== false;
}

function migrateSqlite(SQLite3 $db): void {
    if (sqliteHasStockTakingReferenceType($db)) {
        echo "SQLite migration skipped: constraint already allows stock_taking.\n";
        return;
    }

    $db->exec('BEGIN TRANSACTION');
    try {
        $db->exec('ALTER TABLE stock_ledger RENAME TO stock_ledger_old');

        $db->exec("CREATE TABLE stock_ledger (
            ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            cid INTEGER NOT NULL,
            reference_type TEXT NOT NULL CHECK(reference_type IN ('purchase','sale','adjustment','stock_taking')),
            reference_id INTEGER,
            qty_in INTEGER DEFAULT 0,
            qty_out INTEGER DEFAULT 0,
            balance_after INTEGER NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
            FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
        )");

        $db->exec("INSERT INTO stock_ledger (
            ledger_id, product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after, created_at
        )
        SELECT
            ledger_id, product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after, created_at
        FROM stock_ledger_old");

        $db->exec('DROP TABLE stock_ledger_old');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_stock_ledger_product ON stock_ledger(product_id)');

        $db->exec('COMMIT');
        echo "SQLite migration successful.\n";
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

function migratePostgres(PDO $pdo): void {
    $checkSql = "
        SELECT 1
        FROM information_schema.table_constraints tc
        JOIN information_schema.check_constraints cc
            ON cc.constraint_name = tc.constraint_name
        WHERE tc.table_schema = 'public'
          AND tc.table_name = 'stock_ledger'
          AND tc.constraint_type = 'CHECK'
          AND cc.check_clause ILIKE '%reference_type%stock_taking%'
        LIMIT 1
    ";

    $already = $pdo->query($checkSql);
    if ($already && $already->fetch(PDO::FETCH_ASSOC)) {
        echo "PostgreSQL migration skipped: constraint already allows stock_taking.\n";
        return;
    }

    $pdo->beginTransaction();
    try {
        // Drop existing check constraint name used in this schema.
        $pdo->exec('ALTER TABLE stock_ledger DROP CONSTRAINT IF EXISTS stock_ledger_reference_type_check');

        // Add the updated check constraint.
        $pdo->exec("ALTER TABLE stock_ledger
            ADD CONSTRAINT stock_ledger_reference_type_check
            CHECK (reference_type IN ('purchase','sale','adjustment','stock_taking'))");

        $pdo->commit();
        echo "PostgreSQL migration successful.\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

try {
    if (appDbDriver() === 'pgsql') {
        $config = appPostgresConfig();
        if (($config['host'] ?? '') === '' || ($config['dbname'] ?? '') === '') {
            throw new RuntimeException('DATABASE_URL/PG config missing for PostgreSQL migration.');
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

        migratePostgres($pdo);
    } else {
        $sqlite = appDbConnect();
        migrateSqlite($sqlite);
        $sqlite->close();
    }

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
