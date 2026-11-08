<?php
require_once __DIR__ . '/../config.php';

$q   = isset($_GET['q'])   ? trim($_GET['q'])   : '';
$cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';

$db = getDB();

$catClause = '';
$catParam  = '';
if ($cat !== '') {
    $catClause = " AND c.name = ?";
    $catParam  = $cat;
}

if ($q === '') {
    $sql = "SELECT p.id, p.name, p.sku, p.barcode, p.sell_price, p.stock_quantity, p.unit, p.status,
                   c.name AS category
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            $catClause
            ORDER BY p.name ASC
            LIMIT 60";
    $stmt = $db->prepare($sql);
    if ($catParam !== '') {
        $stmt->bind_param('s', $catParam);
    }
    $stmt->execute();
} else {
    $like = '%' . $db->real_escape_string($q) . '%';
    $sql  = "SELECT p.id, p.name, p.sku, p.barcode, p.sell_price, p.stock_quantity, p.unit, p.status,
                    c.name AS category
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'active'
               AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
             $catClause
             ORDER BY p.name ASC
             LIMIT 30";
    $stmt = $db->prepare($sql);
    if ($catParam !== '') {
        $stmt->bind_param('ssss', $like, $like, $like, $catParam);
    } else {
        $stmt->bind_param('sss', $like, $like, $like);
    }
    $stmt->execute();
}

$result = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id'             => (int)$row['id'],
        'name'           => $row['name'],
        'sku'            => $row['sku'],
        'barcode'        => $row['barcode'],
        'sell_price'     => (float)$row['sell_price'],
        'stock_quantity' => (int)$row['stock_quantity'],
        'unit'           => $row['unit'],
        'category'       => $row['category'],
        'status'         => $row['status'],
    ];
}

$stmt->close();
$db->close();

jsonResponse($products);
