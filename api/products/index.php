<?php
require_once __DIR__ . '/../config.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/* ── GET — list products ─────────────────────────────────── */
if ($method === 'GET') {
    $q     = isset($_GET['q'])       ? trim($_GET['q'])       : '';
    $cat   = isset($_GET['cat'])     ? trim($_GET['cat'])     : '';
    $stock = isset($_GET['stock'])   ? trim($_GET['stock'])   : '';

    $where  = ["p.status != 'deleted'"];
    $params = [];
    $types  = '';

    if ($cat !== '') {
        $where[]  = 'c.name = ?';
        $params[] = $cat;
        $types   .= 's';
    }
    if ($stock === 'low') {
        $where[] = 'p.stock_quantity > 0 AND p.stock_quantity <= p.low_stock_alert';
    } elseif ($stock === 'out') {
        $where[] = 'p.stock_quantity = 0';
    } elseif ($stock === 'ok') {
        $where[] = 'p.stock_quantity > p.low_stock_alert';
    }
    if ($q !== '') {
        $like     = '%' . $q . '%';
        $where[]  = '(p.name LIKE ? OR p.sku LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $types   .= 'ss';
    }

    $whereSQL = implode(' AND ', $where);
    $sql = "SELECT p.id, p.name, p.sku, p.category_id, c.name AS category,
                   p.unit, p.buy_price, p.sell_price, p.stock_quantity,
                   p.low_stock_alert, p.status, p.description
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE $whereSQL
            ORDER BY p.name ASC
            LIMIT 200";

    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    foreach ($rows as &$r) {
        $r['id']             = (int)$r['id'];
        $r['buy_price']      = (float)$r['buy_price'];
        $r['sell_price']     = (float)$r['sell_price'];
        $r['stock_quantity'] = (int)$r['stock_quantity'];
        $r['low_stock_alert']= (int)$r['low_stock_alert'];
    }
    jsonResponse($rows);
}

/* ── POST — add product ──────────────────────────────────── */
if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $name    = sanitize($d['name']        ?? '');
    $sku     = sanitize($d['sku']         ?? '');
    $cat_id  = (int)($d['category_id']    ?? 0);
    $unit    = sanitize($d['unit']        ?? 'piece');
    $buy     = (float)($d['buy_price']    ?? 0);
    $sell    = (float)($d['sell_price']   ?? 0);
    $stock   = (int)($d['stock_quantity'] ?? 0);
    $alert   = (int)($d['low_stock_alert']?? 5);
    $status  = in_array($d['status'] ?? '', ['active','inactive']) ? $d['status'] : 'active';
    $desc    = sanitize($d['description'] ?? '');

    if ($name === '' || $sell <= 0) jsonResponse(['error' => 'Name and sell price are required'], 400);

    if ($sku === '') {
        $r   = $db->query("SELECT COUNT(*) AS c FROM products");
        $cnt = (int)$r->fetch_assoc()['c'];
        $sku = 'SKU-' . str_pad($cnt + 1, 4, '0', STR_PAD_LEFT);
    }

    $stmt = $db->prepare(
        "INSERT INTO products (name,sku,category_id,unit,buy_price,sell_price,stock_quantity,low_stock_alert,status,description)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('ssissddiss', $name, $sku, $cat_id, $unit, $buy, $sell, $stock, $alert, $status, $desc);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    $db->close();
    jsonResponse(['success' => true, 'id' => $id, 'sku' => $sku]);
}

/* ── PUT — edit product ──────────────────────────────────── */
if ($method === 'PUT') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$d || $id <= 0) jsonResponse(['error' => 'Invalid data'], 400);

    $name   = sanitize($d['name']         ?? '');
    $sku    = sanitize($d['sku']          ?? '');
    $cat_id = (int)($d['category_id']     ?? 0);
    $unit   = sanitize($d['unit']         ?? 'piece');
    $buy    = (float)($d['buy_price']     ?? 0);
    $sell   = (float)($d['sell_price']    ?? 0);
    $stock  = (int)($d['stock_quantity']  ?? 0);
    $alert  = (int)($d['low_stock_alert'] ?? 5);
    $status = in_array($d['status'] ?? '', ['active','inactive']) ? $d['status'] : 'active';
    $desc   = sanitize($d['description']  ?? '');

    $stmt = $db->prepare(
        "UPDATE products SET name=?,sku=?,category_id=?,unit=?,buy_price=?,sell_price=?,
         stock_quantity=?,low_stock_alert=?,status=?,description=? WHERE id=?"
    );
    $stmt->bind_param('ssissddissi', $name, $sku, $cat_id, $unit, $buy, $sell, $stock, $alert, $status, $desc, $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    jsonResponse(['success' => true]);
}

/* ── DELETE — soft delete ────────────────────────────────── */
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);
    $stmt = $db->prepare("UPDATE products SET status='deleted' WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
