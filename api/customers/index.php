<?php
require_once __DIR__ . '/../config.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/* ── GET ─────────────────────────────────────────────────── */
if ($method === 'GET') {
    $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($status !== '') {
        $where[]  = 'c.status = ?';
        $params[] = $status;
        $types   .= 's';
    }
    if ($q !== '') {
        $like     = '%' . $q . '%';
        $where[]  = "(CONCAT(c.first_name,' ',c.last_name) LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'sss';
    }

    $whereSQL = implode(' AND ', $where);
    $sql = "SELECT c.id, c.first_name, c.last_name, c.phone, c.email, c.address,
                   c.credit_limit, c.status, c.created_at,
                   COALESCE(SUM(s.total),0) AS total_purchases,
                   COALESCE((SELECT SUM(cr.balance) FROM credits cr WHERE cr.customer_id=c.id AND cr.status!='paid'),0) AS outstanding_credit
            FROM customers c
            LEFT JOIN sales s ON s.customer_id = c.id AND s.status='completed'
            WHERE $whereSQL
            GROUP BY c.id
            ORDER BY c.first_name, c.last_name ASC
            LIMIT 200";

    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    foreach ($rows as &$r) {
        $r['id']                 = (int)$r['id'];
        $r['credit_limit']       = (float)$r['credit_limit'];
        $r['total_purchases']    = (float)$r['total_purchases'];
        $r['outstanding_credit'] = (float)$r['outstanding_credit'];
    }
    jsonResponse($rows);
}

/* ── POST — add ──────────────────────────────────────────── */
if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) jsonResponse(['error' => 'Invalid JSON'], 400);

    $first  = sanitize($d['first_name']   ?? '');
    $last   = sanitize($d['last_name']    ?? '');
    $phone  = sanitize($d['phone']        ?? '');
    $email  = sanitize($d['email']        ?? '');
    $addr   = sanitize($d['address']      ?? '');
    $limit  = (float)($d['credit_limit']  ?? 0);
    $status = in_array($d['status'] ?? '', ['active','inactive']) ? $d['status'] : 'active';

    if ($first === '' || $last === '') jsonResponse(['error' => 'First and last name required'], 400);

    $stmt = $db->prepare(
        "INSERT INTO customers (first_name,last_name,phone,email,address,credit_limit,status) VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('sssssds', $first, $last, $phone, $email, $addr, $limit, $status);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    $db->close();
    jsonResponse(['success' => true, 'id' => $id]);
}

/* ── PUT — edit ──────────────────────────────────────────── */
if ($method === 'PUT') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$d || $id <= 0) jsonResponse(['error' => 'Invalid data'], 400);

    $first  = sanitize($d['first_name']  ?? '');
    $last   = sanitize($d['last_name']   ?? '');
    $phone  = sanitize($d['phone']       ?? '');
    $email  = sanitize($d['email']       ?? '');
    $addr   = sanitize($d['address']     ?? '');
    $limit  = (float)($d['credit_limit'] ?? 0);
    $status = in_array($d['status'] ?? '', ['active','inactive']) ? $d['status'] : 'active';

    $stmt = $db->prepare(
        "UPDATE customers SET first_name=?,last_name=?,phone=?,email=?,address=?,credit_limit=?,status=? WHERE id=?"
    );
    $stmt->bind_param('sssssdsі', $first, $last, $phone, $email, $addr, $limit, $status, $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    jsonResponse(['success' => true]);
}

/* ── DELETE ──────────────────────────────────────────────── */
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error' => 'Invalid ID'], 400);
    $stmt = $db->prepare("UPDATE customers SET status='inactive' WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $db->close();
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
