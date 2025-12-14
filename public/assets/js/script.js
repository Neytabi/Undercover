document.addEventListener("DOMContentLoaded", () => {
  const checkbox = document.getElementById("rememberCheckbox");
  const themeSwitchText = document.getElementById("themeSwitchToggle");
  const inputCreate = document.getElementById("name");
  const inputJoin = document.getElementById("name2");
  const settingsBtn = document.getElementById("settingsBtn");
  const icon = settingsBtn?.querySelector(".material-symbols-rounded");

  // Crée le conteneur d'animation + la barre verticale
  const overlay = document.createElement("div");
  overlay.id = "themeSlideOverlay";
  const bar = document.createElement("div");
  bar.className = "vertical-bar";
  overlay.appendChild(bar);
  document.body.appendChild(overlay);

  // Appliquer le thème sauvegardé
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) {
    document.body.setAttribute("data-theme", savedTheme);
    if (themeSwitchText) {
      themeSwitchText.textContent = savedTheme === "dark" ? "☀️ Passer en clair" : "🌗 Passer en sombre";
    }
  }

  themeSwitchText?.addEventListener("click", () => {
    const currentTheme = document.body.getAttribute("data-theme") || "light";
    const newTheme = currentTheme === "dark" ? "light" : "dark";

    // Désactiver les transitions pendant l'animation
    document.body.classList.add("disable-transitions");
    
    // Mettre à jour le texte IMMÉDIATEMENT dans les deux cas
    updateThemeText(newTheme);

    // Animation différente selon la direction
    if (currentTheme === "light" && newTheme === "dark") {
      // Light → Dark : animation originale
      const clonedBody = document.body.cloneNode(true);
      clonedBody.setAttribute("data-theme", newTheme);
      clonedBody.style.position = "fixed";
      clonedBody.style.top = 0;
      clonedBody.style.left = 0;
      clonedBody.style.width = "100%";
      clonedBody.style.height = "100%";
      clonedBody.style.overflow = "hidden";
      clonedBody.style.zIndex = "9999";
      clonedBody.style.clipPath = "inset(0 100% 0 0)";
      clonedBody.style.transition = "clip-path 0.6s ease-in-out";

      document.body.appendChild(clonedBody);

      requestAnimationFrame(() => {
        clonedBody.style.clipPath = "inset(0 0% 0 0)";
      });

      setTimeout(() => {
        document.body.setAttribute("data-theme", newTheme);
        localStorage.setItem("theme", newTheme);
        
        setTimeout(() => {
          document.body.classList.remove("disable-transitions");
          clonedBody.remove();
        }, 50);
      }, 700);
    } else {
      // Dark → Light : animation corrigée
      const clonedBody = document.body.cloneNode(true);
      clonedBody.id = "theme-clone";
      clonedBody.style.position = "fixed";
      clonedBody.style.top = 0;
      clonedBody.style.left = 0;
      clonedBody.style.width = "100%";
      clonedBody.style.height = "100%";
      clonedBody.style.overflow = "hidden";
      clonedBody.style.zIndex = "9999";
      clonedBody.style.clipPath = "inset(0 0% 0 0)";
      clonedBody.style.transition = "clip-path 0.6s ease-in-out";

      // Appliquer immédiatement le nouveau thème
      document.body.setAttribute("data-theme", newTheme);
      localStorage.setItem("theme", newTheme);
    
      document.body.appendChild(clonedBody);

      requestAnimationFrame(() => {
        clonedBody.style.clipPath = "inset(0 0 0 100%)";
      });

      setTimeout(() => {
        clonedBody.remove();
        setTimeout(() => {
          document.body.classList.remove("disable-transitions");
        }, 50);
      }, 700);
    }
  });

  // Fonction helper pour mettre à jour le texte du bouton
  function updateThemeText(theme) {
    if (themeSwitchText) {
      themeSwitchText.textContent = theme === "dark" 
        ? "☀️ Passer en clair" 
        : "🌗 Passer en sombre";
    }
  }


  // ⚙️ Réglages : bouton rotation + panneau
  let rotation = 0;
  settingsBtn?.addEventListener("click", () => {
    rotation += 360;
    if (icon) icon.style.transform = "rotate(${rotation}deg)";

    const panel = document.getElementById("settingsPanel");
    if (panel) panel.classList.toggle("show");
  });
  // Fermer le panneau des paramètres si on clique en dehors
  window.addEventListener("click", (e) => {
    const panel = document.getElementById("settingsPanel");
    const isClickInside = panel?.contains(e.target) || settingsBtn?.contains(e.target);
    if (!isClickInside && panel?.classList.contains("show")) {
      panel.classList.remove("show");
      if (icon) icon.style.transform = "rotate(0deg)"; // Optionnel : reset l'icône
      rotation = 0;
    }
  });


  // Checkbox "Se souvenir"
  
  if (checkbox) {
    // Appliquer l'état sauvegardé
    checkbox.checked = localStorage.getItem("remember") === "true";

    // Gérer le changement de l'utilisateur (immédiat)
    checkbox.addEventListener("change", () => {
      if (checkbox.checked) {
        localStorage.setItem("remember", "true");
      } else {
        localStorage.removeItem("remember");
        localStorage.removeItem("pseudo");
      }
    });
  }

  // Remplir automatiquement le pseudo si sauvegardé
  const savedPseudo = localStorage.getItem("pseudo");
  if (savedPseudo && checkbox?.checked) {
    if (inputCreate) inputCreate.value = savedPseudo;
    if (inputJoin) inputJoin.value = savedPseudo;
  }

  // Sauvegarde du pseudo à la soumission
  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", () => {
      const pseudoInput = form.querySelector('input[name="name"]');
      if (!pseudoInput) return;

      if (checkbox?.checked) {
        localStorage.setItem("pseudo", pseudoInput.value.trim());
      }
    });
  });

  // 🧾 Affichage du modal de règles du jeu
  const rulesBtn = document.getElementById("rulesBtn");
  const rulesModal = document.getElementById("rulesModal");
  const closeRules = document.querySelector(".modal .close");

  if (rulesBtn && rulesModal && closeRules) {
    rulesBtn.addEventListener("click", () => {
      rulesModal.style.display = "flex";
    });

    closeRules.addEventListener("click", () => {
      rulesModal.style.display = "none";
    });

    // Fermer le modal si on clique en dehors
    window.addEventListener("click", (e) => {
      if (e.target === rulesModal) {
        rulesModal.style.display = "none";
      }
    });
  }
});