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
</head>
<body>
  <div id="toast" class="toast"></div>

  <header class="header">
    <h1>Suivi stagiaire</h1>
    <nav class="header-auth">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <span class="header-user">Bonjour, <?= htmlspecialchars($_SESSION['user_nom']) ?></span>
        <a href="logout.php" class="auth-btn">Déconnexion</a>
      <?php else: ?>
        <button type="button" class="auth-btn" id="loginBtn" aria-expanded="false" aria-controls="loginPopup">Connexion</button>
      <?php endif; ?>
    </nav>
  </header>

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