<?php 
session_start();
require_once __DIR__ . '/../includes/db.php';

// Vérifie que le joueur est bien connecté
$player_id = $_GET['player_id'] ?? ($_SESSION['player_id'] ?? null);
if (!$player_id) {
    die("Player ID non défini");
}

// Récupère les infos du joueur
$stmt = $pdo->prepare("SELECT * FROM players WHERE id = ?");
$stmt->execute([$player_id]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    die("Joueur introuvable");
}

// Met à jour la session si besoin
$_SESSION['player_id'] = $player_id;
$_SESSION['room_id'] = $player['room_id'];
$room_id = $player['room_id'];

// Vérifie si tous les joueurs vivants ont donné leur indice
$stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE room_id = ? AND is_alive = 1");
$stmt->execute([$room_id]);
$totalAlive = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT h.player_id)
    FROM hints h
    JOIN players p ON h.player_id = p.id
    WHERE h.room_id = ? AND p.is_alive = 1
");
$stmt->execute([$room_id]);
$totalHints = $stmt->fetchColumn();

// ... après avoir défini $room_id ...
// récupérer turn_time_seconds
$stmt = $pdo->prepare("SELECT COALESCE(turn_time_seconds, 30) AS turn_time_seconds FROM rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room_settings = $stmt->fetch(PDO::FETCH_ASSOC);
$turn_time_seconds = ($room_settings && isset($room_settings['turn_time_seconds'])) ? (int)$room_settings['turn_time_seconds'] : 30;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Undercover - Partie</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Luckiest+Guy&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>
<body data-theme="light">
    <div id="gameContainer">
        <p>Chargement de la partie...</p>
    </div>
    <div id="timerContainer" style="display:none; width: 100%; height: 10px; background: #ddd; margin-top: 10px; border-radius: 5px; overflow: hidden;">
        <div id="timerBar" style="width: 0%; height: 100%; background: linear-gradient(to right, #4caf50, #f44336); transition: width 1s linear;"></div>
    </div>


    <script>
        const roomId = <?= json_encode($room_id) ?>;
        const playerId = <?= json_encode($player_id) ?>;
        
        //timer 
        let turnTimer;
        const TURN_DURATION = <?= (int)$turn_time_seconds ?>; // secondes (provenant DB)

        function startTurnTimer() {
            clearInterval(turnTimer);
            let remaining = TURN_DURATION;
            const bar = document.getElementById('timerBar');
            const container = document.getElementById('timerContainer');
    
            container.style.display = "block";  // 👉 rendre visible
            bar.style.width = '100%';

            turnTimer = setInterval(() => {
                remaining--;
                bar.style.width = `${(remaining / TURN_DURATION) * 100}%`;
                if (remaining <= 0) {
                    clearInterval(turnTimer);
                    autoSubmitHint();
                }
            }, 1000);
        }

        function stopTurnTimer() {
            clearInterval(turnTimer);
            document.getElementById('timerBar').style.width = '0%';
            document.getElementById('timerContainer').style.display = "none"; // 👉 cache la barre
        }

        function autoSubmitHint() {
            const form = document.getElementById('hintForm');
            if (form) {
                const hint = document.getElementById('hint').value.trim() || "⏱ Temps écoulé";
                ws.send(JSON.stringify({
                    type: 'send_hint',
                    room_id: roomId,
                    player_id: playerId,
                    hint: hint
                }));
                document.getElementById('confirmation').textContent = "Temps écoulé, indice envoyé automatiquement.";
                form.remove();
                stopTurnTimer();
            }
        }


        const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);

        ws.addEventListener('open', () => {
            console.log('Connecté au WebSocket');

            // On rejoint la room
            ws.send(JSON.stringify({
                type: 'join_room',
                room_id: roomId,
                player_id: playerId
            }));

            // ATTENTION : Ne pas demander tout de suite l'état du jeu ici,
            // on attend la confirmation join_room avant de faire get_game_state
        });

        ws.addEventListener('message', (event) => {
            const data = JSON.parse(event.data);
            console.log('Message WS reçu:', data);

            // Dès que join_room est confirmé, on demande l'état du jeu
            if (data.type === 'info' && data.message.includes('Rejoint la room')) {
                ws.send(JSON.stringify({
                    type: 'get_game_state',
                    room_id: roomId,
                    player_id: playerId
                }));
            }

            if(data.type === 'game_state') {
                updateGame(data);  // <-- On passe data directement (pas data.payload)
            } 
            else if(data.type === 'new_hint') {
                updateHint(data.player_id, data.hint);
            }
            else if(data.type === 'your_turn') {
                notifyYourTurn();
            }
            else if (data.type === 'update_turn') {
              ws.send(JSON.stringify({
                type: 'get_game_state',
                room_id: roomId,
                player_id: playerId
              }));
            }
            else if (data.type === 'start_voting') {
              //alert(data.message);
              window.location.href = `vote.php?room_id=${roomId}`;
            }

            // Gérer d’autres messages du serveur (changement tour, début vote, etc.)
        });

        function updateGame(state) {
            const player = state.player;
            const players = state.players;
            const hints = state.hint_map;
            const yourTurn = state.you_are_turn;

            let html = `<h1>Bienvenue, ${player.name}</h1>`;
            html += `<div class="container">`;
            html += `<p>Ton mot secret : <strong>${player.word}</strong></p>`;
            html += "<h3>Joueurs et leurs indices :</h3><ul>";

            players.forEach(p => {
                const hint = hints[p.id] ?? "🕐 En attente...";
                html += `<li>${p.name} : <strong id='player-hint-${p.id}'>${hint}</strong></li>`;
            });

            html += "</ul><div id='hintSection'>";

            if (yourTurn) {
                const playerHint = hints[player.id];
                if (!playerHint) {
                    html += `
                        <form id="hintForm">
                            <label for="hint">Ton indice :</label>
                            <input type="text" name="hint" id="hint" maxlength="100" required>
                            <button type="submit">Envoyer</button>
                        </form>
                        <p id="confirmation" style="color: green;"></p>
                    `;
                } else {
                    html += `<p><strong>Tu as envoyé ton indice :</strong> ${playerHint}</p>`;
                    html += `<p>Attends que les autres joueurs jouent...</p>`;
                }
            } else {
              const playerHint = hints[player.id];
              if (playerHint) {
                html += `<p><strong>Tu as envoyé ton indice :</strong> ${playerHint}</p>`;
                html += `<p>Attends que les autres joueurs jouent...</p>`;
              } else {
                html += `<p>Ce n’est pas ton tour. Patiente un peu...</p>`;
              }
            }

            html += `</div>`;
            html += `</div>`;
            document.getElementById('gameContainer').innerHTML = html;

            // Gère la soumission de l’indice uniquement si c’est ton tour et pas déjà envoyé
            const form = document.getElementById('hintForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const hint = document.getElementById('hint').value.trim();
                    if (!hint) {
                        alert("L’indice ne peut pas être vide.");
                        return;
                    }

                    // Envoie l’indice via WS au lieu de fetch()
                    ws.send(JSON.stringify({
                        type: 'send_hint',  // IMPORTANT : doit correspondre au case côté serveur
                        room_id: roomId,
                        player_id: playerId,
                        hint: hint
                    }));
                    document.getElementById('confirmation').textContent = "Indice envoyé ! En attente des autres joueurs...";
                    document.getElementById('hintForm').remove(); // Cache le formulaire

                });
            }
            if (yourTurn) {
                const alreadySent = !!hints[player.id];
                if (!alreadySent) startTurnTimer();
                else stopTurnTimer();
            } else {
            stopTurnTimer();
            }
        }

        function updateHint(player_id, hint) {
            const el = document.getElementById('player-hint-' + player_id);
            if (el) {
                el.textContent = hint;
            }
        }

        function notifyYourTurn() {
            alert("C'est ton tour de jouer !");
            startTurnTimer();
        }


        window.addEventListener("beforeunload", () => {
            navigator.sendBeacon("../api/disconnect.php", new URLSearchParams({
                player_id: "<?= $player_id ?>",
                room_id: "<?= $room_id ?>"
            }));
        });


        // PING pour garder le joueur actif (toutes les 5 min)
        setInterval(() => {
            fetch('../api/ping_activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    player_id: playerId,
                    room_id: roomId
                })
            }).then(res => res.json())
              .then(data => {
                  if (!data.success) console.warn('Ping échoué :', data);
              })
              .catch(err => console.error('Erreur de ping :', err));
        }, 5 * 60 * 1000); // toutes les 5 minutes

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

</body>
</html>