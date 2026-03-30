<?php
require_once __DIR__ . '/helpers.php';

function normalizeTransactionType($value): string {
    $type = strtolower(trim((string)$value));
    if (in_array($type, ['sell', 'sale'], true)) return 'sell';
    if (in_array($type, ['buy', 'purchase'], true)) return 'buy';
    return '';
}

switch ($action) {

    case "loadProducts":
        $products = [];
        $stmt = $db->prepare("SELECT p.product_id, p.product_name, p.product_unit, p.cost_price, p.selling_price,
                                     COALESCE(i.quantity, 0) AS available_stock
                              FROM products p
                              LEFT JOIN inventory i ON i.product_id = p.product_id AND i.cid = :cid
                              WHERE p.cid = :cid AND COALESCE(p.is_active, 1) = 1
                              ORDER BY p.product_name ASC");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $products[] = $row;
        }

        respond("success", "Products loaded", ["data" => $products]);
        break;

    case "createTransaction":
    case "createPurchase":
    case "createSale":
        $partnerId = intval($_POST['partner_id'] ?? ($_POST['supplier_id'] ?? 0));
        $defaultTransactionType = $action === 'createSale' ? 'sell' : ($action === 'createPurchase' ? 'buy' : 'sell');
        $transactionType = normalizeTransactionType($_POST['transaction_type'] ?? $defaultTransactionType);
        $amountPaid = floatval($_POST['amountPaid'] ?? 0);
        $transactionDate = trim((string)($_POST['transaction_date'] ?? ''));
        $itemsRaw = $_POST['items'] ?? '[]';

        if ($partnerId <= 0) {
            respond("error", "Invalid partner");
        }

        if ($transactionType === '') {
            respond("error", "Invalid transaction type");
        }

        if ($transactionType === 'sell') {
            requirePermission($db, 'create_sales');
        } else {
            requireAnyPermission($db, ['create_purchases', 'manage_inventory']);
        }

        $createdAt = date('Y-m-d H:i:s');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
            $createdAt = $transactionDate . ' ' . date('H:i:s');
        }

        $items = json_decode($itemsRaw, true);
        if (!is_array($items) || count($items) === 0) {
            respond("error", "Purchase items are required");
        }

        $stmt = $db->prepare("SELECT sid FROM partner WHERE sid = :sid AND cid = :cid LIMIT 1");
        $stmt->bindValue(':sid', $partnerId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        if (!$stmt->execute()->fetchArray(SQLITE3_ASSOC)) {
            respond("error", "Partner not found");
        }

        $normalizedItems = [];
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $productId = intval($item['product_id'] ?? 0);
            $qty = intval($item['qty'] ?? 0);
            $costPrice = floatval($item['costPrice'] ?? 0);

            if ($productId <= 0 || $qty <= 0 || $costPrice < 0) {
                respond("error", "Invalid item in purchase list");
            }

            $pStmt = $db->prepare("SELECT product_id FROM products WHERE product_id = :pid AND cid = :cid AND COALESCE(is_active, 1) = 1 LIMIT 1");
            $pStmt->bindValue(':pid', $productId, SQLITE3_INTEGER);
            $pStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            if (!$pStmt->execute()->fetchArray(SQLITE3_ASSOC)) {
                respond("error", "One or more selected products do not exist");
            }

            $lineTotal = $qty * $costPrice;
            $totalAmount += $lineTotal;
            $normalizedItems[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'unitPrice' => $costPrice
            ];
        }

        if ($amountPaid < 0) {
            respond("error", "Invalid paid amount");
        }

        if ($amountPaid > $totalAmount) {
            $amountPaid = $totalAmount;
        }

        $unpaidAmount = max(0, $totalAmount - $amountPaid);

        $status = "pending";
        if ($amountPaid > 0 && $amountPaid < $totalAmount) {
            $status = "partial";
        } elseif ($totalAmount > 0 && $amountPaid >= $totalAmount) {
            $status = "paid";
        }

        $db->exec("BEGIN");

        try {
            if ($transactionType === 'sell') {
                $stmt = $db->prepare("INSERT INTO sales (cid, partner_id, totalAmount, amountPaid, status, createdAt)
                                      VALUES (:cid, :partner_id, :totalAmount, :amountPaid, :status, :createdAt)");
            } else {
                $stmt = $db->prepare("INSERT INTO purchases (cid, partner_id, totalAmount, amountPaid, status, createdAt)
                                      VALUES (:cid, :partner_id, :totalAmount, :amountPaid, :status, :createdAt)");
            }

            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $stmt->bindValue(':partner_id', $partnerId, SQLITE3_INTEGER);
            $stmt->bindValue(':totalAmount', $totalAmount, SQLITE3_FLOAT);
            $stmt->bindValue(':amountPaid', $amountPaid, SQLITE3_FLOAT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':createdAt', $createdAt, SQLITE3_TEXT);
            $stmt->execute();

            $baseTransactionId = intval($db->lastInsertRowID());
            $transactionId = encodeTransactionId($baseTransactionId, $transactionType === 'sell' ? 'sale' : 'purchase');

            foreach ($normalizedItems as $item) {
                if ($transactionType === 'sell') {
                    $iStmt = $db->prepare("INSERT INTO sales_items (sale_id, product_id, qty, costPrice)
                                           VALUES (:transaction_id, :product_id, :qty, :costPrice)");
                } else {
                    $iStmt = $db->prepare("INSERT INTO purchases_items (purchase_id, product_id, qty, costPrice)
                                           VALUES (:transaction_id, :product_id, :qty, :costPrice)");
                }
                $iStmt->bindValue(':transaction_id', $baseTransactionId, SQLITE3_INTEGER);
                $iStmt->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
                $iStmt->bindValue(':qty', $item['qty'], SQLITE3_INTEGER);
                $iStmt->bindValue(':costPrice', $item['unitPrice'], SQLITE3_FLOAT);
                $iStmt->execute();

                if ($transactionType === 'sell') {
                    $availableStmt = $db->prepare("SELECT quantity FROM inventory WHERE product_id = :product_id AND cid = :cid LIMIT 1");
                    $availableStmt->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
                    $availableStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
                    $invRow = $availableStmt->execute()->fetchArray(SQLITE3_ASSOC);
                    $availableQty = intval($invRow['quantity'] ?? 0);
                    if ($availableQty < $item['qty']) {
                        throw new Exception("Insufficient stock for one or more items");
                    }
                }

                $stockStmt = $db->prepare("INSERT INTO inventory (product_id, cid, quantity, last_updated)
                                           VALUES (:product_id, :cid, :qty, CURRENT_TIMESTAMP)
                                           ON CONFLICT(product_id, cid) DO UPDATE SET
                                           quantity = inventory.quantity + excluded.quantity,
                                           last_updated = CURRENT_TIMESTAMP");
                $stockStmt->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
                $stockStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $stockStmt->bindValue(':qty', $transactionType === 'sell' ? (-1 * $item['qty']) : $item['qty'], SQLITE3_INTEGER);
                $stockStmt->execute();

                $balanceStmt = $db->prepare("SELECT quantity FROM inventory WHERE product_id = :product_id AND cid = :cid LIMIT 1");
                $balanceStmt->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
                $balanceStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $balanceRow = $balanceStmt->execute()->fetchArray(SQLITE3_ASSOC);
                $balanceAfter = intval($balanceRow['quantity'] ?? 0);

                $ledgerStmt = $db->prepare("INSERT INTO stock_ledger (product_id, cid, reference_type, reference_id, qty_in, qty_out, balance_after, created_at)
                                            VALUES (:product_id, :cid, :reference_type, :reference_id, :qty_in, :qty_out, :balance_after, CURRENT_TIMESTAMP)");
                $ledgerStmt->bindValue(':product_id', $item['product_id'], SQLITE3_INTEGER);
                $ledgerStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $ledgerStmt->bindValue(':reference_type', $transactionType === 'sell' ? 'sale' : 'purchase', SQLITE3_TEXT);
                $ledgerStmt->bindValue(':reference_id', $transactionId, SQLITE3_INTEGER);
                $ledgerStmt->bindValue(':qty_in', $transactionType === 'sell' ? 0 : $item['qty'], SQLITE3_INTEGER);
                $ledgerStmt->bindValue(':qty_out', $transactionType === 'sell' ? $item['qty'] : 0, SQLITE3_INTEGER);
                $ledgerStmt->bindValue(':balance_after', $balanceAfter, SQLITE3_INTEGER);
                $ledgerStmt->execute();
            }

            applyUnpaidToPartner($db, $partnerId, $cid, $transactionType, $unpaidAmount);

            if ($unpaidAmount > 0) {
                $unpaidStatus = $transactionType === 'sell' ? 0 : 1;
                $unpaidLabel = $transactionType === 'sell' ? 'Sell' : 'Buy';
                addTransactionNotification(
                    $db,
                    $partnerId,
                    $cid,
                    $unpaidAmount,
                    "$unpaidLabel transaction #$transactionId unpaid balance",
                    $unpaidStatus,
                    $now
                );
            }

            $db->exec("COMMIT");
            respond("success", "Transaction created successfully", ["purchase_id" => $transactionId]);
        } catch (Throwable $e) {
            $db->exec("ROLLBACK");
            respond("error", "Failed to create transaction: " . $e->getMessage());
        }
        break;

    case "loadPurchases":
        $rows = [];

        $stmt = $db->prepare("SELECT purchase_id AS raw_id,
                                     partner_id,
                                     'buy' AS transaction_type,
                                     totalAmount,
                                     amountPaid,
                                     status,
                                     createdAt,
                                     'purchase' AS source_type
                              FROM purchases
                              WHERE cid = :cid
                              UNION ALL
                              SELECT sale_id AS raw_id,
                                     partner_id,
                                     'sell' AS transaction_type,
                                     totalAmount,
                                     amountPaid,
                                     status,
                                     createdAt,
                                     'sale' AS source_type
                              FROM sales
                              WHERE cid = :cid
                              ORDER BY createdAt DESC, raw_id DESC");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $sourceType = strtolower($row['source_type'] ?? 'purchase');
            $row['purchase_id'] = encodeTransactionId(intval($row['raw_id']), $sourceType);
            unset($row['raw_id']);

            $nameStmt = $db->prepare("SELECT sName FROM partner WHERE sid = :sid LIMIT 1");
            $nameStmt->bindValue(':sid', intval($row['partner_id']), SQLITE3_INTEGER);
            $nameRow = $nameStmt->execute()->fetchArray(SQLITE3_ASSOC);
            $row['partner_name'] = $nameRow['sName'] ?? $nameRow['sname'] ?? 'Unknown';

            $rows[] = $row;
        }

        respond("success", "Purchases loaded", ["data" => $rows]);
        break;

    case "loadPurchaseDetails":
        $encodedPurchaseId = intval($_POST['purchase_id'] ?? 0);
        if ($encodedPurchaseId <= 0) {
            respond("error", "Invalid purchase ID");
        }

        $decoded = decodeTransactionId($encodedPurchaseId);
        $sourceType = $decoded['type'];
        $baseId = intval($decoded['id']);

        if ($sourceType === 'sale') {
            $stmt = $db->prepare("SELECT sale_id AS raw_id,
                                         partner_id,
                                         'sell' AS transaction_type,
                                         totalAmount,
                                         amountPaid,
                                         status,
                                         createdAt
                                  FROM sales
                                  WHERE sale_id = :id AND cid = :cid
                                  LIMIT 1");
        } else {
            $stmt = $db->prepare("SELECT purchase_id AS raw_id,
                                         partner_id,
                                         'buy' AS transaction_type,
                                         totalAmount,
                                         amountPaid,
                                         status,
                                         createdAt
                                  FROM purchases
                                  WHERE purchase_id = :id AND cid = :cid
                                  LIMIT 1");
        }
        $stmt->bindValue(':id', $baseId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $purchase = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$purchase) {
            respond("error", "Purchase not found");
        }

        $nameStmt = $db->prepare("SELECT sName, sPhone FROM partner WHERE sid = :sid LIMIT 1");
        $nameStmt->bindValue(':sid', intval($purchase['partner_id']), SQLITE3_INTEGER);
        $nameRow = $nameStmt->execute()->fetchArray(SQLITE3_ASSOC);
        $purchase['partner_name'] = $nameRow['sName'] ?? $nameRow['sname'] ?? 'Unknown';
        $purchase['partner_phone'] = $nameRow['sPhone'] ?? $nameRow['sphone'] ?? '';
        $purchase['purchase_id'] = $encodedPurchaseId;
        unset($purchase['raw_id']);

        $items = [];
        if ($sourceType === 'sale') {
            $iStmt = $db->prepare("SELECT si.item_id, si.product_id, si.qty, si.costPrice, si.total,
                                          pr.product_name, pr.product_unit
                                   FROM sales_items si
                                   LEFT JOIN products pr ON pr.product_id = si.product_id
                                   WHERE si.sale_id = :id
                                   ORDER BY si.item_id ASC");
        } else {
            $iStmt = $db->prepare("SELECT pi.item_id, pi.product_id, pi.qty, pi.costPrice, pi.total,
                                          pr.product_name, pr.product_unit
                                   FROM purchases_items pi
                                   LEFT JOIN products pr ON pr.product_id = pi.product_id
                                   WHERE pi.purchase_id = :id
                                   ORDER BY pi.item_id ASC");
        }
        $iStmt->bindValue(':id', $baseId, SQLITE3_INTEGER);
        $iRes = $iStmt->execute();

        while ($row = $iRes->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }

        respond("success", "Purchase details loaded", [
            "purchase" => $purchase,
            "items" => $items
        ]);
        break;

    case "payPurchase":
        $encodedPurchaseId = intval($_POST['purchase_id'] ?? 0);
        $payAmount = floatval($_POST['amount'] ?? 0);

        if ($encodedPurchaseId <= 0 || $payAmount <= 0) {
            respond("error", "Invalid payment details");
        }

        $decoded = decodeTransactionId($encodedPurchaseId);
        $sourceType = $decoded['type'];
        $baseId = intval($decoded['id']);

        if ($sourceType === 'sale') {
            requirePermission($db, 'create_sales');
            $stmt = $db->prepare("SELECT partner_id, totalAmount, amountPaid
                                  FROM sales
                                  WHERE sale_id = :id AND cid = :cid
                                  LIMIT 1");
        } else {
            requireAnyPermission($db, ['create_purchases', 'manage_inventory']);
            $stmt = $db->prepare("SELECT partner_id, totalAmount, amountPaid
                                  FROM purchases
                                  WHERE purchase_id = :id AND cid = :cid
                                  LIMIT 1");
        }

        $stmt->bindValue(':id', $baseId, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $purchase = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$purchase) {
            respond("error", "Purchase not found");
        }

        $transactionType = $sourceType === 'sale' ? 'sell' : 'buy';

        $totalAmount = floatval($purchase['totalAmount']);
        $currentPaid = floatval($purchase['amountPaid']);
        $remaining = max(0, $totalAmount - $currentPaid);
        $actualPay = min($payAmount, $remaining);
        $amountPaid = $currentPaid + $actualPay;
        if ($amountPaid > $totalAmount) {
            $amountPaid = $totalAmount;
        }

        $status = "pending";
        if ($amountPaid > 0 && $amountPaid < $totalAmount) {
            $status = "partial";
        } elseif ($totalAmount > 0 && $amountPaid >= $totalAmount) {
            $status = "paid";
        }

        $db->exec("BEGIN");

        try {
            if ($sourceType === 'sale') {
                $uStmt = $db->prepare("UPDATE sales
                                       SET amountPaid = :amountPaid, status = :status
                                       WHERE sale_id = :id AND cid = :cid");
            } else {
                $uStmt = $db->prepare("UPDATE purchases
                                       SET amountPaid = :amountPaid, status = :status
                                       WHERE purchase_id = :id AND cid = :cid");
            }

            $uStmt->bindValue(':amountPaid', $amountPaid, SQLITE3_FLOAT);
            $uStmt->bindValue(':status', $status, SQLITE3_TEXT);
            $uStmt->bindValue(':id', $baseId, SQLITE3_INTEGER);
            $uStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);

            if (!$uStmt->execute()) {
                throw new Exception("Failed to update transaction payment");
            }

            if ($actualPay > 0) {
                applyPaymentToPartner(
                    $db,
                    intval($purchase['partner_id']),
                    $cid,
                    $transactionType,
                    $actualPay
                );

                addTransactionNotification(
                    $db,
                    intval($purchase['partner_id']),
                    $cid,
                    $actualPay,
                    "Payment received for " . strtoupper($transactionType) . " transaction #$encodedPurchaseId",
                    1,
                    $now
                );
            }

            $db->exec("COMMIT");
            respond("success", "Transaction payment updated", [
                "amountPaid" => $amountPaid,
                "status" => $status
            ]);
        } catch (Throwable $e) {
            $db->exec("ROLLBACK");
            respond("error", "Failed to update transaction payment: " . $e->getMessage());
        }
        break;

    default:
        respond("error", "Unknown action: $action");
}

?>
