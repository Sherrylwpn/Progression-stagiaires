<?php
require_once 'config.php';
requireAuth(); // Réservé aux 2 utilisateurs connectés

$erreur  = '';
$succes  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $formulaire = $_POST['formulaire'] ?? '';
    $pdo = getDB();

    // ── Formulaire 1 : changement de mot de passe ──
    if ($formulaire === 'mot_de_passe') {
        $ancien   = $_POST['ancien_mot_de_passe'] ?? '';
        $nouveau  = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirme = $_POST['confirme_mot_de_passe'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $utilisateur = $stmt->fetch();

        if (!$utilisateur || !password_verify($ancien, $utilisateur['mot_de_passe'])) {
            $erreur = "L'ancien mot de passe est incorrect.";
        } elseif (mb_strlen($nouveau) < 8) {
            $erreur = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        } elseif ($nouveau !== $confirme) {
            $erreur = "La confirmation ne correspond pas au nouveau mot de passe.";
        } elseif ($nouveau === $ancien) {
            $erreur = "Le nouveau mot de passe doit être différent de l'ancien.";
        } else {
            $hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?")
                ->execute([$hash, $_SESSION['user_id']]);
            $succes = "Mot de passe mis à jour avec succès.";
        }
    }

    // ── Formulaire 2 : mode sombre ──
    if ($formulaire === 'affichage') {
        $modeSombre = isset($_POST['mode_sombre']) ? 1 : 0;
        $pdo->prepare("UPDATE users SET mode_sombre = ? WHERE id = ?")
            ->execute([$modeSombre, $_SESSION['user_id']]);
        $_SESSION['mode_sombre'] = (bool) $modeSombre;
        $succes = "Préférences d'affichage enregistrées.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paramètres — Suivi stagiaire</title>
  <link rel="stylesheet" href="index.css">
  <link rel="stylesheet" href="parametres.css">
</head>
<body class="<?= bodyClass() ?>">
  <header class="header">
    <a href="index.php" class="back-btn">&larr; Retour</a>
    <h1>Paramètres</h1>
  </header>

  <main class="content parametres-content">
    <?php if ($erreur !== ''): ?>
      <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    <?php if ($succes !== ''): ?>
      <div class="alert-succes"><?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>

    <div class="parametres-grid">

      <!-- Sécurité du compte -->
      <section class="form-col">
        <h3>Sécurité du compte</h3>
        <form method="POST" action="parametres.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <input type="hidden" name="formulaire" value="mot_de_passe">

          <div class="field">
            <label for="ancien_mot_de_passe">Mot de passe actuel</label>
            <input type="password" id="ancien_mot_de_passe" name="ancien_mot_de_passe" required autocomplete="current-password">
          </div>
          <div class="field">
            <label for="nouveau_mot_de_passe">Nouveau mot de passe</label>
            <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" minlength="8" required autocomplete="new-password">
          </div>
          <div class="field">
            <label for="confirme_mot_de_passe">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirme_mot_de_passe" name="confirme_mot_de_passe" minlength="8" required autocomplete="new-password">
          </div>

          <button type="submit" class="submit-btn">Changer le mot de passe</button>
        </form>
      </section>

      <!-- Affichage -->
      <section class="form-col">
        <h3>Affichage</h3>
        <form method="POST" action="parametres.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <input type="hidden" name="formulaire" value="affichage">

          <div class="field field-switch">
            <label for="mode_sombre">Mode sombre</label>
            <label class="switch">
              <input type="checkbox" id="mode_sombre" name="mode_sombre" <?= !empty($_SESSION['mode_sombre']) ? 'checked' : '' ?>>
              <span class="switch-slider"></span>
            </label>
          </div>

          <button type="submit" class="submit-btn">Enregistrer</button>
        </form>
      </section>

      <!-- Raccourci vers le suivi des modifications -->
      <section class="form-col">
        <h3>Activité</h3>
        <p class="fiche-empty" style="margin-bottom:16px;">
          Retrouvez l'historique de toutes les créations, modifications et suppressions
          effectuées sur les fiches stagiaires.
        </p>
        <a href="suivi_modifications.php" class="fiche-btn fiche-btn-edit" style="width:100%;">Voir le suivi des modifications</a>
      </section>

    </div>
  </main>
</body>
</html>