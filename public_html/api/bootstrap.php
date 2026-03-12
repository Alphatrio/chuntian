<?php
// api/bootstrap.php
declare(strict_types=1);

// Sessions for authentication (use a writable path so it works after clone)
if (session_status() === PHP_SESSION_NONE) {
  $sessionDir = __DIR__ . '/../storage/sessions';
  if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0755, true);
  }
  if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
  }
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
  // Essayer de récupérer les colonnes étendues si elles existent, sinon retomber sur la requête minimale
  try {
    $stmt = $pdo->prepare("SELECT id,name,email,is_admin,phone_last,remember_contact_default,created_at FROM users WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $stmt = $pdo->prepare("SELECT id,name,email,is_admin,created_at FROM users WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
  }
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
    $product = $catalog[$id];
    $variantLabel = isset($line['variant']) ? trim((string)$line['variant']) : null;
    $variantsJson = $product['variants'] ?? null;
    $variants = is_string($variantsJson) ? json_decode($variantsJson, true) : $variantsJson;
    if (is_array($variants) && $variants !== [] && $variantLabel !== null && $variantLabel !== '') {
      $price_cents = null;
      foreach ($variants as $v) {
        if (isset($v['label']) && (string)$v['label'] === $variantLabel && isset($v['price_cents'])) {
          $price_cents = (int)$v['price_cents'];
          break;
        }
      }
      if ($price_cents === null) {
        continue; // variant introuvable, on ignore la ligne
      }
    } else {
      $price_cents = (int)$product['price_cents'];
    }
    $subtotal = $price_cents * $qty;
    $item = [
      'id' => $id,
      'qty' => $qty,
      'price_cents' => $price_cents,
      'ripeness' => $line['ripeness'] ?? null,
    ];
    if ($variantLabel !== null && $variantLabel !== '') {
      $item['variant'] = $variantLabel;
    }
    $items[] = $item;
    $total += $subtotal;
  }
  return [$items, $total];
}

function store_opening(): array {
  // Plage générique d'ouverture de la boutique (10h-17h tous les jours).
  // Les créneaux clients (10‑13h, 14‑17h) sont gérés plus finement dans make_slots_for_date().
  $default = '{"Mon":["10:00","17:00"],"Tue":["10:00","17:00"],"Wed":["10:00","17:00"],"Thu":["10:00","17:00"],"Fri":["10:00","17:00"],"Sat":["10:00","17:00"],"Sun":["10:00","17:00"]}';
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

  // Créneaux clients fixes, valables tous les jours :
  // 10h-11h, 11h-12h, 12h-13h et 14h-15h, 15h-16h, 16h-17h.
  $allowedDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  if (!in_array($dow, $allowedDays, true)) {
    return [];
  }
  $hours = [10, 11, 12, 14, 15, 16];
  $slots = [];
  foreach ($hours as $h) {
    $t = $date->setTime($h, 0);
    $slots[] = $t->format(DateTimeInterface::ATOM); // ISO8601
  }
  return $slots;
}

function ensure_tables(): void {
  db(); // triggers init if needed
}

function ensure_slot_blocks_table(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS slot_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date_from TEXT NOT NULL,
    date_to TEXT NOT NULL,
    time_from TEXT NOT NULL,
    time_to TEXT NOT NULL,
    label TEXT,
    created_at TEXT
  )");
}

/** Vérifie si un créneau (ISO 8601) est dans une plage bloquée par l'admin. */
function is_slot_blocked(string $slot_iso, PDO $pdo): bool {
  try {
    $dt = new DateTimeImmutable($slot_iso);
  } catch (Throwable $e) {
    return true; // invalid slot considered blocked
  }
  $ymd = $dt->format('Y-m-d');
  $time = $dt->format('H:i');
  ensure_slot_blocks_table($pdo);
  $stmt = $pdo->prepare("SELECT 1 FROM slot_blocks WHERE :ymd BETWEEN date_from AND date_to AND :time >= time_from AND :time < time_to LIMIT 1");
  $stmt->execute([':ymd' => $ymd, ':time' => $time]);
  return $stmt->fetchColumn() !== false;
}

/** Adresse e-mail de l'admin pour les alertes (configurable via .env ADMIN_EMAIL). */
function admin_email(): string {
  return (string) env('ADMIN_EMAIL', 'chuntian94800@gmail.com');
}

/**
 * Envoi d'un e-mail (utilise mail() PHP ; en production, configurer SMTP ou utiliser un service).
 * En local, mail() ne fonctionne souvent pas : les e-mails sont aussi enregistrés dans storage/emails/
 * pour que vous puissiez les ouvrir en .html (voir le contenu).
 * Retourne true si l'envoi a été tenté sans erreur.
 */
