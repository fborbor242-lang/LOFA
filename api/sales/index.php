<?php
require_once __DIR__ . '/../config.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $limit  = min((int)($_GET['limit']  ?? 50), 200);
    $offset = (int)($_GET['offset'] ?? 0);
    $q      = isset($_GET['q']) ? trim($_GET['q']) : '';

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($q !== '') {
        $like     = '%' . $q . '%';
        $where[]  = "(s.invoice_number LIKE ? OR CONCAT(c.first_name,' ',c.last_name) LIKE ?)";
        $params[] = $like; $params[] = $like;
        $types   .= 'ss';
    }

    $whereSQL = implode(' AND ', $where);

    $sql = "SELECT s.id, s.invoice_number, s.total, s.payment_method,
                   s.amount_paid, s.change_amount, s.credit_amount,
                   s.status, s.created_at,
                   COALESCE(CONCAT(c.first_name,' ',c.last_name),'Walk-in') AS customer_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            WHERE $whereSQL
            ORDER BY s.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // total count
    $cntSQL  = "SELECT COUNT(*) AS c FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE $whereSQL";
    $cparams = array_slice($params, 0, -2);
    $ctypes  = substr($types, 0, -2);
    $cstmt   = $db->prepare($cntSQL);
    if ($ctypes !== '') $cstmt->bind_param($ctypes, ...$cparams);
    $cstmt->execute();
    $total_count = (int)$cstmt->get_result()->fetch_assoc()['c'];
    $cstmt->close();
    $db->close();

    foreach ($rows as &$r) {
        $r['id']            = (int)$r['id'];
        $r['total']         = (float)$r['total'];
        $r['amount_paid']   = (float)$r['amount_paid'];
        $r['change_amount'] = (float)$r['change_amount'];
        $r['credit_amount'] = (float)$r['credit_amount'];
    }
    jsonResponse(['data' => $rows, 'total' => $total_count]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
