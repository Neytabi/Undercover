<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// --- DÉBUT CORRECTION ---
// On récupère le secret envoyé par Node (ou vide)
$secret_recu = $_POST['secret'] ?? '';
// Le même secret que dans server.js
$node_secret = getenv('NODE_SHARED_SECRET') ?: 'change_me_locally';

// Si le secret est bon (appel via Node), on fait confiance au POST
// Sinon (appel via navigateur), on exige la SESSION
if ($secret_recu === $node_secret) {
    $player_id = $_POST['player_id'] ?? null;
} else {
    $player_id = $_SESSION['player_id'] ?? null;
}
// --- FIN CORRECTION ---

$room_id = $_POST['room_id'] ?? null;
$hint = trim($_POST['hint'] ?? '');

if (!$player_id || !$room_id || !$hint) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants ou session expirée']);
    exit;
}

try {
    // ... (Le reste du code de connexion DB reste identique) ...
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Vérifie si c’est le tour du joueur
    $stmt = $pdo->prepare("SELECT current_turn FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $currentTurn = $stmt->fetchColumn();

    // Vérification stricte
    if ((int)$currentTurn !== (int)$player_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Ce n\'est pas ton tour (ou usurpation détectée)']);
        exit;
    }

    // ... (Le reste de la logique d'insertion est correcte) ...
    // Vérifie que le joueur est vivant
    $stmt = $pdo->prepare("SELECT is_alive FROM players WHERE id = ? AND room_id = ?");
    $stmt->execute([$player_id, $room_id]);
    $isAlive = $stmt->fetchColumn();

    if (!$isAlive) {
        http_response_code(403);
        echo json_encode(['error' => 'Joueur éliminé']);
        exit;
    }

    // Tente d’insérer l’indice
    $stmt = $pdo->prepare("INSERT INTO hints (room_id, player_id, hint, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$room_id, $player_id, $hint]);

    // ... (Calcul du nextPlayer identique au tien) ...
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

    // ... (Check voting identique) ...
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
    if ($e->getCode() == 23000) {
        http_response_code(403);
        echo json_encode(['error' => 'Indice déjà soumis']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur serveur']);
    }
    exit;
}
?>