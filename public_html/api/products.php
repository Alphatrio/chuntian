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
try {
  $pdo->exec("ALTER TABLE products ADD COLUMN categories TEXT");
} catch (Throwable $e) { /* ignore */ }
try {
  $pdo->exec("ALTER TABLE products ADD COLUMN variants TEXT");
} catch (Throwable $e) { /* ignore */ }
$q = $pdo->query("SELECT id,name,category,categories,origin,unit,image,price_cents,tags,featured,available,created_at,updated_at,ripeness_enabled,ripeness,variants FROM products ORDER BY name COLLATE NOCASE");
$rows = $q->fetchAll(PDO::FETCH_ASSOC);
$out = [];
foreach ($rows as $r) {
  $categories = null;
  if (!empty($r['categories'])) {
    $decoded = json_decode($r['categories'], true);
    $categories = is_array($decoded) ? $decoded : null;
  }
  if ($categories === null && !empty($r['category'])) {
    $categories = [$r['category']];
  }
  $variantsRaw = $r['variants'] ?? null;
  $variants = null;
  if (is_string($variantsRaw) && $variantsRaw !== '') {
    $decoded = json_decode($variantsRaw, true);
    if (is_array($decoded) && $decoded !== []) {
      $variants = array_map(function ($v) {
        return [
          'label' => $v['label'] ?? '',
          'price_cents' => (int)($v['price_cents'] ?? 0),
          'price' => isset($v['price_cents']) ? ($v['price_cents'] / 100.0) : 0.0,
        ];
      }, $decoded);
    }
  }
  $out[] = [
    'id' => $r['id'],
    'name' => $r['name'],
    'category' => $r['category'],
    'categories' => $categories ?? [],
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
    'variants' => $variants,
  ];
}
json_out(['ok'=>true, 'products'=>$out]);

