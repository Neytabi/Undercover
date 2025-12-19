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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Fin de partie - Undercover</title>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&family=Rubik:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .victory-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .victory-emoji {
            font-size: 4rem;
            display: block;
            animation: bounce 2s infinite;
        }

        .player-list-final {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .player-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            margin-bottom: 10px;
            background: var(--white);
            border: 1px solid var(--blue-bg);
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 2px 0 rgba(0,0,0,0.05);
        }

        [data-theme="dark"] .player-row {
            background: var(--input-dark);
            border-color: var(--border-dark);
            color: var(--text-light);
        }

        /* Effet spécial pour l'Undercover */
        .player-row.is-undercover {
            border-left: 5px solid var(--main-red);
        }
        
        .player-row.is-civilian {
            border-left: 5px solid #4CAF50;
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-alive { background: #e8f5e9; color: #2e7d32; }
        .status-dead { background: #ffebee; color: #c62828; text-decoration: line-through; opacity: 0.7; }

        [data-theme="dark"] .status-alive { background: #1b5e20; color: #a5d6a7; }
        [data-theme="dark"] .status-dead { background: #b71c1c; color: #ffcdd2; }

        .role-icon {
            margin-right: 8px;
            font-size: 1.2rem;
            vertical-align: middle;
        }
        
        .restart-btn {
            background-color: var(--h2comics-yellow);
            color: var(--blue-bg);
            font-family: 'Bangers', cursive;
            font-size: 1.4rem;
            padding: 15px 30px;
            width: 100%;
            border: none;
            border-radius: 8px;
            margin-top: 20px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 0 rgba(0,0,0,0.2);
        }
        
        .restart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 0 rgba(0,0,0,0.2);
        }

        .home-btn {
            background-color: transparent;
            color: var(--blue-bg);
            font-family: 'Bangers', cursive;
            font-size: 1.2rem;
            padding: 10px 20px;
            width: 100%;
            border: 2px solid var(--blue-bg);
            border-radius: 8px;
            margin-top: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: block; /* Pour qu'il prenne la largeur */
            box-sizing: border-box; /* Pour gérer le padding correctement */
        }

        .home-btn:hover {
            background-color: var(--blue-bg);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Adaptation Dark Mode */
        [data-theme="dark"] .home-btn {
            color: var(--white);
            border-color: var(--white);
        }
        [data-theme="dark"] .home-btn:hover {
            background-color: var(--white);
            color: var(--blue-bg);
        }
    </style>
</head>
<body data-theme="light">

    <h1 id="mainTitle" style="color: <?= $titleColor ?>;">Victoire !</h1>

    <div class="container">
        <div class="victory-header">
            <span class="victory-emoji"><?= $emoji ?></span>
            <h2 style="color: <?= $titleColor ?>; font-size: 1.8rem; margin: 10px 0;">
                <?= $title ?>
            </h2>
        </div>

        <h3 style="text-align:center; font-family:'Bangers'; color:var(--text-dark);">Rapport de mission</h3>
        
        <ul class="player-list-final">
            <?php foreach ($players as $p): ?>
                <?php 
                    $rowClass = $p['is_undercover'] ? 'is-undercover' : 'is-civilian';
                    $statusClass = $p['is_alive'] ? 'status-alive' : 'status-dead';
                    $roleIcon = $p['is_undercover'] ? '🕵️' : '👤';
                    $roleName = $p['is_undercover'] ? 'Undercover' : 'Civil';
                ?>
                <li class="player-row <?= $rowClass ?>">
                    <div>
                        <span class="role-icon"><?= $roleIcon ?></span>
                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                        <div style="font-size:0.8rem; opacity:0.8; margin-left: 28px;"><?= $roleName ?></div>
                    </div>
                    <span class="status-badge <?= $statusClass ?>">
                        <?= $p['is_alive'] ? 'Vivant' : 'Éliminé' ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>

        <a href="../api/reset_room.php?room_id=<?= urlencode($room_id) ?>" style="text-decoration:none;">
            <button class="restart-btn">Rejouer une partie 🔄</button>
        </a>
        <a href="../index.php" style="text-decoration:none;">
            <button class="home-btn">Retour à l'accueil 🏠</button>
        </a>
    </div>

    <button id="settingsBtn" class="settings-btn" aria-label="Paramètres">
        <span class="material-symbols-rounded">settings</span>
    </button>
    <div id="settingsPanel">
        <div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div>
    </div>

    <script src="assets/js/theme-apply.js?v=<?= time() ?>"></script>
    <script>
        // Appliquer le thème stocké
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
    </script>
    <script>
        const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
        const roomId = "<?= urlencode($room_id) ?>"; 
        const playerId = "<?= $_SESSION['player_id'] ?? '' ?>";

        ws.addEventListener('open', () => {
            // On rejoint la room WebSocket pour écouter les messages
            ws.send(JSON.stringify({
                type: 'join_room',
                room_id: roomId,
                player_id: playerId
            }));
        });

        ws.addEventListener('message', (event) => {
            try {
                const msg = JSON.parse(event.data);
                
                // Si le serveur envoie un signal de reset
                if (msg.type === 'game_reset') {
                    console.log("La partie recommence ! Redirection...");
                    // Tout le monde est redirigé vers l'URL fournie (game_view.php)
                    window.location.href = "../" + msg.redirect_url;
                }
            } catch (e) {
                console.error("Erreur WS:", e);
            }
        });
    </script>
</body>
</html>