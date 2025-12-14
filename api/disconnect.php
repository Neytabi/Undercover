<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$player_id = $_POST['player_id'] ?? null;
$room_id = $_POST['room_id'] ?? null;

if (!$player_id || !$room_id) {
    http_response_code(400);
    exit;
}

// Marque le joueur comme déconnecté
$stmt = $pdo->prepare("UPDATE players SET is_connected = 0 WHERE id = :id AND room_id = :room_id");
$stmt->execute([
    'id' => $player_id,
    'room_id' => $room_id
]);

echo json_encode(['success' => true]);
