<?php
require_once 'config.php';
requireAuth(); // Seuls les 2 utilisateurs connectés peuvent accéder à ce formulaire

$erreur  = '';
$succes  = '';

// Mode édition si un id est fourni (en GET pour l'affichage, en POST pour la soumission)
$idEdition = filter_input(INPUT_POST, 'id_stagiaire', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// ── Traitement de la soumission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nom           = trim($_POST['nom']           ?? '');
    $prenom        = trim($_POST['prenom']         ?? '');
    $classe        = trim($_POST['classe']         ?? '');
    $etablissement = trim($_POST['etablissement']  ?? '');
    $dateDebut     = trim($_POST['date_debut']     ?? '');
    $dateFin       = trim($_POST['date_fin']       ?? '');

    $notesTech    = $_POST['tech']    ?? []; // [id_competence_technique => niveau]
    $notesHumaine = $_POST['humaine'] ?? []; // [id_competence_humaine   => niveau]
    $notesBadge   = $_POST['badge']   ?? []; // [id_badge                => niveau]

    // Note globale /20 (facultative)
    $note = trim($_POST['note'] ?? '');
    $note = str_replace(',', '.', $note);

    if ($nom === '' || $prenom === '' || $classe === '' || $etablissement === '') {
        $erreur = "Merci de remplir tous les champs des informations générales.";
    } elseif ($dateDebut !== '' && $dateFin !== '' && $dateFin < $dateDebut) {
        $erreur = "La date de fin de période ne peut pas être avant la date de début.";
    } elseif ($note !== '' && (!is_numeric($note) || (float) $note < 0 || (float) $note > 20)) {
        $erreur = "La notation doit être un nombre compris entre 0 et 20.";
    } else {
        $pdo = getDB();

        try {
            $pdo->beginTransaction();

            if ($idEdition) {
                // 1) Mise à jour du stagiaire existant
                $stmt = $pdo->prepare(
                    "UPDATE stagiaire SET nom = :nom, prenom = :prenom, classe = :classe, etablissement = :etablissement,
                     date_debut = :date_debut, date_fin = :date_fin
                     WHERE id_stagiaire = :id"
                );
                $stmt->execute([
                    ':nom'           => $nom,
                    ':prenom'        => $prenom,
                    ':classe'        => $classe,
                    ':etablissement' => $etablissement,
                    ':date_debut'    => $dateDebut !== '' ? $dateDebut : null,
                    ':date_fin'      => $dateFin !== '' ? $dateFin : null,
                    ':id'            => $idEdition,
                ]);
                $idStagiaire = $idEdition;
            } else {
                // 1) Création du stagiaire
                $stmt = $pdo->prepare(
                    "INSERT INTO stagiaire (nom, prenom, classe, etablissement, date_debut, date_fin)
                     VALUES (:nom, :prenom, :classe, :etablissement, :date_debut, :date_fin)"
                );
                $stmt->execute([
                    ':nom'           => $nom,
                    ':prenom'        => $prenom,
                    ':classe'        => $classe,
                    ':etablissement' => $etablissement,
                    ':date_debut'    => $dateDebut !== '' ? $dateDebut : null,
                    ':date_fin'      => $dateFin !== '' ? $dateFin : null,
                ]);
                $idStagiaire = $pdo->lastInsertId();
            }

            // 2) Évaluations compétences techniques
            $stmtTech = $pdo->prepare(
                "INSERT INTO evaluation_competence_technique (id_stagiaire, id_competence_technique, niveau)
                 VALUES (:id_stagiaire, :id_competence, :niveau)
                 ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)"
            );
            $stmtTechDelete = $pdo->prepare(
                "DELETE FROM evaluation_competence_technique WHERE id_stagiaire = :id_stagiaire AND id_competence_technique = :id_competence"
            );
            foreach ($notesTech as $idCompetence => $niveau) {
                $niveau = (int) $niveau;
                if ($niveau >= 1 && $niveau <= 3) {
                    $stmtTech->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_competence' => (int) $idCompetence,
                        ':niveau' => $niveau,
                    ]);
                } else {
                    // Niveau remis à 0 : on supprime l'évaluation existante si elle existe
                    $stmtTechDelete->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_competence' => (int) $idCompetence,
                    ]);
                }
            }

            // 3) Évaluations compétences humaines
            $stmtHumaine = $pdo->prepare(
                "INSERT INTO evaluation_competence_humaine (id_stagiaire, id_competence_humaine, niveau)
                 VALUES (:id_stagiaire, :id_competence, :niveau)
                 ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)"
            );
            $stmtHumaineDelete = $pdo->prepare(
                "DELETE FROM evaluation_competence_humaine WHERE id_stagiaire = :id_stagiaire AND id_competence_humaine = :id_competence"
            );
            foreach ($notesHumaine as $idCompetence => $niveau) {
                $niveau = (int) $niveau;
                if ($niveau >= 1 && $niveau <= 5) {
                    $stmtHumaine->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_competence' => (int) $idCompetence,
                        ':niveau' => $niveau,
                    ]);
                } else {
                    $stmtHumaineDelete->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_competence' => (int) $idCompetence,
                    ]);
                }
            }

            // 4) Évaluations badges
            $stmtBadge = $pdo->prepare(
                "INSERT INTO evaluation_badge (id_stagiaire, id_badge, niveau)
                 VALUES (:id_stagiaire, :id_badge, :niveau)
                 ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)"
            );
            $stmtBadgeDelete = $pdo->prepare(
                "DELETE FROM evaluation_badge WHERE id_stagiaire = :id_stagiaire AND id_badge = :id_badge"
            );
            foreach ($notesBadge as $idBadge => $niveau) {
                $niveau = (int) $niveau;
                if ($niveau >= 1 && $niveau <= 3) {
                    $stmtBadge->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_badge' => (int) $idBadge,
                        ':niveau' => $niveau,
                    ]);
                } else {
                    $stmtBadgeDelete->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_badge' => (int) $idBadge,
                    ]);
                }
            }

            // 5) Notation globale (/20)
            if ($note !== '') {
                $stmtNote = $pdo->prepare(
                    "INSERT INTO notation (id_stagiaire, note) VALUES (:id_stagiaire, :note)
                     ON DUPLICATE KEY UPDATE note = VALUES(note)"
                );
                $stmtNote->execute([
                    ':id_stagiaire' => $idStagiaire,
                    ':note'         => (float) $note,
                ]);
            } else {
                // Champ vidé : on supprime la notation existante s'il y en a une
                $pdo->prepare("DELETE FROM notation WHERE id_stagiaire = :id_stagiaire")
                    ->execute([':id_stagiaire' => $idStagiaire]);
            }

            $pdo->commit();

            logAction(
                $idEdition ? 'modification' : 'creation',
                (int) $idStagiaire,
                $nom . ' ' . $prenom
            );

            $succes = $idEdition ? "Stagiaire modifié avec succès." : "Stagiaire enregistré avec succès.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

