<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? ($_SESSION['room_id'] ?? null);
$player_id = $_SESSION['player_id'] ?? null;

if (!$room_id || !$player_id) die("Erreur : Identifiants manquants.");

// Vérifier état de la partie
$stmt = $pdo->prepare("SELECT state, anonymize_votes FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room || $room['state'] !== 'voting') die("Ce n'est pas le moment de voter.");

// Joueur actuel
$stmt = $pdo->prepare("SELECT is_alive, name FROM players WHERE id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player || !$player['is_alive']) die("Vous êtes éliminé (spectateur).");

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

        // Notification Node.js (identique à ton code original)
        $node_url = 'http://127.0.0.1:3001/internal/vote_submitted';
        $post_fields = http_build_query(['room_id' => $room_id, 'secret' => getenv('NODE_SHARED_SECRET') ?: 'change_me_locally']);
        
        $opts = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => $post_fields, 'timeout' => 2]];
        @file_get_contents($node_url, false, stream_context_create($opts));
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
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&family=Rubik:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Grille de vote style Comics */
        .vote-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }

        .vote-card {
            background-color: var(--white);
            border: 2px solid var(--blue-bg);
            border-radius: 12px;
            padding: 15px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
            box-shadow: 0 4px 0 rgba(0,0,0,0.1);
            cursor: pointer;
            height: 100%;
        }

        .vote-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 0 var(--main-red);
            border-color: var(--main-red);
        }

        [data-theme="dark"] .vote-card {
            background-color: var(--input-dark);
            border-color: var(--border-dark);
            color: var(--text-light);
        }

        .player-avatar {
            font-size: 2rem;
            margin-bottom: 10px;
            background: var(--light-red);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--main-red);
        }
        
        .player-name {
            font-family: 'Rubik', sans-serif;
            font-weight: bold;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 10px;
            word-break: break-word;
        }

        .vote-action-btn {
            background-color: var(--blue-bg);
            color: var(--white);
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-family: 'Bangers', cursive;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }

        .vote-card:hover .vote-action-btn {
            background-color: var(--main-red);
        }

        /* État d'attente */
        .waiting-screen {
            text-align: center;
            padding: 30px 10px;
        }
        
        .waiting-icon {
            font-size: 4rem;
            animation: pulse 1.5s infinite;
            color: var(--main-red);
            display: block;
            margin-bottom: 15px;
        }

        .counter-badge {
            background: var(--h2comics-yellow);
            color: var(--blue-bg);
            padding: 5px 15px;
            border-radius: 12px;
            font-family: 'Bangers', cursive;
            font-size: 1.5rem;
            display: inline-block;
            margin-top: 15px;
            border: 2px solid var(--blue-bg);
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body data-theme="light">

    <h1 id="mainTitle">À votre tour !</h1>
    
    <div class="container">
        <h2 style="margin-top:0;">
            <?php if (!$hasVoted): ?>
                Qui est l'intrus ?
            <?php else: ?>
                Vote enregistré !
            <?php endif; ?>
        </h2>

        <?php if (!$hasVoted): ?>
            <p style="text-align:center; margin-bottom: 5px;">Choisissez un joueur à éliminer :</p>
            
            <div class="vote-grid">
                <?php foreach ($alivePlayers as $p): ?>
                    <?php if ($p['id'] != $player_id): ?>
                        <form method="POST" style="height:100%;">
                            <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="vote-card" style="width:100%; padding: 15px 10px;">
                                <div class="player-avatar">👤</div>
                                <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                                <div class="vote-action-btn">VOTER</div>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="waiting-screen">
                <span class="material-symbols-rounded waiting-icon">hourglass_top</span>
                <p>En attente des autres joueurs...</p>
                <div id="vote-counter" class="counter-badge">
                    <?= $totalVotes ?> / <?= $totalAlive ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($totalVotes == $totalAlive): ?>
            <div style="text-align:center; margin-top:20px;">
                <a href="../api/results.php?room_id=<?= urlencode($room_id) ?>">
                    <button class="vote-action-btn" style="background:var(--main-red); font-size:1.2rem; padding: 10px 20px;">
                        Voir le verdict &rarr;
                    </button>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);

    ws.addEventListener('open', () => {
        ws.send(JSON.stringify({
            type: 'join_room',
            room_id: <?= json_encode($room_id) ?>,
            player_id: <?= json_encode($player_id) ?>
        }));
    });

    ws.addEventListener('message', (event) => {
        const msg = JSON.parse(event.data);
        
        // Mise à jour du compteur
        if (msg.type === 'vote_update') {
            const counter = document.getElementById('vote-counter');
            if(counter) {
                counter.textContent = `${msg.totalVotes} / ${msg.totalAlive}`;
            }
        }
        
        // Redirection quand le vote est fini
        if (msg.type === 'voting_complete') {
            window.location.href = msg.redirect_url;
        }
    });

    // Theme manager
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.body.setAttribute('data-theme', savedTheme);
    </script>
    
    <button id="settingsBtn" class="settings-btn" aria-label="Paramètres">
        <span class="material-symbols-rounded">settings</span>
    </button>
    <div id="settingsPanel">
        <div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div>
    </div>
    <script src="assets/js/theme-apply.js?v=<?= time() ?>"></script>
</body>
</html>