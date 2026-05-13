<?php
// Run this script ONCE to migrate stock_ledger to allow 'stock_taking' in reference_type
// BACKUP your database before running!

require_once __DIR__ . '/../INC/db.php';

$db = appDbConnect();

$db->exec('BEGIN TRANSACTION');
try {
    // 1. Rename old table
    $db->exec('ALTER TABLE stock_ledger RENAME TO stock_ledger_old');

    // 2. Create new table with updated constraint
    $db->exec('CREATE TABLE stock_ledger (
        ledger_id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        cid INTEGER NOT NULL,
        reference_type TEXT NOT NULL CHECK(reference_type IN ("purchase","sale","adjustment","stock_taking")),
        reference_id INTEGER,
        qty_in INTEGER DEFAULT 0,
        qty_out INTEGER DEFAULT 0,
        balance_after INTEGER NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
        FOREIGN KEY (cid) REFERENCES company(cid) ON DELETE CASCADE
    )');

    // 3. Copy data
    $db->exec('INSERT INTO stock_ledger (ledger_id, product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after, created_at)
        SELECT ledger_id, product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after, created_at FROM stock_ledger_old');

    // 4. Drop old table
    $db->exec('DROP TABLE stock_ledger_old');

    $db->exec('COMMIT');
    echo "Migration successful.\n";
} catch (Exception $e) {
    $db->exec('ROLLBACK');
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
