<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['room_id'], $_SESSION['player_id'])) {
    header('Location: /index.php');
    exit();
}

$room_id   = $_SESSION['room_id'];
$player_id = $_SESSION['player_id'];

$stmt = $pdo->prepare("SELECT id, code, state, creator_id, turn_time_seconds, anonymize_votes, undercover_count FROM rooms WHERE id = :id");
$stmt->execute(['id' => $room_id]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) die("Mission introuvable.");
if ($room['state'] === 'started') {
    header("Location: game.php?room_id=$room_id&player_id=$player_id");
    exit;
}

$stmt = $pdo->prepare("SELECT id, name FROM players WHERE room_id = :room_id AND is_connected = 1");
$stmt->execute(['room_id' => $room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_creator = ($player_id == $room['creator_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Briefing - <?= htmlspecialchars($room['code']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&family=Rubik:wght@400;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">
  <div id="page-transition-overlay">
    <div class="transition-panel top"></div>
    <div class="transition-message">MISSION START !</div>
    <div class="transition-panel bottom"></div>
  </div>

  <h1 class="main-title">Briefing de Mission</h1>

  <div class="container">
    <div class="code-display" style="text-align:center; margin-bottom:20px;">
      <span style="font-size:1.2rem; opacity:0.8;">CODE D'ACCÈS :</span>
      <div id="room-code" class="copy-code" data-code="<?= htmlspecialchars($room['code']) ?>" 
           style="display:block; font-size:2.5rem; font-family:'Bangers'; letter-spacing:3px; margin-top:5px; cursor:pointer;">
        <?= htmlspecialchars($room['code']) ?>
        <span class="copy-tooltip" id="copy-tooltip">Copié !</span>
      </div>
    </div>

    <h3>Agents en attente (<span id="pCount"><?= count($players) ?></span>)</h3>
    <ul id="playersList" class="styled-list">
      <?php foreach ($players as $p): ?>
        <li>
          <span class="material-symbols-rounded">person</span>
          <span class="player-name"><?= htmlspecialchars($p['name']) ?></span>
          <?php if ($p['id'] == $room['creator_id']): ?>
            <span class="creator-badge">👑 Chef</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($is_creator): ?>
      <form id="startGameForm" method="post" action="../api/start_game.php">
          <input type="hidden" name="room_id" value="<?= $room_id ?>">
          <input type="hidden" name="turn_time_seconds" id="hidden_turn_time" value="<?= (int)$room['turn_time_seconds'] ?>">
          <input type="hidden" name="anonymize_votes" id="hidden_anonymize" value="<?= (int)$room['anonymize_votes'] ?>">
          <input type="hidden" name="undercover_count" id="hidden_undercover" value="<?= (int)$room['undercover_count'] ?>">
          <button type="submit" class="action-btn">LANCER L'INFILTRATION 🚀</button>
      </form>
    <?php else: ?>
      <div class="waiting-box" style="text-align:center; padding:20px; opacity:0.7;">
        <span class="material-symbols-rounded spin" style="font-size:3rem;">hourglass_top</span>
        <p>Le chef de mission prépare le matériel...</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="main-row settings-card">
    <h3>Configuration</h3>
    <?php if ($is_creator): ?>
      <ul class="settings-list">
        <li class="param-item">
          <label>⏱ Durée du tour</label>
          <input type="number" id="input_turn_time" min="5" max="120" value="<?= (int)$room['turn_time_seconds'] ?>">
        </li>
        <li class="param-item">
          <label>🕵️ Undercovers</label>
          <input type="number" id="input_undercover" min="1" max="10" value="<?= (int)$room['undercover_count'] ?>">
        </li>
        <li class="param-item">
          <label>🔒 Anonymat</label>
          <input type="checkbox" id="input_anonymize" <?= ((int)$room['anonymize_votes']) ? 'checked' : '' ?>>
        </li>
      </ul>
    <?php else: ?>
      <ul class="settings-list read-only">
        <li class="param-item">
            <span>⏱️ Temps :</span> 
            <strong id="read_turn_time"><?= (int)$room['turn_time_seconds'] ?>s</strong>
        </li>
        <li class="param-item">
            <span>🕵️ Intrus :</span> 
            <strong id="read_undercover"><?= (int)$room['undercover_count'] ?></strong>
        </li>
        <li class="param-item">
            <span>🔒 Votes :</span> 
            <strong id="read_anonymize"><?= ((int)$room['anonymize_votes']) ? 'Secrets' : 'Publics' ?></strong>
        </li>
      </ul>
    <?php endif; ?>
  </div>

  <!-- Boutons flottants -->
  <button id="backBtn" class="back-btn"><span class="material-symbols-rounded">logout</span></button>
  <button id="rulesBtn" class="help-btn"><span class="material-symbols-rounded">menu_book</span></button>
  <button id="settingsBtn" class="settings-btn"><span class="material-symbols-rounded">settings</span></button>
  <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Mode Sombre</div></div>

  <!-- Modal Règles -->
  <div id="rulesModal" class="modal">
    <div class="modal-content">
      <span class="close" id="closeRules">&times;</span>
      <h2>🎯 Briefing de Mission</h2>
      
      <div class="rule-block">
        <h3>🕵️ Civil vs Undercover</h3>
        <p>Les <strong>Civils</strong> ont le même mot. L'<strong>Undercover</strong> a un mot légèrement différent.</p>
      </div>
      <div class="rule-block">
        <h3>🗣️ Déroulement</h3>
        <p>Chacun son tour donne un indice. Après le tour, débattez et votez pour éliminer l'intrus !</p>
      </div>
    </div>
  </div>

  <script src="assets/js/theme-apply.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
        const roomId = <?= json_encode($room_id) ?>;
        const playerId = <?= json_encode($player_id) ?>;
        const creatorId = <?= json_encode($room['creator_id']) ?>;
        const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
        let transitioning = false;

        function redirect(url) {
            if (transitioning) return; transitioning = true;
            document.getElementById('page-transition-overlay').classList.add('transition-active');
            setTimeout(() => { window.location.href = url; }, 600);
        }

        ws.onopen = () => ws.send(JSON.stringify({ type: "join_room", room_id: roomId, player_id: playerId }));
        
        ws.onmessage = (e) => {
            const data = JSON.parse(e.data);
            
            // 1. Démarrage
            if (data.type === "start_game") redirect(`game.php?room_id=${roomId}&player_id=${playerId}`);
            
            // 2. Mise à jour joueurs
            if (data.type === "update_players") {
                const list = document.getElementById("playersList");
                document.getElementById("pCount").textContent = data.players.length;
                list.innerHTML = data.players.map(p => `
                    <li>
                        <span class="material-symbols-rounded">person</span>
                        <span class="player-name">${escapeHtml(p.name)}</span>
                        ${p.id == creatorId ? '<span class="creator-badge">👑 Chef</span>' : ''}
                    </li>
                `).join('');
            }

            // 3. Mise à jour Paramètres (POUR LES NON-CRÉATEURS)
            if (data.type === 'turn_time_updated') {
                updateRead('read_turn_time', data.turn_time_seconds + 's');
            }
            if (data.type === 'room_settings_updated') {
                if (data.anonymize_votes !== undefined) {
                    updateRead('read_anonymize', data.anonymize_votes ? 'Secrets' : 'Publics');
                }
                if (data.undercover_count !== undefined) {
                    updateRead('read_undercover', data.undercover_count);
                }
            }
        };

        function updateRead(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        function escapeHtml(text) {
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Copie Code
        document.getElementById('room-code').onclick = function() {
            navigator.clipboard.writeText(this.dataset.code).then(() => {
                const tip = document.getElementById('copy-tooltip');
                tip.classList.add('show');
                setTimeout(() => tip.classList.remove('show'), 1500);
            });
        };

        // Lancement (Créateur)
        document.getElementById('startGameForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const res = await fetch('../api/start_game.php', { method: 'POST', body: new FormData(e.target) });
            const data = await res.json();
            if (data.success) redirect(`game.php?room_id=${roomId}&player_id=${playerId}`);
        });

        // Autosave (Créateur)
        function setupSave(id, hidden, key, isCheck = false) {
            const input = document.getElementById(id);
            if (!input) return;
            input.onchange = () => {
                const val = isCheck ? (input.checked ? 1 : 0) : input.value;
                const hiddenInput = document.getElementById(hidden);
                if(hiddenInput) hiddenInput.value = val;
                
                fetch('../api/save_room_setting.php', { 
                    method: 'POST', 
                    body: new URLSearchParams({ room_id: roomId, [key]: val }) 
                });
            };
        }
        setupSave('input_turn_time', 'hidden_turn_time', 'turn_time_seconds');
        setupSave('input_undercover', 'hidden_undercover', 'undercover_count');
        setupSave('input_anonymize', 'hidden_anonymize', 'anonymize_votes', true);

        // Bouton Retour
        document.getElementById('backBtn').onclick = () => {
            navigator.sendBeacon('/api/disconnect.php', new URLSearchParams({player_id: playerId, room_id: roomId}));
            window.location.href = '/index.php';
        };

        // Modal Règles
        const modal = document.getElementById('rulesModal');
        const btnRules = document.getElementById('rulesBtn');
        const spanClose = document.getElementById('closeRules');

        if(btnRules) btnRules.onclick = () => modal.style.display = "flex";
        if(spanClose) spanClose.onclick = () => modal.style.display = "none";
        window.onclick = (e) => { if (e.target == modal) modal.style.display = "none"; };
    });
  </script>
</body>
</html>