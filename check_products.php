<?php
require 'helpers.php';
$db = getDB();
$cid = getCompanyId();
$stmt = $db->prepare('SELECT COUNT(*) as cnt FROM products WHERE cid = :cid');
$stmt->bindValue(':cid', $cid);
$res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
echo 'Products count: ' . ($res['cnt'] ?? 0) . PHP_EOL;
?>