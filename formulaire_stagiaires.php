<?php
require_once 'config.php';

$erreur  = '';
$succes  = '';

// ── Traitement de la soumission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $nom           = trim($_POST['nom']           ?? '');
    $prenom        = trim($_POST['prenom']         ?? '');
    $classe        = trim($_POST['classe']         ?? '');
    $etablissement = trim($_POST['etablissement']  ?? '');

    $notesTech    = $_POST['tech']    ?? []; // [id_competence_technique => niveau]
    $notesHumaine = $_POST['humaine'] ?? []; // [id_competence_humaine   => niveau]
    $notesBadge   = $_POST['badge']   ?? []; // [id_badge                => niveau]

    if ($nom === '' || $prenom === '' || $classe === '' || $etablissement === '') {
        $erreur = "Merci de remplir tous les champs des informations générales.";
    } else {
        $pdo = getDB();

        try {
            $pdo->beginTransaction();

            // 1) Création du stagiaire
            $stmt = $pdo->prepare(
                "INSERT INTO stagiaire (nom, prenom, classe, etablissement)
                 VALUES (:nom, :prenom, :classe, :etablissement)"
            );
            $stmt->execute([
                ':nom'           => $nom,
                ':prenom'        => $prenom,
                ':classe'        => $classe,
                ':etablissement' => $etablissement,
            ]);
            $idStagiaire = $pdo->lastInsertId();

            // 2) Évaluations compétences techniques
            $stmtTech = $pdo->prepare(
                "INSERT INTO evaluation_competence_technique (id_stagiaire, id_competence_technique, niveau)
                 VALUES (:id_stagiaire, :id_competence, :niveau)
                 ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)"
            );
            foreach ($notesTech as $idCompetence => $niveau) {
                $niveau = (int) $niveau;
                if ($niveau >= 1 && $niveau <= 3) {
                    $stmtTech->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_competence' => (int) $idCompetence,
                        ':niveau' => $niveau,
                    ]);
                }
            }

            // 3) Évaluations compétences humaines
            $stmtHumaine = $pdo->prepare(
                "INSERT INTO evaluation_competence_humaine (id_stagiaire, id_competence_humaine, niveau)
                 VALUES (:id_stagiaire, :id_competence, :niveau)
                 ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)"
            );
            foreach ($notesHumaine as $idCompetence => $niveau) {
                $niveau = (int) $niveau;
                if ($niveau >= 1 && $niveau <= 5) {
                    $stmtHumaine->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_competence' => (int) $idCompetence,
                        ':niveau' => $niveau,
                    ]);
                }
            }

            // 4) Évaluations badges
            $stmtBadge = $pdo->prepare(
                "INSERT INTO evaluation_badge (id_stagiaire, id_badge, niveau)
                 VALUES (:id_stagiaire, :id_badge, :niveau)
                 ON DUPLICATE KEY UPDATE niveau = VALUES(niveau)"
            );
            foreach ($notesBadge as $idBadge => $niveau) {
                $niveau = (int) $niveau;
                if ($niveau >= 1 && $niveau <= 3) {
                    $stmtBadge->execute([
                        ':id_stagiaire' => $idStagiaire,
                        ':id_badge' => (int) $idBadge,
                        ':niveau' => $niveau,
                    ]);
                }
            }

            $pdo->commit();
            $succes = "Stagiaire enregistré avec succès.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

