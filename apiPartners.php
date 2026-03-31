<?php
require_once __DIR__ . '/helpers.php';

try {
switch ($action) {
      
    // ============================
    // ADD PARTNER 
    // ============================
     case "addPartner":
        $name    = safe_input($_POST['aName'] ?? '');
        $email   = strtolower(safe_input($_POST['aEmail'] ?? ''));
        $address = safe_input($_POST['aAddress'] ?? '');
        $phone   = safe_input($_POST['aPhone'] ?? '');
        $logo    = handleFileUpload('partnerImage', 'user.jpg');
        if ($name === '') respond("error", "Partner name required");
        $dup = $db->prepare("SELECT 1 FROM partner WHERE cid=:cid AND lower(sName)=lower(:name)");
        if (!$dup) {
            throw new Exception("Unable to prepare duplicate partner lookup");
        }
        $dup->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $dup->bindValue(':name', $name, SQLITE3_TEXT);
        $dupRes = $dup->execute();
        if ($dupRes === false) {
            throw new Exception("Unable to execute duplicate partner lookup");
        }
        if ($dupRes->fetchArray())
            respond("error", "Partner name already exists");

        try {
            $createdAt = appNowBusinessDateTime();
            // Primary insert path for schemas that include created_at/updated_at.
            $stmt = $db->prepare("
                INSERT INTO partner
                (sName, sEmail, sPhone, sAddress, outstanding, advancePayment, sLogo, cid, created_at, updated_at)
                VALUES
                (:name, :email, :phone, :address, 0, 0, :logo, :cid, :created_at, strftime('%s','now'))
            ");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':address', $address, SQLITE3_TEXT);
            $stmt->bindValue(':logo', $logo, SQLITE3_TEXT);
            $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
            $stmt->bindValue(':created_at', $createdAt, SQLITE3_TEXT);
            $result = $stmt->execute();
            if ($result === false) {
                throw new Exception("Insert with timestamps failed");
            }
            respond("success", "Partner added successfully");
        } catch (Throwable $firstInsertError) {
            try {
                // Fallback insert path for older schemas without created_at/updated_at.
                $fallback = $db->prepare("
                    INSERT INTO partner
                    (sName, sEmail, sPhone, sAddress, outstanding, advancePayment, sLogo, cid)
                    VALUES
                    (:name, :email, :phone, :address, 0, 0, :logo, :cid)
                ");
                $fallback->bindValue(':name', $name, SQLITE3_TEXT);
                $fallback->bindValue(':email', $email, SQLITE3_TEXT);
                $fallback->bindValue(':phone', $phone, SQLITE3_TEXT);
                $fallback->bindValue(':address', $address, SQLITE3_TEXT);
                $fallback->bindValue(':logo', $logo, SQLITE3_TEXT);
                $fallback->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $fbResult = $fallback->execute();
                if ($fbResult === false) {
                    throw new Exception("Insert without timestamps also failed");
                }
                respond("success", "Partner added successfully");
            } catch (Throwable $secondInsertError) {
                respond("error", "Unable to create partner: " . $secondInsertError->getMessage());
            }
        }
        break;


        
    // ============================
    // EDIT PARTNER
    // ============================
    case "editPartner":
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) respond("error", "Invalid partner ID");
        $partner = getPartner($db, $id, $cid);
        if (!$partner) respond("error", "Partner not found");
        $name    = safe_input($_POST['aName']);
        $email   = strtolower(safe_input($_POST['aEmail']));
        $address = safe_input($_POST['aAddress']);
        $phone   = safe_input($_POST['aPhone']);
        $logo    = handleFileUpload('editPartnerImage', $partner['sLogo'] ?? 'user.jpg');
        $stmt = $db->prepare("
            UPDATE partner SET
                sName = :name,
                sEmail = :email,
                sPhone = :phone,
                sAddress = :address,
                sLogo = :logo,
                updated_at = strftime('%s','now')
            WHERE sid = :id AND cid = :cid
        ");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $stmt->bindValue(':address', $address, SQLITE3_TEXT);
        $stmt->bindValue(':logo', $logo, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) {
            respond("error", "Unable to update partner");
        }
        respond("success", "Partner updated successfully");
        break;



        
    // ============================
    // DELETE PARTNER
    // ============================
    case "deletePartner":
        requirePermission($db, 'delete_records');
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) respond("error", "Invalid partner ID");
        $stmt = $db->prepare("DELETE FROM partner WHERE sid = :id AND cid = :cid");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) {
            respond("error", "Unable to delete partner");
        }
        respond("success", "Partner deleted successfully");
        break;

        
    // ============================
    // LOAD PARTNERS
    // ============================
    case "loadAllPartners":
        $sql = "SELECT * FROM partner WHERE cid = :cid ORDER BY sid DESC";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Unable to prepare partners query");
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if ($res === false) {
            throw new Exception("Unable to load partners");
        }
        $data = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC))
            $data[] = $row;
        respond("success", "All partners loaded", ["data" => $data]);
        break;

        
        
    // ============================
    // LOAD ACTIVE PARTNER DEBTORS
    // ============================
    case "loadActivePartnerDebtors":
        $sql = "SELECT * FROM partner WHERE cid = :cid AND outstanding > 0 ORDER BY sid DESC";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Unable to prepare debtor partners query");
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if ($res === false) {
            throw new Exception("Unable to load active debtors");
        }
        $data = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC))
            $data[] = $row;
        respond("success", "Active debtors loaded", ["data" => $data]);
        break;



    // ============================
    // LOAD ACTIVE PARTNER CREDITORS
    // ============================
    case "loadActivePartnerCreditors":
        $sql = "SELECT * FROM partner WHERE cid = :cid AND advancePayment > 0 ORDER BY sid DESC";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception("Unable to prepare creditor partners query");
        }
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        if ($res === false) {
            throw new Exception("Unable to load active creditors");
        }
        $data = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC))
            $data[] = $row;
        respond("success", "Active creditors loaded", ["data" => $data]);
        break;


