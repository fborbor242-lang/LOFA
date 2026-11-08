<?php
require_once __DIR__ . '/../config.php';

$db = getDB();

$stmt = $db->prepare(
    "SELECT id, first_name, last_name, phone, credit_limit
     FROM customers
     WHERE status = 'active'
     ORDER BY first_name, last_name ASC"
);
$stmt->execute();
$result = $stmt->get_result();
$customers = [];

while ($row = $result->fetch_assoc()) {
    $customers[] = [
        'id'           => (int)$row['id'],
        'name'         => $row['first_name'] . ' ' . $row['last_name'],
        'phone'        => $row['phone'],
        'credit_limit' => (float)$row['credit_limit'],
    ];
}

$stmt->close();
$db->close();

jsonResponse($customers);
