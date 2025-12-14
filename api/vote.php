<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? ($_SESSION['room_id'] ?? null);
$player_id = $_SESSION['player_id'] ?? null;

if (!$room_id || !$player_id) {
    die("Room ID ou Player ID manquant");
}

// Vérifie que la room est bien en phase vote
$stmt = $pdo->prepare("SELECT state FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$state = $stmt->fetchColumn();

if ($state !== 'voting') {
    die("La phase de vote n'a pas encore commencé.");
}

// Vérifie que le joueur est vivant
$stmt = $pdo->prepare("SELECT is_alive, name FROM players WHERE id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player || !$player['is_alive']) {
    die("Tu ne peux pas voter car tu es éliminé ou joueur introuvable.");
}

// Vérifie si le joueur a déjà voté
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$hasVoted = $stmt->fetchColumn() > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted) {
    $target_id = $_POST['target_id'] ?? null;

    // Vérifie que la cible du vote est valide et vivante
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE id = ? AND room_id = ? AND is_alive = 1");
    $stmt->execute([$target_id, $room_id]);
    if ($stmt->fetchColumn() == 0) {
        $error = "Le joueur choisi est invalide.";
    } else {
        // Insérer le vote
        $stmt = $pdo->prepare("INSERT INTO votes (room_id, voter_id, target_id) VALUES (?, ?, ?)");
        $stmt->execute([$room_id, $player_id, $target_id]);

        $hasVoted = true;
    }
}

// Récupère la liste des joueurs vivants pour afficher les options
$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$alivePlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupère le nombre total de joueurs vivants pour savoir si tout le monde a voté
$totalAlive = count($alivePlayers);

// Récupère le nombre de votes déjà enregistrés
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE room_id = ?");
$stmt->execute([$room_id]);
$totalVotes = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Undercover - Vote</title>
</head>
<body>
    <h1>Phase de Vote</h1>
    <p>Bienvenue, <?= htmlspecialchars($player['name']) ?></p>

    <?php if (isset($error)): ?>
        <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($hasVoted): ?>
        <p>Merci d'avoir voté !</p>
    <?php else: ?>
        <form method="POST">
            <label for="target_id">Vote pour :</label>
            <select name="target_id" id="target_id" required>
                <option value="">-- Choisis un joueur --</option>
                <?php foreach ($alivePlayers as $p): ?>
                    <?php if ($p['id'] != $player_id): // On ne peut pas se voter soi-même ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit">Envoyer mon vote</button>
        </form>
    <?php endif; ?>

    <p id="vote-counter">Votes reçus : <?= $totalVotes ?> / <?= $totalAlive ?></p>


    <?php if ($totalVotes == $totalAlive): ?>
        <p><strong>Tous les joueurs ont voté.</strong> Calcul des résultats en cours...</p>
        <a href="results.php?room_id=<?= urlencode($room_id) ?>">Voir les résultats</a>
    <?php else: ?>
        <p>En attente des votes des autres joueurs...</p>
    <?php endif; ?>

    <?php if ($hasVoted): ?>
    <script>
    const socket = new WebSocket('ws://localhost:3001');

    socket.onopen = () => {
        socket.send(JSON.stringify({
            type: 'vote_submitted',
            room_id: <?= json_encode($room_id) ?>,
            player_id: <?= json_encode($player_id) ?>
        }));
    };
    </script>
    <?php endif; ?>

    <script>
    const socket = new WebSocket('ws://localhost:3001');

    socket.onmessage = (event) => {
        const msg = JSON.parse(event.data);

        if (msg.type === 'vote_update') {
            const compteur = document.getElementById('vote-counter');
            if (compteur) {
                compteur.textContent = `Votes reçus : ${msg.totalVotes} / ${msg.totalAlive}`;
            }
        }

        if (msg.type === 'voting_complete') {
            alert('Tous les joueurs ont voté ! Redirection vers les résultats...');
            window.location.href = msg.redirect_url;
        }
    };
    </script>


</body>
</html>
