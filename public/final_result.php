<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    die("Room ID manquant.");
}

$stmt = $pdo->prepare("SELECT result FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$result = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT name, is_alive, is_undercover FROM players WHERE room_id = ?");
$stmt->execute([$room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Résultat final</title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Luckiest+Guy&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-theme="light">
    <?php if ($result === 'civilians_win'): ?>
        <h2>🎉 Les civils ont gagné !</h2>
    <?php elseif ($result === 'undercover_wins'): ?>
        <h2>😈 Les undercover ont gagné !</h2>
    <?php else: ?>
        <h2>Partie terminée</h2>
    <?php endif; ?>

    <h3>Liste des joueurs :</h3>
    <ul>
        <?php foreach ($players as $player): 
            $class = $player['is_alive'] ? 'alive' : 'dead';
            if ($player['is_undercover']) $class .= ' undercover';
        ?>
            <li class="<?= $class ?>">
                <?= htmlspecialchars($player['name']) ?> 
                (<?= $player['is_alive'] ? 'Vivant' : 'Éliminé' ?>, 
                <?= $player['is_undercover'] ? 'Undercover' : 'Civil' ?>)
            </li>
        <?php endforeach; ?>
    </ul>

    <p>
        <a href="../api/reset_room.php?room_id=<?= urlencode($room_id) ?>" style="...">Recommencer une partie</a>
    </p>
  <script>
  const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
  </script>
  <script src="assets/js/theme-apply.js?v=<?= time() ?>"></script>
  <!-- Bouton avec rotation -->
  <button id="settingsBtn" class="settings-btn" aria-label="Paramètres">
    <span class="material-symbols-rounded">settings</span>
  </button>

  <!-- Panneau dépliable -->
  <div id="settingsPanel">
    <div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div>
  </div>

</body>
</html>
