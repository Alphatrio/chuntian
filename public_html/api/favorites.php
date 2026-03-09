<?php
// api/favorites.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

// Ensure table exists
$pdo->exec("
  CREATE TABLE IF NOT EXISTS favorites (
    user_id INTEGER NOT NULL,
    product_id TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY(user_id, product_id)
  )
");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $user = current_user();
  if (!$user) {
    json_out(['ok'=>true, 'favorites'=>[]]);
  }
  $stmt = $pdo->prepare("SELECT product_id FROM favorites WHERE user_id = :uid");
  $stmt->execute([':uid'=>$user['id']]);
  $ids = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ids[] = $row['product_id'];
  }
  json_out(['ok'=>true, 'favorites'=>$ids]);
}

if ($method === 'POST') {
  $user = current_user();
  if (!$user) {
    json_out(['ok'=>false,'error'=>'unauthorized'], 401);
  }
  $in = json_in();
  $pid = trim($in['product_id'] ?? '');
  if ($pid === '') {
    json_out(['ok'=>false,'error'=>'missing_product_id'], 400);
  }
  $uid = (int)$user['id'];

  // Toggle favorite
  $stmt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = :uid AND product_id = :pid");
  $stmt->execute([':uid'=>$uid, ':pid'=>$pid]);
  $exists = (bool)$stmt->fetchColumn();

  if ($exists) {
    $del = $pdo->prepare("DELETE FROM favorites WHERE user_id = :uid AND product_id = :pid");
    $del->execute([':uid'=>$uid, ':pid'=>$pid]);
    json_out(['ok'=>true, 'favorited'=>false]);
  } else {
    $ins = $pdo->prepare("INSERT INTO favorites (user_id,product_id,created_at) VALUES (:uid,:pid,:created)");
    $ins->execute([':uid'=>$uid, ':pid'=>$pid, ':created'=>date(DATE_ATOM)]);
    json_out(['ok'=>true, 'favorited'=>true]);
  }
}

json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);

