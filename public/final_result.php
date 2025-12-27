<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) die("Room manquante.");

$stmt = $pdo->prepare("SELECT result FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$result = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT name, is_alive, is_undercover FROM players WHERE room_id = ? ORDER BY is_undercover DESC");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$win = ($result === 'civilians_win');
$title = $win ? "Mission Réussie !" : "Échec de la Mission";
$color = $win ? "#4CAF50" : "var(--main-red)";
$emoji = $win ? "🕵️‍♂️✅" : "🕵️‍♂️❌";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Fin de Mission - Undercover</title>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <h1 class="main-title" style="color: <?= $color ?>;"><?= $title ?></h1>

    <div class="container">
        <div class="victory-header">
            <div style="font-size: 4rem;"><?= $emoji ?></div>
            <h2 style="color: <?= $color ?>;"><?= $win ? "Les Civils ont gagné !" : "L'imposteur l'emporte !" ?></h2>
        </div>

        <h3 style="text-align:center; margin-top: 30px;">Rapport d'Identité</h3>
        <ul class="styled-list">
            <?php foreach ($players as $p): ?>
                <li class="player-row <?= $p['is_undercover'] ? 'is-undercover' : 'is-civilian' ?>">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <!-- CORRECTION ICI : 'domino_mask' au lieu de 'mask' -->
                        <span class="material-symbols-rounded"><?= $p['is_undercover'] ? 'domino_mask' : 'person' ?></span>
                        <div>
                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                            <div style="font-size:0.8rem; opacity:0.8;"><?= $p['is_undercover'] ? 'Undercover' : 'Civil' ?></div>
                        </div>
                    </div>
                    <span class="<?= $p['is_alive'] ? 'status-alive' : 'status-dead' ?>">
                        <?= $p['is_alive'] ? 'Sain et sauf' : 'Éliminé' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="action-zone" style="margin-top: 25px;">
            <a href="../api/reset_room.php?room_id=<?= $room_id ?>" style="text-decoration:none;">
                <button class="action-btn">REJOUER UNE MANCHE 🔄</button>
            </a>
            <a href="../index.php" style="text-decoration:none;">
                <button class="action-btn secondary-btn" style="margin-top:10px;">QUITTER LE QG 🏠</button>
            </a>
        </div>
    </div>

    <script src="assets/js/theme-apply.js"></script>
    <script>
        const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
        ws.onopen = () => ws.send(JSON.stringify({ type: 'join_room', room_id: "<?= $room_id ?>", player_id: "<?= $_SESSION['player_id'] ?>" }));
        ws.onmessage = (e) => {
            const m = JSON.parse(e.data);
            if (m.type === 'game_reset') window.location.href = "../" + m.redirect_url;
        };
    </script>
</body>
</html>