<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$player_id = $_SESSION['player_id'] ?? null;
if (!$player_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$stmt = $pdo->prepare("SELECT room_id FROM players WHERE id = ?");
$stmt->execute([$player_id]);
$room_id = $stmt->fetchColumn();

if (!$room_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Room introuvable']);
    exit;
}

$stmt = $pdo->prepare("SELECT players_order, current_turn FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    http_response_code(400);
    echo json_encode(['error' => 'Room introuvable']);
    exit;
}

$current_player_id = (int)$room['current_turn'];

echo json_encode([
    'current_player_id' => $current_player_id,
    'current_turn' => $current_player_id // optionnel, mais clair
]);
