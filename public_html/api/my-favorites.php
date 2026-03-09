<?php
// api/my-favorites.php
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

// Ensure tables exist
$pdo->exec("
  CREATE TABLE IF NOT EXISTS favorites (
    user_id INTEGER NOT NULL,
    product_id TEXT NOT NULL,
    created_at TEXT NOT NULL,
    PRIMARY KEY(user_id, product_id)
  )
");

$stmt = $pdo->prepare("
  SELECT p.id,p.name,p.category,p.origin,p.unit,p.image,p.price_cents
  FROM favorites f
  JOIN products p ON p.id = f.product_id
  WHERE f.user_id = :uid
  ORDER BY datetime(f.created_at) DESC
");
$stmt->execute([':uid'=>$user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = array_map(function($r){
  return [
    'id' => $r['id'],
    'name' => $r['name'],
    'category' => $r['category'],
    'origin' => $r['origin'],
    'unit' => $r['unit'],
    'image' => $r['image'],
    'price' => isset($r['price_cents']) ? ($r['price_cents'] / 100.0) : 0.0,
  ];
}, $rows);

json_out(['ok'=>true,'favorites'=>$products]);

