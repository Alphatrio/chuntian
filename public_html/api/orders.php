<?php
// api/orders.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  require_admin();
  $status = $_GET['status'] ?? null;
  $pdo = db();
  if ($status) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE status = :s ORDER BY datetime(created_at) DESC");
    $stmt->execute([':s'=>$status]);
  } else {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY datetime(created_at) DESC");
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok'=>true, 'orders'=>$rows]);
}

if ($method === 'POST') {
  require_admin();
  $in = json_in();
  $id = $in['id'] ?? null;
  $status = $in['status'] ?? null;
  if (!$id || !$status) json_out(['ok'=>false,'error'=>'missing_params'], 400);
  $allowed = ['pending','accepted','declined','paid','cancelled'];
  if (!in_array($status, $allowed, true)) json_out(['ok'=>false,'error'=>'invalid_status'], 400);
  $pdo = db();
  $stmt = $pdo->prepare("UPDATE orders SET status=:st, updated_at=:u WHERE id=:id");
  $stmt->execute([':st'=>$status, ':u'=>date(DATE_ATOM), ':id'=>$id]);
  json_out(['ok'=>true]);
}

json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);