<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_admin(); 

$in = json_in();
$jsonPath = $in['path'] ?? '../assets/products.json';
if (!is_file(__DIR__ . '/' . basename($jsonPath)) && !is_file($jsonPath)) {
  json_out(['ok'=>false,'error'=>'file_not_found','path'=>$jsonPath], 400);
}
$raw = @file_get_contents($jsonPath);
if ($raw === false) { json_out(['ok'=>false,'error'=>'read_failed'], 400); }
$data = json_decode($raw, true);
if (!is_array($data)) { json_out(['ok'=>false,'error'=>'invalid_json'], 400); }

$pdo = db();
$now = date(DATE_ATOM);
$ins = $pdo->prepare("
  INSERT INTO products (id,name,category,origin,unit,image,price_cents,tags,featured,created_at,updated_at)
  VALUES (:id,:name,:category,:origin,:unit,:image,:price_cents,:tags,:featured,:now,:now)
  ON CONFLICT(id) DO UPDATE SET
    name=excluded.name,
    category=excluded.category,
    origin=COALESCE(NULLIF(excluded.origin,''), products.origin),
    unit=COALESCE(NULLIF(excluded.unit,''), products.unit),
    image=excluded.image,
    tags=COALESCE(excluded.tags, products.tags),
    featured=COALESCE(excluded.featured, products.featured),
    updated_at=:now2
");
$cnt = 0;
foreach ($data as $p) {
  if (!isset($p['id'], $p['name'])) continue;
  $ins->execute([
    ':id' => $p['id'],
    ':name' => $p['name'],
    ':category' => $p['category'] ?? null,
    ':origin' => $p['origin'] ?? null,
    ':unit' => $p['unit'] ?? null,
    ':image' => $p['image'] ?? null,
    ':price_cents' => isset($p['price']) ? (int)round(floatval($p['price'])*100) : 0, // reste 0 si manquant
    ':tags' => isset($p['tags']) ? json_encode($p['tags'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
    ':featured' => !empty($p['featured']) ? 1 : 0,
    ':now' => $now,
    ':now2' => $now,
  ]);
  $cnt++;
}
json_out(['ok'=>true,'imported'=>$cnt]);
