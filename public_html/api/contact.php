<?php
// api/contact.php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Ensure table exists (simple migration)
$pdo = db();
$pdo->exec("
  CREATE TABLE IF NOT EXISTS contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    message TEXT NOT NULL,
    created_at TEXT NOT NULL
  )
");

if ($method === 'POST') {
  $data = json_in();
  $name = trim($data['name'] ?? '');
  $email = trim($data['email'] ?? '');
  $message = trim($data['message'] ?? '');

  if ($name === '' || $email === '' || $message === '') {
    json_out(['ok' => false, 'error' => 'missing_fields'], 422);
  }

  $stmt = $pdo->prepare("
    INSERT INTO contact_messages (name, email, message, created_at)
    VALUES (:name, :email, :message, :created_at)
  ");

  $stmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':message' => $message,
    ':created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
  ]);

  json_out(['ok' => true]);
}

if ($method === 'GET') {
  require_admin();
  $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY datetime(created_at) DESC");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok' => true, 'messages' => $rows]);
}

if ($method === 'DELETE') {
  require_admin();
  $data = json_in();
  $id = (int)($data['id'] ?? 0);
  if ($id <= 0) {
    json_out(['ok' => false, 'error' => 'missing_id'], 400);
  }
  $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = :id");
  $stmt->execute([':id' => $id]);
  json_out(['ok' => true]);
}

json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);

