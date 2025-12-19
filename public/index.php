<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Undercover - QG</title>
  
  <!-- Polices & Icones -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;700;900&family=Rubik:wght@400;900&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet" />
  
  <!-- CSS -->
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body data-theme="light" class="home-body">

  <!-- Titre Principal avec effet -->
  <div class="hero-section">
    <h1 class="main-title">UNDERCOVER</h1>
    <p class="subtitle">WEB EDITION</p>
  </div>

  <!-- Conteneur Principal : Carte d'accès -->
  <div class="container home-container">
    
    <!-- Onglets de navigation -->
    <div class="tabs">
      <button class="tab-btn active" onclick="switchTab('join')">Rejoindre</button>
      <button class="tab-btn" onclick="switchTab('create')">Créer</button>
    </div>

    <!-- Contenu : Rejoindre -->
    <div id="tab-join" class="tab-content active">
      <form action="../api/join_room.php" method="POST" class="styled-form" onsubmit="savePseudo()">
        
        <div class="input-group">
          <span class="material-symbols-rounded input-icon">key</span>
          <input type="text" name="code" id="join-code" required placeholder="Code de la room" autocomplete="off" style="text-transform:uppercase;">
        </div>

        <div class="input-group">
          <span class="material-symbols-rounded input-icon">person</span>
          <input type="text" name="name" id="join-name" required placeholder="Votre pseudo" maxlength="12">
        </div>

        <button type="submit" class="action-btn">
          <span class="material-symbols-rounded">login</span> Infiltrer
        </button>
      </form>
    </div>

    <!-- Contenu : Créer -->
    <div id="tab-create" class="tab-content">
      <form action="../api/create_room.php" method="POST" class="styled-form" onsubmit="savePseudo()">
        
        <div class="info-box">
          <span class="material-symbols-rounded">add_circle</span>
          <p>Créez une nouvelle salle et invitez vos amis avec un code unique.</p>
        </div>

        <div class="input-group">
          <span class="material-symbols-rounded input-icon">person</span>
          <input type="text" name="name" id="create-name" required placeholder="Votre pseudo" maxlength="12">
        </div>

        <button type="submit" class="action-btn create-btn">
          <span class="material-symbols-rounded">rocket_launch</span> Lancer la mission
        </button>
      </form>
    </div>

  </div>

  <!-- Scripts -->
  <script src="assets/js/theme-apply.js"></script>

  <!-- Boutons Flottants -->
  <button id="settingsBtn" class="settings-btn" aria-label="Paramètres"><span class="material-symbols-rounded">settings</span></button>
  <button id="rulesBtn" class="help-btn" aria-label="Règles"><span class="material-symbols-rounded">menu_book</span></button>

  <!-- Panel Paramètres -->
  <div id="settingsPanel">
    <label class="setting-row">
      <input type="checkbox" id="rememberCheckbox"/>
      <span>Se souvenir de mon pseudo</span>
    </label>
    <div class="theme-switch-text" id="themeSwitchToggle">🌗 Mode Sombre / Clair</div>
  </div>

  <!-- Modal Règles -->
  <div id="rulesModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="toggleRules()">&times;</span>
      <h2>🎯 Briefing de Mission</h2>
      
      <div class="rule-block">
        <h3>🕵️ Civil vs Undercover</h3>
        <p>Les <strong>Civils</strong> ont le même mot. L'<strong>Undercover</strong> a un mot légèrement différent. <strong>Mr. White</strong> n'a aucun mot.</p>
      </div>

      <div class="rule-block">
        <h3>🗣️ Déroulement</h3>
        <p>Chacun son tour donne un indice. Après le tour, débattez et votez pour éliminer l'intrus !</p>
      </div>
      
      <div class="rule-block">
        <h3>🏆 Victoire</h3>
        <p><strong>Civils :</strong> Éliminez tous les intrus.<br><strong>Undercover :</strong> Restez caché jusqu'à qu'il ne reste qu'un seul civil.</p>
      </div>
    </div>
  </div>

  <script>
    // --- 1. Gestion des Onglets (Tabs) ---
    function switchTab(tab) {
        // Visuel boutons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        const activeBtn = document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`);
        if(activeBtn) activeBtn.classList.add('active');

        // Visuel contenu (avec petite animation via CSS)
        document.querySelectorAll('.tab-content').forEach(c => {
            c.classList.remove('active');
            c.style.display = 'none'; // Reset immédiat
        });
        
        const target = document.getElementById(`tab-${tab}`);
        if(target) {
            target.style.display = 'block';
            // Petit délai pour permettre la transition d'opacité
            setTimeout(() => target.classList.add('active'), 10);
        }
    }

    // --- 2. Gestion du Pseudo (LocalStorage) ---
    const rememberCheckbox = document.getElementById('rememberCheckbox');
    const nameInputs = [document.getElementById('join-name'), document.getElementById('create-name')];

    // Chargement au démarrage
    document.addEventListener("DOMContentLoaded", () => {
        const savedName = localStorage.getItem('undercover_username');
        const shouldRemember = localStorage.getItem('undercover_remember') === 'true';

        if (rememberCheckbox) rememberCheckbox.checked = shouldRemember;

        if (shouldRemember && savedName) {
            nameInputs.forEach(input => { if(input) input.value = savedName; });
        }
        
        // Sync des inputs de pseudo (si on tape dans l'un, ça remplit l'autre)
        nameInputs.forEach(input => {
            if(!input) return;
            input.addEventListener('input', (e) => {
                const val = e.target.value;
                nameInputs.forEach(other => { if(other !== e.target && other) other.value = val; });
            });
        });

        // Gestionnaire Checkbox
        if(rememberCheckbox) {
            rememberCheckbox.addEventListener('change', (e) => {
                localStorage.setItem('undercover_remember', e.target.checked);
                if(!e.target.checked) localStorage.removeItem('undercover_username');
            });
        }
    });

    // Sauvegarde à la soumission
    function savePseudo() {
        if(rememberCheckbox && rememberCheckbox.checked) {
            const val = nameInputs[0].value || nameInputs[1].value;
            if(val) localStorage.setItem('undercover_username', val);
        }
    }

    // --- 3. Gestion Modales ---
    const rulesModal = document.getElementById('rulesModal');
    const rulesBtn = document.getElementById('rulesBtn');
    const closeSpan = document.querySelector('.close');

    function toggleRules() {
        if(!rulesModal) return;
        rulesModal.style.display = (rulesModal.style.display === "flex") ? "none" : "flex";
    }

    if(rulesBtn) rulesBtn.addEventListener('click', toggleRules);
    
    // Fermer en cliquant dehors
    window.onclick = function(event) {
        if (event.target == rulesModal) toggleRules();
        
        // Fermer settings aussi si on clique ailleurs
        const settingsPanel = document.getElementById('settingsPanel');
        const settingsBtn = document.getElementById('settingsBtn');
        if (event.target !== settingsPanel && event.target !== settingsBtn && !settingsPanel.contains(event.target) && settingsPanel.classList.contains('show')) {
            settingsPanel.classList.remove('show');
        }
    }
  </script>
</body>
</html>