<?php
// api/checkout.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

ensure_tables();

$pdo = db();
foreach (['notes TEXT', 'contact_consent INTEGER NOT NULL DEFAULT 0'] as $colDef) {
  $col = explode(' ', $colDef)[0];
  try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN {$col} " . preg_replace('/^' . preg_quote($col, '/') . '\s+/', '', $colDef));
  } catch (Throwable $e) { /* ignore */ }
}

$body = json_in();
$customer = $body['customer'] ?? [];
$mode = $body['mode'] ?? 'cc';
$slot = $body['slot'] ?? null;
$address = $body['address'] ?? null;
$notes = trim((string)($body['notes'] ?? ''));
$contact_consent = !empty($body['contact_consent']);
$remember_contact = !empty($body['remember_contact']);
$cart = $body['cart'] ?? [];

if (!isset($customer['name'], $customer['email'])) {
  json_out(['ok'=>false,'error'=>'name_email_required'], 400);
}
if (empty($customer['phone'])) {
  json_out(['ok'=>false,'error'=>'phone_required','message'=>'Téléphone requis'], 400);
}
if (!$contact_consent) {
  json_out(['ok'=>false,'error'=>'contact_consent_required','message'=>'Vous devez accepter d\'être contacté pour valider la commande.'], 400);
}

$catalog = products_index();
[$items, $total] = recalc_amount($cart, $catalog);
if (!$items) json_out(['ok'=>false,'error'=>'cart_empty'], 400);

// Règles de montant (affichées sur la page Livraison)
// - Minimum de commande : 25 €
$minOrderCents = 2500;
if ($total < $minOrderCents) {
  json_out(['ok'=>false,'error'=>'min_order_25','message'=>'Montant minimum de commande 25 €'], 400);
}

// Frais de livraison : 3,99 € (à redéfinir si besoin). Offerte à partir de 45 €
$freeDeliveryThresholdCents = 4500;
$deliveryFeeCents = 399;
$deliveryFee = ($mode === 'livraison' && $total < $freeDeliveryThresholdCents) ? $deliveryFeeCents : 0;
$total += $deliveryFee;

// Unicité des créneaux : réserver le slot avant de créer la commande (1 commande par créneau)
$slot = $slot ? trim((string)$slot) : null;
if ($slot !== null && $slot !== '') {
  if (is_slot_blocked($slot, $pdo)) {
    json_out(['ok'=>false,'error'=>'slot_unavailable','message'=>'Ce créneau n\'est plus disponible.'], 400);
  }
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS slots (slot_iso TEXT PRIMARY KEY, capacity INTEGER NOT NULL DEFAULT 1, used INTEGER NOT NULL DEFAULT 0)");
    $upd = $pdo->prepare("UPDATE slots SET used = used + 1 WHERE slot_iso = :slot AND used < capacity");
    $upd->execute([':slot' => $slot]);
    if ($upd->rowCount() === 0) {
      $ins = $pdo->prepare("INSERT OR IGNORE INTO slots (slot_iso, capacity, used) VALUES (:slot, 1, 1)");
      $ins->execute([':slot' => $slot]);
      if ($ins->rowCount() === 0) {
        json_out(['ok'=>false,'error'=>'slot_unavailable','message'=>'Ce créneau n\'est plus disponible.'], 400);
      }
    }
  } catch (Throwable $e) {
    json_out(['ok'=>false,'error'=>'slot_error','message'=>'Erreur réservation créneau.'], 500);
  }
}

$id = 'ord_' . bin2hex(random_bytes(3));
$now = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);

$stmt = $pdo->prepare("INSERT INTO orders (id,status,mode,slot,customer_name,customer_email,customer_phone,address,cart,amount_cents,notes,contact_consent,created_at) VALUES (:id,'pending',:mode,:slot,:cn,:ce,:cp,:addr,:cart,:amt,:notes,:consent,:created)");
$stmt->execute([
  ':id'=>$id,
  ':mode'=>$mode,
  ':slot'=>$slot,
  ':cn'=>$customer['name'] ?? '',
  ':ce'=>$customer['email'] ?? '',
  ':cp'=>$customer['phone'] ?? '',
  ':addr'=>$address,
  ':cart'=>json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  ':amt'=>$total,
  ':notes'=>$notes,
  ':consent'=>$contact_consent ? 1 : 0,
  ':created'=>$now
]);

