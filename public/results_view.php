<?php
session_start();

if (!isset($_SESSION['vote_results'])) {
    die("Aucun résultat à afficher.");
}

$data = $_SESSION['vote_results'];
$players = $data['players'];
$voteCount = $data['vote_count'];
$eliminated = $data['eliminated'];
$room_id = $data['room_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Résultats du vote</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Luckiest+Guy&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <style>
        .players-list { display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; }
        .player-card {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            width: 120px;
            transition: transform 0.5s ease;
        }
        .player-card.eliminated { background: #b33; color: #fff; transform: scale(1.1); }
        .votes { font-size: 14px; margin-top: 5px; opacity: 0.8; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .reveal { animation: fadeIn 1s ease forwards; }
    </style>
</head>
<body data-theme="light">
    <h1>Résultats du vote</h1>
    <div class="players-list">
        <?php foreach ($players as $id => $name): ?>
            <div class="player-card" id="player-<?= $id ?>">
                <strong><?= htmlspecialchars($name) ?></strong>
                <div class="votes"><?= $voteCount[$id] ?> vote<?= $voteCount[$id] > 1 ? 's' : '' ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
        const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
    </script>
    <script>
        // Révélation progressive des joueurs éliminés
        const eliminated = <?= json_encode($eliminated) ?>;
        let delay = 1000;

        eliminated.forEach((id, index) => {
            setTimeout(() => {
                document.getElementById('player-' + id)?.classList.add('eliminated', 'reveal');
            }, delay * (index + 1));
        });

        // Redirection après animation
        setTimeout(() => {
            window.location.href = "/api/next_round.php?room_id=<?= urlencode($room_id) ?>";
        }, delay * (eliminated.length + 2));
    </script>
</body>
</html>
