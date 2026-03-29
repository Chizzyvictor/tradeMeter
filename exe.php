<?php
require_once __DIR__ . '/INC/db.php';

// ============================
//  FRESH DATABASE INITIALIZER
// ============================

try {

    $db = appDbConnectCompat();

    appInitializeTradeMeterSchema($db);

    echo "✅ Database schema initialized successfully (driver: " . $db->driver() . ").";

} catch (Throwable $e) {
    http_response_code(500);
    echo "❌ Initialization failed: " . $e->getMessage();
}
