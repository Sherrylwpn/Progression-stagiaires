<?php
require_once 'config.php';
requireAuth(); // Réservé aux 2 utilisateurs connectés

$pdo = getDB();

// Pagination simple pour ne pas charger un historique trop long d'un coup
$page       = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$parPage    = 40;
$offset     = ($page - 1) * $parPage;

$recherche = trim($_GET['q'] ?? '');

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

$libellesAction = [
    'creation'     => 'Création',
    'modification' => 'Modification',
    'suppression'  => 'Suppression',
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
</head>
<body class="<?= bodyClass() ?>">
  <header class="header">
    <a href="index.php" class="back-btn">&larr; Retour</a>
    <h1>Suivi des modifications</h1>
  </header>

  <main class="content">
    <form method="GET" action="historique.php" class="search-bar" style="max-width:480px;margin-bottom:22px;">
      <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" name="q" id="historiqueSearch" placeholder="Rechercher un stagiaire ou un utilisateur" value="<?= htmlspecialchars($recherche) ?>">
    </form>

    <?php if (empty($entrees)): ?>
      <div class="empty-state">
        <p><?= $recherche !== '' ? 'Aucun résultat pour cette recherche.' : 'Aucune modification enregistrée pour le moment.' ?></p>
      </div>
    <?php else: ?>
      <table class="historique-table" id="historiqueTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Utilisateur</th>
            <th>Action</th>
            <th>Stagiaire concerné</th>
            <th>Détails</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entrees as $e): ?>
            <tr>
              <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($e['date_action']))) ?></td>
              <td><?= htmlspecialchars($e['nom_user']) ?></td>
              <td>
                <span class="tag action-<?= htmlspecialchars($e['action']) ?>">
                  <?= htmlspecialchars($libellesAction[$e['action']] ?? $e['action']) ?>
                </span>
              </td>
              <td>
                <?php if ($e['id_stagiaire']): ?>
                  <?= htmlspecialchars($e['nom_stagiaire']) ?>
                <?php else: ?>
                  <?= htmlspecialchars($e['nom_stagiaire']) ?> <span class="non-evalue">(supprimé)</span>
                <?php endif; ?>
              </td>
              <td><?= $e['details'] ? htmlspecialchars($e['details']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="historique.php?page=<?= $p ?><?= $recherche !== '' ? '&q=' . urlencode($recherche) : '' ?>" class="pagination-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</body>
</html>