// ── Chargement des listes depuis la base (pour générer le formulaire) ──
$pdo = getDB();
$competencesTechniques = $pdo->query("SELECT id_competence_technique, nom FROM competence_technique ORDER BY nom")->fetchAll();
$competencesHumaines   = $pdo->query("SELECT id_competence_humaine, nom FROM competence_humaine ORDER BY nom")->fetchAll();
$badges                = $pdo->query("SELECT id_badge, nom FROM badge ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Formulaire Stagiaire</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="card">
    <h1>Formulaire stagiaire</h1>

    <?php if ($succes !== ''): ?>
      <p style="color: green; font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($succes) ?></p>
    <?php endif; ?>
    <?php if ($erreur !== ''): ?>
      <p style="color: #c62828; font-weight: 600; margin-bottom: 16px;"><?= htmlspecialchars($erreur) ?></p>
    <?php endif; ?>

    <form method="POST" action="formulaire_stagiaires.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <!-- Informations générales -->
      <div class="section-title">Informations générales</div>
      <div class="info-grid">
        <div class="field">
          <label>Nom</label>
          <input type="text" name="nom" required>
        </div>
        <div class="field">
          <label>Prénom</label>
          <input type="text" name="prenom" required>
        </div>
        <div class="field">
          <label>Classe</label>
          <select name="classe" required>
            <option value="" disabled selected>Sélectionner...</option>
            <option value="Seconde">Seconde</option>
            <option value="Première">Première</option>
            <option value="Terminale">Terminale</option>
            <option value="Post-bac">Post-bac</option>
            <option value="Licence 1">Licence 1</option>
            <option value="Licence 2">Licence 2</option>
            <option value="Licence 3">Licence 3</option>
          </select>
        </div>
        <div class="field">
          <label>Etablissement</label>
          <select name="etablissement" required>
            <option value="" disabled selected>Sélectionner...</option>
            <option value="Lycée Dick Ukeiwé">Lycée Dick Ukeiwé</option>
            <option value="Lycée polyvalent du Mont-Dore">Lycée polyvalent du Mont-Dore</option>
            <option value="Université de la Nouvelle-Calédonie (UNC)">Université de la Nouvelle-Calédonie (UNC)</option>
          </select>
        </div>
      </div>

      <!-- Compétences techniques -->
      <div class="section-title">Compétences technique</div>
      <div class="skills-grid">
        <?php foreach ($competencesTechniques as $comp): ?>
          <div class="skill-item">
            <div class="skill-name"><?= htmlspecialchars($comp['nom']) ?></div>
            <div class="stars" data-max="3">
              <span class="star">★</span>
              <span class="star">★</span>
              <span class="star">★</span>
            </div>
            <input type="hidden" class="rating-input" name="tech[<?= (int) $comp['id_competence_technique'] ?>]" value="0">
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Badges -->
      <div class="section-title">Badges</div>
      <div class="skills-grid">
        <?php foreach ($badges as $badge): ?>
          <div class="skill-item">
            <div class="skill-name"><?= htmlspecialchars($badge['nom']) ?></div>
            <div class="stars" data-max="3">
              <span class="star">★</span>
              <span class="star">★</span>
              <span class="star">★</span>
            </div>
            <input type="hidden" class="rating-input" name="badge[<?= (int) $badge['id_badge'] ?>]" value="0">
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Compétences humaines / soft skills -->
      <div class="section-title">Compétences humaine</div>
      <div class="skills-grid-soft">
        <?php foreach ($competencesHumaines as $comp): ?>
          <div class="skill-item-soft">
            <div class="skill-name"><?= htmlspecialchars($comp['nom']) ?></div>
            <div class="stars-5">
              <span class="star">★</span>
              <span class="star">★</span>
              <span class="star">★</span>
              <span class="star">★</span>
              <span class="star">★</span>
            </div>
            <input type="hidden" class="rating-input" name="humaine[<?= (int) $comp['id_competence_humaine'] ?>]" value="0">
          </div>
        <?php endforeach; ?>
      </div>

      <button type="submit" class="submit-btn">Enregistrer le stagiaire</button>
    </form>
  </div>

  <script>
    // Star rating interactivity — met aussi à jour l'input caché associé
    document.querySelectorAll('.stars, .stars-5').forEach(container => {
      const stars = container.querySelectorAll('.star');
      const hiddenInput = container.parentElement.querySelector('.rating-input');
      let currentRating = hiddenInput ? (parseInt(hiddenInput.value, 10) || 0) : 0;

      stars.forEach((star, index) => {
        star.addEventListener('mouseenter', () => {
          stars.forEach((s, i) => {
            s.style.color = i <= index ? '#111' : '#aaa';
          });
        });

        star.addEventListener('click', () => {
          currentRating = index + 1;
          updateStars();
          if (hiddenInput) {
            hiddenInput.value = currentRating;
          }
        });
      });

      container.addEventListener('mouseleave', () => {
        updateStars();
      });

      function updateStars() {
        stars.forEach((s, i) => {
          s.style.color = i < currentRating ? '#111' : '#aaa';
        });
      }

      updateStars();
    });
  </script>
</body>
</html>