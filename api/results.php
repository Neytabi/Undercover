<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) die("Room ID manquant.");

// 1. Compter les votes
$stmt = $pdo->prepare("SELECT target_id, COUNT(*) as count FROM votes WHERE room_id = ? GROUP BY target_id ORDER BY count DESC");
$stmt->execute([$room_id]);
$votes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 2. Noms des joueurs
$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = ?");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. Identifier l'éliminé (CORRECTION ÉGALITÉ)
$eliminatedIds = [];
$tie = false;

if (!empty($votes)) {
    $maxVotes = max($votes);
    
    // On compte combien de joueurs ont le max de votes
    $playersWithMax = 0;
    foreach ($votes as $pid => $count) {
        if ($count == $maxVotes) {
            $playersWithMax++;
            $eliminatedIds[] = $pid;
        }
    }

    // Si plus d'un joueur a le max de votes, c'est une égalité -> Personne n'est éliminé
    if ($playersWithMax > 1) {
        $eliminatedIds = []; // On vide la liste des éliminés
        $tie = true;
    }
}

// 4. Mettre à jour la DB
if (!empty($eliminatedIds)) {
    $placeholders = implode(',', array_fill(0, count($eliminatedIds), '?'));
    $sql = "UPDATE players SET is_alive = 0 WHERE id IN ($placeholders)";
    $pdo->prepare($sql)->execute($eliminatedIds);
}

// 5. Sauvegarde Session
$_SESSION['vote_results'] = [
    'room_id' => $room_id,
    'players' => $players,
    'vote_count' => $votes,
    'eliminated' => $eliminatedIds,
    'is_tie' => $tie // Utile pour afficher "Égalité, personne ne sort !" dans la vue
];

// 6. Redirection
header("Location: /results_view.php?room_id=" . $room_id);
exit;
?>