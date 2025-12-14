<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    die("Room ID manquant.");
}

// Récupère les joueurs
$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = ?");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => name

// Récupère les votes
$stmt = $pdo->prepare("SELECT target_id, COUNT(*) as vote_count FROM votes WHERE room_id = ? GROUP BY target_id");
$stmt->execute([$room_id]);
$votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialise le tableau des votes
$voteCount = array_fill_keys(array_keys($players), 0);
foreach ($votes as $vote) {
    $voteCount[$vote['target_id']] = $vote['vote_count'];
}

// Détermine les joueurs éliminés (ceux avec le plus de votes)
$maxVotes = !empty($voteCount) ? max($voteCount) : 0;
$eliminated = ($maxVotes > 0) ? array_keys($voteCount, $maxVotes) : [];

// Stockage en session pour affichage dans results_view.php
$_SESSION['vote_results'] = [
    'players' => $players,
    'vote_count' => $voteCount,
    'eliminated' => $eliminated,
    'room_id' => $room_id
];

// Redirection vers la page d’animation
header("Location: /results_view.php?room_id=" . urlencode($room_id));
exit;
