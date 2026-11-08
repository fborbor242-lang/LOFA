<?php
require_once __DIR__ . '/../config.php';

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

/* ── GET ─────────────────────────────────────────────────── */
if ($method === 'GET') {
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $q      = isset($_GET['q'])      ? trim($_GET['q'])      : '';
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($status !== '') { $where[] = 'cr.status = ?'; $params[] = $status; $types .= 's'; }
    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "CONCAT(c.first_name,' ',c.last_name) LIKE ?";
        $params[] = $like; $types .= 's';
    }

    $whereSQL = implode(' AND ', $where);
    $sql = "SELECT cr.id, cr.sale_id, cr.original_amount, cr.amount_paid, cr.balance,
                   cr.due_date, cr.status, cr.created_at,
                   s.invoice_number,
                   CONCAT(c.first_name,' ',c.last_name) AS customer_name,
                   c.id AS customer_id, c.phone
            FROM credits cr
            JOIN customers c ON c.id = cr.customer_id
            JOIN sales s ON s.id = cr.sale_id
            WHERE $whereSQL
            ORDER BY cr.created_at DESC
            LIMIT 200";

    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    foreach ($rows as &$r) {
        $r['id']              = (int)$r['id'];
        $r['original_amount'] = (float)$r['original_amount'];
        $r['amount_paid']     = (float)$r['amount_paid'];
        $r['balance']         = (float)$r['balance'];
    }
    jsonResponse($rows);
}

/* ── POST — receive payment ──────────────────────────────── */
if ($method === 'POST') {
    $d         = json_decode(file_get_contents('php://input'), true);
    $credit_id = (int)($d['credit_id'] ?? 0);
    $amount    = (float)($d['amount']  ?? 0);
    $method_pay= sanitize($d['method'] ?? 'cash');
    $notes     = sanitize($d['notes']  ?? '');

    if ($credit_id <= 0 || $amount <= 0) jsonResponse(['error' => 'Invalid data'], 400);
    if (!in_array($method_pay, ['cash','bank_transfer','mobile_money'])) {
        $method_pay = 'cash';
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT id, balance, amount_paid, original_amount FROM credits WHERE id=? FOR UPDATE");
        $stmt->bind_param('i', $credit_id);
        $stmt->execute();
        $credit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$credit) throw new Exception('Credit record not found');

        $balance = (float)$credit['balance'];
        if ($amount > $balance) $amount = $balance;

        $new_paid    = round((float)$credit['amount_paid'] + $amount, 2);
        $new_balance = round($balance - $amount, 2);
        $new_status  = $new_balance <= 0 ? 'paid' : 'partial';

        $stmt = $db->prepare("UPDATE credits SET amount_paid=?, balance=?, status=? WHERE id=?");
        $stmt->bind_param('ddsi', $new_paid, $new_balance, $new_status, $credit_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("INSERT INTO credit_payments (credit_id, amount, method, notes) VALUES (?,?,?,?)");
        $stmt->bind_param('idss', $credit_id, $amount, $method_pay, $notes);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        $db->close();
        jsonResponse(['success' => true, 'new_balance' => $new_balance, 'status' => $new_status]);
    } catch (Exception $e) {
        $db->rollback();
        $db->close();
        jsonResponse(['error' => $e->getMessage()], 422);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