function send_mail(string $to, string $subject, string $bodyHtml, ?string $bodyText = null): bool {
  $to = trim($to);
  if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    return false;
  }
  $subject = trim($subject);
  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: Chun Tian <noreply@chuntian.fr>',
    'X-Mailer: PHP/' . PHP_VERSION,
  ];
  $body = $bodyText !== null
    ? "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"></head><body><pre>" . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . "</pre></body></html>"
    : $bodyHtml;
  $headerStr = implode("\r\n", $headers);

  // Toujours sauvegarder une copie en local (utile quand mail() ne marche pas, ex. en dev)
  $storageDir = __DIR__ . '/../storage/emails';
  if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
  }
  if (is_dir($storageDir) && is_writable($storageDir)) {
    $slug = preg_replace('/[^a-z0-9_-]/i', '_', substr($subject, 0, 50));
    $filename = date('Y-m-d_H-i-s') . '_' . $slug . '.html';
    $path = $storageDir . '/' . $filename;
    $envelope = "<!-- To: {$to}\nSubject: {$subject}\n-->\n";
    @file_put_contents($path, $envelope . $body);
  }

  return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headerStr);
}

/**
 * Génère le HTML du ticket de caisse / facture pour une commande (tableau associatif order).
 */
function order_receipt_html(array $order): string {
  $id = htmlspecialchars($order['id'] ?? '', ENT_QUOTES, 'UTF-8');
  $created = isset($order['created_at']) ? (new DateTimeImmutable($order['created_at']))->format('d/m/Y H:i') : '';
  $mode = $order['mode'] ?? 'cc';
  $modeLabel = $mode === 'livraison' ? 'Livraison' : 'Click & Collect';
  $slot = $order['slot'] ?? '';
  $slotStr = $slot !== '' ? (new DateTimeImmutable($slot))->format('d/m/Y à H:i') : '—';
  $name = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
  $email = htmlspecialchars($order['customer_email'] ?? '', ENT_QUOTES, 'UTF-8');
  $phone = htmlspecialchars($order['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8');
  $address = htmlspecialchars($order['address'] ?? '', ENT_QUOTES, 'UTF-8');
  $amount = (int) ($order['amount_cents'] ?? 0);
  $amountStr = number_format($amount / 100, 2, ',', ' ') . ' €';
  $cart = json_decode($order['cart'] ?? '[]', true);
  if (!is_array($cart)) {
    $cart = [];
  }
  $lines = '';
  foreach ($cart as $it) {
    $qty = (int) ($it['qty'] ?? 0);
    $label = htmlspecialchars($it['id'] ?? '', ENT_QUOTES, 'UTF-8');
    if (!empty($it['variant'])) {
      $label .= ' – ' . htmlspecialchars($it['variant'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($it['ripeness'])) {
      $label .= ' (' . htmlspecialchars($it['ripeness'], ENT_QUOTES, 'UTF-8') . ')';
    }
    $lineTotal = $qty * (int) ($it['price_cents'] ?? 0);
    $lines .= "<tr><td>{$qty}</td><td>{$label}</td><td style=\"text-align:right\">" . number_format($lineTotal / 100, 2, ',', ' ') . " €</td></tr>";
  }
  $notes = trim($order['notes'] ?? '');
  $notesBlock = $notes !== '' ? '<p><strong>Notes :</strong> ' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</p>' : '';
  $addressBlock = $mode === 'livraison'
    ? '<p><strong>Adresse de livraison :</strong><br>' . ($address !== '' ? $address : 'Non renseignée') . '</p>'
    : '';

  return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="font-family:Georgia,serif;max-width:560px;margin:0 auto;padding:20px;color:#222;">
  <h1 style="font-size:1.4em;border-bottom:2px solid #2d5a27;">Chun Tian – Ticket / Facture</h1>
  <p><strong>Commande #{$id}</strong> – {$created}</p>
  <p><strong>Mode :</strong> {$modeLabel} – <strong>Créneau :</strong> {$slotStr}</p>
  <p><strong>Client :</strong> {$name}<br>
  E-mail : {$email}<br>
  Téléphone : {$phone}</p>
  {$addressBlock}
  <table style="width:100%;border-collapse:collapse;margin:16px 0;">
    <thead><tr style="border-bottom:1px solid #ccc;"><th style="text-align:left">Qté</th><th style="text-align:left">Article</th><th style="text-align:right">Total</th></tr></thead>
    <tbody>{$lines}</tbody>
  </table>
  <p style="font-size:1.2em;"><strong>Total : {$amountStr}</strong></p>
  {$notesBlock}
  <p style="margin-top:24px;font-size:0.9em;color:#666;">Merci pour votre commande. Chun Tian – 151 Rue Jean Jaurès, 94800 Villejuif.</p>
</body>
</html>
HTML;
}