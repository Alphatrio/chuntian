<?php
// api/webhook.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$secret = env('STRIPE_WEBHOOK_SECRET');
if (!$secret) { http_response_code(200); echo "no secret set"; exit; }

require_once __DIR__ . '/../vendor/autoload.php';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
  $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $secret);
} catch(\UnexpectedValueException $e) {
  http_response_code(400); echo 'Invalid payload'; exit;
} catch(\Stripe\Exception\SignatureVerificationException $e) {
  http_response_code(400); echo 'Invalid signature'; exit;
}

$type = $event['type'] ?? '';
if ($type === 'checkout.session.completed') {
  /** @var \Stripe\Checkout\Session $session */
  $session = $event['data']['object'];
  $orderId = $session['metadata']['order_id'] ?? null;
  if ($orderId) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE orders SET status='paid', stripe_payment_intent=:pi, updated_at=:u WHERE id=:id");
    $stmt->execute([':pi'=>$session['payment_intent'] ?? null, ':u'=>date(DATE_ATOM), ':id'=>$orderId]);
  }
}

http_response_code(200);
echo 'ok';