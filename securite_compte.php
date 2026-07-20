<?php
/**
 * securite_compte.php
 * Endpoint AJAX appelé depuis le pop-up "Sécurité du compte" (index.php).
 * Remplace l'ancien formulaire de changement de mot de passe de parametres.php.
 */
require_once 'config.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erreur' => 'Méthode non autorisée.']);
    exit;
}

// Vérification CSRF adaptée (réponse JSON plutôt que die() en texte brut)
$tokenEnvoye  = $_POST['csrf_token'] ?? '';
$tokenAttendu = $_SESSION['csrf_token'] ?? '';
if ($tokenAttendu === '' || !hash_equals($tokenAttendu, $tokenEnvoye)) {
    echo json_encode(['success' => false, 'erreur' => 'Jeton de sécurité invalide, merci de recharger la page.']);
    exit;
}

$ancien   = $_POST['ancien_mot_de_passe']   ?? '';
$nouveau  = $_POST['nouveau_mot_de_passe']  ?? '';
$confirme = $_POST['confirme_mot_de_passe'] ?? '';

// Deux modes possibles :
// - 'soi' (par défaut) : l'utilisateur change SON PROPRE mot de passe, ancien requis.
// - 'admin_reinit' : un admin réinitialise le mot de passe d'un AUTRE admin qui a
//   oublié le sien ; pas d'ancien mot de passe demandé, réservé au rôle admin.
$mode = $_POST['mode'] ?? 'soi';

// Toute la logique métier est entourée d'un try/catch : sans ça, la moindre
// exception (ex. PDOException) produit une page d'erreur PHP à la place du
// JSON attendu, et le fetch() du popup échoue avec un message générique
// ("Une erreur est survenue") sans qu'on sache pourquoi. Le détail réel part
// dans les logs serveur (même principe que formulaire_stagiaires.php).
try {
    if ($mode === 'admin_reinit') {
        // On ne réutilise pas requireRole() ici : elle répond en texte brut avec un
        // exit(), ce qui casserait le fetch().then(r => r.json()) côté client. On
        // reproduit donc la même vérification, avec une réponse JSON cohérente.
        if (($_SESSION['role'] ?? '') !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'erreur' => "Accès réservé aux administrateurs."]);
            exit;
        }

        $idCible = filter_input(INPUT_POST, 'id_cible', FILTER_VALIDATE_INT);
        if (!$idCible) {
            echo json_encode(['success' => false, 'erreur' => "Merci de sélectionner un compte administrateur."]);
            exit;
        }

        $resultat = reinitialiserMotDePasseAdmin((int) $_SESSION['user_id'], $idCible, $nouveau, $confirme);
    } else {
        $resultat = changerMotDePasse((int) $_SESSION['user_id'], $ancien, $nouveau, $confirme);
    }

    if ($resultat['succes']) {
        echo json_encode(['success' => true, 'succes' => $resultat['message']]);
    } else {
        echo json_encode(['success' => false, 'erreur' => $resultat['message']]);
    }
} catch (Throwable $e) {
    error_log('securite_compte.php : erreur — ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'erreur' => "Une erreur serveur est survenue. Merci de réessayer ou de contacter un administrateur si le problème persiste."]);
}