// ============================
// LOAD PARTNER DETAILS (FULL)
// ============================
case "loadPartnerDetails":

    $sid = intval($_POST['id'] ?? 0);
    if ($sid <= 0) {
        respond("error", "Invalid partner ID");
    }

    // ----------------------------
    // 1. Load Partner
    // ----------------------------
    $partner = getPartner($db, $sid, $cid);
    if (!$partner) {
        respond("error", "Partner not found");
    }

    // ----------------------------
    // 2. Load Partner Ledger
    // ----------------------------
    $partner_ledger = [];
    $stmt = $db->prepare("
        SELECT *
        FROM partner_ledger
        WHERE sid = :sid AND cid = :cid
        ORDER BY createdAt DESC
    ");
    if (!$stmt) {
        throw new Exception("Unable to prepare partner ledger query");
    }
    $stmt->bindValue(':sid', $sid, SQLITE3_INTEGER);
    $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
    $res = $stmt->execute();
    if ($res === false) {
        throw new Exception("Unable to load partner ledger");
    }

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $partner_ledger[] = $row;
    }

    // ----------------------------
    // 3. Load Purchases
    // ----------------------------
    $purchases = [];
    $pStmt = $db->prepare("
        SELECT purchase_id,
               partner_id,
               transaction_type,
               totalAmount,
               amountPaid,
               status,
               createdAt,
               source_type
        FROM (
            SELECT p.purchase_id AS purchase_id,
                   p.partner_id AS partner_id,
                   'buy' AS transaction_type,
                   p.totalAmount,
                   p.amountPaid,
                   p.status,
                   p.createdAt,
                   'purchase' AS source_type
            FROM purchases p
            WHERE p.partner_id = :sid_buy AND p.cid = :cid_buy

            UNION ALL

            SELECT (s.sale_id + 1000000000) AS purchase_id,
                   s.partner_id AS partner_id,
                   'sell' AS transaction_type,
                   s.totalAmount,
                   s.amountPaid,
                   s.status,
                   s.createdAt,
                   'sale' AS source_type
            FROM sales s
            WHERE s.partner_id = :sid_sell AND s.cid = :cid_sell
        ) x
        ORDER BY createdAt DESC
    ");
    $pStmt->bindValue(':sid_buy', $sid, SQLITE3_INTEGER);
    $pStmt->bindValue(':cid_buy', $cid, SQLITE3_INTEGER);
    $pStmt->bindValue(':sid_sell', $sid, SQLITE3_INTEGER);
    $pStmt->bindValue(':cid_sell', $cid, SQLITE3_INTEGER);
    $pRes = $pStmt->execute();
    if ($pRes === false) {
        throw new Exception("Unable to load partner transactions");
    }

    while ($purchase = $pRes->fetchArray(SQLITE3_ASSOC)) {
        $items = [];

        $decodedId = intval($purchase['purchase_id']);
        $sourceType = strtolower($purchase['source_type'] ?? 'purchase');

        if ($sourceType === 'sale') {
            $baseId = max(0, $decodedId - 1000000000);
            $iStmt = $db->prepare("
                SELECT si.item_id,
                       si.product_id,
                       si.qty,
                       si.costPrice,
                       si.total,
                       pr.product_name,
                       pr.product_unit
                FROM sales_items si
                LEFT JOIN products pr ON pr.product_id = si.product_id
                WHERE si.sale_id = :transaction_id
                ORDER BY si.item_id ASC
            ");
            if (!$iStmt) {
                throw new Exception("Unable to prepare sale items query");
            }
            $iStmt->bindValue(':transaction_id', $baseId, SQLITE3_INTEGER);
        } else {
            $iStmt = $db->prepare("
                SELECT pi.item_id,
                       pi.product_id,
                       pi.qty,
                       pi.costPrice,
                       pi.total,
                       pr.product_name,
                       pr.product_unit
                FROM purchases_items pi
                LEFT JOIN products pr ON pr.product_id = pi.product_id
                WHERE pi.purchase_id = :transaction_id
                ORDER BY pi.item_id ASC
            ");
            if (!$iStmt) {
                throw new Exception("Unable to prepare purchase items query");
            }
            $iStmt->bindValue(':transaction_id', $decodedId, SQLITE3_INTEGER);
        }

        $iRes = $iStmt->execute();
        if ($iRes === false) {
            throw new Exception("Unable to load transaction items");
        }
        while ($row = $iRes->fetchArray(SQLITE3_ASSOC)) {
            $items[] = $row;
        }

        unset($purchase['source_type']);
        $purchase['items'] = $items;
        $purchases[] = $purchase;
    }

    // ----------------------------
    // Final Response
    // ----------------------------
    respond("success", "Partner full details loaded", [
        "partner" => $partner,
        "partner_ledger" => $partner_ledger,
        "purchases" => $purchases
    ]);

    break;
        

    // ============================
    // Add Payment (Customer)
    // ============================
    case "payDebt":
        $sid    = intval($_POST["id"]);
        $amount = floatval(safe_input($_POST["amount"]));
        $desc   = safe_input($_POST["payDesc"]);

        if ($sid <= 0 || $amount <= 0) {
            respond("error", "Invalid payment input");
        }

        $db->exec("BEGIN");
        $customer = getPartner($db, $sid, $cid);
        if (!$customer) {
            $db->exec("ROLLBACK");
            respond("error", "Customer not found");
        }

        $outstanding    = floatval($customer["outstanding"] ?? 0);
        $advancePayment = floatval($customer["advancePayment"] ?? 0);

        if ($outstanding > 0) {
            if ($amount >= $outstanding) {
                $advancePayment += ($amount - $outstanding);
                $outstanding = 0;
            } else {
                $outstanding -= $amount;
            }
        } else {
            $advancePayment += $amount;
        }

        $stmt = $db->prepare("UPDATE partner SET outstanding = :out, advancePayment = :adv, updated_at = strftime('%s','now') WHERE sid = :sid AND cid = :cid");
        $stmt->bindValue(":out", $outstanding, SQLITE3_FLOAT);
        $stmt->bindValue(":adv", $advancePayment, SQLITE3_FLOAT);
        $stmt->bindValue(":sid", $sid, SQLITE3_INTEGER);
        $stmt->bindValue(":cid", $cid, SQLITE3_INTEGER);
        $ok = $stmt->execute();

        $createdAt = appNowBusinessDateTime();
        $stmt = $db->prepare("INSERT INTO partner_ledger (cid, sid, type, debit, credit, outstanding, advancePayment, note, reference_id, createdAt)
                 VALUES (:cid, :sid, 'payDebt', 0, :amount, :outstanding, :advancePayment, :desc, NULL, :createdAt)");
        $stmt->bindValue(":cid", $cid, SQLITE3_INTEGER);
        $stmt->bindValue(":sid", $sid, SQLITE3_INTEGER);
        $stmt->bindValue(":amount", $amount, SQLITE3_FLOAT);        
        $stmt->bindValue(":outstanding", $outstanding, SQLITE3_FLOAT);
        $stmt->bindValue(":advancePayment", $advancePayment, SQLITE3_FLOAT);
        $stmt->bindValue(":desc", $desc, SQLITE3_TEXT);
        $stmt->bindValue(":createdAt", $createdAt, SQLITE3_TEXT);
        $stmt->execute();

        if ($ok) {
            $db->exec("COMMIT");
            respond("success", "Payment recorded successfully!");
        } else {
            $db->exec("ROLLBACK");
            respond("error", "Failed to record payment");
        }
        break;

    // ============================
    // Add Debt (Customer)
    // ============================
    case "addDebt":
        $sid    = intval($_POST["id"]);
        $amount = floatval(safe_input($_POST["amount"]));
        $desc   = safe_input($_POST["debtDesc"]);

        if ($sid <= 0 || $amount <= 0) {
            respond("error", "Invalid debt input");
        }

        $db->exec("BEGIN");
        $customer = getPartner($db, $sid, $cid);
        if (!$customer) {
            $db->exec("ROLLBACK");
            respond("error", "Customer not found");
        }

        $outstanding    = floatval($customer["outstanding"] ?? 0);
        $advancePayment = floatval($customer["advancePayment"] ?? 0);

        if ($advancePayment > 0) {
            if ($amount >= $advancePayment) {
                $outstanding += ($amount - $advancePayment);
                $advancePayment = 0;
            } else {
                $advancePayment -= $amount;
            }
        } else {
            $outstanding += $amount;
        }

        $stmt = $db->prepare("UPDATE partner SET outstanding = :out, advancePayment = :adv, updated_at = strftime('%s','now') WHERE sid = :sid AND cid = :cid");
        $stmt->bindValue(":out", $outstanding, SQLITE3_FLOAT);
        $stmt->bindValue(":adv", $advancePayment, SQLITE3_FLOAT);
        $stmt->bindValue(":sid", $sid, SQLITE3_INTEGER);
        $stmt->bindValue(":cid", $cid, SQLITE3_INTEGER);
        $ok = $stmt->execute();

        $createdAt = appNowBusinessDateTime();
        $stmt = $db->prepare("INSERT INTO partner_ledger (cid, sid, type, debit, credit, outstanding, advancePayment, note, reference_id, createdAt)
                 VALUES (:cid, :sid, 'addDebt', :amount, 0, :outstanding, :advancePayment, :desc, NULL, :createdAt)");
        $stmt->bindValue(":cid", $cid, SQLITE3_INTEGER);
        $stmt->bindValue(":sid", $sid, SQLITE3_INTEGER);
        $stmt->bindValue(":amount", $amount, SQLITE3_FLOAT);        
        $stmt->bindValue(":outstanding", $outstanding, SQLITE3_FLOAT);
        $stmt->bindValue(":advancePayment", $advancePayment, SQLITE3_FLOAT);
        $stmt->bindValue(":desc", $desc, SQLITE3_TEXT);
        $stmt->bindValue(":createdAt", $createdAt, SQLITE3_TEXT);
        $stmt->execute();

        if ($ok) {
            $db->exec("COMMIT");
            respond("success", "Debt added successfully!");
        } else {
            $db->exec("ROLLBACK");
            respond("error", "Failed to add debt");
        }
        break;
    
    // ============================
    // DEFAULT CASE
    // ============================
        default:
            respond("error", "Invalid action");     
}
} catch (Throwable $e) {
    error_log('TradeMeter apiPartners error: ' . $e->getMessage());
    respond('error', 'Partner request failed: ' . $e->getMessage());
}
