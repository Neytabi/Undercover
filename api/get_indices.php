<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['player_id'])) {
    exit;
}

$stmt = $pdo->prepare("SELECT room_id FROM players WHERE id = ?");
$stmt->execute([$_SESSION['player_id']]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT name, hint FROM players WHERE room_id = ?");
$stmt->execute([$player['room_id']]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($players);
