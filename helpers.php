<?php
session_start();
require_once __DIR__ . '/INC/db.php';

header('Content-Type: application/json; charset=utf-8');


// -------------------------
// Helper Functions
// -------------------------
function buildApiPayload($status, $text = "", $extra = []): array {
    $normalizedStatus = strtolower((string)$status) === 'success' ? 'success' : 'error';
    $isSuccess = $normalizedStatus === 'success';
    $safeExtra = is_array($extra) ? $extra : [];
    $meta = [];

    if (isset($safeExtra['meta']) && is_array($safeExtra['meta'])) {
        $meta = $safeExtra['meta'];
        unset($safeExtra['meta']);
    }

    if (array_key_exists('data', $safeExtra)) {
        $data = $safeExtra['data'];
        unset($safeExtra['data']);
    } else {
        $data = !empty($safeExtra) ? $safeExtra : null;
    }

    return array_merge([
        'ok' => $isSuccess,
        'status' => $normalizedStatus,
        'text' => (string)$text,
        'message' => (string)$text,
        'data' => $data,
        'meta' => $meta,
    ], $safeExtra);
}

function respond($status, $text = "", $extra = []) {
    echo json_encode(buildApiPayload($status, $text, $extra));
    exit;
}

function appDebugEnabled(): bool {
    $raw = strtolower(trim((string)(appEnv('APP_DEBUG', 'false') ?? 'false')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function respondUnhandledApiError(string $message, array $context = []): void {
    $reference = 'tm_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 8);
    $publicMessage = 'Unexpected server error. Ref: ' . $reference;
    $payload = buildApiPayload('error', $publicMessage, [
        'meta' => ['reference' => $reference],
        'reference' => $reference,
    ]);

    if (appDebugEnabled()) {
        $payload['meta']['debug'] = $message;
        $payload['debug'] = $message;
        if (!empty($context)) {
            $payload['meta']['context'] = $context;
            $payload['context'] = $context;
        }
    }

    error_log('TradeMeter API unhandled error [' . $reference . ']: ' . $message . ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES));
    echo json_encode($payload);
    exit;
}

set_exception_handler(function (Throwable $e): void {
    respondUnhandledApiError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'action' => (string)($_REQUEST['action'] ?? ''),
        'cid' => intval($_SESSION['cid'] ?? 0),
        'uid' => intval($_SESSION['user_id'] ?? 0),
    ]);
});

