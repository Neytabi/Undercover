<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['room_id'], $_SESSION['player_id'])) {
    header('Location: /index.php');
    exit();
}

$room_id   = $_SESSION['room_id'];
$player_id = $_SESSION['player_id'];

// Récupération du code de la room et du turn_time_seconds et anonymize_votes
$stmt = $pdo->prepare("
  SELECT code, state, creator_id,
         COALESCE(turn_time_seconds, 30) AS turn_time_seconds,
         COALESCE(anonymize_votes, 0) AS anonymize_votes
  FROM rooms WHERE id = :id
");
$stmt->execute(['id' => $room_id]);
$room = $stmt->fetch();
if (!$room) die("Room introuvable");


// Redirection si la partie est déjà lancée (sécurité backup)
if ($room['state'] === 'started') {
    header("Location: game.php?room_id=$room_id&player_id=$player_id");
    exit;
}

// Récupération des joueurs
$stmt = $pdo->prepare("SELECT name, id FROM players WHERE room_id = :room_id AND is_connected = 1");
$stmt->execute(['room_id' => $room_id]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Récupération du joueur connecté
$stmt = $pdo->prepare("SELECT is_creator FROM players WHERE id = :id");
$stmt->execute(['id' => $player_id]);
$current_player = $stmt->fetch();

$is_creator = $current_player && isset($current_player['is_creator']) ? $current_player['is_creator'] : 0;

$error_message = '';
if (isset($_GET['error']) && $_GET['error'] === 'not_enough_players') {
    $error_message = "Il faut au moins 3 joueurs pour commencer.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Salle d'attente - <?= htmlspecialchars($room['code']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Luckiest+Guy&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>

<body data-theme="light">

  <!-- Bouton Retour (coller juste après <body>) -->
  <button id="backBtn" class="back-btn" aria-label="Retour à l'accueil" title="Retour à l'accueil">
    <span class="material-symbols-rounded">home</span>
  </button>

  <style>
  /* Style du bouton - discret et fixe en haut à droite */
  .back-btn {
    display: flex;
    position: fixed;
    bottom: 20px;
    right: 20px;
    background-color: var(--main-red);
    color: var(--white);
    border: none;
    border-radius: 50%;
    width: 44px;
    height: 44px;
    font-size: 1.4rem;
    font-weight: bold;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    transition: background-color 0.3s, transform 0.2s;
    z-index: 1000;
    justify-content: center;
    align-items: center;
  }
  .back-btn:hover {
    background-color: var(--dark-red);
    transform: translateY(-2px);
    color: var(--white);
  }

  /* icône Material */
  .material-symbols-rounded {
    display: inline-block;
    font-size: 24px;
    line-height: 1;
    transition: transform 0.4s ease;
    vertical-align: middle;
  }
  </style>

  <script>
  document.getElementById('backBtn').addEventListener('click', function (e) {
    e.preventDefault();

    // données de session (côté serveur injectées via PHP)
    const playerId = <?= json_encode($player_id) ?>;
    const roomId   = <?= json_encode($room_id) ?>;

    // Prépare FormData pour sendBeacon (plus compatible)
    const fd = new FormData();
    fd.append('player_id', playerId);
    fd.append('room_id', roomId);

    const url = '/api/disconnect.php';

    // Si sendBeacon disponible -> feu vert (ne bloque pas la navigation)
    if (navigator.sendBeacon) {
      navigator.sendBeacon(url, fd);
      // redirection immédiate — le beacon continue en arrière-plan
      window.location.href = '/index.php';
      return;
    }

    // fallback : fetch avec keepalive (optionnel) puis redirige
    fetch(url, {
      method: 'POST',
      body: fd,
      keepalive: true
    }).catch(() => {
      // ignore erreurs réseau, on redirige quand même
    }).finally(() => {
      window.location.href = '/index.php';
    });
  });
  </script>

  <?php if ($error_message): ?>
    <div id="error-toast" class="toast"><?= htmlspecialchars($error_message) ?></div>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const toast = document.getElementById("error-toast");
        toast.classList.add("show");
        setTimeout(() => {
          toast.classList.remove("show");
        }, 2000); // disparaît après 2 sec
      });
    </script>
  <?php endif; ?>

  <h1>Room :
    <span id="room-code" class="copy-code" data-code="<?= htmlspecialchars($room['code']) ?>" role="button" tabindex="0" aria-label="Copier le code de la room">
      <?= htmlspecialchars($room['code']) ?>
      <span class="copy-tooltip" id="copy-tooltip" aria-hidden="true">Copié !</span>
    </span>
  </h1>
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

      <?php if ($player_id == $room['creator_id']): ?>
        <form id="startGameForm" method="post" action="../api/start_game.php">
            <input type="hidden" name="room_id" value="<?= $room_id ?>">
            <!-- input envoyé aussi à start_game: pratique si start_game veut utiliser la valeur -->
            <input type="hidden" name="turn_time_seconds" id="start_turn_time_input" value="<?= (int)$room['turn_time_seconds'] ?>">
            <input type="hidden" name="anonymize_votes" id="start_anonymize_input" value="<?= (int)($room['anonymize_votes'] ?? 0) ?>">
            <input type="hidden" name="undercover_count" id="start_undercover_input" value="<?= (int)($room['undercover_count'] ?? 1) ?>">
            <button type="submit">Lancer la partie</button>
        </form>
      <?php else: ?>
        <p>En attente du créateur pour démarrer...</p>
      <?php endif; ?>
    </div>

    <div class="main-row">
      <h3>Paramètres de la partie :</h3>

      <?php if ($is_creator): ?>
        <ul class="settings-list">
          <li class="param-item">
            <div style="flex:1">
              <div style="font-weight:700">Durée du tour</div>
              <div style="font-size:0.9rem">Secondes (5–120)</div>
            </div>
            <div style="min-width:160px; text-align:right">
              <input type="number" id="turnTimeInput" min="5" max="120" step="1" value="<?= (int)$room['turn_time_seconds'] ?>">
              <div id="turnValueDisplay" style="font-size:0.85rem;margin-top:6px">Durée : <?= (int)$room['turn_time_seconds'] ?>s</div>
              <div id="saveStatus" style="font-size:0.85rem;margin-top:6px"></div>
            </div>
          </li>

          <li class="param-item">
            <div style="flex:1">
              <div style="font-weight:700">Votes anonymes</div>
              <div style="font-size:0.9rem">Les votes ne seront pas rattachés aux noms</div>
            </div>
            <div style="min-width:160px; text-align:right">
              <label style="display:inline-flex; align-items:center; gap:8px;">
                <input type="checkbox" id="anonymizeVotesCheckbox" <?= ((int)($room['anonymize_votes'] ?? 0)) ? 'checked' : '' ?>>
                <span style="font-weight:600">Activé</span>
              </label>
              <div id="anonymizeSaveStatus" style="font-size:0.85rem;margin-top:6px"></div>
            </div>
          </li>

          <li class="param-item">
            <div style="flex:1">
              <div style="font-weight:700">Nombre d'Undercover</div>
              <div style="font-size:0.9rem">Combien de joueurs seront undercover</div>
            </div>
            <div style="min-width:160px; text-align:right">
              <input type="number" id="undercoverCountInput" min="1" max="10" step="1" value="<?= (int)($room['undercover_count'] ?? 1) ?>">
              <div id="undercoverSaveStatus" style="font-size:0.85rem;margin-top:6px"></div>
            </div>
          </li>
        </ul>

      <?php else: ?>
        <!-- lecture seule pour non-créateur — même style visuel -->
        <ul class="settings-list">
          <li class="param-item">
            <div style="flex:1">
              <div style="font-weight:700">Durée du tour</div>
            </div>
            <div style="min-width:160px; text-align:right">
              <div id="turnTimeRead" class="turn-read" aria-live="polite">Durée : <?= (int)$room['turn_time_seconds'] ?>s</div>
              <p class="hint" style="margin-top:8px">Seul le créateur peut modifier ce paramètre.</p>
            </div>
          </li>

          <li class="param-item">
            <div style="flex:1">
              <div style="font-weight:700">Votes anonymes</div>
              <div style="font-size:0.9rem">Les votes ne seront pas rattachés aux noms</div>
            </div>
            <div style="min-width:160px; text-align:right">
              <div id="anonBadge" style="font-weight:600"><?= ((int)($room['anonymize_votes'] ?? 0)) ? '🔒 Activés' : '🔓 Désactivés' ?></div>
            </div>
          </li>

          <li class="param-item">
            <div style="flex:1">
              <div style="font-weight:700">Nombre d'Undercover</div>
              <div style="font-size:0.9rem">Combien de joueurs seront undercover</div>
            </div>
            <div style="min-width:160px; text-align:right">
              <div id="undercoverCountRead" style="font-weight:600"><?= (int)($room['undercover_count'] ?? 1) ?> undercover(s)</div>
            </div>
          </li>
        </ul>
      <?php endif; ?>
    </div>

  <script>
 
    const codeSpan = document.getElementById("room-code");
    const tooltip = document.getElementById("copy-tooltip");

    codeSpan.addEventListener("click", () => {
      const originalText = codeSpan.childNodes[0].nodeValue.trim(); // get only the code, not the icon or tooltip

      navigator.clipboard.writeText(originalText).then(() => {
        tooltip.classList.add("show");
        setTimeout(() => {
          tooltip.classList.remove("show");
        }, 1500);
      }).catch(err => {
        alert("Erreur de copie : " + err);
      });
    });


    const roomId = <?= json_encode($room_id) ?>;
    const playerId = <?= json_encode($player_id) ?>;
    const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);

    ws.addEventListener("open", () => {
        ws.send(JSON.stringify({
            type: "join_room",
            room_id: roomId,
            player_id: playerId  
        }));
    });

    ws.addEventListener("message", (event) => {
      const data = JSON.parse(event.data);

      if (data.type === "game_state") {
        if (data.player?.word) {
          window.location.href = `game.php?room_id=${roomId}&player_id=${playerId}`;
        }
      }

      if (data.type === "update_players") {
        const list = document.getElementById("playersList");
        list.innerHTML = "";
        data.players.forEach(p => {
          const li = document.createElement("li");
          li.textContent = p.name;
          if (p.id === <?= json_encode($room['creator_id']) ?>) {
            const badge = document.createElement("span");
            badge.className = "creator-badge";
            badge.textContent = " 👑 Créateur";
            li.appendChild(badge);
          }
          list.appendChild(li);
        });
      }

      if (data.type === 'turn_time_updated') {
        const newVal = data.turn_time_seconds;

        // 1) si l'input existe (créateur), on le met à jour sauf s'il est en train d'être édité
        const turnInput = document.getElementById('turnTimeInput');
        if (turnInput) {
          if (!turnInput.matches(':focus')) {
            turnInput.value = newVal;
          }
          // petit affichage local pour le créateur
          const tv = document.getElementById('turnValueDisplay');
          if (tv) tv.textContent = `Durée : ${newVal}s`;
          // mettre à jour le champ caché utilisé par le form de démarrage
          const startTurnInput = document.getElementById('start_turn_time_input');
          if (startTurnInput) startTurnInput.value = newVal;
        }

        // 2) pour les non-créateurs (lecture seule)
        const turnRead = document.getElementById('turnTimeRead');
        if (turnRead) {
          turnRead.textContent = `Durée : ${newVal}s`;
        }

        // 3) statut visible (créateur) — conserve l'ancien comportement
        const status = document.getElementById('saveStatus');
        if (status) status.textContent = `Durée mise à jour : ${newVal}s`;

        // 4) debug console (optionnel, utile en dev)
        // console.log('turn_time_updated reçu:', newVal);
      }

      if (data.type === 'room_settings_updated') {
        if (typeof data.anonymize_votes !== 'undefined') {
          const anon = Number(data.anonymize_votes) ? 1 : 0;
          // si le checkbox existe (créateur), on le met à jour sauf si il est en train d'être modifié
          const checkbox = document.getElementById('anonymizeVotesCheckbox');
          if (checkbox && !checkbox.matches(':focus')) {
            checkbox.checked = !!anon;
          }
          // mettre à jour badge pour non-créateurs
          const badge = document.getElementById('anonBadge');
          if (badge) badge.textContent = anon ? '🔒 Activés' : '🔓 Désactivés';
          // mise à jour du champ caché start_anonymize_input
          const startAnon = document.getElementById('start_anonymize_input');
          if (startAnon) startAnon.value = anon ? 1 : 0;
          // afficher statut temporaire
            const saveStatus = document.getElementById('anonymizeSaveStatus');
          if (saveStatus) {
            saveStatus.textContent = anon ? 'Anonymisation activée' : 'Anonymisation désactivée';
              clearTimeout(saveStatus._t);
              saveStatus._t = setTimeout(()=> saveStatus.textContent = '', 2000);
          }
        }

        if (typeof data.undercover_count !== 'undefined') {
          const uc = parseInt(data.undercover_count, 10) || 1;
          const input = document.getElementById('undercoverCountInput');
          if (input && !input.matches(':focus')) input.value = uc;
          const read = document.getElementById('undercoverCountRead');
          if (read) read.textContent = `${uc} undercover(s)`;
          const startUc = document.getElementById('start_undercover_input');
          if (startUc) startUc.value = uc;

          const ucStatus = document.getElementById('undercoverSaveStatus');
          if (ucStatus) {
            ucStatus.textContent = `Mis à jour : ${uc}`;
            clearTimeout(ucStatus._t);
            ucStatus._t = setTimeout(()=> ucStatus.textContent = '', 2000);
          }
        }
      }
    });


    // Envoie une requête WebSocket pour vérifier si le jeu a commencé
    setInterval(() => {
        ws.send(JSON.stringify({
            type: "get_game_state",
            room_id: roomId,
            player_id: playerId
        }));
    }, 3000);
  </script>

  <script>
    window.addEventListener("beforeunload", () => {
        navigator.sendBeacon("../api/disconnect.php", new URLSearchParams({
            player_id: "<?= $player_id ?>",
            room_id: "<?= $room_id ?>"
        }));
    });
  </script>
  <script>
    /*
    Autosave du turn time pour le créateur :
    - debounce pendant la saisie (800ms)
    - sauvegarde immédiate au blur
    - validation 5-120s
    - annulation des requêtes précédentes avec AbortController
    - mise à jour du champ caché start_turn_time_input pour que "Lancer la partie" prenne la valeur actuelle
    */

  (function(){
    const turnInput = document.getElementById('turnTimeInput');
    const saveStatus = document.getElementById('saveStatus');
    const startTurnInput = document.getElementById('start_turn_time_input');
    const applyBtn = document.getElementById('applyToStartBtn'); // on garde si tu veux
    if (!turnInput) return;

    let debounceTimer = null;
    let currentController = null;
    const DEBOUNCE_MS = 800;
    const MIN = 5;
    const MAX = 120;
    const roomId = <?= json_encode($room_id) ?>;

    function showStatus(text, temporary=true) {
      if (!saveStatus) return;
      saveStatus.textContent = text;
      if (temporary) {
        clearTimeout(saveStatus._t);
        saveStatus._t = setTimeout(() => { saveStatus.textContent = ''; }, 2500);
      }
    }

    async function doSaveTurnTime(value) {
      // validation locale
      const v = parseInt(value, 10);
      if (isNaN(v) || v < MIN || v > MAX) {
        showStatus(`Valeur entre ${MIN} et ${MAX} secondes.`, true);
        return false;
      }

      // abort previous
      if (currentController) {
        try { currentController.abort(); } catch(e) {}
        currentController = null;
      }
      currentController = new AbortController();
      const signal = currentController.signal;

      showStatus('Enregistrement...', false);
      try {
        const form = new URLSearchParams();
        form.append('room_id', roomId);
        form.append('turn_time_seconds', v);

        const res = await fetch('../api/save_room_setting.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString(),
          signal
        });

        // si aborted, on sort
        if (signal.aborted) return false;

        const data = await res.json();
        if (data && data.success) {
          showStatus('Enregistré ✅', true);
          // mettre à jour le champ caché utilisé par startGameForm
          if (startTurnInput) startTurnInput.value = v;
          // Optionnel : mettre à jour l'affichage local immédiat (pour le créateur)
          const tv = document.getElementById('turnValueDisplay');
          if (tv) tv.textContent = `Durée : ${v}s`;
          return true;
        } else {
          showStatus('Erreur: ' + (data.error || 'inconnue'), true);
          return false;
        }
      } catch (err) {
        if (err.name === 'AbortError') {
          // requête annulée volontairement -> pas d'UI
          return false;
        }
        console.error('Erreur save turn:', err);
        showStatus('Erreur réseau', true);
        return false;
      } finally {
        currentController = null;
      }
    }

    // Debounced handler
    function scheduleSave() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        doSaveTurnTime(turnInput.value);
      }, DEBOUNCE_MS);
    }

    // Events
    turnInput.addEventListener('input', () => {
      // update small local preview
      const v = parseInt(turnInput.value, 10);
      const tv = document.getElementById('turnValueDisplay');
      if (tv && !isNaN(v)) tv.textContent = `Durée : ${v}s`;

      scheduleSave();
    });

    // save immediately on blur
    turnInput.addEventListener('blur', () => {
      clearTimeout(debounceTimer);
      doSaveTurnTime(turnInput.value);
    });

    // keyboard "Enter" -> save immediately and remove focus
    turnInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        turnInput.blur(); // triggers blur handler -> save
      }
    });

    // applyToStart button keeps behavior (sets start input)
    if (applyBtn) {
      applyBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const v = parseInt(turnInput.value, 10) || 30;
        if (startTurnInput) startTurnInput.value = v;
        showStatus('Valeur appliquée au démarrage.');
      });
    }

    // initial display
    (function initDisplay() {
      const tv = document.getElementById('turnValueDisplay');
      const v = parseInt(turnInput.value, 10) || '';
      if (tv && v) tv.textContent = `Durée : ${v}s`;
    })();
  })();
  </script>
  <script>
  (function(){
    const checkbox = document.getElementById('anonymizeVotesCheckbox');
    const saveStatus = document.getElementById('anonymizeSaveStatus');
    const startAnonInput = document.getElementById('start_anonymize_input');
    if (!checkbox) return;

    let currentController = null;

    function showStatus(text, temporary=true){
      if (!saveStatus) return;
      saveStatus.textContent = text;
      if (temporary){
        clearTimeout(saveStatus._t);
        saveStatus._t = setTimeout(()=> saveStatus.textContent = '', 2500);
      }
    }

    async function saveAnonymize(value){
      // abort previous
      if (currentController){
        try { currentController.abort(); } catch(e){}
        currentController = null;
      }
      currentController = new AbortController();
      const signal = currentController.signal;

      showStatus('Enregistrement...', false);
      try {
        const form = new URLSearchParams();
        form.append('room_id', <?= json_encode($room_id) ?>);
        form.append('anonymize_votes', value ? 1 : 0);

        const res = await fetch('../api/save_room_setting.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString(),
          signal
        });

        if (signal.aborted) return false;
        const data = await res.json();
        if (data && data.success){
          showStatus('Enregistré ✅', true);
          if (startAnonInput) startAnonInput.value = value ? 1 : 0;
          return true;
        } else {
          showStatus('Erreur: ' + (data.error || 'inconnue'), true);
          return false;
        }
      } catch (err) {
        if (err.name === 'AbortError') return false;
        console.error('Erreur save anonymize:', err);
        showStatus('Erreur réseau', true);
        return false;
      } finally {
        currentController = null;
      }
    }

    // save on change
    checkbox.addEventListener('change', () => {
      saveAnonymize(checkbox.checked);
    });

  })();
  </script>
  <script>
  (function(){
    const ucInput = document.getElementById('undercoverCountInput');
    const ucStatus = document.getElementById('undercoverSaveStatus');
    const startUcInput = document.getElementById('start_undercover_input');
    if (!ucInput) return;

    let debounceTimer = null;
    let currentController = null;
    const DEBOUNCE_MS = 800;
    const MIN = 1;
    const MAX = 10; // front-limit; server valide plus strictement à partir du nombre de joueurs
    const roomId = <?= json_encode($room_id) ?>;

    function showUCStatus(text, temporary=true) {
      if (!ucStatus) return;
      ucStatus.textContent = text;
      if (temporary) {
        clearTimeout(ucStatus._t);
        ucStatus._t = setTimeout(() => { ucStatus.textContent = ''; }, 2500);
      }
    }

    async function doSaveUndercoverCount(value){
      const v = parseInt(value, 10);
      if (isNaN(v) || v < MIN || v > MAX) {
        showUCStatus(`Valeur entre ${MIN} et ${MAX}.`, true);
        return false;
      }

      if (currentController) {
        try { currentController.abort(); } catch(e) {}
        currentController = null;
      }
      currentController = new AbortController();
      const signal = currentController.signal;

      showUCStatus('Enregistrement...', false);
      try {
        const form = new URLSearchParams();
        form.append('room_id', roomId);
        form.append('undercover_count', v);

        const res = await fetch('../api/save_room_setting.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString(),
          signal
        });

        if (signal.aborted) return false;
        const data = await res.json();
        if (data && data.success) {
          showUCStatus('Enregistré ✅', true);
          if (startUcInput) startUcInput.value = v;
          return true;
        } else {
          showUCStatus('Erreur: ' + (data.error || 'inconnue'), true);
          return false;
        }
      } catch (err) {
        if (err.name === 'AbortError') return false;
        console.error('Erreur save undercover:', err);
        showUCStatus('Erreur réseau', true);
        return false;
      } finally {
        currentController = null;
      }
    }

    function scheduleSave() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        doSaveUndercoverCount(ucInput.value);
      }, DEBOUNCE_MS);
    }

    ucInput.addEventListener('input', () => {
      scheduleSave();
    });

    ucInput.addEventListener('blur', () => {
      clearTimeout(debounceTimer);
      doSaveUndercoverCount(ucInput.value);
    });

    ucInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        ucInput.blur();
      }
    });

  })();
  </script>

  <script src="assets/js/theme-apply.js"></script>

  <!-- Bouton avec rotation -->
  <button id="settingsBtn" class="settings-btn" aria-label="Paramètres">
    <span class="material-symbols-rounded">settings</span>
  </button>

  <!-- Panneau dépliable -->
  <div id="settingsPanel">
    <div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div>
  </div>
  <script>
    (function() {
      const codeSpan = document.getElementById('room-code');
      const tooltip = document.getElementById('copy-tooltip');
      if (!codeSpan) return;

      function showTooltip(ms = 1500) {
        if (!tooltip) return;
        tooltip.classList.add('show');
        clearTimeout(tooltip._t);
        tooltip._t = setTimeout(() => tooltip.classList.remove('show'), ms);
      }

      async function copyText(text) {
        // Clipboard API (secure contexts)
        if (navigator.clipboard && navigator.clipboard.writeText) {
          try {
            await navigator.clipboard.writeText(text);
            return true;
          } catch (e) {
            // fallback below
          }
        }

        // Fallback : execCommand copy via textarea
        try {
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed'; // évite le scroll
          ta.style.left = '-9999px';
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          const ok = document.execCommand && document.execCommand('copy');
          document.body.removeChild(ta);
          return !!ok;
        } catch (e) {
          return false;
        }
      }

      async function handleCopy() {
        const code = codeSpan.dataset.code || codeSpan.textContent.trim();
        if (!code) return;

        const ok = await copyText(code);
        if (ok) {
          showTooltip();
        } else {
          // dernier recours : afficher prompt pour que l'utilisateur copie manuellement
          const fallback = window.prompt('Copiez le code de la room :', code);
          if (fallback !== null) showTooltip();
        }
      }

      // clic + support clavier (Enter / Space)
      codeSpan.addEventListener('click', handleCopy);
      codeSpan.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          handleCopy();
        }
      });
    })();
  </script>

</body>
</html>