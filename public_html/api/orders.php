<?php
// api/orders.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  require_admin();
  $status = $_GET['status'] ?? null;
  $pdo = db();
  if ($status) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE status = :s ORDER BY datetime(created_at) DESC");
    $stmt->execute([':s'=>$status]);
  } else {
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY datetime(created_at) DESC");
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok'=>true, 'orders'=>$rows]);
}

if ($method === 'POST') {
  require_admin();
  $in = json_in();
  $id = $in['id'] ?? null;
  $status = $in['status'] ?? null;
  if (!$id || !$status) json_out(['ok'=>false,'error'=>'missing_params'], 400);
  $allowed = ['pending','accepted','declined','paid','cancelled'];
  if (!in_array($status, $allowed, true)) json_out(['ok'=>false,'error'=>'invalid_status'], 400);
  $pdo = db();
  if ($status === 'accepted') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order && !empty($order['customer_email'])) {
      $clientBody = '<p>Bonjour ' . htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') . ',</p>';
      $clientBody .= '<p>Votre commande <strong>#' . htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8') . '</strong> a été <strong>acceptée</strong>.</p>';
      $clientBody .= '<p>Vous trouverez ci-dessous votre ticket de caisse / facture pour justificatif.</p>';
      $clientBody .= '<hr style="margin:20px 0;">';
      $clientBody .= order_receipt_html($order);
      @send_mail(
        $order['customer_email'],
        'Chun Tian – Votre commande #' . $order['id'] . ' a été acceptée',
        $clientBody
      );
    }
  }
  $stmt = $pdo->prepare("UPDATE orders SET status=:st, updated_at=:u WHERE id=:id");
  $stmt->execute([':st'=>$status, ':u'=>date(DATE_ATOM), ':id'=>$id]);
  json_out(['ok'=>true]);
}

json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);