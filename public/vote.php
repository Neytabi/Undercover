<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? ($_SESSION['room_id'] ?? null);
$player_id = $_SESSION['player_id'] ?? null;

if (!$room_id || !$player_id) die("Room ID ou Player ID manquant");

// Vérifier état de la partie
$stmt = $pdo->prepare("SELECT state, anonymize_votes FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room || $room['state'] !== 'voting') die("La phase de vote n'a pas commencé.");

// Joueur actuel
$stmt = $pdo->prepare("SELECT is_alive, name FROM players WHERE id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player || !$player['is_alive']) die("Tu es éliminé.");

// Déjà voté ?
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$hasVoted = $stmt->fetchColumn() > 0;

// Traitement du vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted) {
    $target_id = $_POST['target_id'] ?? null;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE id = ? AND room_id = ? AND is_alive = 1");
    $stmt->execute([$target_id, $room_id]);
    if ($stmt->fetchColumn() > 0 && $target_id != $player_id) {
        $pdo->prepare("INSERT INTO votes (room_id, voter_id, target_id) VALUES (?, ?, ?)")
            ->execute([$room_id, $player_id, $target_id]);
        $hasVoted = true;
        // ... après l'exécution de l'INSERT
        // Notify Node server that a vote was submitted (internal endpoint)
        $node_url = 'http://127.0.0.1:3001/internal/vote_submitted';
        $node_secret = getenv('NODE_SHARED_SECRET') ?: 'change_me_locally';
        $post_fields = http_build_query([
            'room_id' => $room_id,
            'secret' => $node_secret
        ]);

        if (function_exists('curl_version')) {
            $ch = curl_init($node_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_exec($ch);
            curl_close($ch);
        } else {
            $opts = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => $post_fields, 'timeout' => 2]];
            @file_get_contents($node_url, false, stream_context_create($opts));
        }
    }
}

// Joueurs vivants
$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$alivePlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAlive = count($alivePlayers);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE room_id = ?");
$stmt->execute([$room_id]);
$totalVotes = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Vote - Undercover</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Luckiest+Guy&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet"/>
    <style>
        .vote-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .player-card {
            background-color: var(--light-red);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            transition: transform 0.2s;
        }
        .player-card:hover {
            transform: translateY(-3px);
        }
        [data-theme="dark"] .player-card {
            background-color: var(--input-dark);
            border: 1px solid var(--border-dark);
        }
        .player-name {
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }
        .vote-btn {
            padding: 8px 12px;
            background-color: var(--main-red);
            color: var(--white);
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .vote-btn:hover {
            background-color: var(--h2comics-yellow);
            color: var(--blue-bg);
        }
        .vote-waiting {
            text-align: center;
            font-size: 1.2rem;
            margin-top: 20px;
        }
        .vote-results {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body data-theme="light">
<h1>Phase de Vote</h1>
<p>Bienvenue, <?= htmlspecialchars($player['name']) ?></p>

<div class="container">
    <?php if (!$hasVoted): ?>
        <div class="vote-grid">
            <?php foreach ($alivePlayers as $p): ?>
                <?php if ($p['id'] != $player_id): ?>
                    <div class="player-card">
                        <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                        <form method="POST">
                            <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="vote-btn">Voter</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="vote-waiting">
            ✅ Vote enregistré<br>
            En attente des autres joueurs...
        </div>
    <?php endif; ?>

    <p id="vote-counter">Votes reçus : <?= $totalVotes ?> / <?= $totalAlive ?></p>

    <?php if ($totalVotes == $totalAlive): ?>
        <div class="vote-results">
            <a href="../api/results.php?room_id=<?= urlencode($room_id) ?>" class="vote-btn">
                Voir les résultats <?= $room['anonymize_votes'] ? "(votes anonymisés)" : "" ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);

ws.addEventListener('open', () => {
  // s'identifier auprès du serveur WebSocket pour rejoindre la room
  ws.send(JSON.stringify({
    type: 'join_room',
    room_id: <?= json_encode($room_id) ?>,
    player_id: <?= json_encode($player_id) ?>
  }));
});

ws.addEventListener('message', (event) => {
  const msg = JSON.parse(event.data);
  if (msg.type === 'vote_update') {
      document.getElementById('vote-counter').textContent =
          `Votes reçus : ${msg.totalVotes} / ${msg.totalAlive}`;
  }
  if (msg.type === 'voting_complete') {
      window.location.href = msg.redirect_url;
  }
});

ws.addEventListener('error', (err) => {
  console.error('WebSocket error:', err);
});
</script>


<script src="assets/js/theme-apply.js?v=<?= time() ?>"></script>
<button id="settingsBtn" class="settings-btn"><span class="material-symbols-rounded">settings</span></button>
<div id="settingsPanel">
    <div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div>
</div>
</body>
</html>
