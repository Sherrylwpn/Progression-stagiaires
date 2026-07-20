<?php
/**
 * config.php
 * Fichier central : à inclure (require_once) dans chaque page qui a besoin
 * de la base de données, de la session, ou de la protection CSRF.
 */

// ── Session sécurisée ──
// On démarre la session ici, une seule fois, pour toute l'application.
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true, // le cookie de session n'est pas accessible en JS
        'cookie_samesite' => 'Lax',
    ]);
}

// ── Paramètres de connexion à la base de données ──
define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'staginf');
define('DB_USER', 'root');
define('DB_PASS', ''); // vide par défaut sous XAMPP

/**
 * Retourne une connexion PDO unique (réutilisée si déjà ouverte).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    return $pdo;
}

// ── Protection CSRF ──

/**
 * Génère (si besoin) et retourne le jeton CSRF de la session en cours.
 * À placer dans un champ hidden de chaque formulaire POST.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le jeton CSRF envoyé en POST. Arrête l'exécution s'il est invalide.
 */
function verifyCsrf(): void
{
    $tokenEnvoye = $_POST['csrf_token'] ?? '';
    $tokenAttendu = $_SESSION['csrf_token'] ?? '';

    if ($tokenAttendu === '' || !hash_equals($tokenAttendu, $tokenEnvoye)) {
        die("Erreur de sécurité : jeton CSRF invalide. Merci de recharger la page et réessayer.");
    }
}

// ── Contrôle d'accès ──

/**
 * Indique si un utilisateur est actuellement connecté.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

// ── Expiration de session (correction 3.14) ──
//
// logged_at était enregistré mais jamais relu : une session restait valable
// indéfiniment tant que le cookie existait. On introduit une expiration par
// INACTIVITÉ, contrôlée à chaque requête dans requireAuth().
define('SESSION_INACTIVITE_SECONDES', 1800); // 30 minutes sans requête → déconnexion

/**
 * Bloque l'accès à la page si l'utilisateur n'est pas connecté, si son compte
 * a été désactivé entre-temps, ou si sa session a expiré par inactivité.
 * À placer tout en haut des pages réservées (ajout / modification / suppression).
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }

    // Compte désactivé après la connexion (cf. correction 3.13) : on ne fait
    // confiance qu'à la valeur mémorisée en session au login ; un compte
    // réactivé nécessitera une nouvelle connexion pour le voir refléter ici,
    // ce qui est un compromis acceptable pour une application de ce volume.
    if (empty($_SESSION['actif'])) {
        session_unset();
        session_destroy();
        header("Location: login.php?desactive=1");
        exit;
    }

    $derniereActivite = $_SESSION['last_activity'] ?? null;
    if ($derniereActivite !== null && (time() - $derniereActivite) > SESSION_INACTIVITE_SECONDES) {
        session_unset();
        session_destroy();
        header("Location: login.php?expired=1");
        exit;
    }

    // Session valide : on horodate cette requête comme la dernière activité.
    $_SESSION['last_activity'] = time();
}

/**
 * Bloque l'accès à la page si l'utilisateur connecté n'a pas le rôle requis
 * (par ex. 'admin'). À appeler APRÈS requireAuth(). Prévu pour les futurs
 * écrans d'administration (gestion des comptes, catalogues) évoqués en 3.15 ;
 * aucune route n'utilise encore cette fonction dans la version actuelle.
 */
function requireRole(string $role): void
{
    if (($_SESSION['role'] ?? null) !== $role) {
        http_response_code(403);
        exit("Accès refusé : cette page nécessite le rôle « {$role} ».");
    }
}

// ── Préférence d'affichage (mode sombre) ──

/**
 * Retourne la classe CSS à appliquer sur <body> selon la préférence
 * de l'utilisateur connecté (mémorisée en session après le login).
 */
function bodyClass(): string
{
    return !empty($_SESSION['mode_sombre']) ? 'dark-mode' : '';
}

// ── Journal des modifications ──

/**
 * Enregistre une action (création / modification / suppression) effectuée
 * par l'utilisateur actuellement connecté sur une fiche de stage.
 *
 * IMPORTANT (cf. rapport d'audit, point 3.5) : cette fonction doit être appelée
 * AVANT le commit() de la transaction métier, avec la même connexion $pdo
 * (getDB() retourne toujours la même instance), afin que la modification et sa
 * trace dans le journal réussissent ou échouent ensemble. Ne jamais l'appeler
 * après un commit() déjà effectué.
 */
function logAction(string $action, ?int $idStage, string $nomStagiaire, string $details = ''): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO journal_modifications (id_user, nom_user, action, id_stage, nom_stagiaire, details)
         VALUES (:id_user, :nom_user, :action, :id_stage, :nom_stagiaire, :details)"
    );
    $stmt->execute([
        ':id_user'       => $_SESSION['user_id'] ?? null,
        ':nom_user'      => $_SESSION['user_nom'] ?? 'Inconnu',
        ':action'        => $action,
        ':id_stage'      => $idStage,
        ':nom_stagiaire' => $nomStagiaire,
        ':details'       => $details !== '' ? $details : null,
    ]);
}

