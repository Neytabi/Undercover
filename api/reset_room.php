<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    die("Room ID manquant.");
}

// Remet à zéro la salle
$stmt = $pdo->prepare("UPDATE rooms SET state = 'waiting', result = NULL, players_order = NULL, current_turn = 0 WHERE id = ?");
$stmt->execute([$room_id]);

// Incrémente le compteur remake
$stmt = $pdo->prepare("UPDATE rooms SET remake = remake + 1 WHERE id = ?");
$stmt->execute([$room_id]);

// Remet tous les joueurs à vivant
$stmt = $pdo->prepare("UPDATE players SET is_alive = 1 WHERE room_id = ?");
$stmt->execute([$room_id]);

// Supprime votes et indices
$stmt = $pdo->prepare("DELETE FROM votes WHERE room_id = ?");
$stmt->execute([$room_id]);

$stmt = $pdo->prepare("DELETE FROM hints WHERE room_id = ?");
$stmt->execute([$room_id]);

header("Location: join_room.php?room_id=" . urlencode($room_id));
exit;
?>
