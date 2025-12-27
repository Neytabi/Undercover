<?php
session_start();
$data = $_SESSION['vote_results'] ?? null;
if (!$data) die("Accès interdit.");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Le Verdict - Undercover</title>
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light">

    <h1 class="main-title">Rapport d'Élimination</h1>

    <div class="container">
        <h2 style="margin-top: 0; text-align:center;">Dépouillement des votes</h2>
        
        <div class="results-grid">
            <?php foreach ($players as $id => $name): ?>
                <?php $count = $voteCount[$id] ?? 0; ?>
                <div class="player-card" id="player-<?= $id ?>">
                    <div class="stamp-overlay">ÉLIMINÉ</div>
                    <span class="player-name"><?= htmlspecialchars($name) ?></span>
                    <div class="vote-badge"><?= $count ?> vote<?= $count > 1 ? 's' : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="loading-zone">
            <div id="progress" class="loader-bar"></div>
            <p id="status-text">Calcul des conséquences...</p>
        </div>
    </div>

    <script src="assets/js/theme-apply.js"></script>
    <script>
        const eliminatedIds = <?= json_encode($eliminated) ?>;
        const roomId = "<?= $room_id ?>";
        const revealDelay = 1500;
        const nextStepDelay = 3000;

        function startReveal() {
            eliminatedIds.forEach((id, i) => {
                setTimeout(() => {
                    const card = document.getElementById('player-' + id);
                    if (card) card.classList.add('eliminated');
                    if (i === eliminatedIds.length - 1) {
                        document.getElementById('status-text').textContent = "Mise à jour de la mission...";
                    }
                }, revealDelay * (i + 1));
            });
        }

        startReveal();

        const totalTime = (eliminatedIds.length * revealDelay) + nextStepDelay;
        const bar = document.getElementById('progress');
        setTimeout(() => {
            bar.style.transition = `width ${totalTime}ms linear`;
            bar.style.width = '100%';
        }, 100);

        setTimeout(() => {
            window.location.href = "../api/next_round.php?room_id=" + roomId;
        }, totalTime);
    </script>
</body>
</html>