// ── Changement de mot de passe (logique partagée) ──

/**
 * Valide puis applique un changement de mot de passe pour un utilisateur donné.
 * Centralise les règles utilisées à la fois par l'ancienne page parametres.php
 * et par l'endpoint AJAX securite_compte.php, pour n'avoir qu'un seul endroit
 * à corriger si une règle change.
 *
 * @return array{succes: bool, message: string}
 */
function changerMotDePasse(int $userId, string $ancien, string $nouveau, string $confirme): array
{
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $utilisateur = $stmt->fetch();

    if (!$utilisateur || !password_verify($ancien, $utilisateur['mot_de_passe'])) {
        return ['succes' => false, 'message' => "L'ancien mot de passe est incorrect."];
    }
    if (mb_strlen($nouveau) < 8) {
        return ['succes' => false, 'message' => "Le nouveau mot de passe doit contenir au moins 8 caractères."];
    }
    if ($nouveau !== $confirme) {
        return ['succes' => false, 'message' => "La confirmation ne correspond pas au nouveau mot de passe."];
    }
    if ($nouveau === $ancien) {
        return ['succes' => false, 'message' => "Le nouveau mot de passe doit être différent de l'ancien."];
    }

    $hash = password_hash($nouveau, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?")->execute([$hash, $userId]);

    return ['succes' => true, 'message' => 'Mot de passe mis à jour avec succès.'];
}

// ── Protection anti brute-force (stockée en base, indépendante de la session) ──
//
// Auparavant, le compteur d'échecs vivait dans $_SESSION : il suffisait de
// supprimer ses cookies (ou d'ouvrir une navigation privée) pour obtenir 5
// nouvelles tentatives. Le compteur est maintenant en base, rattaché à la
// fois au nom de compte tenté ET à l'adresse IP, ce qui rend le verrouillage
// effectif même en changeant de session.

define('LOGIN_MAX_TENTATIVES', 5);
define('LOGIN_VERROU_SECONDES', 5);

/**
 * Adresse IP du visiteur. Ne fait volontairement pas confiance aux en-têtes
 * X-Forwarded-For (falsifiables par le client) : à n'activer que si
 * l'application est placée derrière un proxy de confiance qui les positionne
 * lui-même.
 */
function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Enregistre une tentative de connexion échouée pour ce nom de compte / IP.
 */
function enregistrerEchecConnexion(string $identifiant, string $adresseIp): void
{
    $pdo = getDB();
    $pdo->prepare(
        "INSERT INTO login_attempts (identifiant, adresse_ip) VALUES (:identifiant, :ip)"
    )->execute([':identifiant' => $identifiant, ':ip' => $adresseIp]);

    // Ménage occasionnel des anciennes tentatives (plus d'un jour) pour éviter
    // que la table ne grossisse indéfiniment. Peu coûteux vu le volume attendu.
    $pdo->prepare("DELETE FROM login_attempts WHERE date_tentative < :seuil")
        ->execute([':seuil' => date('Y-m-d H:i:s', time() - 86400)]);
}

/**
 * Supprime les tentatives échouées après une connexion réussie.
 */
function viderEchecsConnexion(string $identifiant, string $adresseIp): void
{
    $pdo = getDB();
    $pdo->prepare("DELETE FROM login_attempts WHERE identifiant = :identifiant OR adresse_ip = :ip")
        ->execute([':identifiant' => $identifiant, ':ip' => $adresseIp]);
}

/**
 * Indique si ce nom de compte OU cette adresse IP a atteint la limite
 * d'échecs de connexion sur la fenêtre de verrouillage.
 */
function estVerrouille(string $identifiant, string $adresseIp): bool
{
    $pdo    = getDB();
    $depuis = date('Y-m-d H:i:s', time() - LOGIN_VERROU_SECONDES);
    $stmt   = $pdo->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (identifiant = :identifiant OR adresse_ip = :ip)
           AND date_tentative >= :depuis"
    );
    $stmt->execute([':identifiant' => $identifiant, ':ip' => $adresseIp, ':depuis' => $depuis]);

    return (int) $stmt->fetchColumn() >= LOGIN_MAX_TENTATIVES;
}

/**
 * Nombre de secondes restantes avant déverrouillage (0 si déjà déverrouillé).
 */
function secondesAvantDeverrouillage(string $identifiant, string $adresseIp): int
{
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT date_tentative FROM login_attempts
         WHERE identifiant = :identifiant OR adresse_ip = :ip
         ORDER BY date_tentative DESC
         LIMIT 1 OFFSET :offset"
    );
    $stmt->bindValue(':identifiant', $identifiant);
    $stmt->bindValue(':ip', $adresseIp);
    $stmt->bindValue(':offset', LOGIN_MAX_TENTATIVES - 1, PDO::PARAM_INT);
    $stmt->execute();
    $ligne = $stmt->fetch();

    if (!$ligne) {
        return 0;
    }

    $finVerrou = strtotime($ligne['date_tentative']) + 5; // 5 secondes
    return max(0, $finVerrou - time());
}