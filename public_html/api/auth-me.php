<?php
// api/auth-me.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);
}

$u = current_user();
if (!$u) {
  json_out(['ok'=>true,'user'=>null]);
}

// s'assurer que les colonnes optionnelles existent (idempotent)
try {
  $pdo = db();
  $pdo->exec("ALTER TABLE users ADD COLUMN phone_last TEXT");
  $pdo->exec("ALTER TABLE users ADD COLUMN remember_contact_default INTEGER NOT NULL DEFAULT 0");
} catch (Throwable $e) {
  // ignore si déjà là
}

// Fusionner les infos DB + session pour le téléphone / préférence contact
$phone = $u['phone_last'] ?? null;
if(!$phone && !empty($_SESSION['saved_phone'] ?? '')){
  $phone = (string)$_SESSION['saved_phone'];
}
$rememberDefault = !empty($u['remember_contact_default'] ?? 0) || !empty($_SESSION['remember_contact_default'] ?? 0);

json_out([
  'ok'=>true,
  'user'=>[
    'id'=>(int)$u['id'],
    'name'=>$u['name'],
    'email'=>$u['email'],
    'phone'=>$phone,
    'remember_contact'=>$rememberDefault,
    'is_admin'=>(bool)$u['is_admin'],
  ]
]);

