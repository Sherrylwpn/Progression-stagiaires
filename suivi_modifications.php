<?php
require_once 'config.php';
requireAuth(); // Réservé aux utilisateurs connectés

$pdo = getDB();

$recherche = trim($_GET['q'] ?? '');

// Regroupement d'affichage : aucun (vue générale paginée), par dates, par utilisateur, par action, par stagiaire
$groupesValides = ['date', 'utilisateur', 'action', 'stagiaire'];
$groupe = $_GET['groupe'] ?? '';
if (!in_array($groupe, $groupesValides, true)) {
    $groupe = '';
}

// Seules 3 actions existent : Ajout / Modification / Suppression
$libellesAction = [
    'creation'     => 'Ajout',
    'modification' => 'Modification',
    'suppression'  => 'Suppression',
];

/**
 * Affiche un tableau du suivi des modifications.
 * $colonneMasquee permet de ne pas répéter la colonne déjà utilisée
 * comme titre du groupe (ex : pas de colonne "Utilisateur" sous un titre "Cromain").
 */
function afficherTableauSuivi(array $entrees, string $colonneMasquee, array $libellesAction): void
{
    echo '<table class="historique-table">';
    echo '<thead><tr>';
    if ($colonneMasquee !== 'date')        echo '<th>Date</th>';
    if ($colonneMasquee !== 'utilisateur') echo '<th>Utilisateur</th>';
    if ($colonneMasquee !== 'stagiaire')   echo '<th>Stagiaire</th>';
    if ($colonneMasquee !== 'action')      echo '<th>Action</th>';
    echo '<th>Détails</th>';
    echo '</tr></thead><tbody>';

    foreach ($entrees as $e) {
        echo '<tr>';

        if ($colonneMasquee !== 'date') {
            echo '<td>' . htmlspecialchars(date('d/m/Y', strtotime($e['date_action']))) . '</td>';
        }
        if ($colonneMasquee !== 'utilisateur') {
            echo '<td>' . htmlspecialchars($e['nom_user']) . '</td>';
        }
        if ($colonneMasquee !== 'stagiaire') {
            echo '<td>';
            echo htmlspecialchars($e['nom_stagiaire']);
            if (!$e['id_stage']) {
                echo ' <span class="non-evalue">(supprimé)</span>';
            }
            echo '</td>';
        }
        if ($colonneMasquee !== 'action') {
            echo '<td><span class="tag action-' . htmlspecialchars($e['action']) . '">'
               . htmlspecialchars($libellesAction[$e['action']] ?? $e['action'])
               . '</span></td>';
        }
        echo '<td>' . ($e['details'] ? htmlspecialchars($e['details']) : '—') . '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
}

if ($groupe === '') {
    // ── Vue générale, paginée (comportement d'origine) ──
    $page    = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
    $parPage = 40;
    $offset  = ($page - 1) * $parPage;

    if ($recherche !== '') {
        $like = '%' . $recherche . '%';

        $stmtCount = $pdo->prepare(
            "SELECT COUNT(*) FROM journal_modifications
             WHERE nom_stagiaire LIKE :like OR nom_user LIKE :like"
        );
        $stmtCount->execute([':like' => $like]);
        $total = (int) $stmtCount->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT * FROM journal_modifications
             WHERE nom_stagiaire LIKE :like OR nom_user LIKE :like
             ORDER BY date_action DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':like', $like, PDO::PARAM_STR);
    } else {
        $total = (int) $pdo->query("SELECT COUNT(*) FROM journal_modifications")->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT * FROM journal_modifications
             ORDER BY date_action DESC
             LIMIT :limit OFFSET :offset"
        );
    }
    $stmt->bindValue(':limit', $parPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $entrees = $stmt->fetchAll();

    $totalPages = max(1, (int) ceil($total / $parPage));
} else {
    // ── Vue groupée : toutes les entrées correspondantes, réparties en plusieurs petits tableaux ──
    if ($recherche !== '') {
        $like = '%' . $recherche . '%';
        $stmt = $pdo->prepare(
            "SELECT * FROM journal_modifications
             WHERE nom_stagiaire LIKE :like OR nom_user LIKE :like
             ORDER BY date_action DESC"
        );
        $stmt->execute([':like' => $like]);
    } else {
        $stmt = $pdo->query("SELECT * FROM journal_modifications ORDER BY date_action DESC");
    }
    $toutesEntrees = $stmt->fetchAll();

    $groupes = [];

    if ($groupe === 'action') {
        // Ordre fixe et logique plutôt qu'alphabétique
        foreach ($libellesAction as $cleAction => $label) {
            $sousListe = array_values(array_filter($toutesEntrees, fn($e) => $e['action'] === $cleAction));
            if (!empty($sousListe)) {
                $groupes[$label] = $sousListe;
            }
        }
    } else {
        foreach ($toutesEntrees as $e) {
            switch ($groupe) {
                case 'utilisateur':
                    $cle = $e['nom_user'];
                    break;
                case 'stagiaire':
                    $cle = $e['nom_stagiaire'];
                    break;
                case 'date':
                default:
                    $cle = date('d/m/Y', strtotime($e['date_action']));
                    break;
            }
            $groupes[$cle][] = $e;
        }

        if ($groupe === 'utilisateur' || $groupe === 'stagiaire') {
            ksort($groupes, SORT_NATURAL | SORT_FLAG_CASE);
        }
        // Pour 'date', l'ordre d'insertion suit déjà le tri DESC de la requête.
    }
}

