<?php
// api/products.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$q = $pdo->query("SELECT id,name,category,origin,unit,image,price_cents,tags,featured,created_at,updated_at FROM products ORDER BY name COLLATE NOCASE");
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
$out = [];
foreach ($rows as $r) {
  $out[] = [
    'id' => $r['id'],
    'name' => $r['name'],
    'category' => $r['category'],
    'origin' => $r['origin'],
    'unit' => $r['unit'],
    'image' => $r['image'],
    'price' => isset($r['price_cents']) ? ($r['price_cents'] / 100.0) : 0.0,
    'tags' => $r['tags'] ? json_decode($r['tags'], true) : [],
    'featured' => (bool)$r['featured'],
    'created' => $r['created_at'],
    'updated' => $r['updated_at'],
  ];
}
json_out(['ok'=>true, 'products'=>$out]);

