<?php
require_once 'config.php';
$pdo = getDB();

// ── Liste des stagiaires + nombre d'évaluations déjà saisies ──
$stagiaires = $pdo->query("
  SELECT
    s.id_stagiaire, s.nom, s.prenom, s.classe, s.etablissement,
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

  <header class="header">
    <h1>Suivi stagiaire</h1>
  </header>

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
        <button class="filters-btn" id="filtersBtn" aria-expanded="false">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
            <polygon points="2,5 22,5 14,15 14,21 10,21 10,15"></polygon>
          </svg>
          Filtres
        </button>
        <a href="formulaire_stagiaires.php" class="add-btn">+ Nouveau stagiaire</a>
      </div>
    </div>

    <div class="filters-panel" id="filtersPanel" hidden>
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
          $rempli    = (int) $s['nb_tech'] + (int) $s['nb_hum'] + (int) $s['nb_badge'];
          $pct       = $totalItems > 0 ? (int) round(($rempli / $totalItems) * 100) : 0;
        ?>
          <article
            class="stagiaire-card"
            data-nom="<?= htmlspecialchars(mb_strtolower($s['nom'] . ' ' . $s['prenom'])) ?>"
            data-classe="<?= htmlspecialchars($s['classe']) ?>"
            data-etablissement="<?= htmlspecialchars($s['etablissement']) ?>"
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

  <script>
    const searchInput = document.getElementById('searchInput');
    const filtersBtn = document.getElementById('filtersBtn');
    const filtersPanel = document.getElementById('filtersPanel');
    const filterClasse = document.getElementById('filterClasse');
    const filterEtablissement = document.getElementById('filterEtablissement');
    const grid = document.getElementById('stagiaireGrid');
    const noResults = document.getElementById('noResults');

    if (filtersBtn && filtersPanel) {
      filtersBtn.addEventListener('click', () => {
        const isHidden = filtersPanel.hasAttribute('hidden');
        if (isHidden) {
          filtersPanel.removeAttribute('hidden');
          filtersBtn.setAttribute('aria-expanded', 'true');
        } else {
          filtersPanel.setAttribute('hidden', '');
          filtersBtn.setAttribute('aria-expanded', 'false');
        }
      });
    }

    function applyFilters() {
      if (!grid) return;

      const query = searchInput.value.trim().toLowerCase();
      const classe = filterClasse ? filterClasse.value : '';
      const etablissement = filterEtablissement ? filterEtablissement.value : '';
      const cards = grid.querySelectorAll('.stagiaire-card');
      let visibleCount = 0;

      cards.forEach(card => {
        const matchesQuery = card.dataset.nom.includes(query);
        const matchesClasse = !classe || card.dataset.classe === classe;
        const matchesEtablissement = !etablissement || card.dataset.etablissement === etablissement;
        const visible = matchesQuery && matchesClasse && matchesEtablissement;

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
  </script>

</body>
</html>