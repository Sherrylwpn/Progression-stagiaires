<?php
require_once 'config.php';
$pdo = getDB();

// ── Liste des stagiaires + nombre d'évaluations déjà saisies ──
$stagiaires = $pdo->query("
  SELECT
    s.id_stagiaire, s.nom, s.prenom, s.classe, s.etablissement, s.date_debut, s.date_fin,
    (SELECT COUNT(*) FROM evaluation_competence_technique et WHERE et.id_stagiaire = s.id_stagiaire) AS nb_tech,
    (SELECT COUNT(*) FROM evaluation_competence_humaine  eh WHERE eh.id_stagiaire = s.id_stagiaire) AS nb_hum,
    (SELECT COUNT(*) FROM evaluation_badge               eb WHERE eb.id_stagiaire = s.id_stagiaire) AS nb_badge
  FROM stagiaire s
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
              <span class="progress-label"><?= $pct ?>% évalué</span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

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
    const noResults = document.getElementById('noResults');

    function applyFilters() {
      if (!grid) return;

      const query = searchInput.value.trim().toLowerCase();
      const classe = filterClasse ? filterClasse.value : '';
      const etablissement = filterEtablissement ? filterEtablissement.value : '';
      const periode = filterPeriode ? filterPeriode.value : '';
      const cards = grid.querySelectorAll('.stagiaire-card');
      let visibleCount = 0;

      cards.forEach(card => {
        const matchesQuery = card.dataset.nom.includes(query);
        const matchesClasse = !classe || card.dataset.classe === classe;
        const matchesEtablissement = !etablissement || card.dataset.etablissement === etablissement;
        const matchesPeriode = !periode || card.dataset.annee === periode;
        const visible = matchesQuery && matchesClasse && matchesEtablissement && matchesPeriode;

        card.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
      });

      if (noResults) {
        noResults.hidden = visibleCount !== 0;
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

    if (grid) {
      grid.addEventListener('click', (e) => {
        const card = e.target.closest('.stagiaire-card');
        if (card) openFiche(card.dataset.id);
      });

      grid.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('stagiaire-card')) {
          e.preventDefault();
          openFiche(e.target.dataset.id);
        }
      });
    }

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