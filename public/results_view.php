<?php
session_start();
// ... (Logique PHP) ...
$data = $_SESSION['vote_results'] ?? null;
if(!$data) die("Pas de résultats");
$players = $data['players'];
$voteCount = $data['vote_count'];
$eliminated = $data['eliminated'];
$room_id = $data['room_id'];
arsort($voteCount);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Verdict</title>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&family=Rubik:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <h1 class="main-title">Le Verdict</h1>

    <div class="container">
        <div class="results-grid">
            <?php foreach ($players as $id => $name): ?>
                <?php $count = $voteCount[$id] ?? 0; ?>
                <!-- L'ID player-X sert au JS pour ajouter la classe .eliminated -->
                <div class="player-card" id="player-<?= $id ?>">
                    <div class="stamp-overlay">ÉLIMINÉ</div>
                    <span style="font-family:'Rubik'; font-weight:bold; font-size:1.2rem;"><?= htmlspecialchars($name) ?></span>
                    <div class="vote-badge"><?= $count ?> vote<?= $count > 1 ? 's' : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="progress" class="loader-bar"></div>
        <p style="text-align:center; margin-top:10px; opacity:0.7;">Analyse en cours...</p>
    </div>

    <script>
        // ... Ton script JS existant pour l'animation d'élimination ...
        // Le CSS s'occupe de l'animation 'shake' et 'stampIn'
        const eliminatedIds = <?= json_encode($eliminated) ?>;
        const revealDelay = 1500;
        
        function revealElimination() {
            eliminatedIds.forEach((id, index) => {
                setTimeout(() => {
                    const card = document.getElementById('player-' + id);
                    if (card) card.classList.add('eliminated'); // Déclenche le CSS
                }, revealDelay * (index + 1));
            });
        }
        revealElimination();
        
        // Redirection
        setTimeout(() => {
            window.location.href = "/api/next_round.php?room_id=<?= $room_id ?>";
        }, (eliminatedIds.length * revealDelay) + 4000);
    </script>
    <script src="assets/js/theme-apply.js"></script>
</body>
</html>