<?php
// api/bootstrap.php
declare(strict_types=1);

// Sessions for authentication
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header('Content-Type: application/json; charset=utf-8');

// CORS (adjust if needed)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  header('Access-Control-Allow-Origin: *');
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type, Authorization');
  http_response_code(200); exit;
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

function env(string $key, $default=null) {
  static $loaded = false;
  static $map = [];
  if (!$loaded) {
    $loaded = true;
    $path = __DIR__ . '/../.env';
    if (is_file($path)) {
      $raw = file_get_contents($path);
      $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
      $lines = preg_split('/\r?\n/', $raw, -1, PREG_SPLIT_NO_EMPTY);
      foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $map[trim($k)] = trim($v);
      }
    }
  }
  return $map[$key] ?? $default;
}

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $dbPath = env('DB_PATH', __DIR__ . '/../storage/db.sqlite');
  $needInit = !file_exists($dbPath);
  $pdo = new PDO('sqlite:' . $dbPath);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  if ($needInit) {
    $sql = file_get_contents(__DIR__ . '/../init.sql');
    $pdo->exec($sql);
  }
  return $pdo;
}

function json_in(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function json_out($data, int $code=200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function bearer(): ?string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return $m[1];
  return null;
}

function current_user(): ?array {
  static $cached = null;
  static $loaded = false;
  if ($loaded) return $cached;
  $loaded = true;
  $id = $_SESSION['user_id'] ?? null;
  if (!$id) return null;
  $pdo = db();
  $stmt = $pdo->prepare("SELECT id,name,email,is_admin,created_at FROM users WHERE id = :id");
  $stmt->execute([':id'=>$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) return null;
  // normalise is_admin to bool
  $row['is_admin'] = (bool)($row['is_admin'] ?? 0);
  $cached = $row;
  return $cached;
}

function require_admin(): void {
  $u = current_user();
  if ($u && !empty($u['is_admin'])) {
    return;
  }
  json_out(['ok'=>false,'error'=>'unauthorized'], 401);
}

function money_cents($n): int {
  return (int) round(floatval($n) * 100);
}

function products_index(): array {
  // For now, read products from DB if exists; if empty, return empty array.
  $pdo = db();
  $rows = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
  $out = [];
  foreach ($rows as $r) {
    $out[$r['id']] = $r;
  }
  return $out;
}

function recalc_amount(array $cart, array $catalog): array {
  $items = [];
  $total = 0;
  foreach ($cart as $line) {
    $id = $line['id'] ?? null;
    $qty = (int) ($line['qty'] ?? 0);
    if (!$id || $qty <= 0) continue;
    if (!isset($catalog[$id])) continue;
    $price_cents = (int)$catalog[$id]['price_cents'];
    $subtotal = $price_cents * $qty;
    $items[] = [
      'id'=>$id,
      'qty'=>$qty,
      'price_cents'=>$price_cents,
      'ripeness'=>$line['ripeness'] ?? null,
    ];
    $total += $subtotal;
  }
  return [$items, $total];
}

function store_opening(): array {
  $default = '{"Mon":["09:00","19:00"],"Tue":["09:00","19:00"],"Wed":["09:00","19:00"],"Thu":["09:00","19:00"],"Fri":["09:00","19:00"],"Sat":["09:00","18:00"],"Sun":null}';
  $json = trim((string) env('STORE_OPENING_JSON', $default));
  if ($json !== '' && $json[0] !== '{') {
    $json = $default;
  }
  $arr = json_decode($json, true);
  return is_array($arr) ? $arr : json_decode($default, true) ?? [];
}

function make_slots_for_date(string $ymd): array {
  $date = new DateTimeImmutable($ymd);
  $dow = $date->format('D'); // Mon, Tue...
  $map = store_opening();
  $range = $map[$dow] ?? null;
  if (!$range) return [];
  [$start, $end] = $range;
  [$sh,$sm] = array_map('intval', explode(':', $start));
  [$eh,$em] = array_map('intval', explode(':', $end));
  $step = (int) env('STORE_SLOT_EVERY_MIN', 30);
  $slots = [];
  $t = $date->setTime($sh,$sm);
  $endT = $date->setTime($eh,$em);
  while ($t < $endT) {
    $slots[] = $t->format(DateTimeInterface::ATOM); // ISO8601
    $t = $t->modify("+{$step} minutes");
  }
  return $slots;
}

function ensure_tables(): void {
  db(); // triggers init if needed
}