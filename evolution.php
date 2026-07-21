<?php
/**
 * evolution.php
 * Page dédiée à l'affichage de l'évolution des compétences / badges / notation
 * d'un stage, sur toute la durée du suivi. Extrait de formulaire_stagiaires.php
 * (qui redirige désormais vers l'accueil juste après l'enregistrement, laissant
 * trop peu de temps pour consulter ces graphiques) pour être consultable à tout
 * moment, indépendamment de l'édition d'une fiche.
 */
require_once 'config.php';
requireAuth();

$idStage = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idStage) {
    header("Location: index.php");
    exit;
}

$pdo = getDB();

// ── Informations générales du stage (pour le titre de la page) ──
$stmt = $pdo->prepare(
    "SELECT s.nom, s.prenom
     FROM stage st JOIN stagiaire s ON s.id_stagiaire = st.id_stagiaire
     WHERE st.id_stage = ?"
);
$stmt->execute([$idStage]);
$stagiaire = $stmt->fetch();

if (!$stagiaire) {
    header("Location: index.php");
    exit;
}

// ── Référentiels (noms des compétences / badges) ──
$competencesTechniques = $pdo->query("SELECT id_competence_technique, nom FROM competence_technique ORDER BY nom")->fetchAll();
$competencesHumaines   = $pdo->query("SELECT id_competence_humaine, nom FROM competence_humaine ORDER BY nom")->fetchAll();
$badges                = $pdo->query("SELECT id_badge, nom FROM badge ORDER BY nom")->fetchAll();

$nomsTech    = array_column($competencesTechniques, 'nom', 'id_competence_technique');
$nomsHumaine = array_column($competencesHumaines, 'nom', 'id_competence_humaine');
$nomsBadge   = array_column($badges, 'nom', 'id_badge');

// ── Historique des évaluations ──
// On trace ici l'évolution de chaque compétence / badge PRIS INDIVIDUELLEMENT,
// mais on ne garde que ceux dont le niveau a réellement varié au fil des
// séances : une compétence toujours notée pareil n'apporte rien à afficher.
$labelsSeances      = [];
$evolutionTechnique = [];
$evolutionHumaine   = [];
$evolutionBadge     = [];
$evolutionNote      = [];

$stmtSeances = $pdo->prepare(
    "SELECT id_evaluation, date_evaluation, note FROM evaluation WHERE id_stage = ? ORDER BY date_evaluation ASC, id_evaluation ASC"
);
$stmtSeances->execute([$idStage]);
$seances    = $stmtSeances->fetchAll();
$idsSeances = array_column($seances, 'id_evaluation');
$labelsSeances = array_map(
    fn($s) => $s['date_evaluation'] ? date('d/m/Y', strtotime($s['date_evaluation'])) : '—',
    $seances
);

/**
 * Construit, pour une table de niveaux donnée (technique / humaine / badge),
 * la liste des items dont le niveau a varié entre au moins deux séances,
 * chacun avec sa série de valeurs alignée sur $idsSeances (null = non noté
 * lors de cette séance-là, pour que la courbe laisse un trou plutôt que de
 * redescendre artificiellement à zéro).
 */
$construireEvolution = function (string $table, string $colonneId, array $noms) use ($pdo, $idsSeances): array {
    if (empty($idsSeances)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($idsSeances), '?'));
    $stmt = $pdo->prepare("SELECT id_evaluation, {$colonneId} AS id_item, niveau FROM {$table} WHERE id_evaluation IN ({$placeholders})");
    $stmt->execute($idsSeances);

    $parItem = [];
    foreach ($stmt->fetchAll() as $ligne) {
        $parItem[(int) $ligne['id_item']][(int) $ligne['id_evaluation']] = (int) $ligne['niveau'];
    }

    $resultat = [];
    foreach ($parItem as $idItem => $valeursParSeance) {
        $valeursUniques = array_unique(array_values($valeursParSeance));
        if (count($valeursParSeance) < 2 || count($valeursUniques) < 2) {
            continue; // pas assez de points, ou toujours la même note : on n'affiche pas
        }
        $serie = [];
        foreach ($idsSeances as $idSeance) {
            $serie[] = $valeursParSeance[$idSeance] ?? null;
        }
        $resultat[] = ['nom' => $noms[$idItem] ?? ('Compétence #' . $idItem), 'valeurs' => $serie];
    }
    return $resultat;
};

