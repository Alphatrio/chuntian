<?php
// api/my-orders.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);
}

$user = current_user();
if (!$user) {
  json_out(['ok'=>false,'error'=>'unauthorized'], 401);
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id,status,mode,slot,amount_cents,created_at FROM orders WHERE customer_email = :email ORDER BY datetime(created_at) DESC LIMIT 50");
$stmt->execute([':email'=>$user['email']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

json_out(['ok'=>true,'orders'=>$rows]);

