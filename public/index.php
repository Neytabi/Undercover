<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Undercover Web Edition</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Luckiest+Guy&family=Rubik:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
</head>
<body data-theme="light">
  <h1 id="mainTitle">Undercover Web Edition</h1>
  <h2 id="mainContent">Créer une nouvelle partie</h2>
    <div class="container">
      <form action="../api/create_room.php" method="POST">
        <label for="name">Votre pseudo :</label>
        <input type="text" name="name" id="name" required>
        <button type="submit">Créer</button>
      </form>
    </div>
  <h2 id="mainContent" class="hidden">Rejoindre une partie</h2>
    <div class="container" id="joinContainer" style="flex: 1;">
      <form action="../api/join_room.php" method="POST">
        <label for="code">Code de la room :</label>
        <input type="text" name="code" id="code" required placeholder="A1B2C" autocomplete="off" />
        <label for="name2">Votre pseudo :</label>
        <input type="text" name="name" id="name2" required>
        <button type="submit">Rejoindre</button>
      </form>
    </div>
  <script src="assets/js/script.js"></script>

  <script>
  const ws = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws/`);
  </script>

  <!-- Bouton avec rotation -->
  <button id="settingsBtn" class="settings-btn" aria-label="Paramètres">
    <span class="material-symbols-rounded">settings</span>
  </button>

  <!-- Panneau dépliable -->
  <div id="settingsPanel">
    <label>
      <input type="checkbox" id="rememberCheckbox"/>Se souvenir du pseudo
    </label>
    <br><br>
    <div class="theme-switch-text" id="themeSwitchToggle">🌗 Passer en sombre</div>
  </div>

  <!--panneau info game-->
  <button id="rulesBtn" class="help-btn" aria-label="Règles du jeu">
    <span class="material-symbols-rounded">question_mark</span>
  </button>

  <div id="rulesModal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>🎯 Règles du jeu</h2>
      <h3>🕵️ Mode Undercover</h3>
      <p>Tous les joueurs reçoivent un mot similaire sauf un — l'Undercover — qui reçoit un mot proche mais différent. Le but est de deviner qui est l'intrus sans trop se faire remarquer.</p>

      <h3>🎭 Mode Tiercelieu</h3>
      <p>Plus chaotique : meme principe que pour le mode normal sauf qu'ici les undercover vons éliminer chaque nuit un civil</p>
    </div>
  </div>

</body>
</html>