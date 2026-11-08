<?php
require_once __DIR__ . '/../config.php';

$db = getDB();

$today = date('Y-m-d');

// Today's sales
$r = $db->query("SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS count FROM sales WHERE DATE(created_at)='$today' AND status='completed'");
$today_sales = $r->fetch_assoc();

// Today's cash received
$r = $db->query("SELECT COALESCE(SUM(amount_paid),0) AS cash FROM sales WHERE DATE(created_at)='$today' AND payment_method='cash' AND status='completed'");
$cash = (float)$r->fetch_assoc()['cash'];

// Today's credit sales
$r = $db->query("SELECT COALESCE(SUM(credit_amount),0) AS credit FROM sales WHERE DATE(created_at)='$today' AND status='completed'");
$credit_today = (float)$r->fetch_assoc()['credit'];

// Low stock
$r = $db->query("SELECT COUNT(*) AS c FROM products WHERE stock_quantity <= low_stock_alert AND stock_quantity > 0 AND status='active'");
$low_stock = (int)$r->fetch_assoc()['c'];

// Out of stock
$r = $db->query("SELECT COUNT(*) AS c FROM products WHERE stock_quantity = 0 AND status='active'");
$out_stock = (int)$r->fetch_assoc()['c'];

// Total customers
$r = $db->query("SELECT COUNT(*) AS c FROM customers WHERE status='active'");
$total_customers = (int)$r->fetch_assoc()['c'];

// Total products
$r = $db->query("SELECT COUNT(*) AS c FROM products WHERE status='active'");
$total_products = (int)$r->fetch_assoc()['c'];

// Outstanding credits
$r = $db->query("SELECT COALESCE(SUM(balance),0) AS total FROM credits WHERE status != 'paid'");
$outstanding = (float)$r->fetch_assoc()['total'];

// This month purchases
$month_start = date('Y-m-01');
$r = $db->query("SELECT COALESCE(SUM(total),0) AS total FROM purchases WHERE created_at >= '$month_start' AND status='received'");
$month_purchases = (float)$r->fetch_assoc()['total'];

// Today's gross profit
$r = $db->query(
    "SELECT COALESCE(SUM((si.unit_price - p.buy_price) * si.quantity),0) AS profit
     FROM sale_items si
     JOIN products p ON p.id = si.product_id
     JOIN sales s ON s.id = si.sale_id
     WHERE DATE(s.created_at)='$today' AND s.status='completed'"
);
$profit = (float)$r->fetch_assoc()['profit'];

// Recent transactions (last 10)
$r = $db->query(
    "SELECT s.invoice_number, s.total, s.payment_method, s.status, s.created_at,
            COALESCE(CONCAT(c.first_name,' ',c.last_name),'Walk-in') AS customer_name
     FROM sales s
     LEFT JOIN customers c ON c.id = s.customer_id
     ORDER BY s.created_at DESC LIMIT 10"
);
$recent = $r->fetch_all(MYSQLI_ASSOC);

// Low stock items
$r = $db->query(
    "SELECT p.name, p.sku, p.stock_quantity, p.low_stock_alert
     FROM products p
     WHERE p.stock_quantity <= p.low_stock_alert AND p.status='active'
     ORDER BY p.stock_quantity ASC LIMIT 5"
);
$low_stock_items = $r->fetch_all(MYSQLI_ASSOC);

// Outstanding credit list
$r = $db->query(
    "SELECT CONCAT(c.first_name,' ',c.last_name) AS customer_name,
            cr.balance, cr.due_date, cr.status
     FROM credits cr
     JOIN customers c ON c.id = cr.customer_id
     WHERE cr.status != 'paid'
     ORDER BY cr.balance DESC LIMIT 5"
);
$credit_list = $r->fetch_all(MYSQLI_ASSOC);

$db->close();

jsonResponse([
    'today_sales'      => (float)$today_sales['total'],
    'today_count'      => (int)$today_sales['count'],
    'cash_received'    => $cash,
    'credit_today'     => $credit_today,
    'low_stock'        => $low_stock,
    'out_of_stock'     => $out_stock,
    'total_customers'  => $total_customers,
    'total_products'   => $total_products,
    'outstanding'      => $outstanding,
    'month_purchases'  => $month_purchases,
    'gross_profit'     => $profit,
    'recent'           => $recent,
    'low_stock_items'  => $low_stock_items,
    'credit_list'      => $credit_list,
]);
