<?php
require_once 'config.php';
$pdo = getDB();

// ── Liste des stagiaires + nombre d'évaluations déjà saisies ──
$stagiaires = $pdo->query("
  SELECT
    s.id_stagiaire, s.nom, s.prenom, s.classe, s.etablissement, s.date_debut, s.date_fin,
    (SELECT COUNT(*) FROM evaluation_competence_technique et WHERE et.id_stagiaire = s.id_stagiaire) AS nb_tech,
    (SELECT COUNT(*) FROM evaluation_competence_humaine  eh WHERE eh.id_stagiaire = s.id_stagiaire) AS nb_hum,
    (SELECT COUNT(*) FROM evaluation_badge               eb WHERE eb.id_stagiaire = s.id_stagiaire) AS nb_badge,
    n.note AS note
  FROM stagiaire s
  LEFT JOIN notation n ON n.id_stagiaire = s.id_stagiaire
  ORDER BY s.nom, s.prenom
")->fetchAll();

// ── Totaux possibles, pour calculer la progression de chaque fiche ──
$totalTech  = (int) $pdo->query("SELECT COUNT(*) FROM competence_technique")->fetchColumn();
$totalHum   = (int) $pdo->query("SELECT COUNT(*) FROM competence_humaine")->fetchColumn();
$totalBadge = (int) $pdo->query("SELECT COUNT(*) FROM badge")->fetchColumn();
$totalItems = $totalTech + $totalHum + $totalBadge;

// ── Listes uniques pour les filtres ──
$classes = array_values(array_unique(array_column($stagiaires, 'classe')));
sort($classes);
$etablissements = array_values(array_unique(array_column($stagiaires, 'etablissement')));
sort($etablissements);

// Années de période distinctes, déduites des date_debut réellement enregistrées en base
$annees = [];
foreach ($stagiaires as $s) {
    if (!empty($s['date_debut'])) {
        $annees[] = date('Y', strtotime($s['date_debut']));
    }
}
$annees = array_values(array_unique($annees));
rsort($annees); // les plus récentes en premier
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suivi stagiaire</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="parametres.css">
</head>
<body class="<?= bodyClass() ?>">
  <div id="toast" class="toast"></div>

  <header class="header">
    <h1>Suivi stagiaire</h1>
    <nav class="header-auth">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <span class="header-user"><?= htmlspecialchars($_SESSION['user_nom']) ?></span>
        <button type="button" class="hamburger-btn" id="menuBtn" aria-expanded="false" aria-controls="menuPopup" aria-label="Ouvrir le menu">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
      <?php else: ?>
        <button type="button" class="auth-btn" id="loginBtn" aria-expanded="false" aria-controls="loginPopup">Connexion</button>
      <?php endif; ?>
    </nav>
  </header>

  <?php if (!empty($_SESSION['user_id'])): ?>
  <!-- Popup du menu hamburger, ancré en haut à droite -->
  <div class="menu-popup" id="menuPopup" hidden>
    <div class="menu-popup-header">
      <h3>Menu</h3>
      <button type="button" class="menu-popup-close" id="menuPopupClose" aria-label="Fermer">&times;</button>
    </div>

    <a href="index.php" class="menu-popup-item">Accueil</a>
    <a href="suivi_modifications.php" class="menu-popup-item">Suivi des modifications</a>
    <button type="button" class="menu-popup-item" id="securityMenuBtn">Sécurité du compte</button>
    <button type="button" class="menu-popup-item" id="displayMenuBtn">Affichage</button>
    <a href="logout.php" class="menu-popup-item menu-popup-item-danger">Déconnexion</a>
  </div>
  <?php endif; ?>

  <?php if (!empty($_SESSION['user_id'])): ?>
  <!-- Popup "Sécurité du compte" : changement de mot de passe -->
  <div class="login-popup" id="securityPopup" hidden>
    <div class="login-popup-header">
      <h3>Sécurité du compte</h3>
      <button type="button" class="login-popup-close" id="securityPopupClose" aria-label="Fermer">&times;</button>
    </div>

    <div class="login-popup-error" id="securityPopupError" hidden></div>

    <form id="securityPopupForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <div class="login-popup-field">
        <label for="securityAncien">Mot de passe actuel</label>
        <input type="password" id="securityAncien" name="ancien_mot_de_passe" placeholder="Mot de passe actuel" required autocomplete="current-password">
      </div>
      <div class="login-popup-field">
        <label for="securityNouveau">Nouveau mot de passe</label>
        <input type="password" id="securityNouveau" name="nouveau_mot_de_passe" placeholder="8 caractères minimum" minlength="8" required autocomplete="new-password">
      </div>
      <div class="login-popup-field">
        <label for="securityConfirme">Confirmer le nouveau mot de passe</label>
        <input type="password" id="securityConfirme" name="confirme_mot_de_passe" placeholder="Retapez le nouveau mot de passe" minlength="8" required autocomplete="new-password">
      </div>

      <button type="submit" class="login-popup-submit" id="securityPopupSubmit">Changer le mot de passe</button>
    </form>
  </div>

  <!-- Popup "Affichage" : mode sombre -->
  <div class="login-popup" id="displayPopup" hidden>
    <div class="login-popup-header">
      <h3>Affichage</h3>
      <button type="button" class="login-popup-close" id="displayPopupClose" aria-label="Fermer">&times;</button>
    </div>

    <div class="field field-switch">
      <label for="displayModeSombre">Mode sombre</label>
      <label class="switch">
        <input type="checkbox" id="displayModeSombre" <?= !empty($_SESSION['mode_sombre']) ? 'checked' : '' ?>>
        <span class="switch-slider"></span>
      </label>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($_SESSION['user_id'])): ?>
  <!-- Popup de connexion, ancré en haut à droite -->
  <div class="login-popup" id="loginPopup" hidden>
    <div class="login-popup-header">
      <h3>Connexion</h3>
      <button type="button" class="login-popup-close" id="loginPopupClose" aria-label="Fermer">&times;</button>
    </div>

    <div class="login-popup-error" id="loginPopupError" hidden></div>

    <form id="loginPopupForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <div class="login-popup-field">
        <label for="loginPopupNom">Nom</label>
        <input type="text" id="loginPopupNom" name="nom" placeholder="Entrez votre nom" required>
      </div>

      <div class="login-popup-field">
        <label for="loginPopupMdp">Mot de passe</label>
        <input type="password" id="loginPopupMdp" name="mot_de_passe" placeholder="Entrez votre mot de passe" required>
      </div>

      <button type="submit" class="login-popup-submit" id="loginPopupSubmit">Se connecter</button>
    </form>
  </div>
  <?php endif; ?>

  <main class="content">
    <div class="toolbar">
      <div class="toolbar-spacer" aria-hidden="true"></div>

      <div class="search-bar">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="searchInput" placeholder="Rechercher">
      </div>

      <div class="toolbar-right">
        <?php if (isLoggedIn()): ?>
          <a href="formulaire_stagiaires.php" class="add-btn">+ Nouveau stagiaire</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="filters-panel" id="filtersPanel">
      <div class="filters-panel-title">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
          <polygon points="2,5 22,5 14,15 14,21 10,21 10,15"></polygon>
        </svg>
        Filtres
      </div>
      <div class="filters-panel-row">
        <div class="filter-group">
          <label for="filterClasse">Classe</label>
          <select id="filterClasse">
            <option value="">Toutes</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label for="filterEtablissement">Établissement</label>
          <select id="filterEtablissement">
            <option value="">Tous</option>
            <?php foreach ($etablissements as $e): ?>
              <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label for="filterPeriode">Période</label>
          <select id="filterPeriode">
            <option value="">Toutes</option>
            <?php foreach ($annees as $a): ?>
              <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Liste des stagiaires -->
    <?php if (empty($stagiaires)): ?>
      <div class="empty-state">
        <p>Aucun stagiaire enregistré pour le moment.</p>
        <?php if (isLoggedIn()): ?>
          <a href="formulaire_stagiaires.php" class="add-btn">+ Ajouter le premier stagiaire</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="stagiaire-grid" id="stagiaireGrid">
        <?php foreach ($stagiaires as $s):
          $initiales = mb_strtoupper(mb_substr($s['prenom'], 0, 1) . mb_substr($s['nom'], 0, 1));
          $rempli    = (int) $s['nb_tech'] + (int) $s['nb_hum'] + (int) $s['nb_badge'];
          $pct       = $totalItems > 0 ? (int) round(($rempli / $totalItems) * 100) : 0;
          $annee     = $s['date_debut'] ? date('Y', strtotime($s['date_debut'])) : '';
        ?>
          <article
            class="stagiaire-card"
            data-id="<?= (int) $s['id_stagiaire'] ?>"
            data-nom="<?= htmlspecialchars(mb_strtolower($s['nom'] . ' ' . $s['prenom'])) ?>"
            data-classe="<?= htmlspecialchars($s['classe']) ?>"
            data-etablissement="<?= htmlspecialchars($s['etablissement']) ?>"
            data-annee="<?= htmlspecialchars($annee) ?>"
            data-debut="<?= htmlspecialchars($s['date_debut'] ?? '') ?>"
            tabindex="0"
            role="button"
            aria-haspopup="dialog"
          >
            <div class="stagiaire-avatar"><?= htmlspecialchars($initiales) ?></div>

            <div class="stagiaire-info">
              <h2 class="stagiaire-name"><?= htmlspecialchars($s['nom']) ?> <?= htmlspecialchars($s['prenom']) ?></h2>
              <div class="stagiaire-tags">
                <span class="tag tag-classe"><?= htmlspecialchars($s['classe']) ?></span>
                <span class="tag tag-etablissement"><?= htmlspecialchars($s['etablissement']) ?></span>
              </div>
            </div>

            <div class="stagiaire-progress">
              <div class="progress-track">
                <div class="progress-fill" style="width: <?= $pct ?>%;"></div>
              </div>
              <span class="progress-label">
                <?= $pct ?>% évalué
                <?php if ($s['note'] !== null): ?>
                  · Note : <?= htmlspecialchars(rtrim(rtrim(number_format((float) $s['note'], 2, '.', ''), '0'), '.')) ?>/20
                <?php endif; ?>
              </span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <!-- Vue groupée par date de début, affichée uniquement quand une période précise est sélectionnée -->
      <div class="periode-groups" id="periodeGroups" hidden></div>

      <p class="no-results" id="noResults" hidden>Aucun stagiaire ne correspond à votre recherche.</p>
    <?php endif; ?>

  </main>

  <!-- Modale : fiche détaillée d'un stagiaire -->
  <div class="modal-overlay" id="modalOverlay" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
      <div class="modal-topbar">
        <button class="modal-back" id="modalBack">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
          Retour
        </button>
      </div>
      <div class="modal-body" id="modalBody">
        <p class="fiche-loading">Chargement…</p>
      </div>
    </div>
  </div>

  <script>
    const searchInput = document.getElementById('searchInput');
    const filterClasse = document.getElementById('filterClasse');
    const filterEtablissement = document.getElementById('filterEtablissement');
    const filterPeriode = document.getElementById('filterPeriode');
    const grid = document.getElementById('stagiaireGrid');
    const periodeGroups = document.getElementById('periodeGroups');
    const noResults = document.getElementById('noResults');

    function formatDateFR(iso) {
      if (!iso) return '';
      const [y, m, d] = iso.split('-');
      return `${d}/${m}/${y}`;
    }

    function renderGroupedView(matches) {
      periodeGroups.innerHTML = '';

      // Regroupe les cartes correspondantes par date de début exacte
      const groups = new Map();
      matches.forEach(card => {
        const key = card.dataset.debut || '__non_renseigne__';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(card);
      });

      // Trie les groupes : dates les plus récentes en premier, "non renseigné" en dernier
      const sortedKeys = Array.from(groups.keys()).sort((a, b) => {
        if (a === '__non_renseigne__') return 1;
        if (b === '__non_renseigne__') return -1;
        return b.localeCompare(a);
      });

      sortedKeys.forEach(key => {
        const groupCards = groups.get(key);

        const section = document.createElement('div');
        section.className = 'periode-group';

        const title = document.createElement('h3');
        title.className = 'periode-group-title';
        const label = key === '__non_renseigne__'
          ? 'Date de début non renseignée'
          : 'Arrivée le ' + formatDateFR(key);
        const nb = groupCards.length;
        title.textContent = `${label} — ${nb} stagiaire${nb > 1 ? 's' : ''}`;

        const groupGrid = document.createElement('div');
        groupGrid.className = 'stagiaire-grid';

        groupCards.forEach(card => {
          const clone = card.cloneNode(true);
          clone.style.display = '';
          groupGrid.appendChild(clone);
        });

        section.appendChild(title);
        section.appendChild(groupGrid);
        periodeGroups.appendChild(section);
      });
    }

    function applyFilters() {
      if (!grid) return;

      const query = searchInput.value.trim().toLowerCase();
      const classe = filterClasse ? filterClasse.value : '';
      const etablissement = filterEtablissement ? filterEtablissement.value : '';
      const periode = filterPeriode ? filterPeriode.value : '';
      const cards = Array.from(grid.querySelectorAll('.stagiaire-card'));

      const matches = cards.filter(card => {
        const matchesQuery = card.dataset.nom.includes(query);
        const matchesClasse = !classe || card.dataset.classe === classe;
        const matchesEtablissement = !etablissement || card.dataset.etablissement === etablissement;
        const matchesPeriode = !periode || card.dataset.annee === periode;
        return matchesQuery && matchesClasse && matchesEtablissement && matchesPeriode;
      });

      if (periode) {
        // Une période précise est sélectionnée → vue groupée par date de début
        grid.hidden = true;
        periodeGroups.hidden = false;
        renderGroupedView(matches);
      } else {
        // "Toutes" → grille simple habituelle
        periodeGroups.hidden = true;
        periodeGroups.innerHTML = '';
        grid.hidden = false;
        cards.forEach(card => {
          card.style.display = matches.includes(card) ? '' : 'none';
        });
      }

      if (noResults) {
        noResults.hidden = matches.length !== 0;
      }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (filterClasse) filterClasse.addEventListener('change', applyFilters);
    if (filterEtablissement) filterEtablissement.addEventListener('change', applyFilters);
    if (filterPeriode) filterPeriode.addEventListener('change', applyFilters);

    // ── Modale : fiche détaillée d'un stagiaire ──
    const modalOverlay = document.getElementById('modalOverlay');
    const modalBody = document.getElementById('modalBody');
    const modalBack = document.getElementById('modalBack');

    function openFiche(id) {
      modalOverlay.removeAttribute('hidden');
      modalBody.innerHTML = '<p class="fiche-loading">Chargement…</p>';

      fetch('comp.php?id=' + encodeURIComponent(id))
        .then(response => {
          if (!response.ok) throw new Error('Erreur ' + response.status);
          return response.text();
        })
        .then(html => {
          modalBody.innerHTML = html;
        })
        .catch(() => {
          modalBody.innerHTML = '<p class="fiche-loading">Impossible de charger la fiche du stagiaire.</p>';
        });
    }

    function closeFiche() {
      modalOverlay.setAttribute('hidden', '');
    }

    // Délégation d'événements : fonctionne aussi bien sur la grille simple
    // que sur les cartes clonées dans la vue groupée par période.
    function bindCardEvents(container) {
      if (!container) return;

      container.addEventListener('click', (e) => {
        const card = e.target.closest('.stagiaire-card');
        if (card) openFiche(card.dataset.id);
      });

      container.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('stagiaire-card')) {
          e.preventDefault();
          openFiche(e.target.dataset.id);
        }
      });
    }

    bindCardEvents(grid);
    bindCardEvents(periodeGroups);

    if (modalBack) modalBack.addEventListener('click', closeFiche);
    if (modalOverlay) {
      modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) closeFiche();
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !modalOverlay.hasAttribute('hidden')) closeFiche();
    });

    // ── Popup de connexion (haut à droite) ──
    (function() {
      const loginBtn = document.getElementById('loginBtn');
      const loginPopup = document.getElementById('loginPopup');
      if (!loginBtn || !loginPopup) return;

      const loginPopupClose = document.getElementById('loginPopupClose');
      const loginPopupForm = document.getElementById('loginPopupForm');
      const loginPopupError = document.getElementById('loginPopupError');
      const loginPopupSubmit = document.getElementById('loginPopupSubmit');
      const loginPopupNom = document.getElementById('loginPopupNom');

      function openPopup() {
        loginPopup.removeAttribute('hidden');
        loginBtn.setAttribute('aria-expanded', 'true');
        loginPopupNom.focus();
      }

      function closePopup() {
        loginPopup.setAttribute('hidden', '');
        loginBtn.setAttribute('aria-expanded', 'false');
      }

      loginBtn.addEventListener('click', () => {
        if (loginPopup.hasAttribute('hidden')) {
          openPopup();
        } else {
          closePopup();
        }
      });

      if (loginPopupClose) loginPopupClose.addEventListener('click', closePopup);

      document.addEventListener('click', (e) => {
        if (!loginPopup.hasAttribute('hidden') && !loginPopup.contains(e.target) && e.target !== loginBtn) {
          closePopup();
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !loginPopup.hasAttribute('hidden')) closePopup();
      });

      if (loginPopupForm) {
        loginPopupForm.addEventListener('submit', (e) => {
          e.preventDefault();

          loginPopupError.hidden = true;
          loginPopupSubmit.disabled = true;
          loginPopupSubmit.textContent = 'Connexion…';

          fetch('login.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(loginPopupForm),
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                window.location.href = data.redirect || 'index.php';
              } else {
                loginPopupError.textContent = data.erreur || 'Erreur de connexion.';
                loginPopupError.hidden = false;
                loginPopupSubmit.disabled = false;
                loginPopupSubmit.textContent = 'Se connecter';
              }
            })
            .catch(() => {
              loginPopupError.textContent = 'Une erreur est survenue. Merci de réessayer.';
              loginPopupError.hidden = false;
              loginPopupSubmit.disabled = false;
              loginPopupSubmit.textContent = 'Se connecter';
            });
        });
      }
    })();

    // ── Popup du menu hamburger (haut à droite) ──
    (function() {
      const menuBtn = document.getElementById('menuBtn');
      const menuPopup = document.getElementById('menuPopup');
      if (!menuBtn || !menuPopup) return;

      const menuPopupClose = document.getElementById('menuPopupClose');

      function openMenu() {
        menuPopup.removeAttribute('hidden');
        menuBtn.setAttribute('aria-expanded', 'true');
      }

      function closeMenu() {
        menuPopup.setAttribute('hidden', '');
        menuBtn.setAttribute('aria-expanded', 'false');
      }

      menuBtn.addEventListener('click', () => {
        if (menuPopup.hasAttribute('hidden')) {
          openMenu();
        } else {
          closeMenu();
        }
      });

      if (menuPopupClose) menuPopupClose.addEventListener('click', closeMenu);

      document.addEventListener('click', (e) => {
        if (!menuPopup.hasAttribute('hidden') && !menuPopup.contains(e.target) && e.target !== menuBtn && !menuBtn.contains(e.target)) {
          closeMenu();
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !menuPopup.hasAttribute('hidden')) closeMenu();
      });
    })();

    // ── Pop-up "Sécurité du compte" (changement de mot de passe) ──
    (function() {
      const securityMenuBtn = document.getElementById('securityMenuBtn');
      const securityPopup = document.getElementById('securityPopup');
      if (!securityMenuBtn || !securityPopup) return;

      const securityPopupClose = document.getElementById('securityPopupClose');
      const securityPopupForm = document.getElementById('securityPopupForm');
      const securityPopupError = document.getElementById('securityPopupError');
      const securityPopupSubmit = document.getElementById('securityPopupSubmit');
      const menuPopupEl = document.getElementById('menuPopup');
      const menuBtnEl = document.getElementById('menuBtn');

      function openSecurityPopup() {
        if (menuPopupEl) menuPopupEl.setAttribute('hidden', '');
        if (menuBtnEl) menuBtnEl.setAttribute('aria-expanded', 'false');
        securityPopupError.hidden = true;
        securityPopupForm.reset();
        securityPopup.removeAttribute('hidden');
        document.getElementById('securityAncien').focus();
      }

      function closeSecurityPopup() {
        securityPopup.setAttribute('hidden', '');
      }

      securityMenuBtn.addEventListener('click', openSecurityPopup);
      if (securityPopupClose) securityPopupClose.addEventListener('click', closeSecurityPopup);

      document.addEventListener('click', (e) => {
        if (!securityPopup.hasAttribute('hidden') && !securityPopup.contains(e.target) && e.target !== securityMenuBtn) {
          closeSecurityPopup();
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !securityPopup.hasAttribute('hidden')) closeSecurityPopup();
      });

      if (securityPopupForm) {
        securityPopupForm.addEventListener('submit', (e) => {
          e.preventDefault();

          securityPopupError.hidden = true;
          securityPopupSubmit.disabled = true;
          securityPopupSubmit.textContent = 'Enregistrement…';

          fetch('securite_compte.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(securityPopupForm),
          })
            .then(response => response.json())
            .then(data => {
              securityPopupSubmit.disabled = false;
              securityPopupSubmit.textContent = 'Changer le mot de passe';

              if (data.success) {
                closeSecurityPopup();
                const toast = document.getElementById('toast');
                toast.textContent = data.succes || 'Mot de passe mis à jour avec succès.';
                toast.classList.add('show');
                setTimeout(() => toast.classList.remove('show'), 2500);
              } else {
                securityPopupError.textContent = data.erreur || 'Erreur lors du changement de mot de passe.';
                securityPopupError.hidden = false;
              }
            })
            .catch(() => {
              securityPopupSubmit.disabled = false;
              securityPopupSubmit.textContent = 'Changer le mot de passe';
              securityPopupError.textContent = 'Une erreur est survenue. Merci de réessayer.';
              securityPopupError.hidden = false;
            });
        });
      }
    })();

    // ── Pop-up "Affichage" (mode sombre) ──
    (function() {
      const displayMenuBtn = document.getElementById('displayMenuBtn');
      const displayPopup = document.getElementById('displayPopup');
      if (!displayMenuBtn || !displayPopup) return;

      const displayPopupClose = document.getElementById('displayPopupClose');
      const displayModeSombre = document.getElementById('displayModeSombre');
      const menuPopupEl = document.getElementById('menuPopup');
      const menuBtnEl = document.getElementById('menuBtn');
      const csrfToken = <?= json_encode(csrfToken()) ?>;

      function openDisplayPopup() {
        if (menuPopupEl) menuPopupEl.setAttribute('hidden', '');
        if (menuBtnEl) menuBtnEl.setAttribute('aria-expanded', 'false');
        displayPopup.removeAttribute('hidden');
      }

      function closeDisplayPopup() {
        displayPopup.setAttribute('hidden', '');
      }

      displayMenuBtn.addEventListener('click', openDisplayPopup);
      if (displayPopupClose) displayPopupClose.addEventListener('click', closeDisplayPopup);

      document.addEventListener('click', (e) => {
        if (!displayPopup.hasAttribute('hidden') && !displayPopup.contains(e.target) && e.target !== displayMenuBtn) {
          closeDisplayPopup();
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !displayPopup.hasAttribute('hidden')) closeDisplayPopup();
      });

      if (displayModeSombre) {
        displayModeSombre.addEventListener('change', () => {
          const active = displayModeSombre.checked;

          // Application immédiate, sans attendre la réponse du serveur
          document.body.classList.toggle('dark-mode', active);

          const formData = new FormData();
          formData.append('csrf_token', csrfToken);
          if (active) formData.append('mode_sombre', '1');

          fetch('preference_affichage.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
          })
            .then(response => response.json())
            .then(data => {
              if (!data.success) {
                // En cas d'échec, on revient à l'état précédent
                displayModeSombre.checked = !active;
                document.body.classList.toggle('dark-mode', !active);
              }
            })
            .catch(() => {
              displayModeSombre.checked = !active;
              document.body.classList.toggle('dark-mode', !active);
            });
        });
      }
    })();

    <?php if (isset($_GET['supprime'])): ?>
    // Petit popup de confirmation après suppression d'un stagiaire
    (function() {
      const toast = document.getElementById('toast');
      toast.textContent = 'Stagiaire supprimé avec succès.';
      toast.classList.add('show');
      setTimeout(function() {
        toast.classList.remove('show');
      }, 2500);
      // Nettoie l'URL pour éviter que le message ne réapparaisse au rechargement
      window.history.replaceState({}, document.title, 'index.php');
    })();
    <?php endif; ?>
  </script>

</body>
</html>