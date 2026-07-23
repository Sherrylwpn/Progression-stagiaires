<?php
/**
 * archive.php
 * Liste des stages ARCHIVÉS (années révolues, archivées automatiquement à
 * chaque chargement de index.php / de cette page — cf. archiverAnneesRevolues()
 * dans config.php — ou archivées manuellement depuis la fiche détaillée).
 * Un stage archivé n'apparaît plus sur l'accueil, mais reste consultable et
 * peut être désarchivé à tout moment.
 */
require_once 'config.php';
requireAuth();

$pdo = getDB();

// On applique aussi l'archivage automatique ici : si quelqu'un arrive
// directement sur cette page sans être passé par l'accueil, l'année qui vient
// de basculer est prise en compte quand même.
archiverAnneesRevolues($pdo);

$stagesArchives = $pdo->query("
  SELECT
    st.id_stage, s.nom, s.prenom, c.nom AS classe, e.nom AS etablissement,
    st.date_debut, st.date_fin,
    dern.note AS note
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
  WHERE st.archive = 1
  ORDER BY st.date_debut DESC, s.nom, s.prenom
")->fetchAll();

// Regroupement par année (même convention que l'accueil : année de la date de début)
$parAnnee = [];
foreach ($stagesArchives as $s) {
    $annee = $s['date_debut'] ? date('Y', strtotime($s['date_debut'])) : 'Année inconnue';
    $parAnnee[$annee][] = $s;
}
krsort($parAnnee); // les années les plus récentes en premier

/**
 * Formate la note sans zéro décimal inutile (15.50 -> 15.5, 16.00 -> 16).
 */
function formatNoteArchive(?float $note): string
{
    if ($note === null) {
        return '—';
    }
    return rtrim(rtrim(number_format($note, 2, '.', ''), '0'), '.') . ' / 20';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Archives — Suivi stagiaire</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="index.css">
</head>
<body class="<?= bodyClass() ?>">
  <div id="toast" class="toast"></div>

  <header class="header">
    <a href="index.php" class="back-btn">&larr; Retour à l'accueil</a>
    <h1>Archives</h1>
  </header>

  <main class="content">
    <?php if (empty($stagesArchives)): ?>
      <div class="form-col" style="max-width:600px;margin:0 auto;">
        <p class="fiche-empty">Aucun stagiaire archivé pour le moment. Les stages dont l'année est révolue sont archivés automatiquement, et vous pouvez aussi archiver une fiche manuellement depuis sa page de détail.</p>
      </div>
    <?php else: ?>
      <?php foreach ($parAnnee as $annee => $stages): ?>
        <section class="form-col" style="margin-bottom:24px;">
          <h3><?= htmlspecialchars($annee) ?> <span style="font-weight:500;color:var(--color-text-muted);font-size:14px;">(<?= count($stages) ?> stagiaire<?= count($stages) > 1 ? 's' : '' ?>)</span></h3>
          <table class="print-table" style="margin-top:12px;">
            <thead>
              <tr>
                <th style="width:auto;">Nom</th>
                <th style="width:auto;">Classe</th>
                <th style="width:auto;">Établissement</th>
                <th style="width:auto;">Période</th>
                <th style="width:auto;">Dernière note</th>
                <th style="width:1%;white-space:nowrap;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stages as $s): ?>
                <tr>
                  <td><?= htmlspecialchars($s['nom'] . ' ' . $s['prenom']) ?></td>
                  <td><?= htmlspecialchars($s['classe']) ?></td>
                  <td><?= htmlspecialchars($s['etablissement']) ?></td>
                  <td>
                    <?= $s['date_debut'] ? htmlspecialchars(date('d/m/Y', strtotime($s['date_debut']))) : '—' ?>
                    →
                    <?= $s['date_fin'] ? htmlspecialchars(date('d/m/Y', strtotime($s['date_fin']))) : '—' ?>
                  </td>
                  <td><?= htmlspecialchars(formatNoteArchive($s['note'] !== null ? (float) $s['note'] : null)) ?></td>
                  <td style="white-space:nowrap;">
                    <a href="formulaire_stagiaires.php?id=<?= (int) $s['id_stage'] ?>" style="margin-right:10px;color:var(--color-primary);font-weight:600;">Voir la fiche</a>
                    <form method="POST" action="archiver_stagiaire.php" style="display:inline;" onsubmit="return confirm('Désarchiver ce stagiaire ? Il réapparaîtra dans la liste principale.');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                      <input type="hidden" name="id" value="<?= (int) $s['id_stage'] ?>">
                      <input type="hidden" name="action" value="restaurer">
                      <button type="submit" style="background:none;border:none;color:var(--color-accent-dark);font-weight:600;cursor:pointer;padding:0;font:inherit;">Désarchiver</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <script>
    <?php if (isset($_GET['restaure'])): ?>
    // Petit popup de confirmation après désarchivage
    (function() {
      const toast = document.getElementById('toast');
      toast.textContent = 'Stagiaire désarchivé avec succès.';
      toast.classList.add('show');
      setTimeout(function() {
        toast.classList.remove('show');
      }, 2500);
      window.history.replaceState({}, document.title, 'archive.php');
    })();
    <?php endif; ?>
  </script>
</body>
</html>
