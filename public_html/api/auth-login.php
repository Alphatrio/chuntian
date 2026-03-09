<?php
// api/auth-login.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);
}

$in = json_in();
$email = strtolower(trim($in['email'] ?? ''));
$password = (string)($in['password'] ?? '');

if ($email === '' || $password === '') {
  json_out(['ok'=>false,'error'=>'missing_fields'], 422);
}

$pdo = db();

// Ensure users table exists (for safety)
$pdo->exec("
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    is_admin INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
  )
");

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
$stmt->execute([':email'=>$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
  json_out(['ok'=>false,'error'=>'invalid_credentials'], 401);
}

$_SESSION['user_id'] = (int)$user['id'];

json_out([
  'ok'=>true,
  'user'=>[
    'id'=>(int)$user['id'],
    'name'=>$user['name'],
    'email'=>$user['email'],
    'is_admin'=>(bool)($user['is_admin'] ?? 0),
  ]
]);

