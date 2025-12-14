<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['error' => 'room_id manquant']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = ? AND is_connected = 1");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['players' => $players]);
