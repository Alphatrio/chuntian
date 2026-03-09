<?php

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$password = $input['password'] ?? '';

$adminPassword = getenv('ADMIN_PASSWORD');
$adminToken = getenv('ADMIN_TOKEN');

if (!$adminPassword) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'ADMIN_PASSWORD not configured']);
    exit;
}

if ($password === $adminPassword) {
    echo json_encode([
        'ok' => true,
        'token' => $adminToken
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid password'
    ]);
}
