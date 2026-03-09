<?php
// api/auth-register.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(['ok'=>false,'error'=>'method_not_allowed'], 405);
}

$in = json_in();
$name = trim($in['name'] ?? '');
$email = strtolower(trim($in['email'] ?? ''));
$password = (string)($in['password'] ?? '');

if ($name === '' || $email === '' || $password === '') {
  json_out(['ok'=>false,'error'=>'missing_fields'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_out(['ok'=>false,'error'=>'invalid_email'], 422);
}
if (strlen($password) < 6) {
  json_out(['ok'=>false,'error'=>'password_too_short'], 422);
}

$pdo = db();

// Ensure users table exists
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

// Check duplicate
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute([':email'=>$email]);
if ($stmt->fetchColumn()) {
  json_out(['ok'=>false,'error'=>'email_exists'], 409);
}

// First user becomes admin
$hasAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn() > 0;
$isAdmin = $hasAdmin ? 0 : 1;

$hash = password_hash($password, PASSWORD_DEFAULT);
$now = date(DATE_ATOM);

$stmt = $pdo->prepare("
  INSERT INTO users (name,email,password_hash,is_admin,created_at)
  VALUES (:name,:email,:hash,:is_admin,:created_at)
");
$stmt->execute([
  ':name'=>$name,
  ':email'=>$email,
  ':hash'=>$hash,
  ':is_admin'=>$isAdmin,
  ':created_at'=>$now,
]);

$userId = (int)$pdo->lastInsertId();
$_SESSION['user_id'] = $userId;

json_out([
  'ok'=>true,
  'user'=>[
    'id'=>$userId,
    'name'=>$name,
    'email'=>$email,
    'is_admin'=>(bool)$isAdmin,
  ]
]);

