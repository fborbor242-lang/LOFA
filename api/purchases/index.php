<?php
require_once __DIR__ . '/../config.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/* ── GET ─────────────────────────────────────────────────── */
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id   = (int)$_GET['id'];
        $stmt = $db->prepare("SELECT * FROM purchases WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$purchase) jsonResponse(['error' => 'Not found'], 404);

        $stmt = $db->prepare(
            "SELECT pi.*, p.name AS product_name, p.sku
             FROM purchase_items pi
             JOIN products p ON p.id = pi.product_id
             WHERE pi.purchase_id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $purchase['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $db->close();
        jsonResponse($purchase);
    }

    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($status !== '') { $where[] = 'p.status = ?'; $params[] = $status; $types .= 's'; }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = '(p.reference_number LIKE ? OR p.supplier LIKE ?)';
        $params[] = $like; $params[] = $like; $types .= 'ss';
    }

    $whereSQL = implode(' AND ', $where);
    $sql = "SELECT p.*, COUNT(pi.id) AS item_count
            FROM purchases p
            LEFT JOIN purchase_items pi ON pi.purchase_id = p.id
            WHERE $whereSQL GROUP BY p.id ORDER BY p.created_at DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['total'] = (float)$r['total'];
        $r['item_count'] = (int)$r['item_count'];
    }
    jsonResponse($rows);
}

/* ── POST ────────────────────────────────────────────────── */
if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $supplier = sanitize($d['supplier'] ?? '');
    $ref      = sanitize($d['reference_number'] ?? '');
    $status   = in_array($d['status'] ?? '', ['received','pending','cancelled']) ? $d['status'] : 'pending';
    $notes    = sanitize($d['notes'] ?? '');
    $items    = $d['items'] ?? [];

    if ($supplier === '') jsonResponse(['error' => 'Supplier required'], 400);
    if (empty($items))   jsonResponse(['error' => 'At least one item required'], 400);

    if ($ref === '') {
        $cnt = (int)$db->query("SELECT COUNT(*) AS c FROM purchases")->fetch_assoc()['c'];
        $ref = 'PO-' . str_pad($cnt + 1, 4, '0', STR_PAD_LEFT);
    }

    $db->begin_transaction();
    try {
        $total = 0.0;
        $validated = [];
        foreach ($items as $item) {
            $pid  = (int)($item['product_id'] ?? 0);
            $qty  = (int)($item['quantity']   ?? 0);
            $cost = (float)($item['unit_cost'] ?? 0);
            if ($pid <= 0 || $qty <= 0 || $cost <= 0) throw new Exception('Invalid item');
            $sub = round($qty * $cost, 2);
            $total += $sub;
            $validated[] = ['pid' => $pid, 'qty' => $qty, 'cost' => $cost, 'sub' => $sub];
        }
        $total = round($total, 2);

        $stmt = $db->prepare("INSERT INTO purchases (reference_number,supplier,total,status,notes) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssdss', $ref, $supplier, $total, $status, $notes);
        $stmt->execute();
        $pid_new = (int)$db->insert_id;
        $stmt->close();

        foreach ($validated as $vi) {
            $stmt = $db->prepare("INSERT INTO purchase_items (purchase_id,product_id,quantity,unit_cost,subtotal) VALUES (?,?,?,?,?)");
            $stmt->bind_param('iiidd', $pid_new, $vi['pid'], $vi['qty'], $vi['cost'], $vi['sub']);
            $stmt->execute();
            $stmt->close();

            if ($status === 'received') {
                $stmt = $db->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?");
                $stmt->bind_param('ii', $vi['qty'], $vi['pid']);
                $stmt->execute();
                $stmt->close();

                $rtype = 'purchase';
                $stmt = $db->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference_id,reference_type) VALUES (?,'purchase',?,?,?)");
                $stmt->bind_param('iiis', $vi['pid'], $vi['qty'], $pid_new, $rtype);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->commit();
        $db->close();
        jsonResponse(['success' => true, 'id' => $pid_new, 'reference_number' => $ref, 'total' => $total]);
    } catch (Exception $e) {
        $db->rollback();
        $db->close();
        jsonResponse(['error' => $e->getMessage()], 422);
    }
}

/* ── PUT — update status ─────────────────────────────────── */
if ($method === 'PUT') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);

    $new_status = sanitize($d['status'] ?? 'received');

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT status FROM purchases WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new Exception('Not found');

        $stmt = $db->prepare("UPDATE purchases SET status=? WHERE id=?");
        $stmt->bind_param('si', $new_status, $id);
        $stmt->execute();
        $stmt->close();

        if ($new_status === 'received' && $row['status'] !== 'received') {
            $stmt = $db->prepare("SELECT product_id, quantity FROM purchase_items WHERE purchase_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $pitems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($pitems as $pi) {
                $stmt = $db->prepare("UPDATE products SET stock_quantity=stock_quantity+? WHERE id=?");
                $stmt->bind_param('ii', $pi['quantity'], $pi['product_id']);
                $stmt->execute();
                $stmt->close();

                $rtype = 'purchase';
                $stmt = $db->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference_id,reference_type) VALUES (?,'purchase',?,?,?)");
                $stmt->bind_param('iiis', $pi['product_id'], $pi['quantity'], $id, $rtype);
                $stmt->execute();
                $stmt->close();
            }
        }

        $db->commit();
        $db->close();
        jsonResponse(['success' => true]);
    } catch (Exception $e) {
        $db->rollback();
        $db->close();
        jsonResponse(['error' => $e->getMessage()], 422);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
