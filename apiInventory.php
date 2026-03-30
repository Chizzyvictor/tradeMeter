<?php 
require_once __DIR__ . '/helpers.php';

switch ($action) {


// =============================
// CREATE CATEGORY
// =============================
case "createCategory":

requireAnyPermission($db, ['manage_products', 'manage_inventory']);

$categoryName = safe_input($_POST['category_name'] ?? '');
$categoryDescription = safe_input($_POST['category_description'] ?? '');

if($categoryName == ""){
    respond("error","Category name required");
}

$stmt = $db->prepare("
INSERT INTO product_categories
(cid, category_name, category_description)
VALUES (:cid,:name,:description)
");

$stmt->bindValue(":cid",$cid);
$stmt->bindValue(":name",$categoryName);
$stmt->bindValue(":description",$categoryDescription);
$stmt->execute();

respond("success","Category created",[
"category_id"=>$db->lastInsertRowID()
]);

break;



// =============================
// LOAD CATEGORIES
// =============================
case "loadCategories":

$data=[];

$stmt = $db->prepare(" 
SELECT category_id,category_name,category_description,is_active
FROM product_categories
WHERE cid=:cid
ORDER BY category_name
");
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
$res = $stmt->execute();

while($row=$res->fetchArray(SQLITE3_ASSOC)){
$data[]=$row;
}

respond("success","Categories loaded",["data"=>$data]);

break;



// =============================
// CREATE PRODUCT
// =============================
case "createProduct":

requireAnyPermission($db, ['manage_products', 'manage_inventory']);

$productName = safe_input($_POST['product_name'] ?? '');
$categoryId = intval($_POST['category_id'] ?? 0);
$productUnit = safe_input($_POST['product_unit'] ?? 'pcs');
$costPrice = floatval($_POST['cost_price'] ?? 0);
$sellingPrice = floatval($_POST['selling_price'] ?? 0);
$reorderLevel = intval($_POST['reorder_level'] ?? 0);
$openingQty = intval($_POST['opening_qty'] ?? 0);

if($productName==""){
respond("error","Product name required");
}

$image = handleFileUpload("product_image","product.jpg");

$db->exec("BEGIN");

try{

$stmt=$db->prepare("
INSERT INTO products
(cid,category_id,product_name,product_image,product_unit,cost_price,selling_price,reorder_level)
VALUES
(:cid,:category,:name,:image,:unit,:cost,:sell,:reorder)
");

$stmt->bindValue(":cid",$cid);
$stmt->bindValue(":category",$categoryId);
$stmt->bindValue(":name",$productName);
$stmt->bindValue(":image",$image);
$stmt->bindValue(":unit",$productUnit);
$stmt->bindValue(":cost",$costPrice);
$stmt->bindValue(":sell",$sellingPrice);
$stmt->bindValue(":reorder",$reorderLevel);
$stmt->execute();

$productId=$db->lastInsertRowID();

$stmt=$db->prepare("
INSERT INTO inventory
(product_id,cid,quantity)
VALUES
(:product,:cid,:qty)
");

$stmt->bindValue(":product",$productId);
$stmt->bindValue(":cid",$cid);
$stmt->bindValue(":qty",$openingQty);
$stmt->execute();


if($openingQty>0){

$stmt=$db->prepare("
INSERT INTO stock_ledger
(product_id,cid,reference_type,qty_in,balance_after)
VALUES
(:product,:cid,'adjustment',:qty,:balance)
");

$stmt->bindValue(":product",$productId);
$stmt->bindValue(":cid",$cid);
$stmt->bindValue(":qty",$openingQty);
$stmt->bindValue(":balance",$openingQty);
$stmt->execute();
}

$db->exec("COMMIT");

respond("success","Product created",[
"product_id"=>$productId
]);

}catch(Throwable $e){

$db->exec("ROLLBACK");
respond("error",$e->getMessage());

}

break;



// =============================
// LOAD INVENTORY
// =============================
case "loadInventory":

$categoryId = intval($_POST['category_id'] ?? 0);

$data=[];

$sql="
SELECT
p.product_id,
p.product_name,
p.product_image,
p.product_unit,
p.cost_price,
p.selling_price,
p.reorder_level,
p.is_active,
pc.category_name,
i.quantity,
COALESCE(SUM(sl.qty_out), 0) as total_sold,
MIN(sl.created_at) as first_sale_date,
MAX(sl.created_at) as last_sale_date
FROM products p
LEFT JOIN inventory i ON i.product_id=p.product_id AND i.cid=p.cid
LEFT JOIN product_categories pc ON pc.category_id=p.category_id
LEFT JOIN stock_ledger sl ON sl.product_id = p.product_id AND sl.cid = p.cid AND sl.reference_type = 'sale'
WHERE p.cid=:cid
";

if($categoryId>0){
$sql.=" AND p.category_id=:category_id";
}

$sql.="
GROUP BY
p.product_id,
p.product_name,
p.product_image,
p.product_unit,
p.cost_price,
p.selling_price,
p.reorder_level,
p.is_active,
pc.category_name,
i.quantity
ORDER BY p.product_name";

$stmt=$db->prepare($sql);
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
if($categoryId>0){
$stmt->bindValue(':category_id', $categoryId, SQLITE3_INTEGER);
}
$res=$stmt->execute();

while($row=$res->fetchArray(SQLITE3_ASSOC)){
$data[]=$row;
}

respond("success","Inventory loaded",["data"=>$data]);

break;



// =============================
// GET REORDER SUGGESTIONS
// =============================
case "getReorderSuggestions":

$suggestions = [];

$stmt = $db->prepare("
SELECT
p.product_id,
p.product_name,
p.reorder_level,
p.is_active,
COALESCE(i.quantity, 0) as quantity,
COALESCE(SUM(sl.qty_out), 0) as total_sold,
MIN(sl.created_at) as first_sale_date,
MAX(sl.created_at) as last_sale_date
FROM products p
LEFT JOIN inventory i ON i.product_id = p.product_id AND i.cid = p.cid
LEFT JOIN stock_ledger sl ON sl.product_id = p.product_id AND sl.cid = p.cid AND sl.reference_type = 'sale'
WHERE p.cid = :cid
GROUP BY
p.product_id,
p.product_name,
p.reorder_level,
p.is_active,
i.quantity
ORDER BY p.product_name
");
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
$res = $stmt->execute();

while($row = $res->fetchArray(SQLITE3_ASSOC)){
    $isActive = intval($row['is_active'] ?? 1) === 1;
    if(!$isActive){
        continue;
    }

    $qty = intval($row['quantity'] ?? 0);
    $reorder = intval($row['reorder_level'] ?? 0);
    $totalSold = floatval($row['total_sold'] ?? 0);
    $days = 0;
    $suggestedQty = max(0, ($reorder * 2) - $qty);

    if($totalSold > 0 && !empty($row['first_sale_date']) && !empty($row['last_sale_date'])){
        $first = strtotime($row['first_sale_date']);
        $last = strtotime($row['last_sale_date']);

        if($first !== false && $last !== false){
            $diffDays = max(1, ($last - $first) / 86400);
            $dailySales = $totalSold / $diffDays;

            if($dailySales > 0){
                $days = (int) floor($qty / $dailySales);
                $suggestedQty = max(0, (int) ceil(($dailySales * 14) - $qty));
            }
        }
    }

    if($qty <= $reorder || ($days > 0 && $days <= 7)){
        $row['days_left'] = $days;
        $row['suggested_qty'] = $suggestedQty;
        $suggestions[] = $row;
    }
}

respond("success","Reorder suggestions",["data"=>$suggestions]);

break;



// =============================
// LOAD STOCK LEDGER
// =============================
case "loadStockLedger":

$limit = intval($_POST['limit'] ?? 100);
if($limit <= 0){
$limit = 100;
}
if($limit > 500){
$limit = 500;
}

$data=[];

$stmt=$db->prepare(" 
SELECT
sl.ledger_id,
sl.product_id,
p.product_name,
sl.reference_type,
sl.reference_id,
sl.qty_in,
sl.qty_out,
sl.balance_after,
sl.created_at
FROM stock_ledger sl
LEFT JOIN products p ON p.product_id=sl.product_id
WHERE sl.cid=:cid
ORDER BY sl.ledger_id DESC
LIMIT :limit
");
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
$stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
$res=$stmt->execute();

while($row=$res->fetchArray(SQLITE3_ASSOC)){
$data[]=$row;
}

respond("success","Stock ledger loaded",["data"=>$data]);

break;



// =============================
// EDIT CATEGORY
// =============================
case "editCategory":

requireAnyPermission($db, ['manage_products', 'manage_inventory']);

$categoryId=intval($_POST['category_id'] ?? 0);
$name=safe_input($_POST['category_name'] ?? '');
$desc=safe_input($_POST['category_description'] ?? '');
$status=intval($_POST['status'] ?? 1);

$stmt=$db->prepare("
UPDATE product_categories
SET category_name=:name,
category_description=:desc,
is_active=:status
WHERE category_id=:id AND cid=:cid
");

$stmt->bindValue(":name",$name);
$stmt->bindValue(":desc",$desc);
$stmt->bindValue(":status",$status);
$stmt->bindValue(":id",$categoryId);
$stmt->bindValue(":cid",$cid);
$stmt->execute();

respond("success","Category updated");

break;



// =============================
// DELETE CATEGORY
// =============================
case "deleteCategory":

requirePermission($db, 'delete_records');

$categoryId=intval($_POST['category_id'] ?? 0);

$stmt=$db->prepare("
UPDATE product_categories
SET is_active=0
WHERE category_id=:id AND cid=:cid
");

$stmt->bindValue(":id",$categoryId);
$stmt->bindValue(":cid",$cid);
$stmt->execute();

respond("success","Category deleted");

break;



// =============================
// LOAD PRODUCT DETAILS
// =============================
case "loadProductDetails":

$productId=intval($_POST['product_id'] ?? 0);

$stmt=$db->prepare(" 
SELECT
p.product_id,
p.product_name,
p.product_image,
p.product_unit,
p.cost_price,
p.selling_price,
p.reorder_level,
p.is_active,
p.category_id,
pc.category_name,
i.quantity
FROM products p
LEFT JOIN product_categories pc ON pc.category_id=p.category_id
LEFT JOIN inventory i ON i.product_id=p.product_id AND i.cid=p.cid
WHERE p.product_id=:product_id AND p.cid=:cid
");
$stmt->bindValue(':product_id', $productId, SQLITE3_INTEGER);
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
$productRes = $stmt->execute();
$product = $productRes ? $productRes->fetchArray(SQLITE3_ASSOC) : null;

$transactions=[];

$stockMovement=[];

$stmt=$db->prepare("
SELECT
t.transaction_id AS purchase_id,
t.createdAt,
t.transaction_type,
t.totalAmount,
t.amountPaid,
t.status,
par.sName AS partner_name
FROM (
  SELECT p.purchase_id AS transaction_id,
         p.partner_id,
         p.createdAt,
         'buy' AS transaction_type,
         p.totalAmount,
         p.amountPaid,
         p.status
  FROM purchases p
  INNER JOIN purchases_items pi ON pi.purchase_id = p.purchase_id
  WHERE p.cid = :cid_buy AND pi.product_id = :product_id_buy

  UNION ALL

  SELECT (s.sale_id + 1000000000) AS transaction_id,
         s.partner_id,
         s.createdAt,
         'sell' AS transaction_type,
         s.totalAmount,
         s.amountPaid,
         s.status
  FROM sales s
  INNER JOIN sales_items si ON si.sale_id = s.sale_id
  WHERE s.cid = :cid_sell AND si.product_id = :product_id_sell
) t
LEFT JOIN partner par ON par.sid = t.partner_id
ORDER BY t.createdAt DESC
LIMIT 50
");

$stmt->bindValue(':cid_buy',$cid,SQLITE3_INTEGER);
$stmt->bindValue(':product_id_buy',$productId,SQLITE3_INTEGER);
$stmt->bindValue(':cid_sell',$cid,SQLITE3_INTEGER);
$stmt->bindValue(':product_id_sell',$productId,SQLITE3_INTEGER);
$res=$stmt->execute();

while($res && $row=$res->fetchArray(SQLITE3_ASSOC)){
$transactions[]=$row;
}

$stmt=$db->prepare(" 
SELECT
sl.ledger_id,
sl.reference_type,
sl.reference_id,
sl.qty_in,
sl.qty_out,
sl.balance_after,
sl.created_at
FROM stock_ledger sl
WHERE sl.cid=:cid
AND sl.product_id=:product_id
ORDER BY sl.ledger_id DESC
LIMIT 50
");
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
$stmt->bindValue(':product_id', $productId, SQLITE3_INTEGER);
$res=$stmt->execute();

while($row=$res->fetchArray(SQLITE3_ASSOC)){
$stockMovement[]=$row;
}

respond("success","Product details loaded",[
"product"=>$product,
"transactions"=>$transactions,
"stockMovement"=>$stockMovement
]);

break;



// =============================
// EDIT PRODUCT
// =============================
case "editProduct":

requireAnyPermission($db, ['manage_products', 'manage_inventory']);

$productId=intval($_POST['product_id'] ?? 0);
$name=safe_input($_POST['product_name'] ?? '');
$category=intval($_POST['category_id'] ?? 0);
$unit=safe_input($_POST['product_unit'] ?? 'pcs');
$reorder=intval($_POST['reorder_level'] ?? 0);
$cost=floatval($_POST['cost_price'] ?? 0);
$sell=floatval($_POST['selling_price'] ?? 0);
$status=intval($_POST['status'] ?? 1);

$image=handleFileUpload("product_image",null);

$sql="
UPDATE products SET
product_name=:name,
category_id=:category,
product_unit=:unit,
reorder_level=:reorder,
cost_price=:cost,
selling_price=:sell,
is_active=:status
";

if($image){
$sql.=",product_image=:image";
}

$sql.=" WHERE product_id=:id AND cid=:cid";

$stmt=$db->prepare($sql);

$stmt->bindValue(":name",$name);
$stmt->bindValue(":category",$category);
$stmt->bindValue(":unit",$unit);
$stmt->bindValue(":reorder",$reorder);
$stmt->bindValue(":cost",$cost);
$stmt->bindValue(":sell",$sell);
$stmt->bindValue(":status",$status);
$stmt->bindValue(":id",$productId);
$stmt->bindValue(":cid",$cid);

if($image){
$stmt->bindValue(":image",$image);
}

$stmt->execute();

respond("success","Product updated");

break;



// =============================
// RESTOCK PRODUCT
// =============================
case "restockProduct":

requireAnyPermission($db, ['manage_inventory', 'create_purchases']);

$productId=intval($_POST['product_id'] ?? 0);
$qty=intval($_POST['quantity'] ?? 0);

adjustStock($db,$productId,$cid,$qty,'adjustment',0);

respond("success","Product restocked");

break;



// =============================
// DELETE PRODUCT
// =============================
case "deleteProduct":

requirePermission($db, 'delete_records');

$productId=intval($_POST['product_id'] ?? 0);

$stmt=$db->prepare("
UPDATE products
SET is_active=0
WHERE product_id=:id AND cid=:cid
");

$stmt->bindValue(":id",$productId);
$stmt->bindValue(":cid",$cid);
$stmt->execute();

respond("success","Product deleted");

break;



// =============================
// LOAD LOW STOCK
// =============================
case "loadLowStock":

$data=[];

$stmt=$db->prepare(" 
SELECT
p.product_id,
p.product_name,
p.product_image,
p.product_unit,
p.reorder_level,
i.quantity
FROM products p
LEFT JOIN inventory i
ON i.product_id=p.product_id AND i.cid=p.cid
WHERE p.cid=:cid
AND p.is_active=1
AND i.quantity<=p.reorder_level
ORDER BY p.product_name
");
$stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
$res=$stmt->execute();

while($row=$res->fetchArray(SQLITE3_ASSOC)){
$data[]=$row;
}

respond("success","Low stock loaded",["data"=>$data]);

break;



default:
respond("error","Unknown action");

}