// ── Chargement des données existantes si on est en mode édition (affichage du formulaire) ──
$stagiaireData        = null;
$notesTechExistantes    = [];
$notesHumaineExistantes = [];
$notesBadgeExistantes   = [];
$noteExistante          = null;

if ($idEdition && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pdoTmp = getDB();

    $stmt = $pdoTmp->prepare("SELECT * FROM stagiaire WHERE id_stagiaire = ?");
    $stmt->execute([$idEdition]);
    $stagiaireData = $stmt->fetch();

    if (!$stagiaireData) {
        http_response_code(404);
        exit('Stagiaire introuvable.');
    }

    $stmt = $pdoTmp->prepare("SELECT id_competence_technique, niveau FROM evaluation_competence_technique WHERE id_stagiaire = ?");
    $stmt->execute([$idEdition]);
    foreach ($stmt->fetchAll() as $row) {
        $notesTechExistantes[(int) $row['id_competence_technique']] = (int) $row['niveau'];
    }

    $stmt = $pdoTmp->prepare("SELECT id_competence_humaine, niveau FROM evaluation_competence_humaine WHERE id_stagiaire = ?");
    $stmt->execute([$idEdition]);
    foreach ($stmt->fetchAll() as $row) {
        $notesHumaineExistantes[(int) $row['id_competence_humaine']] = (int) $row['niveau'];
    }

    $stmt = $pdoTmp->prepare("SELECT id_badge, niveau FROM evaluation_badge WHERE id_stagiaire = ?");
    $stmt->execute([$idEdition]);
    foreach ($stmt->fetchAll() as $row) {
        $notesBadgeExistantes[(int) $row['id_badge']] = (int) $row['niveau'];
    }

    // Notation globale (/20), jointure simple sur id_stagiaire
    $stmt = $pdoTmp->prepare("SELECT note FROM notation WHERE id_stagiaire = ?");
    $stmt->execute([$idEdition]);
    $noteRow = $stmt->fetch();
    $noteExistante = $noteRow ? $noteRow['note'] : null;
}

