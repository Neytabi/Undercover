<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$player_id = $_POST['player_id'] ?? null;
$room_id = $_POST['room_id'] ?? null;
$hint = trim($_POST['hint'] ?? '');

if (!$player_id || !$room_id || !$hint) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    error_log("DEBUG - player_id (POST): $player_id");
    error_log("DEBUG - room_id (POST): $room_id");

    // Vérifie si c’est le tour du joueur
    $stmt = $pdo->prepare("SELECT current_turn FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $currentTurn = $stmt->fetchColumn();

    error_log("DEBUG player_id (POST) = " . var_export($player_id, true));
    error_log("DEBUG currentTurn (DB) = " . var_export($currentTurn, true));

    if ((int)$currentTurn !== (int)$player_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Ce n\'est pas ton tour']);
        exit;
    }

    // Vérifie que le joueur est vivant
    $stmt = $pdo->prepare("SELECT is_alive FROM players WHERE id = ? AND room_id = ?");
    $stmt->execute([$player_id, $room_id]);
    $isAlive = $stmt->fetchColumn();

    if (!$isAlive) {
        http_response_code(403);
        echo json_encode(['error' => 'Joueur éliminé ou non trouvé']);
        exit;
    }

    error_log("room_id: $room_id, player_id: $player_id, hint: $hint");

    // Tente d’insérer l’indice
    $stmt = $pdo->prepare("INSERT INTO hints (room_id, player_id, hint, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$room_id, $player_id, $hint]);

    error_log("Indice inséré avec succès");

    // Trouve le prochain joueur vivant
    $stmt = $pdo->prepare("SELECT players_order FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $players_order = json_decode($stmt->fetchColumn(), true);

    $currentIndex = array_search($player_id, $players_order);
    $totalPlayers = count($players_order);
    $nextPlayer = null;

    for ($i = 1; $i <= $totalPlayers; $i++) {
        $candidateIndex = ($currentIndex + $i) % $totalPlayers;
        $candidateId = $players_order[$candidateIndex];

    // Vérifie si ce joueur est vivant
        $stmt = $pdo->prepare("SELECT is_alive FROM players WHERE id = ? AND room_id = ?");
        $stmt->execute([$candidateId, $room_id]);
        $isAlive = $stmt->fetchColumn();

        if ($isAlive) {
            $nextPlayer = $candidateId;
            break;
        }
    }


    if ($nextPlayer) {
        $stmt = $pdo->prepare("UPDATE rooms SET current_turn = ? WHERE id = ?");
        $stmt->execute([$nextPlayer, $room_id]);
    }

    // Vérifie si tous les joueurs vivants ont donné leur indice
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ? AND is_alive = 1");
    $stmt->execute([$room_id]);
    $totalAlive = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT player_id) FROM hints h
        JOIN players p ON h.player_id = p.id
        WHERE h.room_id = ? AND p.is_alive = 1
    ");
    $stmt->execute([$room_id]);
    $totalHints = $stmt->fetchColumn();

    if ($totalAlive > 0 && $totalHints == $totalAlive) {
        $stmt = $pdo->prepare("UPDATE rooms SET state = 'voting' WHERE id = ?");
        $stmt->execute([$room_id]);
    }

    // Récupère le nouvel état
    $stmt = $pdo->prepare("SELECT state FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $state = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'hint' => $hint,
        'next_player' => $nextPlayer ?? null,
        'room_state' => $state
    ]);


} catch (PDOException $e) {
    // Gestion du doublon : le joueur a déjà soumis un indice
    if ($e->getCode() == 23000) {
        http_response_code(403);
        echo json_encode(['error' => 'Indice déjà soumis']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur serveur: ' . $e->getMessage()]);
    }
    exit;
}