$evolutionTechnique = $construireEvolution('evaluation_competence_technique', 'id_competence_technique', $nomsTech);
$evolutionHumaine   = $construireEvolution('evaluation_competence_humaine', 'id_competence_humaine', $nomsHumaine);
$evolutionBadge     = $construireEvolution('evaluation_badge', 'id_badge', $nomsBadge);

// Note globale : même principe, on ne la trace que si elle a varié.
$valeursNote  = array_column($seances, 'note');
$notesConnues = array_filter($valeursNote, fn($v) => $v !== null);
if (count(array_unique($notesConnues)) >= 2) {
    $evolutionNote = array_map(fn($v) => $v !== null ? (float) $v : null, $valeursNote);
}

$aQuelqueChoseAAfficher = !empty($evolutionTechnique) || !empty($evolutionHumaine) || !empty($evolutionBadge) || !empty($evolutionNote);
$aucuneSeance = empty($seances);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Évolution — <?= htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></title>
  <link rel="stylesheet" href="style.css">
  <?php if ($aQuelqueChoseAAfficher): ?>
  <script src="chart.min.js"></script>
  <?php endif; ?>
</head>
<body class="<?= bodyClass() ?>">
  <header class="header">
    <a href="formulaire_stagiaires.php?id=<?= (int) $idStage ?>" class="back-btn">&larr; Retour à la fiche</a>
    <h1>Évolution — <?= htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></h1>
  </header>

  <main class="content">
    <div class="form-grid">
      <?php if ($aucuneSeance): ?>
        <section class="form-col" style="grid-column: 1 / -1;">
          <p class="fiche-empty">Aucune évaluation enregistrée pour ce stage pour l'instant.</p>
        </section>
      <?php elseif (!$aQuelqueChoseAAfficher): ?>
        <section class="form-col" style="grid-column: 1 / -1;">
          <p class="fiche-empty">Aucune compétence, badge ou note n'a encore varié d'une évaluation à l'autre : rien à représenter pour le moment. Les courbes apparaîtront dès qu'un niveau changera entre deux séances.</p>
        </section>
      <?php else: ?>
        <!-- Graphiques d'évolution : uniquement les compétences / la note qui ont
             réellement varié au fil des séances d'évaluation (celles restées
             stables ne sont pas affichées, elles n'apporteraient rien à voir). -->
        <section class="form-col evolution-col">
          <p class="fiche-empty" style="margin-bottom:14px;">Seules les compétences dont le niveau a changé au fil des évaluations sont affichées ci-dessous.</p>
          <div class="evolution-grid">
            <?php if (!empty($evolutionNote)): ?>
            <div class="evolution-chart-card">
              <p class="evolution-chart-title">Notation globale <span>/20</span></p>
              <div class="evolution-chart-canvas-wrap"><canvas id="evolutionChartNote"></canvas></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($evolutionTechnique)): ?>
            <div class="evolution-chart-card">
              <p class="evolution-chart-title">Compétences techniques <span>/3</span></p>
              <div class="evolution-chart-canvas-wrap"><canvas id="evolutionChartTech"></canvas></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($evolutionHumaine)): ?>
            <div class="evolution-chart-card">
              <p class="evolution-chart-title">Compétences humaines <span>/5</span></p>
              <div class="evolution-chart-canvas-wrap"><canvas id="evolutionChartHumaine"></canvas></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($evolutionBadge)): ?>
            <div class="evolution-chart-card">
              <p class="evolution-chart-title">Badges <span>/3</span></p>
              <div class="evolution-chart-canvas-wrap"><canvas id="evolutionChartBadge"></canvas></div>
            </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
    </div>
  </main>

  <script>
    <?php if ($aQuelqueChoseAAfficher): ?>
    // Courbes d'évolution : une ligne par compétence / badge dont le niveau a
    // réellement varié au fil des séances (celles restées stables ne sont pas
    // envoyées au JS, cf. $construireEvolution côté PHP).
    (function() {
      if (typeof Chart === 'undefined') return;

      const labels = <?= json_encode($labelsSeances) ?>;
      // Palette cyclique, réutilisée dans l'ordre pour chaque nouvelle courbe d'un graphique.
      const palette = ['#3d1550', '#b8862e', '#2f8f45', '#6a2f86', '#d9573f', '#1f6b8f', '#8a5aa8', '#c9a227'];

      function degrade(ctx, couleur) {
        const g = ctx.createLinearGradient(0, 0, 0, 220);
        g.addColorStop(0, couleur + '2a');
        g.addColorStop(1, couleur + '00');
        return g;
      }

      // items: [{nom, valeurs}, ...] — une courbe par item
      function tracerMultiCourbes(idCanvas, items, max, suffixe) {
        const canvas = document.getElementById(idCanvas);
        if (!canvas || !items || items.length === 0) return;
        const ctx = canvas.getContext('2d');

        const datasets = items.map(function(item, i) {
          const couleur = palette[i % palette.length];
          return {
            label: item.nom,
            data: item.valeurs,
            borderColor: couleur,
            backgroundColor: degrade(ctx, couleur),
            spanGaps: true,
            tension: 0.35,
            fill: items.length === 1, // un seul dégradé de remplissage a du sens ; à plusieurs courbes ça surcharge
            borderWidth: 2.5,
            pointBackgroundColor: couleur,
            pointBorderColor: '#fff',
            pointBorderWidth: 1.5,
            pointRadius: 4,
            pointHoverRadius: 6,
          };
        });

        new Chart(canvas, {
          type: 'line',
          data: { labels: labels, datasets: datasets },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: { min: 0, max: max, ticks: { stepSize: max <= 5 ? 1 : 5 } },
              x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 5 } }
            },
            plugins: {
              legend: {
                display: datasets.length > 1,
                position: 'bottom',
                labels: { boxWidth: 10, font: { size: 11 } }
              },
              tooltip: {
                callbacks: {
                  label: function(item) {
                    const val = item.parsed.y === null ? 'Non notée' : (item.parsed.y + suffixe);
                    return item.dataset.label + ' : ' + val;
                  }
                }
              }
            }
          }
        });
      }

      <?php if (!empty($evolutionNote)): ?>
      tracerMultiCourbes('evolutionChartNote', [{ nom: 'Note globale', valeurs: <?= json_encode($evolutionNote) ?> }], 20, '/20');
      <?php endif; ?>
      <?php if (!empty($evolutionTechnique)): ?>
      tracerMultiCourbes('evolutionChartTech', <?= json_encode(array_map(fn($i) => ['nom' => $i['nom'], 'valeurs' => $i['valeurs']], $evolutionTechnique)) ?>, 3, '/3');
      <?php endif; ?>
      <?php if (!empty($evolutionHumaine)): ?>
      tracerMultiCourbes('evolutionChartHumaine', <?= json_encode(array_map(fn($i) => ['nom' => $i['nom'], 'valeurs' => $i['valeurs']], $evolutionHumaine)) ?>, 5, '/5');
      <?php endif; ?>
      <?php if (!empty($evolutionBadge)): ?>
      tracerMultiCourbes('evolutionChartBadge', <?= json_encode(array_map(fn($i) => ['nom' => $i['nom'], 'valeurs' => $i['valeurs']], $evolutionBadge)) ?>, 3, '/3');
      <?php endif; ?>
    })();
    <?php endif; ?>
  </script>
</body>
</html>