// ── Chargement des listes depuis la base (pour générer le formulaire) ──
$pdo = getDB();
$competencesTechniques = $pdo->query("SELECT id_competence_technique, nom FROM competence_technique ORDER BY nom")->fetchAll();
$competencesHumaines   = $pdo->query("SELECT id_competence_humaine, nom FROM competence_humaine ORDER BY nom")->fetchAll();
$badges                = $pdo->query("SELECT id_badge, nom FROM badge ORDER BY nom")->fetchAll();

/**
 * Génère un groupe d'étoiles SVG interactives (même visuel que la fiche stagiaire comp.php),
 * accompagné de son input caché qui sera envoyé avec le formulaire.
 */
function renderStarInput(string $name, int $max, int $value): string
{
    $value = max(0, min($max, $value));
    $html = '<div class="stars-input" data-max="' . $max . '">';
    for ($i = 1; $i <= $max; $i++) {
        $filled  = $i <= $value;
        $couleur = $filled ? '#f0a500' : '#e2e2e2';
        $html .= '<svg class="star" data-value="' . $i . '" width="20" height="20" viewBox="0 0 24 24" style="fill:' . $couleur . ';">'
               . '<polygon points="12,2 15,9 22,9 16.5,13.5 18.5,21 12,17 5.5,21 7.5,13.5 2,9 9,9"></polygon>'
               . '</svg>';
    }
    $html .= '</div>';
    $html .= '<input type="hidden" class="rating-input" name="' . htmlspecialchars($name) . '" value="' . $value . '">';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $stagiaireData ? 'Modifier le stagiaire' : 'Nouveau stagiaire' ?></title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="<?= bodyClass() ?>">
  <div id="toast" class="toast"></div>

  <header class="header">
    <a href="index.php" class="back-btn">&larr; Retour</a>
    <h1><?= $stagiaireData ? 'Modifier le stagiaire' : 'Nouveau stagiaire' ?></h1>
  </header>

  <main class="content">
    <?php if ($erreur !== ''): ?>
      <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="formulaire_stagiaires.php<?= $idEdition ? '?id=' . (int) $idEdition : '' ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
      <?php if ($idEdition): ?>
        <input type="hidden" name="id_stagiaire" value="<?= (int) $idEdition ?>">
      <?php endif; ?>

      <div class="form-grid">

        <!-- Colonne 1 : Informations générales -->
        <section class="form-col">
          <h3>Informations générales</h3>

          <div class="field">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($stagiaireData['nom'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($stagiaireData['prenom'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label for="classe">Classe</label>
            <select id="classe" name="classe" required>
              <option value="" disabled <?= empty($stagiaireData) ? 'selected' : '' ?>>Sélectionner...</option>
              <?php foreach (['Seconde', 'Première', 'Terminale', 'Post-bac', 'Licence 1', 'Licence 2', 'Licence 3'] as $optClasse): ?>
                <option value="<?= $optClasse ?>" <?= (($stagiaireData['classe'] ?? '') === $optClasse) ? 'selected' : '' ?>><?= $optClasse ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="etablissement">Établissement</label>
            <select id="etablissement" name="etablissement" required>
              <option value="" disabled <?= empty($stagiaireData) ? 'selected' : '' ?>>Sélectionner...</option>
              <?php foreach (['Lycée Dick Ukeiwé', 'Lycée polyvalent du Mont-Dore', 'Université de la Nouvelle-Calédonie (UNC)'] as $optEtab): ?>
                <option value="<?= $optEtab ?>" <?= (($stagiaireData['etablissement'] ?? '') === $optEtab) ? 'selected' : '' ?>><?= $optEtab ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Période</label>
            <div class="date-range">
              <input type="date" name="date_debut" value="<?= htmlspecialchars($stagiaireData['date_debut'] ?? '') ?>">
              <span class="date-range-sep">→</span>
              <input type="date" name="date_fin" value="<?= htmlspecialchars($stagiaireData['date_fin'] ?? '') ?>">
            </div>
          </div>
        </section>

        <!-- Colonne 2 : Compétences techniques -->
        <section class="form-col">
          <h3>Compétences techniques</h3>
          <div class="skill-list">
            <?php foreach ($competencesTechniques as $comp):
              $val = $notesTechExistantes[(int) $comp['id_competence_technique']] ?? 0;
            ?>
              <div class="skill-item">
                <span class="skill-name"><?= htmlspecialchars($comp['nom']) ?></span>
                <?= renderStarInput('tech[' . (int) $comp['id_competence_technique'] . ']', 3, $val) ?>
              </div>
            <?php endforeach; ?>
            <?php if (empty($competencesTechniques)): ?>
              <p class="fiche-empty">Aucune compétence technique définie.</p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Colonne 3 : Compétences humaines -->
        <section class="form-col">
          <h3>Compétences humaines</h3>
          <div class="skill-list">
            <?php foreach ($competencesHumaines as $comp):
              $val = $notesHumaineExistantes[(int) $comp['id_competence_humaine']] ?? 0;
            ?>
              <div class="skill-item">
                <span class="skill-name"><?= htmlspecialchars($comp['nom']) ?></span>
                <?= renderStarInput('humaine[' . (int) $comp['id_competence_humaine'] . ']', 5, $val) ?>
              </div>
            <?php endforeach; ?>
            <?php if (empty($competencesHumaines)): ?>
              <p class="fiche-empty">Aucune compétence humaine définie.</p>
            <?php endif; ?>
          </div>
        </section>

        <!-- Colonne 4 : Badges -->
        <section class="form-col">
          <h3>Badges</h3>
          <div class="skill-list">
            <?php foreach ($badges as $badge):
              $val = $notesBadgeExistantes[(int) $badge['id_badge']] ?? 0;
            ?>
              <div class="skill-item">
                <span class="skill-name"><?= htmlspecialchars($badge['nom']) ?></span>
                <?= renderStarInput('badge[' . (int) $badge['id_badge'] . ']', 3, $val) ?>
              </div>
            <?php endforeach; ?>
            <?php if (empty($badges)): ?>
              <p class="fiche-empty">Aucun badge défini.</p>
            <?php endif; ?>
          </div>

          <?php
            // En cas de ré-affichage après erreur, on garde la valeur tapée par l'utilisateur ;
            // sinon (chargement initial en édition), on reprend la note déjà enregistrée.
            $valeurNoteAffichee = ($_SERVER['REQUEST_METHOD'] === 'POST')
                ? ($note ?? '')
                : ($noteExistante !== null ? (string) $noteExistante : '');
          ?>
          <div class="field" style="margin-top:20px;padding-top:16px;border-top:1px solid #e0d9e3;">
            <label for="note">Notation globale (/20)</label>
            <input type="number" id="note" name="note" min="0" max="20" step="0.5"
                   placeholder="Ex : 15.5"
                   value="<?= htmlspecialchars($valeurNoteAffichee) ?>">
          </div>
        </section>

      </div>

      <button type="submit" class="submit-btn"><?= $idEdition ? 'Enregistrer les modifications' : 'Enregistrer le stagiaire' ?></button>
    </form>
  </main>

  <script>
    // Étoiles interactives (SVG) — clic pour fixer la note, survol pour prévisualiser
    document.querySelectorAll('.stars-input').forEach(container => {
      const stars = Array.from(container.querySelectorAll('.star'));
      const hiddenInput = container.nextElementSibling; // input.rating-input juste après
      let currentRating = hiddenInput ? (parseInt(hiddenInput.value, 10) || 0) : 0;

      function paint(n) {
        stars.forEach((s, i) => {
          s.style.fill = i < n ? '#f0a500' : '#e2e2e2';
        });
      }

      stars.forEach((star, index) => {
        star.addEventListener('mouseenter', () => paint(index + 1));

        star.addEventListener('click', () => {
          currentRating = index + 1;
          if (hiddenInput) hiddenInput.value = currentRating;
          paint(currentRating);
        });
      });

      container.addEventListener('mouseleave', () => paint(currentRating));

      paint(currentRating);
    });

    <?php if ($succes !== ''): ?>
    // Affiche un petit popup de confirmation puis redirige vers l'index
    (function() {
      const toast = document.getElementById('toast');
      toast.textContent = <?= json_encode($succes) ?>;
      toast.classList.add('show');
      setTimeout(function() {
        window.location.href = 'index.php';
      }, 1500);
    })();
    <?php endif; ?>
  </script>
</body>
</html>