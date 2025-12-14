<?php
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    echo json_encode(['error' => 'room_id requis']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$totalAlive = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE room_id = ?");
$stmt->execute([$room_id]);
$totalVotes = $stmt->fetchColumn();

echo json_encode([
    'totalVotes' => (int)$totalVotes,
    'totalAlive' => (int)$totalAlive
]);