$libellesGroupe = [
    ''            => 'Aucun (vue générale)',
    'date'        => 'Par dates',
    'utilisateur' => 'Par utilisateurs',
    'action'      => 'Par actions',
    'stagiaire'   => 'Par stagiaire',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suivi des modifications — Suivi stagiaire</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="parametres.css">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Barre de recherche plus discrète sur cette page uniquement */
    #suiviSearch {
      font-size: 14px;
      font-weight: normal;
    }
    #suiviSearch::placeholder {
      font-weight: normal;
    }

    .suivi-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      margin-bottom: 22px;
    }

    .suivi-toolbar .search-bar {
      max-width: 420px;
      margin-bottom: 0;
      flex: 1;
      min-width: 240px;
    }

    .suivi-filtre {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .suivi-filtre label {
      font-size: 12.5px;
      font-weight: 600;
      color: var(--color-primary);
      text-transform: uppercase;
      letter-spacing: 0.03em;
      white-space: nowrap;
    }

    .suivi-filtre select {
      border: 1px solid #d8cede;
      border-radius: 8px;
      padding: 8px 10px;
      font-size: 14px;
      font-family: inherit;
      background-color: #faf8f6;
      color: var(--color-text);
    }

    .suivi-filtre select:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(61, 21, 80, 0.13);
    }

    .groupe-bloc {
      margin-bottom: 32px;
    }

    .groupe-titre {
      font-family: var(--font-display);
      font-size: 16px;
      font-weight: 600;
      color: var(--color-primary);
      margin-bottom: 10px;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--color-border);
    }

    .groupe-titre .groupe-count {
      font-size: 12px;
      font-weight: normal;
      color: var(--color-text-muted);
    }

    /* ── Tableau du suivi des actions ── */
    /* Ces styles n'existaient nulle part ailleurs (probablement prévus dans
       parametres.css, absent) : la table était déjà générée correctement en
       PHP (afficherTableauSuivi), simplement sans aucune bordure ni mise en
       forme, ce qui la faisait ressembler à de simples colonnes de texte. */
    .historique-table {
      width: 100%;
      border-collapse: collapse;
      background-color: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      overflow: hidden;
      font-size: 13.5px;
    }

    .historique-table th,
    .historique-table td {
      padding: 10px 14px;
      text-align: left;
      border-bottom: 1px solid var(--color-border);
      vertical-align: top;
    }

    .historique-table thead th {
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      border-bottom: 1px solid var(--color-border);
      white-space: nowrap;
    }

    .historique-table tbody tr:last-child td {
      border-bottom: none;
    }

    .historique-table tbody tr:hover {
      background-color: var(--color-primary-tint);
    }

    /* ── Tags d'action (Ajout / Modification / Suppression), variantes de .tag ── */
    .action-creation {
      background-color: var(--color-success-bg);
      color: var(--color-success-text);
    }

    .action-modification {
      background-color: var(--color-primary-tint);
      color: var(--color-primary);
    }

    .action-suppression {
      background-color: var(--color-danger-bg);
      color: var(--color-danger-text);
    }

    /* ── Pagination de la vue générale ── */
    .pagination {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 20px;
    }

    .pagination-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 34px;
      height: 34px;
      padding: 0 8px;
      border-radius: var(--radius-sm);
      background-color: var(--color-surface);
      border: 1px solid var(--color-border);
      color: var(--color-text);
      font-size: 13.5px;
      font-weight: 600;
      text-decoration: none;
      transition: background-color 0.15s, border-color 0.15s;
    }

    .pagination-link:hover {
      background-color: var(--color-primary-tint);
      border-color: var(--color-primary-light);
    }

    .pagination-link.active {
      background-color: var(--color-primary);
      border-color: var(--color-primary);
      color: #fff;
    }

    body.dark-mode .historique-table {
      background-color: #241a2d;
      border-color: #392c46;
    }

    body.dark-mode .historique-table th,
    body.dark-mode .historique-table td {
      border-color: #392c46;
    }

    body.dark-mode .historique-table thead th {
      background-color: #382c40;
      color: #d8cfe0;
    }

    body.dark-mode .historique-table tbody tr:hover {
      background-color: #382c40;
    }

    body.dark-mode .pagination-link {
      background-color: #241a2d;
      border-color: #392c46;
      color: #e9e2ee;
    }

    body.dark-mode .pagination-link:hover {
      background-color: #382c40;
    }
  </style>
