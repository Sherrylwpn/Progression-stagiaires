<?php
// ⚠️ DEBUG UNIQUEMENT — à retirer avant la mise en production
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config.php';

// Si déjà connecté → rediriger
if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$erreur = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Requête envoyée depuis le popup de connexion (index.php) en AJAX ?
    $estAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

    // ── Sanitisation EN PREMIER ──
    $nom          = trim($_POST['nom'] ?? '');
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');
    $ip           = getClientIp();

    if ($nom === '' || $mot_de_passe === '') {
        $erreur = "Veuillez remplir tous les champs.";
    } elseif (estVerrouille($nom, $ip)) {
        $attente = secondesAvantDeverrouillage($nom, $ip);
        $erreur = "Trop de tentatives échouées. Réessayez dans {$attente} secondes.";
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE nom = :nom LIMIT 1");
        $stmt->execute([':nom' => $nom]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe']) && !(int) $utilisateur['actif']) {
            // Mot de passe correct mais compte désactivé (correction 3.13) : on ne
            // compte pas ça comme un échec de brute-force (les identifiants étaient
            // corrects), mais on refuse quand même l'accès avec un message explicite.
            $erreur = "Ce compte a été désactivé. Contactez un administrateur.";
        } elseif ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
            // Régénère l'identifiant de session à chaque connexion : empêche la
            // fixation de session (un attaquant ne peut pas réutiliser un id
            // de session obtenu avant l'authentification).
            session_regenerate_id(true);

            $_SESSION['user_id']      = $utilisateur['id'];
            $_SESSION['user_nom']     = $utilisateur['nom'];
            $_SESSION['role']         = $utilisateur['role'] ?? 'utilisateur';
            $_SESSION['actif']        = true;
            $_SESSION['logged_at']    = time();
            // Correction 3.14 : sert à faire expirer la session par inactivité
            // (contrôlé à chaque requête dans requireAuth()).
            $_SESSION['last_activity'] = time();
            $_SESSION['mode_sombre']  = (bool) ($utilisateur['mode_sombre'] ?? false);
            viderEchecsConnexion($nom, $ip);

            if ($estAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => 'index.php']);
                exit;
            }

            header("Location: index.php");
            exit;
        } else {
            enregistrerEchecConnexion($nom, $ip);
            $erreur = "Nom ou mot de passe incorrect.";
        }
    }

    // À ce stade, il y a forcément une erreur (champs vides ou identifiants invalides)
    if ($estAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'erreur' => $erreur]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Suivi stagiaire</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>

        <?php if ($erreur !== ''): ?>
            <div class="error-msg"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['expired'])): ?>
            <div class="error-msg">Votre session a expiré. Veuillez vous reconnecter.</div>
        <?php endif; ?>

        <?php if (isset($_GET['desactive'])): ?>
            <div class="error-msg">Ce compte a été désactivé. Contactez un administrateur.</div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom"
                       placeholder="Entrez votre nom"
                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe"
                       placeholder="Entrez votre mot de passe"
                       required>
            </div>

            <button type="submit" class="login-btn">Se connecter</button>
        </form>

        <p style="margin-top: 8px; font-size: 14px;">
            <a href="index.php" style="color: #7C3B9A;">← Retour à l'accueil</a>
        </p>
    </div>
</body>
</html>