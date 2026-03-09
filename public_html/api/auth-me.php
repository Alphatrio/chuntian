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

json_out([
  'ok'=>true,
  'user'=>[
    'id'=>(int)$u['id'],
    'name'=>$u['name'],
    'email'=>$u['email'],
    'is_admin'=>(bool)$u['is_admin'],
  ]
]);