register_shutdown_function(function (): void {
    $lastError = error_get_last();
    if (!is_array($lastError)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array(intval($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    respondUnhandledApiError((string)($lastError['message'] ?? 'Fatal error'), [
        'file' => (string)($lastError['file'] ?? ''),
        'line' => intval($lastError['line'] ?? 0),
        'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'action' => (string)($_REQUEST['action'] ?? ''),
        'cid' => intval($_SESSION['cid'] ?? 0),
        'uid' => intval($_SESSION['user_id'] ?? 0),
    ]);
});

function safe_input($value) {
    return trim(htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'));
}

function appBusinessDateTimePattern(): string {
    return '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
}

function appNowBusinessDateTime(): string {
    return date('Y-m-d H:i:s');
}

function bindParams($stmt, array $params): void {
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, SQLITE3_TEXT);
    }
}

function calcGrowth(float $current, float $previous): float {
    if ($previous == 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 2);
}

function appIsBusinessDateTime($value): bool {
    return is_string($value) && preg_match(appBusinessDateTimePattern(), trim($value)) === 1;
}

function appNormalizeBusinessDateTime($value, bool $allowDateOnly = false, ?string $defaultTime = null): string {
    if ($value === null || $value === '') {
        return appNowBusinessDateTime();
    }

    if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
        $numeric = intval($value);
        if ($numeric > 0) {
            if (strlen((string)$numeric) >= 13) {
                $numeric = intval(round($numeric / 1000));
            }
            return date('Y-m-d H:i:s', $numeric);
        }
    }

    $trimmed = trim((string)$value);
    if (appIsBusinessDateTime($trimmed)) {
        return $trimmed;
    }

    if ($allowDateOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
        $timePart = $defaultTime ?: date('H:i:s');
        return $trimmed . ' ' . $timePart;
    }

    $parsed = strtotime($trimmed);
    if ($parsed !== false) {
        return date('Y-m-d H:i:s', $parsed);
    }

    throw new InvalidArgumentException('Invalid datetime format. Expected YYYY-MM-DD HH:MM:SS.');
}

// -------------------------
// Database Connection
// -------------------------
$db = appDbConnectCompat();

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

function userHasPermission(AppDbConnection $db, int $userId, string $permissionKey): bool {
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

function requirePermission(AppDbConnection $db, string $permissionKey): void {
    $uid = intval($_SESSION['user_id'] ?? 0);

    // Backward compatibility: old accounts without users table session keep full access.
    if ($uid <= 0) {
        return;
    }

    if (!userHasPermission($db, $uid, $permissionKey)) {
        respond('error', 'Unauthorized');
    }
}

function requireAnyPermission(AppDbConnection $db, array $permissionKeys): void {
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

function getUserPrimaryRole(AppDbConnection $db, int $userId, int $cid): string {
    if ($cid <= 0) {
        return 'User';
    }

    // Backward compatibility: legacy sessions without explicit users act as Owner.
    if ($userId <= 0) {
        return 'Owner';
    }

    $stmt = $db->prepare("SELECT COALESCE((
                                SELECT r.role_name
                                FROM user_roles ur
                                JOIN roles r ON ur.role_id = r.role_id
                                WHERE ur.user_id = :uid
                                ORDER BY CASE lower(r.role_name)
                                    WHEN 'owner' THEN 1
                                    WHEN 'manager' THEN 2
                                    WHEN 'staff' THEN 3
                                    ELSE 4 END,
                                    r.role_name ASC
                                LIMIT 1
                            ), 'User') AS role_name");
    if (!$stmt) {
        return 'User';
    }

    $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return (string)($row['role_name'] ?? 'User');
}

function currentUserPrimaryRole(AppDbConnection $db): string {
    return getUserPrimaryRole(
        $db,
        intval($_SESSION['user_id'] ?? 0),
        intval($_SESSION['cid'] ?? 0)
    );
}

function currentUserHasRole(AppDbConnection $db, string $roleName): bool {
    if ($roleName === '') {
        return false;
    }

    return strcasecmp(currentUserPrimaryRole($db), $roleName) === 0;
}


/**
 * Adjust stock for a product
 * Updates inventory table and stock_ledger
 *
 * @param AppDbConnection $db
 * @param int $productId
 * @param int $cid
 * @param int $qtyChange (+ for purchase, - for sale)
 * @param string $referenceType 'purchase', 'sale', 'adjustment'
 * @param int $referenceId ID of purchase/sale/adjustment
 * @throws Exception if stock goes negative
 * @return int new balance
 */
function adjustStock(AppDbConnection $db, int $productId, int $cid, int $qtyChange, string $referenceType, int $referenceId): int {
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
        $timestamp = appNowBusinessDateTime();
        if ($res) {
            $update = $db->prepare("UPDATE inventory SET quantity = :qty, last_updated = :updated_at WHERE product_id = :pid AND cid = :cid");
            $update->bindValue(':qty', $newBalance, SQLITE3_INTEGER);
            $update->bindValue(':pid', $productId, SQLITE3_INTEGER);
            $update->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $update->bindValue(':updated_at', $timestamp, SQLITE3_TEXT);
            $update->execute();
        } else {
            $insert = $db->prepare("INSERT INTO inventory (product_id, cid, quantity, last_updated) VALUES (:pid, :cid, :qty, :updated_at)");
            $insert->bindValue(':pid', $productId, SQLITE3_INTEGER);
            $insert->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $insert->bindValue(':qty', $newBalance, SQLITE3_INTEGER);
            $insert->bindValue(':updated_at', $timestamp, SQLITE3_TEXT);
            $insert->execute();
        }

        // 3️⃣ Insert into stock_ledger
        $ledger = $db->prepare("
            INSERT INTO stock_ledger 
            (product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after, created_at)
            VALUES (:pid, :cid, :refType, :refId, :qtyIn, :qtyOut, :balance, :created_at)
        ");
        $ledger->bindValue(':pid', $productId, SQLITE3_INTEGER);
        $ledger->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $ledger->bindValue(':refType', $referenceType, SQLITE3_TEXT);
        $ledger->bindValue(':refId', $referenceId, SQLITE3_INTEGER);
        $ledger->bindValue(':qtyIn', $qtyChange > 0 ? $qtyChange : 0, SQLITE3_INTEGER);
        $ledger->bindValue(':qtyOut', $qtyChange < 0 ? abs($qtyChange) : 0, SQLITE3_INTEGER);
        $ledger->bindValue(':balance', $newBalance, SQLITE3_INTEGER);
        $ledger->bindValue(':created_at', $timestamp, SQLITE3_TEXT);
        $ledger->execute();

        $db->exec("COMMIT");
        return $newBalance;

    } catch (Exception $e) {
        $db->exec("ROLLBACK");
        throw $e;
    }
}


function savePurchase(AppDbConnection $db, int $cid, int $supplierId, array $items) {
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


function saveSale(AppDbConnection $db, int $cid, int $customerId, array $items) {
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


function applyUnpaidToPartner(AppDbConnection $db, int $partnerId, int $cid, string $transactionType, float $unpaid): void {
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

function applyPaymentToPartner(AppDbConnection $db, int $partnerId, int $cid, string $transactionType, float $payment): void {
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

function addTransactionNotification(AppDbConnection $db, int $partnerId, int $cid, float $amount, string $description, int $status, int $timestamp): void {
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
    $createdAt = appNormalizeBusinessDateTime($timestamp, false);
    $stmt->bindValue(':createdAt', $createdAt, SQLITE3_TEXT);

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
    $stmt->bindValue(':out', $out, SQLITE3_FLOAT);
    $stmt->bindValue(':adv', $adv, SQLITE3_FLOAT);
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

function parseCloudinaryUrl(string $url): array {
    $url = trim($url);
    if ($url === '') {
        return [];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return [];
    }

    return [
        'cloud_name' => trim((string)($parts['host'] ?? '')),
        'api_key' => trim((string)($parts['user'] ?? '')),
        'api_secret' => trim((string)($parts['pass'] ?? '')),
    ];
}

function isValidCloudinaryCloudName(string $cloudName): bool {
    if ($cloudName === '') {
        return false;
    }

    if (in_array(strtolower($cloudName), ['root', 'your_cloud_name', 'cloud_name'], true)) {
        return false;
    }

    return preg_match('/^[A-Za-z0-9_-]+$/', $cloudName) === 1;
}

function appCloudinaryConfig(): array {
    $cloudName = trim((string)appEnv('CLOUDINARY_CLOUD_NAME', ''));
    $apiKey = trim((string)appEnv('CLOUDINARY_API_KEY', ''));
    $apiSecret = trim((string)appEnv('CLOUDINARY_API_SECRET', ''));
    $cloudinaryUrl = trim((string)appEnv('CLOUDINARY_URL', ''));
    $folder = trim((string)appEnv('CLOUDINARY_FOLDER', 'trademeter'));

    // Validate CLOUDINARY_CLOUD_NAME; if invalid or unset, clear it
    if ($cloudName !== '' && !isValidCloudinaryCloudName($cloudName)) {
        $cloudName = '';
    }

    // Accept either separate CLOUDINARY_* vars or a single CLOUDINARY_URL.
    if ($cloudinaryUrl !== '') {
        $urlCfg = parseCloudinaryUrl($cloudinaryUrl);
        $urlCloudName = (string)($urlCfg['cloud_name'] ?? '');
        
        // Only use URL's cloud_name if our CLOUDINARY_CLOUD_NAME is empty AND URL's is valid
        if ($cloudName === '' && $urlCloudName !== '' && isValidCloudinaryCloudName($urlCloudName)) {
            $cloudName = $urlCloudName;
        }
        
        if ($apiKey === '') {
            $apiKey = (string)($urlCfg['api_key'] ?? '');
        }
        if ($apiSecret === '') {
            $apiSecret = (string)($urlCfg['api_secret'] ?? '');
        }
    }

    return [
        'cloud_name' => $cloudName,
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'folder' => $folder,
    ];
}

function appCloudinaryEnabled(): bool {
    $cfg = appCloudinaryConfig();
    return isValidCloudinaryCloudName((string)$cfg['cloud_name'])
        && $cfg['api_key'] !== ''
        && $cfg['api_secret'] !== '';
}

function uploadImageToCloudinary(string $tmpPath, string $originalName, string $subFolder = ''): ?string {
    if (!appCloudinaryEnabled()) {
        return null;
    }

    if (!function_exists('curl_init')) {
        respond('error', 'cURL extension is required for Cloudinary uploads.');
    }

    $cfg = appCloudinaryConfig();
    $timestamp = time();
    $folder = trim($cfg['folder'], '/');
    $subFolder = trim($subFolder, '/');
    if ($subFolder !== '') {
        $folder .= '/' . $subFolder;
    }

    $signatureBase = "folder={$folder}&timestamp={$timestamp}{$cfg['api_secret']}";
    $signature = sha1($signatureBase);

    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cfg['cloud_name']) . '/image/upload';

    $ch = curl_init($endpoint);
    $postFields = [
        'file' => new CURLFile($tmpPath, mime_content_type($tmpPath) ?: 'application/octet-stream', $originalName),
        'api_key' => $cfg['api_key'],
        'timestamp' => $timestamp,
        'folder' => $folder,
        'signature' => $signature,
    ];

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        respond('error', 'Cloudinary upload failed: ' . ($curlError ?: 'unknown error'));
    }

    $data = json_decode((string)$response, true);
    if (!is_array($data) || empty($data['secure_url']) || $httpCode < 200 || $httpCode >= 300) {
        $cloudinaryError = is_array($data['error'] ?? null) ? ($data['error']['message'] ?? 'upload error') : 'upload error';
        respond('error', 'Cloudinary upload failed: ' . $cloudinaryError);
    }

    return (string)$data['secure_url'];
}






// === File Upload Config ===
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
function resolveUploadDir(?string $dir = null): string {
    $rawDir = trim((string)($dir ?? ($_POST['dir'] ?? 'productsDP')));
    $safeDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawDir) ?: 'productsDP';
    $uploadDir = __DIR__ . "/Images/{$safeDir}/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    return $uploadDir;
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

    $ext  = strtolower((string)pathinfo($file["name"], PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : 'jpg');
    }
    $name = uniqid("img_", true) . "." . $ext;
    $uploadDir = resolveUploadDir();
    $path = $uploadDir . $name;

    if (appCloudinaryEnabled()) {
        $dir = trim((string)($_POST['dir'] ?? 'productsDP'));
        $uploadedUrl = uploadImageToCloudinary($file['tmp_name'], (string)$file['name'], $dir);
        if ($uploadedUrl) {
            return $uploadedUrl;
        }
    }

    if (!move_uploaded_file($file["tmp_name"], $path)) {
        respond("error", "Failed to upload file.");
    }

    // Remove old file ONLY if it's not a default file
    if (
        $oldFile &&
        !preg_match('/^https?:\/\//i', (string)$oldFile) &&
        !in_array($oldFile, $defaultFiles) &&
        file_exists($uploadDir . $oldFile)
    ) {
        unlink($uploadDir . $oldFile);
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
