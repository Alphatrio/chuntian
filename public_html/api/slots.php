<?php
// api/slots.php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$date = $_GET['date'] ?? (new DateTimeImmutable())->format('Y-m-d');
$all = make_slots_for_date($date);

// capacity view (optional)
$pdo = db();
$capMap = [];
try {
  $rows = $pdo->query("SELECT slot_iso, capacity, used FROM slots")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) $capMap[$r['slot_iso']] = $r;
} catch (Throwable $e) { /* table may not exist yet */ }

$out = [];
foreach ($all as $iso) {
  $cap = $capMap[$iso] ?? ['capacity'=>5,'used'=>0];
  $available = ($cap['used'] ?? 0) < ($cap['capacity'] ?? 5);
  $out[] = ['iso'=>$iso, 'available'=>$available];
}

json_out(['ok'=>true, 'slots'=>$out]);