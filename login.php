<?php
session_start();
require_once 'config.php';

// Si déjà connecté → rediriger
if (!empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$erreur = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // ── Sanitisation EN PREMIER ──
    $email        = sanitizeEmail($_POST['email']        ?? '');
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');

    if ($email === '' || $mot_de_passe === '') {
        $erreur = "Veuillez remplir tous les champs.";
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur && password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
            $_SESSION['user_id']   = $utilisateur['id'];
            $_SESSION['user_nom']  = $utilisateur['nom'];
            $_SESSION['user_role'] = $utilisateur['role'];
            $_SESSION['logged_at'] = time();

            // Redirection selon le rôle
            switch ($utilisateur['role']) {
                case 'admin':  header("Location: dashboard.php"); break;
                case 'hôtel':  header("Location: dashboard.php"); break;
                default:       header("Location: dashboard.php"); break;
            }
            exit;
        } else {
            $erreur = "Email ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — SNI Hôtel</title>
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

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       placeholder="Entrez votre email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
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

        <p style="margin-top: 15px; font-size: 14px; color: #555;">
            Pas encore de compte ?
            <a href="register.php" style="color: #7C3B9A;">Créer un compte</a>
        </p>

        <p style="margin-top: 8px; font-size: 14px;">
            <a href="index.php" style="color: #7C3B9A;">← Retour à l'accueil</a>
        </p>
    </div>
</body>
</html>