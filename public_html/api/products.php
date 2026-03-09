<?php
// api/products.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
// Ensure \"available\" column exists (idempotent)
try{
  $pdo->exec("ALTER TABLE products ADD COLUMN available INTEGER NOT NULL DEFAULT 1");
}catch(Throwable $e){
  // ignore if already exists
}
try {
  $pdo->exec("ALTER TABLE products ADD COLUMN ripeness_enabled INTEGER NOT NULL DEFAULT 0");
} catch (Throwable $e) { /* ignore */ }
try {
  $pdo->exec("ALTER TABLE products ADD COLUMN ripeness TEXT");
} catch (Throwable $e) { /* ignore */ }
$q = $pdo->query("SELECT id,name,category,origin,unit,image,price_cents,tags,featured,available,created_at,updated_at,ripeness_enabled,ripeness FROM products ORDER BY name COLLATE NOCASE");
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
    'available' => array_key_exists('available',$r) ? (bool)$r['available'] : true,
    'created' => $r['created_at'],
    'updated' => $r['updated_at'],
    'ripeness_enabled' => !empty($r['ripeness_enabled'] ?? 0),
    'ripeness' => $r['ripeness'] ?? null,
  ];
}
json_out(['ok'=>true, 'products'=>$out]);

