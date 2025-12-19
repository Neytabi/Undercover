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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Salle d'attente - <?= htmlspecialchars($room['code']) ?></title>
  
  <!-- Polices -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&family=Rubik:wght@400;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  
  <!-- CSS (Force reload avec timestamp) -->
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>

<body data-theme="light">

  <!-- Animation Overlay -->
  <div id="page-transition-overlay">
    <div class="transition-panel top"></div>
    <div class="transition-message">MISSION START !</div>
    <div class="transition-panel bottom"></div>
  </div>

  <!-- Bouton Retour -->
  <button id="backBtn" class="back-btn" aria-label="Retour à l'accueil">
    <span class="material-symbols-rounded">home</span>
  </button>
  <style>
    /* Style spécifique bouton retour */
    .back-btn { display: flex; position: fixed; bottom: 20px; right: 20px; background-color: var(--main-red); color: var(--white); border: none; border-radius: 50%; width: 44px; height: 44px; font-size: 1.4rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); z-index: 1000; justify-content: center; align-items: center; transition: background-color 0.3s, transform 0.2s; }
    .back-btn:hover { background-color: var(--dark-red); transform: translateY(-2px); }
  </style>

  <!-- Toast Erreur -->
  <?php if ($error_message): ?>
    <div id="error-toast" class="toast show"><?= htmlspecialchars($error_message) ?></div>
    <script>setTimeout(() => document.getElementById("error-toast")?.classList.remove("show"), 3000);</script>
  <?php endif; ?>

  <!-- Titre Room -->
  <h1>Room :
    <span id="room-code" class="copy-code" data-code="<?= htmlspecialchars($room['code']) ?>" role="button" tabindex="0" title="Cliquer pour copier">
      <?= htmlspecialchars($room['code']) ?>
      <span class="copy-tooltip" id="copy-tooltip">Copié !</span>
    </span>
  </h1>

  <!-- Liste Joueurs -->
  <div class="container">
    <h3>Joueurs :</h3>
    <ul id="playersList">
      <?php foreach ($players as $p): ?>
        <li>
          <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
          <?php if ($p['id'] == $room['creator_id']): ?>
            <span class="creator-badge">👑 Créateur</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Bouton Start (Créateur uniquement) -->
    <?php if ($is_creator): ?>
      <form id="startGameForm" method="post" action="../api/start_game.php">
          <input type="hidden" name="room_id" value="<?= $room_id ?>">
          <!-- Les champs hidden seront mis à jour par le JS lors des modifications des inputs -->
          <input type="hidden" name="turn_time_seconds" id="hidden_turn_time" value="<?= (int)$room['turn_time_seconds'] ?>">
          <input type="hidden" name="anonymize_votes" id="hidden_anonymize" value="<?= (int)$room['anonymize_votes'] ?>">
          <input type="hidden" name="undercover_count" id="hidden_undercover" value="<?= (int)$room['undercover_count'] ?>">
          <button type="submit">Lancer la partie</button>
      </form>
    <?php else: ?>
      <p style="text-align:center; opacity:0.8;">En attente du créateur pour démarrer...</p>
    <?php endif; ?>
  </div>

  <!-- Paramètres -->
  <div class="main-row">
    <h3>Paramètres de la partie :</h3>
    
    <?php if ($is_creator): ?>
      <!-- Mode Édition -->
      <ul class="settings-list">
        <li class="param-item">
          <div style="flex:1">
            <div style="font-weight:700">Durée du tour</div>
            <div style="font-size:0.85rem; opacity:0.8">Secondes (5–120)</div>
          </div>
          <div style="text-align:right">
            <input type="number" id="input_turn_time" min="5" max="120" value="<?= (int)$room['turn_time_seconds'] ?>" style="width:70px; text-align:center;">
            <div class="save-status" id="status_turn_time" style="font-size:0.75rem; color:#666; height:1rem;"></div>
          </div>
        </li>

        <li class="param-item">
          <div style="flex:1">
            <div style="font-weight:700">Votes anonymes</div>
          </div>
          <input type="checkbox" id="input_anonymize" <?= ((int)$room['anonymize_votes']) ? 'checked' : '' ?>>
        </li>

        <li class="param-item">
          <div style="flex:1">
            <div style="font-weight:700">Undercover(s)</div>
          </div>
          <input type="number" id="input_undercover" min="1" max="10" value="<?= (int)$room['undercover_count'] ?>" style="width:70px; text-align:center;">
        </li>
      </ul>

    <?php else: ?>
      <!-- Mode Lecture -->
      <ul class="settings-list">
        <li class="param-item">
          <span>Durée du tour :</span>
          <span id="read_turn_time" style="font-weight:bold"><?= (int)$room['turn_time_seconds'] ?>s</span>
        </li>
        <li class="param-item">
          <span>Votes :</span>
          <span id="read_anonymize" style="font-weight:bold"><?= ((int)$room['anonymize_votes']) ? '🔒 Anonymes' : '🔓 Publics' ?></span>
        </li>
        <li class="param-item">
          <span>Undercover(s) :</span>
          <span id="read_undercover" style="font-weight:bold"><?= (int)$room['undercover_count'] ?></span>
        </li>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Scripts Unifiés -->
  <script src="assets/js/theme-apply.js"></script>
  
  <!-- Boutons Flottants Settings / Theme -->
  <button id="settingsBtn" class="settings-btn" aria-label="Paramètres"><span class="material-symbols-rounded">settings</span></button>
  <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div></div>

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
            
            setTimeout(() => { window.location.href = url; }, 600);
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