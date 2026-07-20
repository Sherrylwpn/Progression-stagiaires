<?php
/**
 * stagiaire_detail_fragment.php
 * Anciennement comp.php (renommé, correction 3.21 : le nom ne disait rien de
 * son rôle). Retourne un FRAGMENT HTML (pas une page complète), chargé en
 * fetch() par index.php pour afficher la fiche détaillée d'un stage dans le
 * panneau latéral, sans rechargement de page.
 */
require_once 'config.php';
requireAuth(); // Correction 3.3 : la fiche d'un stagiaire ne doit pas être consultable sans connexion

$pdo = getDB();

// ── Récupération et validation de l'id (id_stage désormais, cf. correction 3.4) ──
$idStage = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idStage) {
    http_response_code(400);
    exit('ID invalide.');
}

$stmt = $pdo->prepare(
    "SELECT st.id_stage, st.date_debut, st.date_fin,
            s.id_stagiaire, s.nom, s.prenom,
            c.nom AS classe, e.nom AS etablissement
     FROM stage st
     JOIN stagiaire s ON s.id_stagiaire = st.id_stagiaire
     JOIN classe_ref c ON c.id_classe = st.id_classe
     JOIN etablissement_ref e ON e.id_etablissement = st.id_etablissement
     WHERE st.id_stage = ?"
);
$stmt->execute([$idStage]);
$stage = $stmt->fetch();

if (!$stage) {
    http_response_code(404);
    exit('Stagiaire introuvable.');
}

// ── Dernière évaluation enregistrée pour ce stage (état "actuel" de la fiche) ──
$stmt = $pdo->prepare(
    "SELECT * FROM evaluation WHERE id_stage = ? ORDER BY date_evaluation DESC, id_evaluation DESC LIMIT 1"
);
$stmt->execute([$idStage]);
$derniereEvaluation = $stmt->fetch();
$idDerniereEvaluation = $derniereEvaluation ? (int) $derniereEvaluation['id_evaluation'] : null;

/**
 * Récupère toutes les compétences (ou badges) d'un catalogue, avec le niveau
 * attribué lors de la dernière évaluation (NULL si jamais évaluée : cf. correction 3.8,
 * on ne transforme plus silencieusement l'absence d'évaluation en zéro étoile).
 */
function fetchCompetences(PDO $pdo, string $table, string $evalTable, string $idCol, ?int $idEvaluation): array
{
    $sql = "SELECT c.{$idCol} AS id, c.nom, e.niveau
            FROM {$table} c
            LEFT JOIN {$evalTable} e ON e.{$idCol} = c.{$idCol} AND e.id_evaluation = :id_evaluation
            ORDER BY c.nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_evaluation' => $idEvaluation]);
    return $stmt->fetchAll();
}

$techniques = fetchCompetences($pdo, 'competence_technique', 'evaluation_competence_technique', 'id_competence_technique', $idDerniereEvaluation);
$humaines   = fetchCompetences($pdo, 'competence_humaine', 'evaluation_competence_humaine', 'id_competence_humaine', $idDerniereEvaluation);
$badges     = fetchCompetences($pdo, 'badge', 'evaluation_badge', 'id_badge', $idDerniereEvaluation);

$note = ($derniereEvaluation && $derniereEvaluation['note'] !== null) ? (float) $derniereEvaluation['note'] : null;

// ── Historique complet des évaluations de ce stage, pour visualiser la progression ──
$stmt = $pdo->prepare(
    "SELECT id_evaluation, date_evaluation, note FROM evaluation WHERE id_stage = ? ORDER BY date_evaluation ASC, id_evaluation ASC"
);
$stmt->execute([$idStage]);
$historiqueEvaluations = $stmt->fetchAll();

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
 * Génère le HTML des étoiles pour un niveau donné (échelle sur $max).
 * $niveau === null signifie "non évalué" : on l'affiche explicitement plutôt
 * que de dessiner des étoiles vides indiscernables d'un vrai niveau 0 (correction 3.8).
 */
