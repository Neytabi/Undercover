<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1. Vérification Session
if (!isset($_SESSION['room_id'], $_SESSION['player_id'])) {
    header('Location: /index.php');
    exit();
}

$room_id   = $_SESSION['room_id'];
$player_id = $_SESSION['player_id'];

// 2. Récupération Données Room
$stmt = $pdo->prepare("
    SELECT id, code, state, creator_id,
           COALESCE(turn_time_seconds, 30) AS turn_time_seconds,
           COALESCE(anonymize_votes, 0) AS anonymize_votes,
           COALESCE(undercover_count, 1) AS undercover_count
    FROM rooms WHERE id = :id
");
$stmt->execute(['id' => $room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) die("Room introuvable");

// 3. Redirection si déjà commencé
if ($room['state'] === 'started') {
    header("Location: game.php?room_id=$room_id&player_id=$player_id");
    exit;
}

// 4. Liste des joueurs
$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = :room_id AND is_connected = 1");
$stmt->execute(['room_id' => $room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_creator = ($player_id == $room['creator_id']);
$error_message = (isset($_GET['error']) && $_GET['error'] === 'not_enough_players') ? "Il faut au moins 3 joueurs pour commencer." : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>QG - Room <?= htmlspecialchars($room['code']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&family=Rubik:wght@500&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">
  <!-- Animation Transition -->
  <div id="page-transition-overlay">
    <div class="transition-panel top"></div>
    <div class="transition-message">MISSION START !</div>
    <div class="transition-panel bottom"></div>
  </div>

  <h1 class="main-title">Salle d'attente</h1>

  <div class="container">
    <div style="text-align:center; margin-bottom:20px;">
      <span style="font-size:1.2rem; color:var(--text-dark);">CODE SECRET :</span>
      <div id="room-code" class="copy-code" data-code="<?= htmlspecialchars($room['code']) ?>" 
           style="font-size:2.5rem; font-family:'Bangers'; letter-spacing:3px; margin-top:5px; display:inline-block;">
        <?= htmlspecialchars($room['code']) ?>
      </div>
      <div id="copy-tooltip" class="copy-tooltip">Copié !</div>
    </div>

    <h3>Agents présents (<span id="playerCount"><?= count($players) ?></span>) :</h3>
    <ul id="playersList">
      <?php foreach ($players as $p): ?>
        <li>
          <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
          <?php if ($p['id'] == $room['creator_id']): ?>
            <span class="creator-badge">👑 Chef de mission</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($is_creator): ?>
      <form id="startGameForm" method="post" action="../api/start_game.php">
          <input type="hidden" name="room_id" value="<?= $room_id ?>">
          <input type="hidden" name="turn_time_seconds" id="hidden_turn_time" value="<?= (int)($room['turn_time_seconds'] ?? 30) ?>">
          <input type="hidden" name="anonymize_votes" id="hidden_anonymize" value="<?= (int)($room['anonymize_votes'] ?? 0) ?>">
          <input type="hidden" name="undercover_count" id="hidden_undercover" value="<?= (int)($room['undercover_count'] ?? 1) ?>">
          <button type="submit" class="action-btn">LANCER LA MISSION 🚀</button>
      </form>
    <?php else: ?>
      <div class="waiting-screen">
        <span class="material-symbols-rounded waiting-icon" style="font-size:3rem;">hourglass_top</span>
        <p>En attente du chef de mission...</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Paramètres (Même logique, design unifié) -->
  <div class="main-row">
    <h3>Configuration de la mission</h3>
    <!-- (Le code HTML des paramètres reste identique mais bénéficie du CSS global) -->
    <!-- ... Copier/Coller les <ul> settings de ton ancien fichier ... -->
    <?php if ($is_creator): ?>
      <ul class="settings-list">
        <li class="param-item">
            <span>⏱️ Durée tour</span>
            <input type="number" id="input_turn_time" min="5" max="120" value="<?= (int)($room['turn_time_seconds']??30) ?>" style="width:70px;">
        </li>
        <li class="param-item">
            <span>🕵️ Undercovers</span>
            <input type="number" id="input_undercover" min="1" max="5" value="<?= (int)($room['undercover_count']??1) ?>" style="width:70px;">
        </li>
        <li class="param-item">
            <span>🔒 Votes anonymes</span>
            <input type="checkbox" id="input_anonymize" <?= ($room['anonymize_votes']??0) ? 'checked' : '' ?>>
        </li>
      </ul>
    <?php else: ?>
      <ul class="settings-list">
         <li class="param-item">⏱️ Durée : <span id="read_turn_time"><?= (int)($room['turn_time_seconds']??30) ?>s</span></li>
         <li class="param-item">🕵️ Undercovers : <span id="read_undercover"><?= (int)($room['undercover_count']??1) ?></span></li>
         <li class="param-item">🔒 Votes : <span id="read_anonymize"><?= ($room['anonymize_votes']??0) ? 'Secrets' : 'Publics' ?></span></li>
      </ul>
    <?php endif; ?>
  </div>

  <button id="backBtn" class="back-btn"><span class="material-symbols-rounded">logout</span></button>
  <button id="settingsBtn" class="settings-btn"><span class="material-symbols-rounded">settings</span></button>
  <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Mode Sombre</div></div>

  <!-- Script (Garde ton logique existante, juste le CSS fait le travail) -->
  <script src="assets/js/theme-apply.js"></script>
  <!-- ... Inclure le script JS existant pour Websocket ... -->
  <script>
          document.addEventListener("DOMContentLoaded", () => {
        // --- Variables Globales ---
        const roomId = <?= json_encode($room_id) ?>;
        const playerId = <?= json_encode($player_id) ?>;
        const creatorId = <?= json_encode($room['creator_id']) ?>;
        const wsProtocol = location.protocol === 'https:' ? 'wss' : 'ws';
        const wsUrl = `${wsProtocol}://${location.host}/ws/`;
        let isTransitioning = false;
        
        // --- 1. Gestion WebSocket & Animation ---
        const ws = new WebSocket(wsUrl);

        function startTransitionAndRedirect(url) {
            if (isTransitioning) return;
            isTransitioning = true;
            document.body.style.pointerEvents = 'none'; // Bloque les clics
            
            const overlay = document.getElementById('page-transition-overlay');
            if (overlay) overlay.classList.add('transition-active');
            
            setTimeout(() => { window.location.href = url; }, 1000);
        }

        ws.onopen = () => {
            ws.send(JSON.stringify({ type: "join_room", room_id: roomId, player_id: playerId }));
        };

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                const gameUrl = `game.php?room_id=${roomId}&player_id=${playerId}`;

                // Démarrage du jeu (Signal WS ou Polling)
                if (data.type === "start_game") startTransitionAndRedirect(gameUrl);
                if (data.type === "game_state" && data.room_state === 'started') startTransitionAndRedirect(gameUrl);

                // Mise à jour liste joueurs
                if (data.type === "update_players") updatePlayersList(data.players);

                // Mise à jour paramètres (Lecture seule pour non-créateurs)
                if (data.type === 'turn_time_updated') updateReadValue('read_turn_time', data.turn_time_seconds + 's');
                if (data.type === 'room_settings_updated') {
                    if (data.anonymize_votes !== undefined) {
                        updateReadValue('read_anonymize', data.anonymize_votes ? '🔒 Anonymes' : '🔓 Publics');
                    }
                    if (data.undercover_count !== undefined) {
                        updateReadValue('read_undercover', data.undercover_count);
                    }
                }
            } catch (e) { console.error("WS Error", e); }
        };

        // Fonction helper UI
        function updatePlayersList(players) {
            const list = document.getElementById("playersList");
            if (!list) return;
            list.innerHTML = players.map(p => `
                <li>
                    <span class="player-name">${escapeHtml(p.name)}</span>
                    ${p.id == creatorId ? '<span class="creator-badge">👑 Créateur</span>' : ''}
                </li>
            `).join('');
        }

        function updateReadValue(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        function escapeHtml(text) {
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // --- 2. Logique Créateur (Start & Autosave) ---
        const startForm = document.getElementById('startGameForm');
        if (startForm) {
            // Lancement du jeu
            startForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(startForm);
                try {
                    const res = await fetch('../api/start_game.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        // FIX: Départ immédiat sans attendre le WS pour le créateur
                        startTransitionAndRedirect(`game.php?room_id=${roomId}&player_id=${playerId}`);
                    } else {
                        alert(data.message || "Erreur lancement");
                    }
                } catch (err) { console.error(err); alert("Erreur connexion"); }
            });

            // Fonction générique d'autosave
            function setupAutoSave(inputId, hiddenId, paramKey, isCheckbox = false) {
                const input = document.getElementById(inputId);
                const hidden = document.getElementById(hiddenId);
                const status = document.getElementById('status_turn_time'); // Peut être partagé

                if (!input) return;

                const save = async () => {
                    const val = isCheckbox ? (input.checked ? 1 : 0) : input.value;
                    // Mise à jour locale du champ caché pour le formulaire start
                    if (hidden) hidden.value = val;

                    if (status) status.textContent = 'Enregistrement...';

                    // Envoi serveur
                    const form = new URLSearchParams({ room_id: roomId });
                    form.append(paramKey, val);
                    
                    try {
                       await fetch('../api/save_room_setting.php', { method: 'POST', body: form });
                       if (status) {
                           status.textContent = 'Enregistré';
                           setTimeout(() => status.textContent = '', 2000);
                       }
                    } catch (e) { console.error("Save error", e); }
                };

                input.addEventListener('change', save);
                if (!isCheckbox) input.addEventListener('blur', save); // Sauvegarde aussi quand on quitte le champ texte
            }

            // Initialisation des autosaves
            setupAutoSave('input_turn_time', 'hidden_turn_time', 'turn_time_seconds');
            setupAutoSave('input_anonymize', 'hidden_anonymize', 'anonymize_votes', true);
            setupAutoSave('input_undercover', 'hidden_undercover', 'undercover_count');
        }

        // --- 3. Utilitaires (Copie, Retour, Polling) ---
        
        // Copie Code Room
        const codeSpan = document.getElementById('room-code');
        if (codeSpan) {
            codeSpan.addEventListener('click', () => {
                const code = codeSpan.dataset.code;
                navigator.clipboard.writeText(code).then(() => {
                    const tip = document.getElementById('copy-tooltip');
                    tip.classList.add('show');
                    setTimeout(() => tip.classList.remove('show'), 1500);
                });
            });
        }

        // Bouton Retour (Déconnexion propre)
        document.getElementById('backBtn')?.addEventListener('click', () => {
            if (navigator.sendBeacon) {
                const fd = new FormData();
                fd.append('player_id', playerId);
                fd.append('room_id', roomId);
                navigator.sendBeacon('/api/disconnect.php', fd);
            } else {
                fetch('/api/disconnect.php', { method: 'POST', body: new URLSearchParams({player_id: playerId, room_id: roomId}), keepalive: true });
            }
            window.location.href = '/index.php';
        });

        // Polling de sécurité (toutes les 3s)
        setInterval(() => {
            if(!isTransitioning && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: "get_game_state", room_id: roomId, player_id: playerId }));
            }
        }, 3000);
    });
  </script>
</body>
</html>