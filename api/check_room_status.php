<?php
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    echo json_encode(['error' => 'Missing room_id']);
    exit;
}

// Vérifie l’état de la room
$stmt = $pdo->prepare("SELECT state FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    echo json_encode(['error' => 'Room not found']);
    exit;
}

$started = ($room['state'] === 'started');

// Récupère les joueurs
$stmt = $pdo->prepare("SELECT name FROM players WHERE room_id = ?");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'started' => $started,
    'players' => $players
]);