// Sauvegarde des coordonnées pour la prochaine commande (si l'utilisateur est connecté)
if ($remember_contact) {
  $u = current_user();
  if ($u && !empty($u['id'])) {
    try {
      // S'assurer que les colonnes optionnelles existent
      $pdo->exec("ALTER TABLE users ADD COLUMN phone_last TEXT");
      $pdo->exec("ALTER TABLE users ADD COLUMN remember_contact_default INTEGER NOT NULL DEFAULT 0");
    } catch (Throwable $e) {
      // ignore si la colonne existe déjà
    }
    try {
      $up = $pdo->prepare("UPDATE users SET name = :name, phone_last = :phone_last, remember_contact_default = 1 WHERE id = :id");
      $up->execute([
        ':name' => (string)($customer['name'] ?? $u['name'] ?? ''),
        ':phone_last' => (string)($customer['phone'] ?? ''),
        ':id' => (int)$u['id'],
      ]);
    } catch (Throwable $e) {
      // ne pas bloquer le checkout si la mise à jour échoue
    }
    // Sauvegarde également en session pour que ce soit disponible immédiatement
    $_SESSION['saved_phone'] = (string)($customer['phone'] ?? '');
    $_SESSION['remember_contact_default'] = 1;
  }
}

// Alerte admin : notification à chaque nouvelle commande
$orderRow = [
  'id' => $id,
  'status' => 'pending',
  'mode' => $mode,
  'slot' => $slot,
  'customer_name' => $customer['name'] ?? '',
  'customer_email' => $customer['email'] ?? '',
  'customer_phone' => $customer['phone'] ?? '',
  'address' => $address,
  'cart' => json_encode($items, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
  'amount_cents' => $total,
  'notes' => $notes,
  'created_at' => $now,
];
$adminBody = '<p>Nouvelle commande reçue.</p>' . order_receipt_html($orderRow);
@send_mail(admin_email(), 'Chun Tian – Nouvelle commande #' . $id, $adminBody);

// Facture client : envoi au client dès la création de la commande
$clientEmail = $customer['email'] ?? '';
if ($clientEmail !== '') {
  $clientInvoiceBody = '<p>Bonjour ' . htmlspecialchars($customer['name'] ?? '', ENT_QUOTES, 'UTF-8') . ',</p>';
  $clientInvoiceBody .= '<p>Votre commande a bien été enregistrée. Vous trouverez ci-dessous votre <strong>facture / ticket de caisse</strong>.</p>';
  $clientInvoiceBody .= '<hr style="margin:20px 0;">';
  $clientInvoiceBody .= order_receipt_html($orderRow);
  @send_mail($clientEmail, 'Chun Tian – Votre commande #' . $id . ' – Facture', $clientInvoiceBody);
}

$stripeKey = env('STRIPE_SECRET');
if (!$stripeKey) {
  json_out(['ok'=>true,'url'=>'/merci.html?id='.$id,'order_id'=>$id,'warn'=>'stripe_key_missing'], 200);
}

try {
  require_once __DIR__ . '/../vendor/autoload.php';
  $stripe = new \Stripe\StripeClient($stripeKey);

  $line_items = [];
  foreach ($items as $it) {
    $p = $catalog[$it['id']];
    $itemName = $p['name'];
    if (!empty($it['variant'])) {
      $itemName .= ' – ' . $it['variant'];
    }
    $itemName .= ' (' . ($p['unit'] ?? '1') . ')';
    $line_items[] = [
      'price_data' => [
        'currency' => 'eur',
        'product_data' => [ 'name' => $itemName ],
        'unit_amount' => (int)($it['price_cents'] ?? $p['price_cents'])
      ],
      'quantity' => (int)$it['qty']
    ];
  }
  if ($deliveryFee > 0) {
    $line_items[] = [
      'price_data' => [
        'currency' => 'eur',
        'product_data' => [ 'name' => 'Livraison locale' ],
        'unit_amount' => $deliveryFee
      ],
      'quantity' => 1
    ];
  }

  $domain = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'];
  $success = $domain . "/merci.html?oid={$id}&cs={CHECKOUT_SESSION_ID}";
  $cancel = $domain . "/panier.html";

  $session = $stripe->checkout->sessions->create([
    'mode' => 'payment',
    'line_items' => $line_items,
    'success_url' => $success,
    'cancel_url' => $cancel,
    'customer_email' => $customer['email'],
    'metadata' => [
      'order_id' => $id,
      'mode' => $mode,
      'slot' => (string)$slot
    ]
  ]);

  $upd = $pdo->prepare("UPDATE orders SET stripe_session_id=:sid, updated_at=:u WHERE id=:id");
  $upd->execute([':sid'=>$session->id, ':u'=>$now, ':id'=>$id]);

  json_out(['ok'=>true, 'url'=>$session->url, 'order_id'=>$id]);
} catch (Throwable $e) {
  json_out(['ok'=>false, 'error'=>'payment_error', 'message'=>$e->getMessage()], 500);
}