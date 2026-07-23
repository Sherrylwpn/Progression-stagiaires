<?php
require_once 'config.php';
requireAuth(); // Correction 3.3 : la liste des stagiaires ne doit pas être consultable sans connexion

$pdo = getDB();

// Archive automatiquement les stages dont l'année est déjà révolue (cf. config.php) :
// exécuté à chaque chargement de l'accueil, avant de lister les stages actifs.
archiverAnneesRevolues($pdo);

// ── Liste des stages (chaque carte = un stage, cf. correction 3.4) avec la dernière
//    évaluation connue (note globale + nombre de critères déjà notés lors de cette séance) ──
$stagiaires = $pdo->query("
  SELECT
    st.id_stage, s.nom, s.prenom, c.nom AS classe, e.nom AS etablissement,
    st.date_debut, st.date_fin,
    dern.note AS note,
    COALESCE(cnt.nb_notes, 0) AS nb_notes
  FROM stage st
  JOIN stagiaire s ON s.id_stagiaire = st.id_stagiaire
  JOIN classe_ref c ON c.id_classe = st.id_classe
  JOIN etablissement_ref e ON e.id_etablissement = st.id_etablissement
  LEFT JOIN (
    SELECT ev1.id_stage, ev1.note
    FROM evaluation ev1
    WHERE ev1.id_evaluation = (
      SELECT ev2.id_evaluation FROM evaluation ev2
      WHERE ev2.id_stage = ev1.id_stage
      ORDER BY ev2.date_evaluation DESC, ev2.id_evaluation DESC LIMIT 1
    )
  ) dern ON dern.id_stage = st.id_stage
  LEFT JOIN (
    SELECT ev.id_stage,
           SUM(
             (SELECT COUNT(*) FROM evaluation_competence_technique WHERE id_evaluation = ev.id_evaluation) +
             (SELECT COUNT(*) FROM evaluation_competence_humaine   WHERE id_evaluation = ev.id_evaluation) +
             (SELECT COUNT(*) FROM evaluation_badge                WHERE id_evaluation = ev.id_evaluation)
           ) AS nb_notes
    FROM evaluation ev
    WHERE ev.id_evaluation = (
      SELECT ev2.id_evaluation FROM evaluation ev2
      WHERE ev2.id_stage = ev.id_stage
      ORDER BY ev2.date_evaluation DESC, ev2.id_evaluation DESC LIMIT 1
    )
    GROUP BY ev.id_stage
  ) cnt ON cnt.id_stage = st.id_stage
  WHERE st.archive = 0
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

// ── Autres comptes admin (pour le popup "Sécurité du compte") ──
// Uniquement chargé si l'utilisateur courant est lui-même admin : sert à
// proposer la réinitialisation du mot de passe d'un autre admin en cas d'oubli.
$autresAdmins = [];
if (($_SESSION['role'] ?? '') === 'admin') {
    $stmt = $pdo->prepare("SELECT id, nom FROM users WHERE role = 'admin' AND id != :id ORDER BY nom");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $autresAdmins = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suivi stagiaire</title>
  <style>
    /* Correction 3.14 : le bouton de déconnexion (désormais un <button> dans un
       <form>, plus un <a>) doit garder exactement le rendu de .menu-popup-item.
       On ne réinitialise ici QUE le look natif du navigateur sur <button>
       (fond, bordure, police) ; placé avant index.css, ces règles cèdent la
       main aux classes .menu-popup-item / .menu-popup-item-danger pour tout
       le reste (couleur, espacement...) à spécificité égale. */
    .menu-popup-item-form { margin: 0; padding: 0; }
    .menu-popup-item-btn {
      background: none;
      border: none;
      font: inherit;
      text-align: left;
      width: 100%;
      box-sizing: border-box;
      cursor: pointer;
    }
  </style>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="parametres.css">
</head>
<body class="<?= bodyClass() ?>">
  <div id="toast" class="toast"></div>

  <header class="header">
    <h1>Suivi stagiaire</h1>
    <nav class="header-auth">
      <span class="header-user"><?= htmlspecialchars($_SESSION['user_nom']) ?></span>
      <button type="button" class="hamburger-btn" id="menuBtn" aria-expanded="false" aria-controls="menuPopup" aria-label="Ouvrir le menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>
    </nav>
  </header>

  <!-- Popup du menu hamburger, ancré en haut à droite -->
  <div class="menu-popup" id="menuPopup" role="dialog" aria-modal="true" aria-labelledby="menuPopupTitle" hidden>
    <div class="menu-popup-header">
      <h3 id="menuPopupTitle">Menu</h3>
      <button type="button" class="menu-popup-close" id="menuPopupClose" aria-label="Fermer">&times;</button>
    </div>

    <a href="index.php" class="menu-popup-item">Accueil</a>
    <a href="archive.php" class="menu-popup-item">Archives</a>
    <a href="suivi_modifications.php" class="menu-popup-item">Suivi des actions</a>
    <button type="button" class="menu-popup-item" id="securityMenuBtn">Sécurité du compte</button>
    <button type="button" class="menu-popup-item" id="displayMenuBtn">Affichage</button>
    <!-- Correction 3.14 : déconnexion en POST + CSRF plutôt qu'un simple lien GET
         (un lien peut être suivi par un tiers, un préchargement de navigateur,
         un robot d'indexation...). Le bouton reprend les classes existantes
         pour garder le même rendu visuel qu'avant. -->
    <form method="POST" action="logout.php" class="menu-popup-item-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
      <button type="submit" class="menu-popup-item menu-popup-item-danger menu-popup-item-btn">Déconnexion</button>
    </form>
  </div>

  <!-- Popup "Sécurité du compte" : changement de mot de passe -->
  <div class="login-popup" id="securityPopup" role="dialog" aria-modal="true" aria-labelledby="securityPopupTitle" hidden>
    <div class="login-popup-header">
      <h3 id="securityPopupTitle">Sécurité du compte</h3>
      <button type="button" class="login-popup-close" id="securityPopupClose" aria-label="Fermer">&times;</button>
    </div>

    <?php if (!empty($autresAdmins)): ?>
    <!-- Onglets visibles uniquement pour un compte admin ayant au moins un autre
         admin en base : permet de basculer entre son propre mot de passe et la
         réinitialisation de celui d'un collègue admin qui l'aurait oublié. -->
    <div class="security-popup-tabs" id="securityPopupTabs">
      <button type="button" class="security-popup-tab active" data-tab="soi">Mon mot de passe</button>
      <button type="button" class="security-popup-tab" data-tab="admin">Un autre admin</button>
    </div>
    <?php endif; ?>

    <div class="login-popup-error" id="securityPopupError" hidden></div>

    <form id="securityPopupForm" data-tab-panel="soi">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
      <input type="hidden" name="mode" value="soi">

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

    <?php if (!empty($autresAdmins)): ?>
    <!-- Réinitialisation du mot de passe d'un AUTRE admin (cas d'oubli) : pas
         besoin de l'ancien mot de passe, protégé côté serveur par le rôle admin. -->
    <form id="securityPopupAdminForm" data-tab-panel="admin" hidden>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
      <input type="hidden" name="mode" value="admin_reinit">

      <p class="fiche-empty" style="margin-bottom:14px;">À utiliser uniquement si l'autre administrateur a oublié son mot de passe.</p>

      <div class="login-popup-field">
        <label for="securityAdminCible">Compte administrateur</label>
        <select id="securityAdminCible" name="id_cible" required>
          <option value="" disabled selected>Sélectionner…</option>
          <?php foreach ($autresAdmins as $admin): ?>
            <option value="<?= (int) $admin['id'] ?>"><?= htmlspecialchars($admin['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="login-popup-field">
        <label for="securityAdminNouveau">Nouveau mot de passe</label>
        <input type="password" id="securityAdminNouveau" name="nouveau_mot_de_passe" placeholder="8 caractères minimum" minlength="8" required autocomplete="new-password">
      </div>
      <div class="login-popup-field">
        <label for="securityAdminConfirme">Confirmer le nouveau mot de passe</label>
        <input type="password" id="securityAdminConfirme" name="confirme_mot_de_passe" placeholder="Retapez le nouveau mot de passe" minlength="8" required autocomplete="new-password">
      </div>

      <button type="submit" class="login-popup-submit" id="securityPopupAdminSubmit">Réinitialiser le mot de passe</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Popup "Affichage" : mode sombre -->
  <div class="login-popup" id="displayPopup" role="dialog" aria-modal="true" aria-labelledby="displayPopupTitle" hidden>
    <div class="login-popup-header">
      <h3 id="displayPopupTitle">Affichage</h3>
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
        <a href="formulaire_stagiaires.php" class="add-btn">+ Nouveau stagiaire</a>
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
        <a href="formulaire_stagiaires.php" class="add-btn">+ Ajouter le premier stagiaire</a>
      </div>
    <?php else: ?>
      <div class="stagiaire-grid" id="stagiaireGrid">
        <?php foreach ($stagiaires as $s):
          $initiales = mb_strtoupper(mb_substr($s['prenom'], 0, 1) . mb_substr($s['nom'], 0, 1));
          $rempli    = (int) $s['nb_notes'];
          $pct       = $totalItems > 0 ? (int) round(($rempli / $totalItems) * 100) : 0;
          $annee     = $s['date_debut'] ? date('Y', strtotime($s['date_debut'])) : '';
        ?>
          <article
            class="stagiaire-card"
            data-id="<?= (int) $s['id_stage'] ?>"
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
                <?= $pct ?>% évalué (dernière séance)
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
    // ── Accessibilité des fenêtres modales (correction 3.20) ──
    // Piège le focus clavier à l'intérieur d'une modale ouverte (Tab/Shift+Tab
    // ne sortent plus de la fenêtre) et rend le focus à l'élément qui l'a
    // ouverte lors de la fermeture, plutôt que de le laisser sur <body>.
    function creerPiegeFocus(dialogEl) {
      let declencheur = null;

      function elementsFocusables() {
        return Array.from(dialogEl.querySelectorAll(
          'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(el => el.offsetParent !== null);
      }

      function surTab(e) {
        if (e.key !== 'Tab') return;
        const focusables = elementsFocusables();
        if (focusables.length === 0) return;
        const premier = focusables[0];
        const dernier = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === premier) {
          e.preventDefault();
          dernier.focus();
        } else if (!e.shiftKey && document.activeElement === dernier) {
          e.preventDefault();
          premier.focus();
        }
      }

      return {
        activer(elementDeclencheur) {
          declencheur = elementDeclencheur || document.activeElement;
          dialogEl.addEventListener('keydown', surTab);
          // Laisse le contenu (parfois chargé en fetch) s'installer avant de focaliser.
          requestAnimationFrame(() => {
            const focusables = elementsFocusables();
            (focusables[0] || dialogEl).focus();
          });
        },
        desactiver() {
          dialogEl.removeEventListener('keydown', surTab);
          if (declencheur && typeof declencheur.focus === 'function') {
            declencheur.focus();
          }
        },
      };
    }

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
    const modalDialog = modalOverlay ? modalOverlay.querySelector('.modal') : null;
    const piegeModal = modalDialog ? creerPiegeFocus(modalDialog) : null;

    function openFiche(id, declencheur) {
      modalOverlay.removeAttribute('hidden');
      modalBody.innerHTML = '<p class="fiche-loading">Chargement…</p>';
      if (piegeModal) piegeModal.activer(declencheur);

      fetch('stagiaire_detail_fragment.php?id=' + encodeURIComponent(id))
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
      if (piegeModal) piegeModal.desactiver();
    }

    // Délégation d'événements : fonctionne aussi bien sur la grille simple
    // que sur les cartes clonées dans la vue groupée par période.
    function bindCardEvents(container) {
      if (!container) return;

      container.addEventListener('click', (e) => {
        const card = e.target.closest('.stagiaire-card');
        if (card) openFiche(card.dataset.id, card);
      });

      container.addEventListener('keydown', (e) => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('stagiaire-card')) {
          e.preventDefault();
          openFiche(e.target.dataset.id, e.target);
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

    // ── Popup du menu hamburger (haut à droite) ──
    (function() {
      const menuBtn = document.getElementById('menuBtn');
      const menuPopup = document.getElementById('menuPopup');
      if (!menuBtn || !menuPopup) return;

      const menuPopupClose = document.getElementById('menuPopupClose');
      const piege = creerPiegeFocus(menuPopup);

      function openMenu() {
        menuPopup.removeAttribute('hidden');
        menuBtn.setAttribute('aria-expanded', 'true');
        piege.activer(menuBtn);
      }

      function closeMenu() {
        menuPopup.setAttribute('hidden', '');
        menuBtn.setAttribute('aria-expanded', 'false');
        piege.desactiver();
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
      const securityPopupAdminForm = document.getElementById('securityPopupAdminForm');
      const securityPopupError = document.getElementById('securityPopupError');
      const securityPopupSubmit = document.getElementById('securityPopupSubmit');
      const securityPopupAdminSubmit = document.getElementById('securityPopupAdminSubmit');
      const securityPopupTabs = document.getElementById('securityPopupTabs');
      const menuPopupEl = document.getElementById('menuPopup');
      const menuBtnEl = document.getElementById('menuBtn');
      const piege = creerPiegeFocus(securityPopup);

      // ── Onglets "Mon mot de passe" / "Un autre admin" (n'existent que pour un admin) ──
      function activerOnglet(nomOnglet) {
        if (!securityPopupTabs) return;
        securityPopupTabs.querySelectorAll('.security-popup-tab').forEach(tab => {
          tab.classList.toggle('active', tab.dataset.tab === nomOnglet);
        });
        securityPopupForm.hidden = nomOnglet !== 'soi';
        if (securityPopupAdminForm) securityPopupAdminForm.hidden = nomOnglet !== 'admin';
        securityPopupError.hidden = true;
      }

      if (securityPopupTabs) {
        securityPopupTabs.querySelectorAll('.security-popup-tab').forEach(tab => {
          tab.addEventListener('click', () => activerOnglet(tab.dataset.tab));
        });
      }

      function openSecurityPopup() {
        if (menuPopupEl) menuPopupEl.setAttribute('hidden', '');
        if (menuBtnEl) menuBtnEl.setAttribute('aria-expanded', 'false');
        securityPopupError.hidden = true;
        securityPopupForm.reset();
        if (securityPopupAdminForm) securityPopupAdminForm.reset();
        activerOnglet('soi');
        securityPopup.removeAttribute('hidden');
        // creerPiegeFocus place le focus sur le premier champ et piège Tab/Shift+Tab
        // à l'intérieur de la popup ; au retour, le focus revient sur le bouton
        // hamburger (déclencheur visible après fermeture du sous-menu).
        piege.activer(menuBtnEl);
      }

      function closeSecurityPopup() {
        securityPopup.setAttribute('hidden', '');
        piege.desactiver();
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

      // Gestion de la soumission, factorisée pour servir aux deux formulaires
      // (changement de son propre mot de passe / réinitialisation d'un autre admin).
      function brancherSoumission(form, submitBtn, texteEnCours, texteParDefaut) {
        if (!form) return;
        form.addEventListener('submit', (e) => {
          e.preventDefault();

          securityPopupError.hidden = true;
          submitBtn.disabled = true;
          submitBtn.textContent = texteEnCours;

          fetch('securite_compte.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
          })
            .then(response => response.json())
            .then(data => {
              submitBtn.disabled = false;
              submitBtn.textContent = texteParDefaut;

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
              submitBtn.disabled = false;
              submitBtn.textContent = texteParDefaut;
              securityPopupError.textContent = 'Une erreur est survenue. Merci de réessayer.';
              securityPopupError.hidden = false;
            });
        });
      }

      brancherSoumission(securityPopupForm, securityPopupSubmit, 'Enregistrement…', 'Changer le mot de passe');
      brancherSoumission(securityPopupAdminForm, securityPopupAdminSubmit, 'Enregistrement…', 'Réinitialiser le mot de passe');
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
      const piege = creerPiegeFocus(displayPopup);

      function openDisplayPopup() {
        if (menuPopupEl) menuPopupEl.setAttribute('hidden', '');
        if (menuBtnEl) menuBtnEl.setAttribute('aria-expanded', 'false');
        displayPopup.removeAttribute('hidden');
        piege.activer(menuBtnEl);
      }

      function closeDisplayPopup() {
        displayPopup.setAttribute('hidden', '');
        piege.desactiver();
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

    <?php if (isset($_GET['archive'])): ?>
    // Petit popup de confirmation après archivage manuel d'un stagiaire
    (function() {
      const toast = document.getElementById('toast');
      toast.textContent = 'Stagiaire archivé avec succès.';
      toast.classList.add('show');
      setTimeout(function() {
        toast.classList.remove('show');
      }, 2500);
      window.history.replaceState({}, document.title, 'index.php');
    })();
    <?php endif; ?>
  </script>

</body>
</html>