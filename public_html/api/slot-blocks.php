<?php
// api/slot-blocks.php – Gestion des créneaux bloqués (admin)
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

ensure_slot_blocks_table(db());

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  require_admin();
  $pdo = db();
  $rows = $pdo->query("SELECT id, date_from, date_to, time_from, time_to, label, created_at FROM slot_blocks ORDER BY date_from, time_from")->fetchAll(PDO::FETCH_ASSOC);
  json_out(['ok' => true, 'blocks' => $rows]);
  return;
}

if ($method === 'POST') {
  require_admin();
  $in = json_in();
  $date_from = trim($in['date_from'] ?? '');
  $date_to = trim($in['date_to'] ?? '');
  $time_from = trim($in['time_from'] ?? '09:00');
  $time_to = trim($in['time_to'] ?? '12:00');
  $label = trim($in['label'] ?? '');

  if ($date_from === '' || $date_to === '') {
    json_out(['ok' => false, 'error' => 'date_range_required', 'message' => 'Date de début et date de fin requises.'], 400);
  }

  // Validation format date (Y-m-d) et heure (H:i)
  $d = DateTimeImmutable::createFromFormat('Y-m-d', $date_from);
  if (!$d || $d->format('Y-m-d') !== $date_from) {
    json_out(['ok' => false, 'error' => 'invalid_date_from'], 400);
  }
  $d = DateTimeImmutable::createFromFormat('Y-m-d', $date_to);
  if (!$d || $d->format('Y-m-d') !== $date_to) {
    json_out(['ok' => false, 'error' => 'invalid_date_to'], 400);
  }
  if ($date_from > $date_to) {
    json_out(['ok' => false, 'error' => 'date_from_after_date_to'], 400);
  }

  foreach (['time_from' => $time_from, 'time_to' => $time_to] as $k => $t) {
    $parsed = DateTimeImmutable::createFromFormat('H:i', $t);
    if (!$parsed || $parsed->format('H:i') !== $t) {
      json_out(['ok' => false, 'error' => 'invalid_time', 'field' => $k], 400);
    }
  }

  $pdo = db();
  $created_at = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
  $stmt = $pdo->prepare("INSERT INTO slot_blocks (date_from, date_to, time_from, time_to, label, created_at) VALUES (:date_from, :date_to, :time_from, :time_to, :label, :created_at)");
  $stmt->execute([
    ':date_from' => $date_from,
    ':date_to' => $date_to,
    ':time_from' => $time_from,
    ':time_to' => $time_to,
    ':label' => $label,
    ':created_at' => $created_at,
  ]);
  $id = (int) $pdo->lastInsertId();
  json_out(['ok' => true, 'id' => $id, 'message' => 'Blocage ajouté.']);
  return;
}

if ($method === 'DELETE') {
  require_admin();
  $in = json_in();
  $id = isset($in['id']) ? (int) $in['id'] : 0;
  if ($id <= 0) {
    json_out(['ok' => false, 'error' => 'id_required'], 400);
  }
  $pdo = db();
  $stmt = $pdo->prepare("DELETE FROM slot_blocks WHERE id = :id");
  $stmt->execute([':id' => $id]);
  if ($stmt->rowCount() === 0) {
    json_out(['ok' => false, 'error' => 'not_found'], 404);
  }
  json_out(['ok' => true, 'message' => 'Blocage supprimé.']);
  return;
}

json_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
