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
// Logique factorisée dans config.php (calculerEvolutionStage), réutilisée
// telle quelle par la fiche imprimable de formulaire_stagiaires.php.
$evolution           = calculerEvolutionStage($pdo, $idStage, $nomsTech, $nomsHumaine, $nomsBadge);
$labelsSeances       = $evolution['labels']    ?? [];
$evolutionTechnique  = array_values($evolution['technique'] ?? []);
$evolutionHumaine    = array_values($evolution['humaine']   ?? []);
$evolutionBadge      = array_values($evolution['badge']     ?? []);
$evolutionNote       = $evolution['note']      ?? [];

$aQuelqueChoseAAfficher = !empty($evolutionTechnique) || !empty($evolutionHumaine) || !empty($evolutionBadge) || !empty($evolutionNote);

// CORRECTION : $seances n'existait plus depuis la factorisation dans
// calculerEvolutionStage(). On se base désormais sur les libellés de séances.
$aucuneSeance = empty($labelsSeances);

// Prépare les données JS (listes simples nom / valeurs)
$formaterItems = function (array $items): array {
    return array_values(array_map(
        fn($i) => ['nom' => $i['nom'], 'valeurs' => $i['valeurs']],
        $items
    ));
};

// ── MODE DIAGNOSTIC : ouvrir evolution.php?id=XX&debug=1 ──
// À SUPPRIMER une fois le problème résolu (affiche la structure de la base).
$debugHtml = '';
if (isset($_GET['debug'])) {
    $h = fn($s) => htmlspecialchars((string) $s);
    $debugHtml .= '<h3>1. Résultat de calculerEvolutionStage()</h3>';
    $debugHtml .= '<p>Séances (labels) : <b>' . count($labelsSeances) . '</b> — '
                . $h(json_encode($labelsSeances, JSON_UNESCAPED_UNICODE)) . '</p>';
    $debugHtml .= '<p>Compétences techniques variantes : <b>' . count($evolutionTechnique) . '</b><br>'
                . 'Compétences humaines variantes : <b>' . count($evolutionHumaine) . '</b><br>'
                . 'Badges variants : <b>' . count($evolutionBadge) . '</b><br>'
                . 'Points de note : <b>' . count($evolutionNote) . '</b></p>';
    $debugHtml .= '<p><b>Interprétation :</b> ';
    if (count($labelsSeances) === 0) {
        $debugHtml .= 'aucune séance trouvée → l\'enregistrement ne crée pas d\'historique, ou la fonction lit la mauvaise table/le mauvais id_stage.';
    } elseif (count($labelsSeances) === 1) {
        $debugHtml .= 'une seule séance → rien ne peut varier. Vos modifications écrasent la séance existante au lieu d\'en créer une nouvelle.';
    } elseif (!$aQuelqueChoseAAfficher) {
        $debugHtml .= 'plusieurs séances mais aucune variation détectée → vérifier que les niveaux diffèrent vraiment entre séances, ou la logique de comparaison dans calculerEvolutionStage().';
    } else {
        $debugHtml .= 'les données sont correctes → le problème est côté affichage/JS (console F12, chemin de chart.min.js).';
    }
    $debugHtml .= '</p>';
    $debugHtml .= '<h3>2. Données brutes</h3><pre>' . $h(var_export($evolution, true)) . '</pre>';

    try {
        $debugHtml .= '<h3>3. Tables liées aux évaluations</h3>';
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $debugHtml .= '<p>Toutes les tables : ' . $h(implode(', ', $tables)) . '</p>';
        foreach ($tables as $t) {
            if (preg_match('/eval|seance|stage|note|comp|badge/i', $t)) {
                $cols = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $t) . '`')->fetchAll(PDO::FETCH_COLUMN);
                $n = (int) $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`')->fetchColumn();
                $debugHtml .= '<p><b>' . $h($t) . '</b> (' . $n . ' lignes) : ' . $h(implode(', ', $cols)) . '</p>';
            }
        }
    } catch (Throwable $e) {
        $debugHtml .= '<p>Diagnostic de schéma indisponible : ' . $h($e->getMessage()) . '</p>';
    }
}
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
    <?php if ($debugHtml !== ''): ?>
    <div style="background:#fff8e1;border:2px solid #b8862e;padding:14px;margin:0 0 16px;font-size:13px;overflow:auto;">
      <h2 style="margin-top:0;">Diagnostic (à retirer après correction)</h2>
      <?= $debugHtml ?>
    </div>
    <?php endif; ?>
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
    // renvoyées par calculerEvolutionStage()).
    (function() {
      if (typeof Chart === 'undefined') {
        console.error('Chart.js non chargé : vérifiez le chemin de chart.min.js');
        return;
      }

      const labels = <?= json_encode($labelsSeances, JSON_UNESCAPED_UNICODE) ?>;
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
      tracerMultiCourbes('evolutionChartNote', [{ nom: 'Note globale', valeurs: <?= json_encode(array_values($evolutionNote)) ?> }], 20, '/20');
      <?php endif; ?>
      <?php if (!empty($evolutionTechnique)): ?>
      tracerMultiCourbes('evolutionChartTech', <?= json_encode($formaterItems($evolutionTechnique), JSON_UNESCAPED_UNICODE) ?>, 3, '/3');
      <?php endif; ?>
      <?php if (!empty($evolutionHumaine)): ?>
      tracerMultiCourbes('evolutionChartHumaine', <?= json_encode($formaterItems($evolutionHumaine), JSON_UNESCAPED_UNICODE) ?>, 5, '/5');
      <?php endif; ?>
      <?php if (!empty($evolutionBadge)): ?>
      tracerMultiCourbes('evolutionChartBadge', <?= json_encode($formaterItems($evolutionBadge), JSON_UNESCAPED_UNICODE) ?>, 3, '/3');
      <?php endif; ?>
    })();
    <?php endif; ?>
  </script>
</body>
</html>