<?php
require_once __DIR__ . '/helpers.php';

switch ($action) {

   case "loadDashboard":
    try {
        requirePermission($db, 'view_reports');

        $cid = intval($cid);
        $range = strtolower(trim($_POST['range'] ?? 'all'));

        // Keep dashboard-heavy paths indexed for faster aggregates and top-N queries.
        $db->exec("CREATE INDEX IF NOT EXISTS idx_partner_cid ON partner(cid)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sales_cid_createdAt ON sales(cid, createdAt)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_purchases_cid_createdAt ON purchases(cid, createdAt)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sales_items_sale_id ON sales_items(sale_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_purchases_partner_cid ON purchases(partner_id, cid)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_sales_partner_cid ON sales(partner_id, cid)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_inventory_cid_product_id ON inventory(cid, product_id)");

            $dateFilter = "";
            $dateFilterAlias = "";
            $rangeMap = ['today', '7d', '30d'];
            $dateFilterParams = [];
            $dateFilterAliasParams = [];

            if (!in_array($range, $rangeMap, true) && $range !== 'all') {
            $range = 'all';
        }

            if ($range !== 'all') {
                $todayStart = new DateTimeImmutable('today');
                if ($range === 'today') {
                    $from = $todayStart;
                } elseif ($range === '7d') {
                    $from = $todayStart->modify('-6 days');
                } else {
                    $from = $todayStart->modify('-29 days');
                }
                $to = $todayStart->modify('+1 day');

                // Use range predicates instead of wrapping columns in date() for index-friendly filtering.
                $fromStr = $from->format('Y-m-d H:i:s');
                $toStr = $to->format('Y-m-d H:i:s');

                $dateFilter = " AND createdAt >= :from_created_at AND createdAt < :to_created_at";
                $dateFilterAlias = " AND t.createdAt >= :from_created_at_alias AND t.createdAt < :to_created_at_alias";
                $dateFilterParams = [
                    ':from_created_at' => $fromStr,
                    ':to_created_at' => $toStr,
                ];
                $dateFilterAliasParams = [
                    ':from_created_at_alias' => $fromStr,
                    ':to_created_at_alias' => $toStr,
                ];
        }

        $partnerStatsStmt = $db->prepare(" 
            SELECT
                COALESCE(SUM(outstanding),0) AS outstanding,
                COALESCE(SUM(advancePayment),0) AS advancePayment,
                COUNT(CASE WHEN outstanding > 0 THEN 1 END) AS activeDebtors,
                COUNT(CASE WHEN advancePayment > 0 THEN 1 END) AS activeCreditors,
                COUNT(sid) AS totalPartners
            FROM partner
            WHERE cid = :cid
        ");
        $partnerStatsStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $partnerStatsRow = $partnerStatsStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

        $salesStatsStmt = $db->prepare(" 
            SELECT
                COALESCE(SUM(totalAmount),0) AS totalSales,
                COUNT(sale_id) AS salesCount
            FROM sales
            WHERE cid = :sales_cid {$dateFilter}
        ");
        $salesStatsStmt->bindValue(':sales_cid', $cid, SQLITE3_INTEGER);
        foreach ($dateFilterParams as $key => $value) {
            $salesStatsStmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $salesStatsRow = $salesStatsStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

        $purchaseStatsStmt = $db->prepare(" 
            SELECT
                COALESCE(SUM(totalAmount),0) AS totalPurchases,
                COUNT(purchase_id) AS purchaseCount
            FROM purchases
            WHERE cid = :purchase_cid {$dateFilter}
        ");
        $purchaseStatsStmt->bindValue(':purchase_cid', $cid, SQLITE3_INTEGER);
        foreach ($dateFilterParams as $key => $value) {
            $purchaseStatsStmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $purchaseStatsRow = $purchaseStatsStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

        $inventoryValueStmt = $db->prepare(" 
            SELECT COALESCE(SUM(COALESCE(inv.qty,0) * COALESCE(p.cost_price,0)),0) AS inventoryValue
            FROM products p
            LEFT JOIN (
                SELECT product_id, cid, SUM(quantity) AS qty
                FROM inventory
                WHERE cid = :inv_cid
                GROUP BY product_id, cid
            ) inv ON inv.product_id = p.product_id AND inv.cid = p.cid
            WHERE p.cid = :prod_cid
        ");
        $inventoryValueStmt->bindValue(':inv_cid', $cid, SQLITE3_INTEGER);
        $inventoryValueStmt->bindValue(':prod_cid', $cid, SQLITE3_INTEGER);
        $inventoryValueRow = $inventoryValueStmt->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

        $topSellingProducts = [];
        $topSellingStmt = $db->prepare(" 
            SELECT
                pr.product_id,
                pr.product_name,
                COALESCE(SUM(si.qty),0) AS total_qty,
                COALESCE(SUM(si.total),0) AS total_amount
            FROM sales_items si
            INNER JOIN sales t ON t.sale_id = si.sale_id
            INNER JOIN products pr ON pr.product_id = si.product_id
            WHERE t.cid = :cid {$dateFilterAlias}
            GROUP BY pr.product_id, pr.product_name
            ORDER BY total_qty DESC, total_amount DESC
            LIMIT 5
        ");
        $topSellingStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        foreach ($dateFilterAliasParams as $key => $value) {
            $topSellingStmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $topSellingRes = $topSellingStmt->execute();
        while($row = $topSellingRes->fetchArray(SQLITE3_ASSOC)){
            $topSellingProducts[] = $row;
        }

        $topSuppliers = [];
        $topSuppliersStmt = $db->prepare(" 
            SELECT
                par.sid,
                par.sName,
                COUNT(t.purchase_id) AS transactions,
                COALESCE(SUM(t.totalAmount),0) AS total_amount
            FROM purchases t
            INNER JOIN partner par ON par.sid = t.partner_id
            WHERE t.cid = :cid {$dateFilterAlias}
            GROUP BY par.sid, par.sName
            ORDER BY total_amount DESC
            LIMIT 5
        ");
        $topSuppliersStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        foreach ($dateFilterAliasParams as $key => $value) {
            $topSuppliersStmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $topSuppliersRes = $topSuppliersStmt->execute();
        while($row = $topSuppliersRes->fetchArray(SQLITE3_ASSOC)){
            $topSuppliers[] = $row;
        }

        $topBuyers = [];
        $topBuyersStmt = $db->prepare(" 
            SELECT
                par.sid,
                par.sName,
                COUNT(t.sale_id) AS transactions,
                COALESCE(SUM(t.totalAmount),0) AS total_amount
            FROM sales t
            INNER JOIN partner par ON par.sid = t.partner_id
            WHERE t.cid = :cid {$dateFilterAlias}
            GROUP BY par.sid, par.sName
            ORDER BY total_amount DESC
            LIMIT 5
        ");
        $topBuyersStmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        foreach ($dateFilterAliasParams as $key => $value) {
            $topBuyersStmt->bindValue($key, $value, SQLITE3_TEXT);
        }
        $topBuyersRes = $topBuyersStmt->execute();
        while($row = $topBuyersRes->fetchArray(SQLITE3_ASSOC)){
            $topBuyers[] = $row;
        }

        $outstanding = floatval($partnerStatsRow['outstanding'] ?? 0);
        $advancePayment = floatval($partnerStatsRow['advancePayment'] ?? 0);
        $activeDebtors = intval($partnerStatsRow['activeDebtors'] ?? 0);
        $activeCreditors = intval($partnerStatsRow['activeCreditors'] ?? 0);
        $totalPartners = intval($partnerStatsRow['totalPartners'] ?? 0);

        $totalSales = floatval($salesStatsRow['totalSales'] ?? 0);
        $totalPurchases = floatval($purchaseStatsRow['totalPurchases'] ?? 0);
        $rangeTransactions = intval($salesStatsRow['salesCount'] ?? 0) + intval($purchaseStatsRow['purchaseCount'] ?? 0);

        $inventoryValue = floatval($inventoryValueRow['inventoryValue'] ?? 0);
        $profit = $totalSales - $totalPurchases;

        respond("success", "Dashboard loaded", [
            "outstanding"      => intval($outstanding),
            "advancePayment"   => intval($advancePayment),
            "activeDebtors"    => intval($activeDebtors),
            "activeCreditors"  => intval($activeCreditors),
            "totalSales"       => floatval($totalSales),
            "totalPurchases"   => floatval($totalPurchases),
            "rangeTransactions"=> intval($rangeTransactions),
            "totalPartners"    => intval($totalPartners),
            "inventoryValue"   => floatval($inventoryValue),
            "profit"           => floatval($profit),
            "metrics"          => [
                "totalSales" => [
                    "raw" => floatval($totalSales),
                    "formatted" => number_format($totalSales, 2)
                ],
                "totalPurchases" => [
                    "raw" => floatval($totalPurchases),
                    "formatted" => number_format($totalPurchases, 2)
                ],
                "inventoryValue" => [
                    "raw" => floatval($inventoryValue),
                    "formatted" => number_format($inventoryValue, 2)
                ],
                "profit" => [
                    "raw" => floatval($profit),
                    "formatted" => number_format($profit, 2)
                ]
            ],
            "topSellingProducts" => $topSellingProducts,
            "topSuppliers"       => $topSuppliers,
            "topBuyers"          => $topBuyers,
            "selectedRange"      => $range
        ]);

    } catch (Exception $e) {
        respond("error", "Failed to load dashboard: " . $e->getMessage());
    }
    break;

    case "loadPartners":
        $rows = [];
        $stmt = $db->prepare("SELECT sid, sName FROM partner WHERE cid = :cid ORDER BY sName ASC");
        $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
        $res = $stmt->execute();

        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        respond("success", "Partners loaded", ["data" => $rows]);
        break;

    default:
        respond("error", "Unknown action: $action");
}

?>