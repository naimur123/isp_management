<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$q = get_param('q');
if (strlen($q) < 1) {
    json_response(['results' => []]);
}

$stmt = db()->prepare(
    "SELECT c.id, c.customer_id, c.customer_name, c.status, co.company_name, cat.category_name, z.zone_name
     FROM customers c
     JOIN companies co ON co.id = c.company_id
     JOIN categories cat ON cat.id = c.category_id
     JOIN zones z ON z.id = c.zone_id
     WHERE c.deleted_at IS NULL AND (c.customer_id LIKE ? OR c.customer_name LIKE ?)
     ORDER BY c.customer_name LIMIT 20"
);
$like = "%$q%";
$stmt->execute([$like, $like]);
$rows = $stmt->fetchAll();

json_response(['results' => $rows]);
