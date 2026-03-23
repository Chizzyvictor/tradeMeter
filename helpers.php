<?php
session_start();

header('Content-Type: application/json; charset=utf-8');


// -------------------------
// Helper Functions
// -------------------------
function respond($status, $text = "", $extra = []) {
    echo json_encode(array_merge(["status" => $status, "text" => $text], $extra));
    exit;
}

function safe_input($value) {
    return trim(htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'));
}




if (!isset($_SESSION['cid'])) {
    session_unset();
    session_destroy();    
    respond("error", "Session expired. ");
}

if (!isset($_SESSION['last_activity']) || time() - $_SESSION['last_activity'] > 15000 ) {
    session_unset();
    session_destroy();
    respond("error", "Session expired due to inactivity.");
}
$_SESSION['last_activity'] = time();


// -------------------------
// Database Connection
// -------------------------
class MyDB extends SQLite3 {
    function __construct() {
       $this->open('mysqlitedb.db');
    }
}
$db = new MyDB();

define('SALE_ID_OFFSET', 1000000000);

function encodeTransactionId(int $id, string $type): int {
    return strtolower($type) === 'sale' ? (SALE_ID_OFFSET + $id) : $id;
}

function decodeTransactionId(int $encodedId): array {
    if ($encodedId >= SALE_ID_OFFSET) {
        return ['type' => 'sale', 'id' => $encodedId - SALE_ID_OFFSET];
    }

    return ['type' => 'purchase', 'id' => $encodedId];
}

function can(string $permissionKey): bool {
    if ($permissionKey === '') {
        return false;
    }

    $permissionMap = $_SESSION['permissions_map'] ?? null;
    if (is_array($permissionMap) && isset($permissionMap[$permissionKey])) {
        return true;
    }

    $permissions = $_SESSION['permissions'] ?? [];
    if (is_array($permissions)) {
        return in_array($permissionKey, $permissions, true);
    }

    return false;
}

function userHasPermission(SQLite3 $db, int $userId, string $permissionKey): bool {
    if ($userId <= 0 || $permissionKey === '') {
        return false;
    }

    if (can($permissionKey)) {
        return true;
    }

    $stmt = $db->prepare("SELECT 1
                         FROM user_roles ur
                         JOIN role_permissions rp ON ur.role_id = rp.role_id
                         JOIN permissions p ON rp.permission_id = p.permission_id
                         WHERE ur.user_id = :uid
                           AND p.permission_key = :perm
                         LIMIT 1");
    if (!$stmt) {
        return false;
    }

    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':perm', $permissionKey, SQLITE3_TEXT);
    $res = $stmt->execute();
    $allowed = $res && $res->fetchArray(SQLITE3_ASSOC) !== false;
    if ($allowed) {
        if (!isset($_SESSION['permissions_map']) || !is_array($_SESSION['permissions_map'])) {
            $_SESSION['permissions_map'] = [];
        }
        $_SESSION['permissions_map'][$permissionKey] = true;
    }
    return $allowed;
}

function requirePermission(SQLite3 $db, string $permissionKey): void {
    $uid = intval($_SESSION['user_id'] ?? 0);

    // Backward compatibility: old accounts without users table session keep full access.
    if ($uid <= 0) {
        return;
    }

    if (!userHasPermission($db, $uid, $permissionKey)) {
        respond('error', 'Unauthorized');
    }
}

function requireAnyPermission(SQLite3 $db, array $permissionKeys): void {
    $uid = intval($_SESSION['user_id'] ?? 0);

    if ($uid <= 0) {
        return;
    }

    foreach ($permissionKeys as $key) {
        if (userHasPermission($db, $uid, (string)$key)) {
            return;
        }
    }

    respond('error', 'Unauthorized');
}


/**
 * Adjust stock for a product
 * Updates inventory table and stock_ledger
 *
 * @param SQLite3 $db
 * @param int $productId
 * @param int $cid
 * @param int $qtyChange (+ for purchase, - for sale)
 * @param string $referenceType 'purchase', 'sale', 'adjustment'
 * @param int $referenceId ID of purchase/sale/adjustment
 * @throws Exception if stock goes negative
 * @return int new balance
 */
function adjustStock(SQLite3 $db, int $productId, int $cid, int $qtyChange, string $referenceType, int $referenceId): int {
    $db->exec("BEGIN");

    try {
        // 1️⃣ Get current stock
        $stmt = $db->prepare("SELECT quantity FROM inventory WHERE product_id = :pid AND cid = :cid");
        $stmt->bindValue(':pid', $productId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        $currentQty = $res ? (int)$res['quantity'] : 0;
        $newBalance = $currentQty + $qtyChange;

        if ($newBalance < 0) {
            throw new Exception("Insufficient stock for product_id $productId (current: $currentQty, change: $qtyChange)");
        }

        // 2️⃣ Update or insert inventory
        if ($res) {
            $update = $db->prepare("UPDATE inventory SET quantity = :qty, last_updated = CURRENT_TIMESTAMP WHERE product_id = :pid AND cid = :cid");
            $update->bindValue(':qty', $newBalance, SQLITE3_INTEGER);
            $update->bindValue(':pid', $productId, SQLITE3_INTEGER);
            $update->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $update->execute();
        } else {
            $insert = $db->prepare("INSERT INTO inventory (product_id, cid, quantity) VALUES (:pid, :cid, :qty)");
            $insert->bindValue(':pid', $productId, SQLITE3_INTEGER);
            $insert->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $insert->bindValue(':qty', $newBalance, SQLITE3_INTEGER);
            $insert->execute();
        }

        // 3️⃣ Insert into stock_ledger
        $ledger = $db->prepare("
            INSERT INTO stock_ledger 
            (product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after)
            VALUES (:pid, :cid, :refType, :refId, :qtyIn, :qtyOut, :balance)
        ");
        $ledger->bindValue(':pid', $productId, SQLITE3_INTEGER);
        $ledger->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $ledger->bindValue(':refType', $referenceType, SQLITE3_TEXT);
        $ledger->bindValue(':refId', $referenceId, SQLITE3_INTEGER);
        $ledger->bindValue(':qtyIn', $qtyChange > 0 ? $qtyChange : 0, SQLITE3_INTEGER);
        $ledger->bindValue(':qtyOut', $qtyChange < 0 ? abs($qtyChange) : 0, SQLITE3_INTEGER);
        $ledger->bindValue(':balance', $newBalance, SQLITE3_INTEGER);
        $ledger->execute();

        $db->exec("COMMIT");
        return $newBalance;

    } catch (Exception $e) {
        $db->exec("ROLLBACK");
        throw $e;
    }
}


function savePurchase(SQLite3 $db, int $cid, int $supplierId, array $items) {
    $db->exec("BEGIN");
    try {
        // 1️⃣ Insert purchase record
        $stmt = $db->prepare("
            INSERT INTO purchases (cid, partner_id, totalAmount, amountPaid, status) 
            VALUES (:cid, :sid, :total, :paid, 'pending')
        ");
        $totalAmount = array_sum(array_column($items, 'qty')); // Or actual calculation
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $stmt->bindValue(':sid', $supplierId, SQLITE3_INTEGER);
        $stmt->bindValue(':total', $totalAmount, SQLITE3_FLOAT);
        $stmt->bindValue(':paid', 0, SQLITE3_FLOAT);
        $stmt->execute();
        $purchaseId = $db->lastInsertRowID();

        // 2️⃣ Loop items and insert purchase items + adjust stock
        foreach ($items as $item) {
            $stmtItem = $db->prepare("
                INSERT INTO purchases_items (purchase_id, product_id, qty, costPrice) 
                VALUES (:pid, :product_id, :qty, :cost)
            ");
            $stmtItem->bindValue(':pid', $purchaseId, SQLITE3_INTEGER);
            $stmtItem->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
            $stmtItem->bindValue(':qty', $item['qty'], SQLITE3_INTEGER);
            $stmtItem->bindValue(':cost', $item['costPrice'], SQLITE3_FLOAT);
            $stmtItem->execute();

            // Adjust inventory (+qty for purchase)
            adjustStock($db, $item['product_id'], $cid, $item['qty'], 'purchase', $purchaseId);
        }

        $db->exec("COMMIT");
        return $purchaseId;

    } catch (Exception $e) {
        $db->exec("ROLLBACK");
        throw $e;
    }
}


function saveSale(SQLite3 $db, int $cid, int $customerId, array $items) {
    $db->exec("BEGIN");
    try {
        // 1️⃣ Insert sale record
        $stmt = $db->prepare("
            INSERT INTO sales (cid, partner_id, totalAmount, amountPaid, status) 
            VALUES (:cid, :sid, :total, :paid, 'pending')
        ");
        $totalAmount = array_sum(array_column($items, 'qty')); // Or actual calculation
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $stmt->bindValue(':sid', $customerId, SQLITE3_INTEGER);
        $stmt->bindValue(':total', $totalAmount, SQLITE3_FLOAT);
        $stmt->bindValue(':paid', 0, SQLITE3_FLOAT);
        $stmt->execute();
        $saleId = $db->lastInsertRowID();

        // 2️⃣ Loop items and insert sale items + adjust stock
        foreach ($items as $item) {
            $stmtItem = $db->prepare("
                INSERT INTO sales_items (sale_id, product_id, qty, costPrice) 
                VALUES (:pid, :product_id, :qty, :cost)
            ");
            $stmtItem->bindValue(':pid', $saleId, SQLITE3_INTEGER);
            $stmtItem->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
            $stmtItem->bindValue(':qty', $item['qty'], SQLITE3_INTEGER);
            $stmtItem->bindValue(':cost', $item['costPrice'], SQLITE3_FLOAT);
            $stmtItem->execute();

            // Adjust inventory (-qty for sale)
            adjustStock($db, $item['product_id'], $cid, -$item['qty'], 'sale', $saleId);
        }

        $db->exec("COMMIT");
        return $saleId;

    } catch (Exception $e) {
        $db->exec("ROLLBACK");
        throw $e;
    }
}


function applyUnpaidToPartner(SQLite3 $db, int $partnerId, int $cid, string $transactionType, float $unpaid): void {
    if ($unpaid <= 0) return;

    $stmt = $db->prepare("SELECT outstanding, advancePayment FROM partner WHERE sid = :sid AND cid = :cid LIMIT 1");
    $stmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $partner = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$partner) {
        throw new Exception("Partner not found");
    }

    $outstanding = floatval($partner['outstanding'] ?? 0);
    $advance = floatval($partner['advancePayment'] ?? 0);

    if ($transactionType === 'sell') {
        $usedFromAdvance = min($advance, $unpaid);
        $advance -= $usedFromAdvance;
        $remaining = $unpaid - $usedFromAdvance;
        if ($remaining > 0) {
            $outstanding += $remaining;
        }
    } else {
        $usedFromOutstanding = min($outstanding, $unpaid);
        $outstanding -= $usedFromOutstanding;
        $remaining = $unpaid - $usedFromOutstanding;
        if ($remaining > 0) {
            $advance += $remaining;
        }
    }

    $uStmt = $db->prepare("UPDATE partner
                           SET outstanding = :outstanding,
                               advancePayment = :advancePayment
                           WHERE sid = :sid AND cid = :cid");
    $uStmt->bindValue(':outstanding', $outstanding, SQLITE3_FLOAT);
    $uStmt->bindValue(':advancePayment', $advance, SQLITE3_FLOAT);
    $uStmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
    $uStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    if (!$uStmt->execute()) {
        throw new Exception("Failed to update partner balances");
    }
}

function applyPaymentToPartner(SQLite3 $db, int $partnerId, int $cid, string $transactionType, float $payment): void {
    if ($payment <= 0) return;

    $stmt = $db->prepare("SELECT outstanding, advancePayment FROM partner WHERE sid = :sid AND cid = :cid LIMIT 1");
    $stmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $partner = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$partner) {
        throw new Exception("Partner not found");
    }

    $outstanding = floatval($partner['outstanding'] ?? 0);
    $advance = floatval($partner['advancePayment'] ?? 0);

    if ($transactionType === 'sell') {
        $usedOnOutstanding = min($outstanding, $payment);
        $outstanding -= $usedOnOutstanding;
        $remaining = $payment - $usedOnOutstanding;
        if ($remaining > 0) {
            $advance += $remaining;
        }
    } else {
        $usedOnAdvance = min($advance, $payment);
        $advance -= $usedOnAdvance;
        $remaining = $payment - $usedOnAdvance;
        if ($remaining > 0) {
            $outstanding += $remaining;
        }
    }

    $uStmt = $db->prepare("UPDATE partner
                           SET outstanding = :outstanding,
                               advancePayment = :advancePayment
                           WHERE sid = :sid AND cid = :cid");
    $uStmt->bindValue(':outstanding', $outstanding, SQLITE3_FLOAT);
    $uStmt->bindValue(':advancePayment', $advance, SQLITE3_FLOAT);
    $uStmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
    $uStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    if (!$uStmt->execute()) {
        throw new Exception("Failed to update partner balances");
    }
}

function addTransactionNotification(SQLite3 $db, int $partnerId, int $cid, float $amount, string $description, int $status, int $timestamp): void {
    if ($amount <= 0) return;

    $type = null;
    if (stripos($description, 'unpaid balance') !== false) {
        $type = $status === 0 ? 'sell' : 'buy';
    } elseif (stripos($description, 'payment received') !== false) {
        $type = 'payDebt';
    } else {
        $type = $status === 0 ? 'sell' : 'buy';
    }

    $debit = in_array($type, ['sell', 'addDebt'], true) ? $amount : 0;
    $credit = in_array($type, ['buy', 'payDebt'], true) ? $amount : 0;

    $partnerStmt = $db->prepare("SELECT outstanding, advancePayment FROM partner WHERE sid = :sid AND cid = :cid LIMIT 1");
    if (!$partnerStmt) {
        throw new Exception("Failed to prepare partner balance lookup");
    }
    $partnerStmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
    $partnerStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $partner = $partnerStmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$partner) {
        throw new Exception("Partner not found for ledger entry");
    }

    $outstanding = floatval($partner['outstanding'] ?? 0);
    $advancePayment = floatval($partner['advancePayment'] ?? 0);

    $referenceId = null;
    if (preg_match('/#(\d+)/', $description, $matches)) {
        $referenceId = intval($matches[1]);
    }

    $stmt = $db->prepare("INSERT INTO partner_ledger (cid, sid, type, debit, credit, outstanding, advancePayment, note, reference_id, createdAt)
                          VALUES (:cid, :sid, :type, :debit, :credit, :outstanding, :advancePayment, :note, :reference_id, :createdAt)");
    if (!$stmt) {
        throw new Exception("Failed to prepare transaction ledger entry");
    }

    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $stmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':debit', $debit, SQLITE3_FLOAT);
    $stmt->bindValue(':credit', $credit, SQLITE3_FLOAT);
    $stmt->bindValue(':outstanding', $outstanding, SQLITE3_FLOAT);
    $stmt->bindValue(':advancePayment', $advancePayment, SQLITE3_FLOAT);
    $stmt->bindValue(':note', $description, SQLITE3_TEXT);
    if ($referenceId !== null) {
        $stmt->bindValue(':reference_id', $referenceId, SQLITE3_INTEGER);
    } else {
        $stmt->bindValue(':reference_id', null, SQLITE3_NULL);
    }
    $stmt->bindValue(':createdAt', $timestamp, SQLITE3_INTEGER);

    if (!$stmt->execute()) {
        throw new Exception("Failed to save transaction ledger entry");
    }
}



// -----------------------------------------
// Helper: Get Partner
// -----------------------------------------
function getPartner($db, $sid, $cid) {
    $stmt = $db->prepare("SELECT * FROM partner WHERE sid = :sid AND cid = :cid LIMIT 1");
    $stmt->bindValue(':sid', $sid, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    return $stmt->execute()->fetchArray(SQLITE3_ASSOC);
}

// -----------------------------------------
// Helper: Update Balance
// -----------------------------------------
function updatePartnerBalance($db, $sid, $cid, $out, $adv) {
    $stmt = $db->prepare("
        UPDATE partner
        SET outstanding = :out,
            advancePayment = :adv,
            updated_at = strftime('%s','now')
        WHERE sid = :sid AND cid = :cid
    ");
    $stmt->bindValue(':out', $out, SQLITE3_INTEGER);
    $stmt->bindValue(':adv', $adv, SQLITE3_INTEGER);
    $stmt->bindValue(':sid', $sid, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    return $stmt->execute();
}


function partnerExistsBy($db, $field, $value, $cid)
{
    $allowed = ["sEmail", "sName"];
    if (!in_array($field, $allowed)) return false;

    $stmt = $db->prepare("SELECT 1 FROM partner WHERE $field = :val AND cid = :cid AND type = 'customer' LIMIT 1");
    $stmt->bindValue(":val", $value, SQLITE3_TEXT);
    $stmt->bindValue(":cid", $cid, SQLITE3_INTEGER);
    return $stmt->execute()->fetchArray() !== false;
}

function loadCompany($db, $cid) { 
    $stmt = $db->prepare("SELECT * FROM company WHERE cid = :cid");
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    return $stmt->execute()->fetchArray(SQLITE3_ASSOC);
}






// === File Upload Config ===
$dir = $_POST['dir'] ?? 'productsDP';  // Fallback to 'productDP'
define("UPLOAD_DIR", __DIR__ . "/Images/{$dir}/");
define("MAX_FILE_SIZE", 2 * 1024 * 1024); // 2MB
$allowedMimeTypes = ["image/jpeg", "image/png", "image/gif"];
// Files that should never be deleted
$defaultFiles = [
    "logo.jpg",
    "user.jpg",
    "product.jpg",
    "no-image.jpg"
];
// Create directory if it doesn’t exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// === Helper: Safe file upload ===
function handleFileUpload($fieldName, $oldFile = null) {
    global $allowedMimeTypes, $defaultFiles;

    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]["error"] !== UPLOAD_ERR_OK) {
        return $oldFile;
    }

    $file = $_FILES[$fieldName];

    if ($file["size"] > MAX_FILE_SIZE) {
        respond("error", "File too large. Max size is 2MB.");
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimeTypes)) {
        respond("error", "Invalid file type.");
    }

    $ext  = pathinfo($file["name"], PATHINFO_EXTENSION);
    $name = uniqid("img_", true) . "." . $ext;
    $path = UPLOAD_DIR . $name;

    if (!move_uploaded_file($file["tmp_name"], $path)) {
        respond("error", "Failed to upload file.");
    }

    // Remove old file ONLY if it's not a default file
    if (
        $oldFile &&
        !in_array($oldFile, $defaultFiles) &&
        file_exists(UPLOAD_DIR . $oldFile)
    ) {
        unlink(UPLOAD_DIR . $oldFile);
    }

    return $name;
}

// -------------------------
// Timezone and Date Ranges
// -------------------------
date_default_timezone_set("Africa/Lagos");
$now        = strtotime('now');
$today      = strtotime('today');
$lastMonth  = strtotime('-1 month');
$beginning  = strtotime("first day of this month");
$end        = strtotime("last day of this month");
$begin      = strtotime("first day of last month");
$ending     = strtotime("last day of last month");
$cid        = $_SESSION['cid'] ?? '';
$action     = $_POST['action'] ?? null;




// -------------------------
// Action Handler
// -------------------------
// === Ensure action exists ===
if (!$action) respond("error", "No action provided");



if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== null) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        respond("error", "Invalid CSRF token.");
    }
}

?>