<?php 
session_start();
require_once __DIR__ . '/../includes/db.php';

// 1. Récupération & Sécurité Session
$player_id = $_GET['player_id'] ?? ($_SESSION['player_id'] ?? null);
if (!$player_id) die("Player ID manquant.");

// 2. Infos Joueur & Room
$stmt = $pdo->prepare("
    SELECT p.*, r.turn_time_seconds 
    FROM players p 
    JOIN rooms r ON p.room_id = r.id 
    WHERE p.id = ?
");
$stmt->execute([$player_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die("Joueur introuvable.");

// Mise à jour session
$_SESSION['player_id'] = $player_id;
$_SESSION['room_id'] = $data['room_id'];

$room_id = $data['room_id'];
$turn_time_seconds = (int)($data['turn_time_seconds'] ?? 30);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Undercover - Partie</title>
    
    <!-- Polices & Icones -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&family=Rubik:wght@400;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    
    <!-- CSS (Force reload) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <!-- Animation Overlay (CONFIDENTIEL) -->
    <div id="page-transition-overlay" class="transition-active">
        <div class="transition-panel top"></div>
        <div class="transition-message">CONFIDENTIEL</div>
        <div class="transition-panel bottom"></div>
    </div>

    <!-- Titre -->
    <h1 id="gameTitle">Mission en cours</h1>

    <!-- Conteneur Principal (Mode JEU = Plus large) -->
    <div class="container game-mode" id="mainContainer">
        
        <!-- Timer Bar (intégré dans le container pour alignement parfait) -->
        <div id="timerContainer">
            <div id="timerBar"></div>
        </div>

        <!-- Zone de contenu dynamique -->
        <div id="gameContent">
            <p style="text-align:center; opacity:0.7;">Connexion au QG...</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/theme-apply.js"></script>
    
    <!-- Boutons Flottants -->
    <button id="settingsBtn" class="settings-btn" aria-label="Paramètres"><span class="material-symbols-rounded">settings</span></button>
    <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div></div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // --- Variables ---
        const roomId = <?= json_encode($room_id) ?>;
        const playerId = <?= json_encode($player_id) ?>;
        const TURN_DURATION = <?= $turn_time_seconds ?>;
        const wsUrl = `${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`;
        let turnTimer = null;
        let ws = null;

        // --- 1. Animation Ouverture ---
        setTimeout(() => {
            const overlay = document.getElementById('page-transition-overlay');
            if(overlay) overlay.classList.remove('transition-active');
        }, 1500); // 1.5s pour lire "CONFIDENTIEL"

        // --- 2. WebSocket ---
        function connectWS() {
            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                console.log("WS Connecté");
                ws.send(JSON.stringify({ type: 'join_room', room_id: roomId, player_id: playerId }));
            };

            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                
                // Routage des messages
                switch(data.type) {
                    case 'info':
                        if (data.message.includes('Rejoint')) fetchGameState();
                        break;
                    case 'game_state':
                        renderGame(data);
                        break;
                    case 'new_hint':
                        updateSingleHint(data.player_id, data.hint);
                        break;
                    case 'update_turn':
                        // Petit délai pour l'UX avant de rafraîchir
                        setTimeout(fetchGameState, 500);
                        break;
                    case 'your_turn':
                        notifyTurn();
                        break;
                    case 'start_voting':
                        window.location.href = `vote.php?room_id=${roomId}`;
                        break;
                }
            };

            ws.onclose = () => {
                console.warn("WS Déconnecté, reconnexion...");
                setTimeout(connectWS, 3000);
            };
        }

        function fetchGameState() {
            if(ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'get_game_state', room_id: roomId, player_id: playerId }));
            }
        }

        // --- 3. Rendu du Jeu (Core Logic) ---
        function renderGame(state) {
            const player = state.player;
            const players = state.players;
            const hints = state.hint_map;
            const yourTurn = state.you_are_turn;

            const contentDiv = document.getElementById('gameContent');
            const titleH1 = document.getElementById('gameTitle');
            if(titleH1) titleH1.textContent = `Bienvenue ${player.name}`;

            let html = `
                <div style="text-align:center; margin-bottom:20px; font-size:1.2rem;">
                    Ton mot secret : <br>
                    <span class="word-given" style="padding: 5px 15px; border-radius:8px; font-size:1.5rem; display:inline-block; margin-top:5px;">
                        ${player.word}
                    </span>
                </div>
                <h3>Indices des joueurs :</h3>
                <ul id="hintsList">
            `;

            players.forEach(p => {
                const hint = hints[p.id] ? hints[p.id] : '<span style="opacity:0.5; font-style:italic;">En attente...</span>';
                const isCurrent = (p.id == state.current_turn_id) ? 'border-color:var(--main-red);' : ''; // Highlight joueur actif
                
                html += `
                    <li style="${isCurrent}">
                        <strong style="min-width:100px;">${p.name}</strong>
                        <span id="player-hint-${p.id}" style="flex:1; text-align:right;">${hint}</span>
                    </li>`;
            });

            html += `</ul><div id="actionSection" style="margin-top:25px; padding-top:15px; border-top:1px solid #ddd;">`;

            // Zone d'action
            if (yourTurn) {
                const alreadySent = !!hints[player.id];
                if (!alreadySent) {
                    html += `
                        <form id="hintForm">
                            <label for="hintInput" style="color:var(--main-red); font-size:1.1rem;">C'est à toi ! Donne un indice :</label>
                            <input type="text" id="hintInput" maxlength="50" placeholder="Ex: Rayures..." autocomplete="off" required>
                            <button type="submit">Envoyer mon indice</button>
                        </form>
                    `;
                    startTimer(); // Lance le timer visuel
                } else {
                    html += `<p style="text-align:center; font-weight:bold;">Indice envoyé ! En attente des autres...</p>`;
                    stopTimer();
                }
            } else {
                html += `<p style="text-align:center; opacity:0.8;">Ce n'est pas ton tour. Observe bien !</p>`;
                stopTimer();
            }

            html += `</div>`;
            contentDiv.innerHTML = html;

            // Attach Form Listener
            const form = document.getElementById('hintForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    sendHint(document.getElementById('hintInput').value);
                });
                // Focus automatique
                setTimeout(() => document.getElementById('hintInput')?.focus(), 100);
            }
        }

        function updateSingleHint(pid, text) {
            const el = document.getElementById(`player-hint-${pid}`);
            if (el) el.textContent = text;
        }

        function sendHint(text) {
            const cleanText = text.trim();
            if (!cleanText) return;

            ws.send(JSON.stringify({ type: 'send_hint', room_id: roomId, player_id: playerId, hint: cleanText }));
            
            // UI Feedback immédiat
            const actionSec = document.getElementById('actionSection');
            if(actionSec) actionSec.innerHTML = '<p style="text-align:center; color:green; font-weight:bold;">Envoyé !</p>';
            stopTimer();
        }

        // --- 4. Gestion Timer ---
        function startTimer() {
            const container = document.getElementById('timerContainer');
            const bar = document.getElementById('timerBar');
            if(!container || !bar) return;

            clearInterval(turnTimer);
            container.style.display = 'block';
            
            // Reset visuel
            bar.style.transition = 'none';
            bar.style.width = '100%';
            
            // Force reflow
            void bar.offsetWidth;

            // Animation
            bar.style.transition = `width ${TURN_DURATION}s linear`;
            bar.style.width = '0%';

            // Logique fin de temps
            turnTimer = setTimeout(() => {
                const form = document.getElementById('hintForm');
                if(form) sendHint("⏱ Temps écoulé");
            }, TURN_DURATION * 1000);
        }

        function stopTimer() {
            clearInterval(turnTimer);
            clearTimeout(turnTimer); // Important: stop le timeout JS aussi
            const container = document.getElementById('timerContainer');
            if(container) container.style.display = 'none';
        }

        function notifyTurn() {
            // Optionnel : Son ou Vibration
            if("vibrate" in navigator) navigator.vibrate(200);
            startTimer();
        }

        // --- 5. Ping & Cleanup ---
        setInterval(() => {
            fetch('../api/ping_activity.php', { method: 'POST', body: new URLSearchParams({player_id: playerId, room_id: roomId}) });
        }, 5 * 60 * 1000);

        window.addEventListener("beforeunload", () => {
            navigator.sendBeacon("../api/disconnect.php", new URLSearchParams({ player_id: playerId, room_id: roomId }));
        });

        // Démarrage
        connectWS();
    });
    </script>
</body>
</html>