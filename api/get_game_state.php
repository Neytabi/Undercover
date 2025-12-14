<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$room_id = $_GET['room_id'] ?? null;
$player_id = $_GET['player_id'] ?? null;

if (!$room_id || !$player_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

// Récupère les infos du joueur
$stmt = $pdo->prepare("SELECT id, name, word, is_alive, room_id FROM players WHERE id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    http_response_code(404);
    echo json_encode(['error' => 'Joueur introuvable']);
    exit;
}

// Met à jour l'activité du joueur
$stmt = $pdo->prepare("UPDATE players SET last_activity = NOW(), is_connected = 1 WHERE id = ?");
$stmt->execute([$player_id]);

// Récupère les infos de la room
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    http_response_code(404);
    echo json_encode(['error' => 'Salle introuvable']);
    exit;
}

$players_order = json_decode($room['players_order'], true) ?: [];
$current_turn_player_id = (int)$room['current_turn'];

// Récupère tous les joueurs
$stmt = $pdo->prepare("SELECT id, name, is_alive, is_connected FROM players WHERE room_id = ?");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupère les indices
$stmt = $pdo->prepare("SELECT player_id, hint FROM hints WHERE room_id = ?");
$stmt->execute([$room_id]);
$hints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Joueurs vivants
$alivePlayerIds = array_map(fn($p) => $p['id'], array_filter($players, fn($p) => $p['is_alive']));

// Map des indices
$hint_map = [];
foreach ($hints as $hint) {
    if (in_array($hint['player_id'], $alivePlayerIds)) {
        $hint_map[$hint['player_id']] = $hint['hint'];
    }
}

// ✅ On retourne toujours l’état, même si aucun joueur connecté
echo json_encode([
    'player' => $player,
    'players' => $players,
    'hint_map' => $hint_map,
    'current_turn_player_id' => $current_turn_player_id,
    'you_are_turn' => $player['id'] == $current_turn_player_id,
    'room_state' => $room['state'] // important pour le check côté Node.js
]);
