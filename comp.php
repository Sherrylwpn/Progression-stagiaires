<?php
require_once 'config.php';
$pdo = getDB();

// ── Récupération et validation de l'id ──
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('ID invalide.');
}

$stmt = $pdo->prepare("SELECT * FROM stagiaire WHERE id_stagiaire = ?");
$stmt->execute([$id]);
$stagiaire = $stmt->fetch();

if (!$stagiaire) {
    http_response_code(404);
    exit('Stagiaire introuvable.');
}

/**
 * Récupère toutes les compétences (ou badges) d'une table donnée,
 * avec le niveau attribué au stagiaire (NULL si pas encore évalué).
 */
function fetchCompetences(PDO $pdo, string $table, string $evalTable, string $idCol, int $idStagiaire): array
{
    $sql = "SELECT c.{$idCol} AS id, c.nom, e.niveau
            FROM {$table} c
            LEFT JOIN {$evalTable} e ON e.{$idCol} = c.{$idCol} AND e.id_stagiaire = :id
            ORDER BY c.nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $idStagiaire]);
    return $stmt->fetchAll();
}

$techniques = fetchCompetences($pdo, 'competence_technique', 'evaluation_competence_technique', 'id_competence_technique', $id);
$humaines   = fetchCompetences($pdo, 'competence_humaine', 'evaluation_competence_humaine', 'id_competence_humaine', $id);
$badges     = fetchCompetences($pdo, 'badge', 'evaluation_badge', 'id_badge', $id);

// ── Notation globale (/20), jointure simple sur id_stagiaire ──
$stmtNote = $pdo->prepare("SELECT note FROM notation WHERE id_stagiaire = ?");
$stmtNote->execute([$id]);
$noteRow = $stmtNote->fetch();
$note    = $noteRow ? (float) $noteRow['note'] : null;

/**
 * Formate la note sans zéro décimal inutile (15.50 -> 15.5, 16.00 -> 16).
 */
function formatNote(float $note): string
{
    return rtrim(rtrim(number_format($note, 2, '.', ''), '0'), '.');
}

// Échelles d'étoiles : techniques et badges sur 3, compétences humaines sur 5
const MAX_ETOILES_TECHNIQUE = 3;
const MAX_ETOILES_HUMAINE   = 5;
const MAX_ETOILES_BADGE     = 3;

/**
 * Génère le HTML des étoiles pour un niveau donné (échelle sur 5).
 */
function renderStars(?int $niveau, int $max = 5): string
{
    $niveau = max(0, min($max, (int) $niveau));
    $html = '<div class="stars" role="img" aria-label="' . $niveau . ' sur ' . $max . '">';
    for ($i = 1; $i <= $max; $i++) {
        $filled = $i <= $niveau;
        $couleur = $filled ? '#b8862e' : '#e5dde8';
        $html .= '<svg class="star ' . ($filled ? 'star-filled' : 'star-empty') . '" width="18" height="18" viewBox="0 0 24 24" style="fill:' . $couleur . ';flex-shrink:0;">'
               . '<polygon points="12,2 15,9 22,9 16.5,13.5 18.5,21 12,17 5.5,21 7.5,13.5 2,9 9,9"></polygon>'
               . '</svg>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Affiche une liste de compétences avec leurs étoiles.
 */
function renderListe(array $items, string $labelVide, int $maxEtoiles = 5): void
{
    if (empty($items)) {
        echo '<p class="fiche-empty">' . htmlspecialchars($labelVide) . '</p>';
        return;
    }
    echo '<ul class="competence-list">';
    foreach ($items as $c) {
        echo '<li class="competence-item">';
        echo '<span class="competence-nom">' . htmlspecialchars($c['nom']) . '</span>';
        echo renderStars($c['niveau'] !== null ? (int) $c['niveau'] : 0, $maxEtoiles);
        echo '</li>';
    }
    echo '</ul>';
}

$initiales = mb_strtoupper(mb_substr($stagiaire['prenom'], 0, 1) . mb_substr($stagiaire['nom'], 0, 1));
?>
<div class="fiche-header">
  <div class="stagiaire-avatar fiche-avatar"><?= htmlspecialchars($initiales) ?></div>
  <div class="fiche-header-info">
    <h2 class="fiche-name"><?= htmlspecialchars($stagiaire['nom']) ?> <?= htmlspecialchars($stagiaire['prenom']) ?></h2>
    <div class="stagiaire-tags">
      <span class="tag tag-classe"><?= htmlspecialchars($stagiaire['classe']) ?></span>
      <span class="tag tag-etablissement"><?= htmlspecialchars($stagiaire['etablissement']) ?></span>
    </div>
  </div>
</div>

<section class="fiche-section">
  <h3>Notation globale</h3>
  <?php if ($note !== null): ?>
    <p class="fiche-note-value"><?= htmlspecialchars(formatNote($note)) ?> <span class="fiche-note-max">/ 20</span></p>
  <?php else: ?>
    <p class="fiche-empty">Aucune notation attribuée pour le moment.</p>
  <?php endif; ?>
</section>

<section class="fiche-section">
  <h3>Compétences techniques</h3>
  <?php renderListe($techniques, 'Aucune compétence technique évaluée pour le moment.', MAX_ETOILES_TECHNIQUE); ?>
</section>

<section class="fiche-section">
  <h3>Compétences humaines</h3>
  <?php renderListe($humaines, 'Aucune compétence humaine évaluée pour le moment.', MAX_ETOILES_HUMAINE); ?>
</section>

<section class="fiche-section">
  <h3>Badges</h3>
  <?php renderListe($badges, 'Aucun badge attribué pour le moment.', MAX_ETOILES_BADGE); ?>
</section>

<?php if (isLoggedIn()): ?>
<div class="fiche-actions">
  <a href="formulaire_stagiaires.php?id=<?= (int) $stagiaire['id_stagiaire'] ?>" class="fiche-btn fiche-btn-edit">Modifier</a>
  <form method="POST" action="delete_stagiaire.php" onsubmit="return confirm('Supprimer définitivement ce stagiaire et toutes ses évaluations ?');" style="flex:1;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int) $stagiaire['id_stagiaire'] ?>">
    <button type="submit" class="fiche-btn fiche-btn-delete" style="width:100%;">Supprimer</button>
  </form>
</div>
<?php endif; ?>