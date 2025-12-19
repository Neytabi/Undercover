<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    die("Room ID manquant.");
}

// 1. Remet à zéro l'état de la salle
$stmt = $pdo->prepare("UPDATE rooms SET state = 'waiting', result = NULL, players_order = NULL, current_turn = 0 WHERE id = ?");
$stmt->execute([$room_id]);

// 2. Incrémente le compteur de parties
$stmt = $pdo->prepare("UPDATE rooms SET remake = remake + 1 WHERE id = ?");
$stmt->execute([$room_id]);

// 3. IMPORTANT : Ressuscite tout le monde ET remet les rôles à zéro (plus d'undercover)
$stmt = $pdo->prepare("UPDATE players SET is_alive = 1, is_undercover = 0 WHERE room_id = ?");
$stmt->execute([$room_id]);

// 4. Nettoyage des données de la partie précédente
$stmt = $pdo->prepare("DELETE FROM votes WHERE room_id = ?");
$stmt->execute([$room_id]);

$stmt = $pdo->prepare("DELETE FROM hints WHERE room_id = ?");
$stmt->execute([$room_id]);

// 5. Signal au serveur Node.js pour rediriger tout le monde
$node_url = 'http://127.0.0.1:3001/internal/game_reset';
$node_secret = getenv('NODE_SHARED_SECRET') ?: 'change_me_locally';

$post_fields = http_build_query([
    'room_id' => $room_id,
    'secret' => $node_secret
]);

// Envoi de la requête POST interne
$opts = ['http' => [
    'method' => 'POST',
    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
    'content' => $post_fields,
    'timeout' => 2
]];
@file_get_contents($node_url, false, stream_context_create($opts));

// 6. Redirection du chef de room (celui qui a cliqué)
// On le renvoie vers la vue du jeu (salle d'attente)
header("Location: ../waiting_room.php?room_id=" . urlencode($room_id));
exit;
?>