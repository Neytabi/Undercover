<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    die("Room ID manquant.");
}

// On récupère les joueurs éliminés depuis la session
if (isset($_SESSION['vote_results']['eliminated'])) {
    $eliminated = $_SESSION['vote_results']['eliminated'];
    if (!empty($eliminated)) {
        $placeholders = implode(',', array_fill(0, count($eliminated), '?'));
        $stmt = $pdo->prepare("UPDATE players SET is_alive = 0 WHERE id IN ($placeholders)");
        $stmt->execute($eliminated);
    }
}

// Vérifie si l’undercover est encore en vie
$stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ? AND is_undercover = 1 AND is_alive = 1");
$stmt->execute([$room_id]);
$undercoverAlive = $stmt->fetchColumn() > 0;

// Compte les joueurs vivants
$stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$aliveCount = $stmt->fetchColumn();

if (!$undercoverAlive) {
    $stmt = $pdo->prepare("UPDATE rooms SET state = 'finished', result = 'civilians_win' WHERE id = ?");
    $stmt->execute([$room_id]);
    header("Location: /final_result.php?room_id=$room_id");
    exit;
} elseif ($aliveCount <= 2) {
    $stmt = $pdo->prepare("UPDATE rooms SET state = 'finished', result = 'undercover_wins' WHERE id = ?");
    $stmt->execute([$room_id]);
    header("Location: /final_result.php?room_id=$room_id");
    exit;
}

// Sinon, nouveau tour
$pdo->prepare("DELETE FROM hints WHERE room_id = ?")->execute([$room_id]);
$pdo->prepare("DELETE FROM votes WHERE room_id = ?")->execute([$room_id]);

$stmt = $pdo->prepare("SELECT id FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$newPlayerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

shuffle($newPlayerIds);
$players_order = json_encode($newPlayerIds);

$stmt = $pdo->prepare("UPDATE rooms SET players_order = ?, current_turn = 0, state = 'in_progress' WHERE id = ?");
$stmt->execute([$players_order, $room_id]);

header("Location: /game.php?room_id=$room_id");
exit;
