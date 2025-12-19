<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) die("Room ID manquant.");

// Récupérer le résultat
$stmt = $pdo->prepare("SELECT result FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$result = $stmt->fetchColumn();

// Récupérer les joueurs
$stmt = $pdo->prepare("SELECT name, is_alive, is_undercover FROM players WHERE room_id = ? ORDER BY is_undercover DESC");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Titre et couleur selon le résultat
$title = "Partie terminée";
$titleColor = "var(--blue-bg)";
$emoji = "🏁";

if ($result === 'civilians_win') {
    $title = "Les Civils gagnent !";
    $titleColor = "#4CAF50"; // Vert victoire
    $emoji = "🎉";
} elseif ($result === 'undercover_wins') {
    $title = "Les Undercover l'emportent !";
    $titleColor = "var(--main-red)"; // Rouge méchant
    $emoji = "😈";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Fin de partie</title>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&family=Rubik:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <h1 class="main-title" style="color: <?= $titleColor ?>;">Mission Terminée</h1>

    <div class="container">
        <div class="victory-header">
            <span class="victory-emoji"><?= $emoji ?></span>
            <h2 style="color: <?= $titleColor ?>;"><?= $title ?></h2>
        </div>

        <h3>Rapport final :</h3>
        <ul class="player-list-final">
            <?php foreach ($players as $p): ?>
                <?php 
                    $roleClass = $p['is_undercover'] ? 'is-undercover' : 'is-civilian';
                    $aliveClass = $p['is_alive'] ? 'status-alive' : 'status-dead';
                    $icon = $p['is_undercover'] ? '👤' : '🕵️';
                ?>
                <li class="player-row <?= $roleClass ?>" style="justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.5rem;"><?= $icon ?></span>
                        <div>
                            <strong><?= htmlspecialchars($p['name']) ?></strong>
                            <div style="font-size:0.8rem; opacity:0.8;"><?= $p['is_undercover']?'Undercover':'Civil' ?></div>
                        </div>
                    </div>
                    <span class="<?= $aliveClass ?>"><?= $p['is_alive']?'Vivant':'Éliminé' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="../api/reset_room.php?room_id=<?= urlencode($room_id) ?>" style="text-decoration:none;">
            <button class="restart-btn">REJOUER 🔄</button>
        </a>
        <a href="../index.php" style="text-decoration:none;">
            <button class="action-btn secondary-btn" style="margin-top:10px;">MENU PRINCIPAL 🏠</button>
        </a>
    </div>

    <button id="settingsBtn" class="settings-btn"><span class="material-symbols-rounded">settings</span></button>
    <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Mode Sombre</div></div>
    <script src="assets/js/theme-apply.js"></script>
</body>
</html>