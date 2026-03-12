<?php
// api/admin-products.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = db();
// Ensure optional columns exist (idempotent)
foreach (['available INTEGER NOT NULL DEFAULT 1', 'ripeness_enabled INTEGER NOT NULL DEFAULT 0', 'ripeness TEXT', 'categories TEXT', 'variants TEXT'] as $colDef) {
  $col = explode(' ', $colDef)[0];
  try {
    $pdo->exec("ALTER TABLE products ADD COLUMN {$col} " . preg_replace('/^' . preg_quote($col, '/') . '\s+/', '', $colDef));
  } catch (Throwable $e) {
    // ignore if column already exists
  }
}

if ($method === 'POST') {
  require_admin();
  $in = json_in();

  $id = trim($in['id'] ?? '');
  $name = trim($in['name'] ?? '');
  if ($id === '' || $name === '') {
    json_out(['ok'=>false,'error'=>'missing_id_or_name'], 400);
  }

  $price = isset($in['price']) ? (float)$in['price'] : 0.0;
  $price_cents = money_cents($price);

  $categoriesArr = $in['categories'] ?? null;
  if (is_array($categoriesArr) && count($categoriesArr) > 0) {
    $categoriesArr = array_values(array_unique(array_map('trim', $categoriesArr)));
    $categoriesJson = json_encode($categoriesArr, JSON_UNESCAPED_UNICODE);
    $category = $categoriesArr[0];
  } else {
    $categoriesJson = null;
    $category = $in['category'] ?? null;
  }
  $origin   = $in['origin'] ?? null;
  $unit     = $in['unit'] ?? null;
  $image    = $in['image'] ?? null;
  $tagsArr  = $in['tags'] ?? null;
  $tagsJson = is_array($tagsArr) ? json_encode($tagsArr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
  $featured = !empty($in['featured']) ? 1 : 0;
  $available = array_key_exists('available', $in) ? (!empty($in['available']) ? 1 : 0) : 1;
  $ripeness_enabled = !empty($in['ripeness_enabled']) ? 1 : 0;
  $ripeness = $ripeness_enabled && isset($in['ripeness']) && in_array($in['ripeness'], ['Mûre', 'Presque mûre', 'Pas Mûre'], true)
    ? $in['ripeness'] : null;

  $variantsJson = null;
  $variantsArr = $in['variants'] ?? null;
  if (is_array($variantsArr) && $variantsArr !== []) {
    $out = [];
    foreach ($variantsArr as $v) {
      $label = trim($v['label'] ?? '');
      if ($label === '') continue;
      $out[] = ['label' => $label, 'price_cents' => money_cents($v['price'] ?? $v['price_cents'] ?? 0)];
    }
    if ($out !== []) {
      $variantsJson = json_encode($out, JSON_UNESCAPED_UNICODE);
      if ($price_cents === 0) {
        $price_cents = $out[0]['price_cents'];
      }
    }
  }

  $now = date(DATE_ATOM);

  // Check if product exists
  $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = :id");
  $existsStmt->execute([':id'=>$id]);
  $exists = (int)$existsStmt->fetchColumn() > 0;

  if ($exists) {
    $sql = "
      UPDATE products SET
        name = :name,
        category = :category,
        categories = :categories,
        origin = :origin,
        unit = :unit,
        image = :image,
        price_cents = :price_cents,
        tags = :tags,
        featured = :featured,
        available = :available,
        ripeness_enabled = :ripeness_enabled,
        ripeness = :ripeness,
        variants = :variants,
        updated_at = :updated_at
      WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':id'=>$id,
      ':name'=>$name,
      ':category'=>$category,
      ':categories'=>$categoriesJson,
      ':origin'=>$origin,
      ':unit'=>$unit,
      ':image'=>$image,
      ':price_cents'=>$price_cents,
      ':tags'=>$tagsJson,
      ':featured'=>$featured,
      ':available'=>$available,
      ':ripeness_enabled'=>$ripeness_enabled,
      ':ripeness'=>$ripeness,
      ':variants'=>$variantsJson,
      ':updated_at'=>$now,
    ]);
  } else {
    $sql = "
      INSERT INTO products (id,name,category,categories,origin,unit,image,price_cents,tags,featured,available,ripeness_enabled,ripeness,variants,created_at,updated_at)
      VALUES (:id,:name,:category,:categories,:origin,:unit,:image,:price_cents,:tags,:featured,:available,:ripeness_enabled,:ripeness,:variants,:created_at,:updated_at)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':id'=>$id,
      ':name'=>$name,
      ':category'=>$category,
      ':categories'=>$categoriesJson,
      ':origin'=>$origin,
      ':unit'=>$unit,
      ':image'=>$image,
      ':price_cents'=>$price_cents,
      ':tags'=>$tagsJson,
      ':featured'=>$featured,
      ':available'=>$available,
      ':ripeness_enabled'=>$ripeness_enabled,
      ':ripeness'=>$ripeness,
      ':variants'=>$variantsJson,
      ':created_at'=>$now,
      ':updated_at'=>$now,
    ]);
  }

  json_out(['ok'=>true]);
}

if ($method === 'DELETE') {
  require_admin();
  $in = json_in();
  $id = trim($in['id'] ?? '');
  if ($id === '') {
    json_out(['ok'=>false,'error'=>'missing_id'], 400);
  }
  $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
  $stmt->execute([':id'=>$id]);
  json_out(['ok'=>true]);
}

json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);

