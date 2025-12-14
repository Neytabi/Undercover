<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// sanitize input ids
$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$player_id = isset($_SESSION['player_id']) ? (int)$_SESSION['player_id'] : 0;
$session_room_id = isset($_SESSION['room_id']) ? (int)$_SESSION['room_id'] : 0;

if (!$room_id || !$player_id || $room_id !== $session_room_id) {
    die("Erreur de session ou room_id invalide.");
}

// Vérifie que le joueur est le créateur
$stmt = $pdo->prepare("SELECT is_creator FROM players WHERE id = :id AND room_id = :room_id");
$stmt->execute(['id' => $player_id, 'room_id' => $room_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player || empty($player['is_creator'])) {
    die("Vous n'êtes pas autorisé à démarrer la partie.");
}

// si turn_time_seconds est envoyé par le formulaire, on l'enregistre (valide côté créateur)
if (isset($_POST['turn_time_seconds']) && is_numeric($_POST['turn_time_seconds'])) {
  $t = (int)$_POST['turn_time_seconds'];
  if ($t >= 5 && $t <= 120) {
    $stmt = $pdo->prepare("UPDATE rooms SET turn_time_seconds = ? WHERE id = ?");
    $stmt->execute([$t, $room_id]);
  }
}

// récupère num_undercover post (optionnel). On le validera contre la BDD/players.
$num_undercover_post = isset($_POST['num_undercover']) ? (int)$_POST['num_undercover'] : null;

// Récupère la liste des joueurs "valide" en salle d'attente.
// Ici on prend les joueurs connectés (is_connected = 1). Change la condition si tu veux inclure tous.
$stmt = $pdo->prepare("SELECT * FROM players WHERE room_id = :room_id AND is_connected = 1");
$stmt->execute(['room_id' => $room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$playerCount = count($players);
if ($playerCount < 3) {
    header("Location: /waiting_room.php?error=not_enough_players");
    exit;
}

// Règle : au maximum floor(playerCount / 2) undercovers (empêche la majorité)
$maxUndercover = max(1, (int) floor($playerCount / 2));

// Lire la valeur officielle depuis la table rooms (si présente)
$stmt = $pdo->prepare("SELECT COALESCE(undercover_count, 1) AS undercover_count FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$db_undercover = isset($row['undercover_count']) ? (int)$row['undercover_count'] : 1;

// Déterminer le nombre final d'undercover (priorité : POST validé, sinon DB, enfin 1)
if ($num_undercover_post !== null && $num_undercover_post > 0) {
    // clamp POST between 1 and $maxUndercover
    $num_undercover = max(1, min($maxUndercover, (int)$num_undercover_post));
} else {
    // use DB value but clamp it too
    $num_undercover = max(1, min($maxUndercover, $db_undercover));
}

// Optionnel : enregistrer la valeur finale dans la table rooms (pour référence)
$stmt = $pdo->prepare("UPDATE rooms SET undercover_count = :uc WHERE id = :room_id");
$stmt->execute(['uc' => $num_undercover, 'room_id' => $room_id]);

// Génère une paire de mots
require_once __DIR__ . '/../words.php';
list($common_word, $undercover_word) = getRandomWordPair();

try {
    // Démarrer transaction pour éviter états partiels
    $pdo->beginTransaction();

    // Shuffle players et choisir $num_undercover distincts
    $player_ids = array_column($players, 'id');
    shuffle($player_ids);
    $selected_undercover_ids = array_slice($player_ids, 0, $num_undercover);
    $selected_map = array_flip($selected_undercover_ids);

    // Assigne les mots aux joueurs (et is_undercover)
    $updatePlayerStmt = $pdo->prepare("UPDATE players SET word = :word, is_undercover = :is_undercover WHERE id = :id");
    foreach ($players as $p) {
        $is_undercover = isset($selected_map[$p['id']]) ? 1 : 0;
        $word = $is_undercover ? $undercover_word : $common_word;
        $updatePlayerStmt->execute([
            'word' => $word,
            'is_undercover' => $is_undercover,
            'id' => $p['id']
        ]);
    }

    // Mets à jour la room en "started" et stocke les mots
    $stmt = $pdo->prepare("
        UPDATE rooms
        SET state = 'started', common_word = :common_word, undercover_word = :undercover_word
        WHERE id = :room_id
    ");
    $stmt->execute([
        'common_word' => $common_word,
        'undercover_word' => $undercover_word,
        'room_id' => $room_id
    ]);

    // Initialise l'ordre des joueurs et le tour
    $player_ids_order = array_column($players, 'id');
    shuffle($player_ids_order);
    $players_order_json = json_encode($player_ids_order);
    $current_turn_player_id = $player_ids_order[0];

    $stmt = $pdo->prepare("UPDATE rooms SET players_order = :players_order, current_turn = :current_turn WHERE id = :room_id");
    $stmt->execute([
        'players_order' => $players_order_json,
        'current_turn' => $current_turn_player_id,
        'room_id' => $room_id
    ]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("start_game failed: " . $e->getMessage());
    die("Erreur lors du démarrage de la partie.");
}

// Redirige vers la page de jeu
header("Location: /game.php?room_id=$room_id&player_id=$player_id");
exit;
