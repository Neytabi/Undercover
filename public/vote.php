<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? ($_SESSION['room_id'] ?? null);
$player_id = $_SESSION['player_id'] ?? null;

if (!$room_id || !$player_id) die("Identifiants manquants.");

// 1. Infos Room
$stmt = $pdo->prepare("SELECT state, anonymize_votes, turn_time_seconds FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room || $room['state'] !== 'voting') {
    header("Location: game.php?room_id=$room_id");
    exit;
}

// 2. Infos Joueur
$stmt = $pdo->prepare("SELECT is_alive, name FROM players WHERE id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player || !$player['is_alive']) die("Vous êtes éliminé (spectateur).");

// 3. A-t-il déjà voté ?
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE voter_id = ? AND room_id = ?");
$stmt->execute([$player_id, $room_id]);
$hasVoted = $stmt->fetchColumn() > 0;

// 4. TRAITEMENT DU VOTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hasVoted) {
    $target_id = $_POST['target_id'] ?? null;
    
    // Vérif validité cible
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE id = ? AND room_id = ? AND is_alive = 1");
    $stmt->execute([$target_id, $room_id]);
    
    if ($stmt->fetchColumn() > 0 && $target_id != $player_id) {
        $pdo->prepare("INSERT INTO votes (room_id, voter_id, target_id) VALUES (?, ?, ?)")
            ->execute([$room_id, $player_id, $target_id]);
        $hasVoted = true;

        // Notification Node.js
        $node_url = 'http://127.0.0.1:3001/internal/vote_submitted';
        $post_fields = http_build_query(['room_id' => $room_id, 'secret' => 'change_me_locally']);
        $opts = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => $post_fields, 'timeout' => 2]];
        @file_get_contents($node_url, false, stream_context_create($opts));
    }
}

// 5. Liste des cibles
$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$alivePlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAlive = count($alivePlayers);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE room_id = ?");
$stmt->execute([$room_id]);
$totalVotes = $stmt->fetchColumn();

$turn_time = $room['turn_time_seconds'] ?? 30;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Vote - Undercover</title>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <h1 class="main-title">L'Heure du Verdict</h1>
    
    <div class="container">
        <div id="timerContainer" style="display:block;">
            <div id="timerBar"></div>
        </div>

        <div class="interrogation-header">
            <span class="material-symbols-rounded" style="font-size: 3rem; color: var(--main-red);">gavel</span>
            <h2><?= $hasVoted ? "En attente du jury" : "Désignez l'imposteur" ?></h2>
        </div>

        <?php if (!$hasVoted): ?>
            <p class="instruction" style="text-align:center;">Le temps presse ! Votez avant la fin du chrono :</p>
            <div class="vote-grid">
                <?php foreach ($alivePlayers as $p): ?>
                    <?php if ($p['id'] != $player_id): ?>
                        <form method="POST" class="vote-form" style="height:100%;">
                            <input type="hidden" name="target_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="vote-card" style="width:100%;">
                                <div class="player-avatar"><span class="material-symbols-rounded">person_search</span></div>
                                <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
                                <div class="vote-action-btn">VOTER</div>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="waiting-screen">
                <span class="material-symbols-rounded spin">cached</span>
                <p>Vote enregistré.</p>
                <div class="counter-badge" id="v-counter"><?= $totalVotes ?> / <?= $totalAlive ?></div>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/theme-apply.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
            const roomId = "<?= $room_id ?>";
            const playerId = "<?= $player_id ?>";
            const totalDuration = <?= $turn_time ?>;
            const hasVoted = <?= $hasVoted ? 'true' : 'false' ?>;

            function startTimer() {
                const bar = document.getElementById('timerBar');
                const container = document.getElementById('timerContainer');
                if(hasVoted) { if(container) container.style.display = 'none'; return; }
                if(!bar) return;

                bar.style.transition = 'none';
                bar.style.width = '100%';
                void bar.offsetWidth; 
                bar.style.transition = `width ${totalDuration}s linear`;
                bar.style.width = '0%';

                setTimeout(() => {
                    const forms = document.querySelectorAll('.vote-form');
                    if(forms.length > 0 && !hasVoted) {
                        const randomForm = forms[Math.floor(Math.random() * forms.length)];
                        randomForm.submit();
                    }
                }, totalDuration * 1000);
            }

            if(!hasVoted) startTimer();
            else document.getElementById('timerContainer').style.display = 'none';

            ws.onopen = () => ws.send(JSON.stringify({ type: 'join_room', room_id: roomId, player_id: playerId }));
            
            ws.onmessage = (e) => {
                const data = JSON.parse(e.data);
                
                if (data.type === 'vote_update') {
                    const c = document.getElementById('v-counter');
                    if(c) c.textContent = `${data.totalVotes} / ${data.totalAlive}`;
                }
                
                if (data.type === 'voting_complete') {
                    // IMPORTANT: On utilise l'URL envoyée par le serveur (qui pointe vers api/results.php)
                    // Ou on force le chemin vers l'API de calcul
                    window.location.href = data.redirect_url || `../api/results.php?room_id=${roomId}`;
                }
            };
        });
    </script>
    <button id="settingsBtn" class="settings-btn" aria-label="Paramètres"><span class="material-symbols-rounded">settings</span></button>
    <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div></div>
</body>
</html>