<?php
// api/checkout.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use Stripe\StripeClient;

ensure_tables();

$body = json_in();
$customer = $body['customer'] ?? [];
$mode = $body['mode'] ?? 'cc';
$slot = $body['slot'] ?? null;
$address = $body['address'] ?? null;
$cart = $body['cart'] ?? [];

if (!isset($customer['name'], $customer['email'])) {
  json_out(['ok'=>false,'error'=>'name_email_required'], 400);
}

$catalog = products_index();
[$items, $total] = recalc_amount($cart, $catalog);
if (!$items) json_out(['ok'=>false,'error'=>'cart_empty'], 400);

$id = 'ord_' . bin2hex(random_bytes(3));
$now = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);

$pdo = db();
$stmt = $pdo->prepare("INSERT INTO orders (id,status,mode,slot,customer_name,customer_email,customer_phone,address,cart,amount_cents,created_at) VALUES (:id,'pending',:mode,:slot,:cn,:ce,:cp,:addr,:cart,:amt,:created)");
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
  ':created'=>$now
]);

// Stripe session
$stripeKey = env('STRIPE_SECRET');
if (!$stripeKey) {
  // Return a fake URL placeholder to allow front testing when key not set
  json_out(['ok'=>true,'url'=>'/merci.html?id='.$id,'order_id'=>$id,'warn'=>'stripe_key_missing'], 200);
}
require_once __DIR__ . '/../vendor/autoload.php';
$stripe = new StripeClient($stripeKey);

$line_items = [];
foreach ($items as $it) {
  $p = $catalog[$it['id']];
  $line_items[] = [
    'price_data' => [
      'currency' => 'eur',
      'product_data' => [ 'name' => $p['name'] . ' (' . ($p['unit'] ?? '1') . ')' ],
      'unit_amount' => (int)$p['price_cents']
    ],
    'quantity' => (int)$it['qty']
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