function renderStars(?int $niveau, int $max = 5): string
{
    if ($niveau === null) {
        return '<span class="non-evalue">Non évalué</span>';
    }
    $niveau = max(0, min($max, $niveau));
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
 * Affiche une liste de compétences avec leurs étoiles (ou "Non évalué").
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
        echo renderStars($c['niveau'] !== null ? (int) $c['niveau'] : null, $maxEtoiles);
        echo '</li>';
    }
    echo '</ul>';
}

/**
 * Taux de complétion réel de la dernière évaluation : proportion de critères du
 * catalogue effectivement notés (niveau non NULL), distinct du niveau obtenu.
 */
function tauxCompletion(array ...$groupes): int
{
    $total = 0;
    $evalues = 0;
    foreach ($groupes as $groupe) {
        foreach ($groupe as $item) {
            $total++;
            if ($item['niveau'] !== null) {
                $evalues++;
            }
        }
    }
    return $total > 0 ? (int) round(($evalues / $total) * 100) : 0;
}

$pctCompletion = tauxCompletion($techniques, $humaines, $badges);

$initiales = mb_strtoupper(mb_substr($stage['prenom'], 0, 1) . mb_substr($stage['nom'], 0, 1));
?>
<div class="fiche-header">
  <div class="stagiaire-avatar fiche-avatar"><?= htmlspecialchars($initiales) ?></div>
  <div class="fiche-header-info">
    <h2 class="fiche-name" id="modalTitle"><?= htmlspecialchars($stage['nom']) ?> <?= htmlspecialchars($stage['prenom']) ?></h2>
    <div class="stagiaire-tags">
      <span class="tag tag-classe"><?= htmlspecialchars($stage['classe']) ?></span>
      <span class="tag tag-etablissement"><?= htmlspecialchars($stage['etablissement']) ?></span>
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
  <p class="fiche-empty" style="margin-top:6px;"><?= $pctCompletion ?>% des critères évalués lors de la dernière séance.</p>
  <?php if ($derniereEvaluation && !empty($derniereEvaluation['commentaire'])): ?>
    <!-- Correction 3.15 : le commentaire de l'évaluateur, saisi depuis
         formulaire_stagiaires.php, est maintenant affiché ici. -->
    <p class="fiche-commentaire" style="margin-top:10px;white-space:pre-wrap;">
      <?= nl2br(htmlspecialchars($derniereEvaluation['commentaire'])) ?>
    </p>
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

<section class="fiche-section">
  <h3>Progression (historique des évaluations)</h3>
  <?php if (count($historiqueEvaluations) < 2): ?>
    <p class="fiche-empty">
      <?= count($historiqueEvaluations) === 1 ? 'Une seule séance d\'évaluation enregistrée pour le moment.' : 'Aucune séance d\'évaluation enregistrée pour le moment.' ?>
      Réalisez plusieurs évaluations au fil du stage pour visualiser la progression.
    </p>
  <?php else: ?>
    <ul class="competence-list">
      <?php foreach ($historiqueEvaluations as $ev): ?>
        <li class="competence-item">
          <span class="competence-nom"><?= htmlspecialchars(date('d/m/Y', strtotime($ev['date_evaluation']))) ?></span>
          <span><?= $ev['note'] !== null ? htmlspecialchars(formatNote((float) $ev['note'])) . ' / 20' : 'Non noté' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php if (isLoggedIn()): ?>
<div class="fiche-actions">
  <a href="formulaire_stagiaires.php?id=<?= (int) $stage['id_stage'] ?>" class="fiche-btn fiche-btn-edit">Modifier / nouvelle évaluation</a>
  <form method="POST" action="delete_stagiaire.php" onsubmit="return confirm('Supprimer définitivement ce stage et toutes ses évaluations ?');" style="flex:1;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int) $stage['id_stage'] ?>">
    <button type="submit" class="fiche-btn fiche-btn-delete" style="width:100%;">Supprimer</button>
  </form>
</div>
<?php endif; ?>