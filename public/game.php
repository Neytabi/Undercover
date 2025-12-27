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
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&family=Rubik:wght@400;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <!-- Animation Overlay -->
    <div id="page-transition-overlay" class="transition-active">
        <div class="transition-panel top"></div>
        <div class="transition-message">CONFIDENTIEL</div>
        <div class="transition-panel bottom"></div>
    </div>

    <h1 id="gameTitle">Mission en cours</h1>

    <div class="container game-mode" id="mainContainer">
        <div id="timerContainer"><div id="timerBar"></div></div>
        <div id="gameContent">
            <p style="text-align:center; opacity:0.7; margin-top:30px;">
                <span class="material-symbols-rounded spin">sync</span><br>
                Connexion au QG...
            </p>
        </div>
    </div>

    <script src="assets/js/theme-apply.js"></script>
    <button id="settingsBtn" class="settings-btn" aria-label="Paramètres"><span class="material-symbols-rounded">settings</span></button>
    <div id="settingsPanel"><div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div></div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const roomId = <?= json_encode($room_id) ?>;
        const playerId = <?= json_encode($player_id) ?>;
        const TURN_DURATION = <?= $turn_time_seconds ?>;
        const wsUrl = `${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`;
        let turnTimer = null;
        let ws = null;
        let hasLoadedState = false;

        // Animation d'ouverture
        setTimeout(() => {
            document.getElementById('page-transition-overlay').classList.remove('transition-active');
        }, 1500);

        // --- 1. WebSocket & Initialisation ---
        function connectWS() {
            ws = new WebSocket(wsUrl);

            ws.onopen = () => {
                console.log("WS Connecté");
                ws.send(JSON.stringify({ type: 'join_room', room_id: roomId, player_id: playerId }));
                // IMPORTANT : On demande l'état du jeu immédiatement
                requestGameState();
            };

            ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    
                    if (data.type === 'game_state') {
                        hasLoadedState = true;
                        renderGame(data);
                    }
                    else if (data.type === 'new_hint') {
                        updateSingleHint(data.player_id, data.hint);
                    }
                    else if (data.type === 'update_turn') {
                        setTimeout(() => requestGameState(), 500);
                    }
                    else if (data.type === 'your_turn') {
                        notifyTurn();
                    }
                    else if (data.type === 'start_voting') {
                        window.location.href = `vote.php?room_id=${roomId}`;
                    }
                } catch(e) { console.error(e); }
            };

            ws.onclose = () => {
                console.warn("WS Perdu. Reconnexion...");
                setTimeout(connectWS, 3000);
            };
        }

        function requestGameState() {
            if(ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'get_game_state', room_id: roomId, player_id: playerId }));
            } else {
                fetchGameStateHTTP();
            }
        }

        // Fallback HTTP si le WS est lent ou bloqué
        async function fetchGameStateHTTP() {
            try {
                const res = await fetch(`../api/get_game_state.php?room_id=${roomId}&player_id=${playerId}`);
                const data = await res.json();
                if(data && !data.error) {
                    hasLoadedState = true;
                    renderGame(data);
                }
            } catch(e) { console.error("Erreur HTTP State", e); }
        }

        // --- 2. Rendu du Jeu ---
        function renderGame(state) {
            const player = state.player;
            const players = state.players;
            const hints = state.hint_map || {};
            const yourTurn = state.you_are_turn;

            document.getElementById('gameTitle').textContent = `Agent ${player.name}`;

            let html = `
                <div style="text-align:center; margin-bottom:20px; font-size:1.2rem;">
                    Code Secret : <br>
                    <span class="word-given" style="padding: 8px 20px; border-radius:8px; font-size:1.6rem; display:inline-block; margin-top:8px; box-shadow:0 4px 0 rgba(0,0,0,0.2);">
                        ${player.word}
                    </span>
                </div>
                <h3>Rapport des Agents :</h3>
                <ul id="hintsList" class="styled-list">
            `;

            players.forEach(p => {
                const hint = hints[p.id] ? `"${hints[p.id]}"` : '<span style="opacity:0.5; font-style:italic;">En attente...</span>';
                const activeStyle = (p.id == state.current_turn_player_id) ? 'border-color:var(--main-red); background-color:rgba(255, 49, 46, 0.05);' : '';
                
                html += `
                    <li style="${activeStyle}">
                        <strong style="min-width:100px;">${p.name}</strong>
                        <span id="player-hint-${p.id}" style="flex:1; text-align:right; font-weight:bold; color:var(--blue-bg);">${hint}</span>
                    </li>`;
            });

            html += `</ul><div id="actionSection" style="margin-top:25px; padding-top:15px; border-top:2px dashed #ddd;">`;

            if (yourTurn) {
                const alreadySent = !!hints[player.id];
                if (!alreadySent) {
                    html += `
                        <form id="hintForm">
                            <label for="hintInput" style="color:var(--main-red); font-size:1.2rem; display:block; margin-bottom:10px;">C'est à votre tour !</label>
                            <input type="text" id="hintInput" maxlength="50" placeholder="Votre indice..." autocomplete="off" required>
                            <button type="submit" class="action-btn">TRANSMETTRE</button>
                        </form>
                    `;
                    startTimer();
                } else {
                    html += `<p style="text-align:center; font-weight:bold; color:green;">Indice transmis.</p>`;
                    stopTimer();
                }
            } else {
                html += `<p style="text-align:center; opacity:0.8;">Analyse des fréquences en cours...</p>`;
                stopTimer();
            }

            html += `</div>`;
            document.getElementById('gameContent').innerHTML = html;

            const form = document.getElementById('hintForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    sendHint(document.getElementById('hintInput').value);
                });
                setTimeout(() => document.getElementById('hintInput')?.focus(), 100);
            }
        }

        function updateSingleHint(pid, text) {
            const el = document.getElementById(`player-hint-${pid}`);
            if (el) el.textContent = `"${text}"`;
        }

        function sendHint(text) {
            const cleanText = text.trim();
            if (!cleanText) return;
            
            if(ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ type: 'send_hint', room_id: roomId, player_id: playerId, hint: cleanText }));
            } else {
                fetch('../api/submit_hint.php', {
                    method: 'POST',
                    body: new URLSearchParams({ room_id: roomId, player_id: playerId, hint: cleanText })
                });
            }

            const actionSec = document.getElementById('actionSection');
            if(actionSec) actionSec.innerHTML = '<p style="text-align:center; color:green; font-weight:bold;">Transmission...</p>';
            stopTimer();
        }

        function startTimer() {
            const container = document.getElementById('timerContainer');
            const bar = document.getElementById('timerBar');
            if(!container || !bar) return;

            clearInterval(turnTimer);
            container.style.display = 'block';
            bar.style.transition = 'none';
            bar.style.width = '100%';
            void bar.offsetWidth;
            bar.style.transition = `width ${TURN_DURATION}s linear`;
            bar.style.width = '0%';

            turnTimer = setTimeout(() => {
                const form = document.getElementById('hintForm');
                if(form) sendHint("⏱ Temps écoulé");
            }, TURN_DURATION * 1000);
        }

        function stopTimer() {
            clearInterval(turnTimer);
            clearTimeout(turnTimer);
            const container = document.getElementById('timerContainer');
            if(container) container.style.display = 'none';
        }

        function notifyTurn() {
            if("vibrate" in navigator) navigator.vibrate([200, 100, 200]);
            startTimer();
        }

        connectWS();
        
        // Timeout de sécurité : force le chargement HTTP après 2s si le WS traîne
        setTimeout(() => {
            if(!hasLoadedState) fetchGameStateHTTP();
        }, 2000);
    });
    </script>
</body>
</html>