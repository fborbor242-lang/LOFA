<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    jsonResponse(['error' => 'Invalid JSON input'], 400);
}

$items          = $input['items']          ?? [];
$customer_id    = (isset($input['customer_id']) && $input['customer_id']) ? (int)$input['customer_id'] : null;
$payment_method = $input['payment_method'] ?? '';
$amount_paid    = (float)($input['amount_paid'] ?? 0);
$notes          = sanitize($input['notes'] ?? '');

if (empty($items)) {
    jsonResponse(['error' => 'Cart is empty'], 400);
}
if (!in_array($payment_method, ['cash','card','mobile_money','credit'])) {
    jsonResponse(['error' => 'Invalid payment method'], 400);
}
if ($payment_method === 'credit' && !$customer_id) {
    jsonResponse(['error' => 'A registered customer is required for credit sales'], 400);
}

$db = getDB();
$db->begin_transaction();

try {
    // 1. Validate stock & calculate subtotal
    $subtotal        = 0.0;
    $validated_items = [];

    foreach ($items as $item) {
        $pid = (int)($item['id']  ?? 0);
        $qty = (int)($item['qty'] ?? 0);
        if ($pid <= 0 || $qty <= 0) throw new Exception('Invalid item data');

        $st = $db->prepare("SELECT id, name, sell_price, stock_quantity FROM products WHERE id=? AND status='active' FOR UPDATE");
        $st->bind_param('i', $pid);
        $st->execute();
        $p = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$p) throw new Exception("Product ID $pid not found or inactive");
        if ((int)$p['stock_quantity'] < $qty) {
            throw new Exception("Insufficient stock for \"{$p['name']}\". Only {$p['stock_quantity']} available.");
        }

        $isub      = round((float)$p['sell_price'] * $qty, 2);
        $subtotal += $isub;
        $validated_items[] = [
            'id'       => $pid,
            'name'     => $p['name'],
            'price'    => (float)$p['sell_price'],
            'qty'      => $qty,
            'subtotal' => $isub,
        ];
    }

    $total = round($subtotal, 2);

    // 2. Resolve payment amounts
    $credit_amount = 0.0;
    $change_amount = 0.0;

    if ($payment_method === 'credit') {
        if ($amount_paid < 0) $amount_paid = 0.0;
        $credit_amount = round($total - $amount_paid, 2);
        if ($credit_amount < 0) $credit_amount = 0.0;
    } elseif ($payment_method === 'cash') {
        if ($amount_paid < $total) throw new Exception('Amount paid is less than total');
        $change_amount = round($amount_paid - $total, 2);
    } else {
        $amount_paid   = $total;
        $change_amount = 0.0;
    }

    // 3. Generate unique invoice number
    $r   = $db->query("SELECT COUNT(*) AS c FROM sales");
    $cnt = (int)$r->fetch_assoc()['c'];
    $inv = 'INV-' . str_pad($cnt + 1, 6, '0', STR_PAD_LEFT);

    $ck = $db->prepare("SELECT id FROM sales WHERE invoice_number=?");
    $ck->bind_param('s', $inv);
    $ck->execute();
    if ($ck->get_result()->num_rows > 0) {
        $inv = 'INV-' . str_pad($cnt + 1000 + rand(1, 999), 6, '0', STR_PAD_LEFT);
    }
    $ck->close();

    // 4. Insert sale
    // bind types: s i i d d s d d d s
    $uid = 1;
    $st  = $db->prepare(
        "INSERT INTO sales (invoice_number,customer_id,user_id,subtotal,total,payment_method,amount_paid,change_amount,credit_amount,notes)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $st->bind_param('siiddsddds',
        $inv, $customer_id, $uid,
        $subtotal, $total, $payment_method,
        $amount_paid, $change_amount, $credit_amount,
        $notes
    );
    $st->execute();
    $sale_id = (int)$db->insert_id;
    $st->close();

    // 5. Sale items + stock deduction + movements
    foreach ($validated_items as $vi) {
        $st = $db->prepare(
            "INSERT INTO sale_items (sale_id,product_id,product_name,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)"
        );
        $st->bind_param('iisidd', $sale_id, $vi['id'], $vi['name'], $vi['qty'], $vi['price'], $vi['subtotal']);
        $st->execute();
        $st->close();

        $st = $db->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=?");
        $st->bind_param('ii', $vi['qty'], $vi['id']);
        $st->execute();
        $st->close();

        $neg_qty  = -$vi['qty'];
        $ref_type = 'sale';
        $st = $db->prepare(
            "INSERT INTO stock_movements (product_id,type,quantity,reference_id,reference_type,notes) VALUES (?,'sale',?,?,?,?)"
        );
        $st->bind_param('iiiss', $vi['id'], $neg_qty, $sale_id, $ref_type, $inv);
        $st->execute();
        $st->close();
    }

    // 6. Payment record
    if ($amount_paid > 0) {
        $pay_method = ($payment_method === 'credit') ? 'cash' : $payment_method;
        if (in_array($pay_method, ['cash','card','mobile_money'])) {
            $st = $db->prepare("INSERT INTO payments (sale_id,amount,method) VALUES (?,?,?)");
            $st->bind_param('ids', $sale_id, $amount_paid, $pay_method);
            $st->execute();
            $st->close();
        }
    }

    // 7. Credit record
    if ($payment_method === 'credit' && $credit_amount > 0) {
        $due_date = date('Y-m-d', strtotime('+7 days'));
        $cstatus  = ($amount_paid > 0) ? 'partial' : 'unpaid';
        $st = $db->prepare(
            "INSERT INTO credits (sale_id,customer_id,original_amount,amount_paid,balance,due_date,status) VALUES (?,?,?,?,?,?,?)"
        );
        $st->bind_param('iidddss', $sale_id, $customer_id, $total, $amount_paid, $credit_amount, $due_date, $cstatus);
        $st->execute();
        $st->close();
    }

    $db->commit();

    // 8. Customer name for receipt
    $cname = 'Walk-in Customer';
    if ($customer_id) {
        $st = $db->prepare("SELECT CONCAT(first_name,' ',last_name) AS n FROM customers WHERE id=?");
        $st->bind_param('i', $customer_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        if ($row) $cname = $row['n'];
        $st->close();
    }

    $db->close();

    jsonResponse([
        'success'        => true,
        'invoice_number' => $inv,
        'sale_id'        => $sale_id,
        'customer'       => $cname,
        'payment_method' => $payment_method,
        'subtotal'       => $subtotal,
        'total'          => $total,
        'amount_paid'    => $amount_paid,
        'change_amount'  => $change_amount,
        'credit_amount'  => $credit_amount,
        'items'          => $validated_items,
        'date'           => date('F j, Y g:i A'),
        'cashier'        => 'Alexander O.',
    ]);

} catch (Exception $e) {
    $db->rollback();
    $db->close();
    jsonResponse(['error' => $e->getMessage()], 422);
}
