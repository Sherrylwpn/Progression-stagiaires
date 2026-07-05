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

/**
 * Bloque l'accès à la page si l'utilisateur n'est pas connecté.
 * À placer tout en haut des pages réservées (ajout / modification / suppression).
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
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
 * par l'utilisateur actuellement connecté sur la fiche d'un stagiaire.
 * À appeler juste après un commit réussi.
 */
function logAction(string $action, ?int $idStagiaire, string $nomStagiaire, string $details = ''): void
{
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO journal_modifications (id_user, nom_user, action, id_stagiaire, nom_stagiaire, details)
         VALUES (:id_user, :nom_user, :action, :id_stagiaire, :nom_stagiaire, :details)"
    );
    $stmt->execute([
        ':id_user'       => $_SESSION['user_id'] ?? null,
        ':nom_user'      => $_SESSION['user_nom'] ?? 'Inconnu',
        ':action'        => $action,
        ':id_stagiaire'  => $idStagiaire,
        ':nom_stagiaire' => $nomStagiaire,
        ':details'       => $details !== '' ? $details : null,
    ]);
}