</head>
<body class="<?= bodyClass() ?>">
  <div id="toast" class="toast"></div>

  <header class="header">
    <a href="index.php" style="text-decoration:none;"><h1>Suivi des actions</h1></a>
    <nav class="header-auth">
      <span class="header-user">Bonjour, <?= htmlspecialchars($_SESSION['user_nom']) ?></span>
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


  <main class="content">
    <div class="suivi-toolbar">
      <form method="GET" action="suivi_modifications.php" class="search-bar">
        <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" name="q" id="suiviSearch" placeholder="Rechercher un stagiaire ou un utilisateur" value="<?= htmlspecialchars($recherche) ?>">
        <input type="hidden" name="groupe" value="<?= htmlspecialchars($groupe) ?>">
      </form>

      <form method="GET" action="suivi_modifications.php" class="suivi-filtre">
        <input type="hidden" name="q" value="<?= htmlspecialchars($recherche) ?>">
        <label for="groupeSelect">Regrouper</label>
        <select id="groupeSelect" name="groupe" onchange="this.form.submit()">
          <?php foreach ($libellesGroupe as $valeur => $label): ?>
            <option value="<?= htmlspecialchars($valeur) ?>" <?= $groupe === $valeur ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if ($groupe === ''): ?>

      <?php if (empty($entrees)): ?>
        <div class="empty-state">
          <p><?= $recherche !== '' ? 'Aucun résultat pour cette recherche.' : 'Aucune modification enregistrée pour le moment.' ?></p>
        </div>
      <?php else: ?>
        <?php afficherTableauSuivi($entrees, '', $libellesAction); ?>

        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
              <a href="suivi_modifications.php?page=<?= $p ?><?= $recherche !== '' ? '&q=' . urlencode($recherche) : '' ?>" class="pagination-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    <?php else: ?>

      <?php if (empty($groupes)): ?>
        <div class="empty-state">
          <p><?= $recherche !== '' ? 'Aucun résultat pour cette recherche.' : 'Aucune modification enregistrée pour le moment.' ?></p>
        </div>
      <?php else: ?>
        <?php foreach ($groupes as $titre => $sousEntrees): ?>
          <div class="groupe-bloc">
            <div class="groupe-titre">
              <?= htmlspecialchars($titre) ?>
              <span class="groupe-count">(<?= count($sousEntrees) ?>)</span>
            </div>
            <?php afficherTableauSuivi($sousEntrees, $groupe, $libellesAction); ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php endif; ?>
  </main>

  <script>
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
  </script>
</body>
</html>