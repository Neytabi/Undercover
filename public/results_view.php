<?php
session_start();

if (!isset($_SESSION['vote_results'])) {
    // Redirection de sécurité ou message simple si accès direct
    die("<div style='text-align:center; color:white; font-family:sans-serif;'>Aucun résultat à afficher.</div>");
}

$data = $_SESSION['vote_results'];
$players = $data['players'];
$voteCount = $data['vote_count'];
$eliminated = $data['eliminated']; // Tableau des IDs éliminés
$room_id = $data['room_id'];

// Optionnel : Trier les joueurs pour afficher ceux qui ont le plus de votes en premier
arsort($voteCount);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Résultats - Undercover</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&family=Rubik:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        /* Styles spécifiques pour la grille de résultats */
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .player-card {
            background-color: var(--light-red);
            border: 2px solid var(--blue-bg);
            border-radius: 8px;
            padding: 15px 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            min-height: 110px;
            overflow: hidden; /* Important pour le tampon */
        }

        /* Support Dark Mode pour les cartes */
        [data-theme="dark"] .player-card {
            background-color: var(--input-dark);
            border-color: var(--border-dark);
            color: var(--text-light);
        }

        .player-name {
            font-family: 'Rubik', sans-serif;
            font-weight: bold;
            z-index: 2;
            margin-bottom: 8px;
            font-size: 1.1rem;
            text-align: center;
            line-height: 1.2;
        }

        .vote-badge {
            background-color: var(--blue-bg);
            color: var(--white);
            font-family: 'Bangers', cursive;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 1.1rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 2;
        }
        
        [data-theme="dark"] .vote-badge {
            background-color: var(--dark-red);
        }

        /* --- Style ÉLIMINÉ (Animation spectaculaire) --- */
        
        /* La carte secoue quand on est éliminé */
        .player-card.eliminated {
            border-color: var(--main-red);
            background-color: #ffebee; /* Rouge très pâle */
            animation: shake 0.5s ease-in-out;
        }
        
        [data-theme="dark"] .player-card.eliminated {
            background-color: #3e1212; /* Rouge sombre */
        }

        /* Le Tampon "ELIMINATED" */
        .stamp-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(5); /* Très gros au départ */
            border: 3px solid var(--main-red);
            color: var(--main-red);
            font-family: 'Bangers', cursive;
            font-size: 1.4rem;
            padding: 5px 10px;
            border-radius: 8px;
            text-transform: uppercase;
            opacity: 0;
            pointer-events: none;
            letter-spacing: 2px;
            background: rgba(255, 255, 255, 0.7);
            z-index: 10;
            white-space: nowrap;
        }

        /* Quand la classe .eliminated est ajoutée, on active l'animation du tampon */
        .player-card.eliminated .stamp-overlay {
            animation: stampIn 0.4s cubic-bezier(0.6, 0.04, 0.98, 0.335) forwards;
        }

        /* --- Keyframes --- */
        @keyframes stampIn {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(3) rotate(-15deg); }
            100% { opacity: 1; transform: translate(-50%, -50%) scale(1) rotate(-15deg); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-5px); }
            40% { transform: translateX(5px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
        }

        /* Barre de chargement avant suite */
        .loader-bar {
            height: 4px;
            background: var(--main-red);
            width: 0%;
            margin-top: 20px;
            border-radius: 2px;
            transition: width 0.2s linear;
        }
    </style>
</head>
<body data-theme="light">

    <h1 id="mainTitle">Le Verdict</h1>

    <div class="container">
        <h2 style="margin-top: 0;">Résultats des votes</h2>
        
        <div class="results-grid">
            <?php foreach ($players as $id => $name): ?>
                <?php $count = isset($voteCount[$id]) ? $voteCount[$id] : 0; ?>
                
                <div class="player-card" id="player-<?= $id ?>">
                    <div class="stamp-overlay">ÉLIMINÉ</div>
                    
                    <span class="player-name"><?= htmlspecialchars($name) ?></span>
                    
                    <div class="vote-badge">
                        <?= $count ?> vote<?= $count > 1 ? 's' : '' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="progress" class="loader-bar"></div>
        <p style="text-align:center; font-size:0.9rem; opacity:0.7; margin-top:10px;">
            La suite arrive...
        </p>
    </div>

    <script>
        const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
    </script>

    <script>
        // Récupération des données PHP
        const eliminatedIds = <?= json_encode($eliminated) ?>;
        const roomId = "<?= urlencode($room_id) ?>";
        
        // Configuration des délais
        const revealDelay = 1500; // Temps avant de révéler le premier éliminé
        const nextStepDelay = 4000; // Temps après révélation avant redirection

        // Fonction pour animer l'élimination
        function revealElimination() {
            eliminatedIds.forEach((id, index) => {
                setTimeout(() => {
                    const card = document.getElementById('player-' + id);
                    if (card) {
                        // Ajout de la classe qui déclenche le shake + stamp
                        card.classList.add('eliminated');
                        
                        // Petit effet sonore "Bam" (optionnel, visuel ici suffisant)
                        // navigator.vibrate(200); // Vibre sur mobile
                    }
                }, revealDelay * (index + 1));
            });
        }

        // Lancement de l'animation
        revealElimination();

        // Gestion de la barre de progression et redirection
        const totalTime = (eliminatedIds.length * revealDelay) + nextStepDelay;
        const progressBar = document.getElementById('progress');
        
        setTimeout(() => {
            progressBar.style.transition = `width ${totalTime}ms linear`;
            progressBar.style.width = '100%';
        }, 100);

        setTimeout(() => {
            window.location.href = "/api/next_round.php?room_id=" + roomId;
        }, totalTime);

        // --- Récupération du thème (Dark/Light) ---
        // Pour que l'affichage soit cohérent avec le choix de l'utilisateur sur index.php
        // On suppose que le thème est stocké dans localStorage ou cookie. 
        // Voici un petit script pour appliquer le thème sauvegardé :
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
    </script>
</body>
</html>