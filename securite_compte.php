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

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$utilisateur = $stmt->fetch();

if (!$utilisateur || !password_verify($ancien, $utilisateur['mot_de_passe'])) {
    echo json_encode(['success' => false, 'erreur' => "L'ancien mot de passe est incorrect."]);
    exit;
}
if (mb_strlen($nouveau) < 8) {
    echo json_encode(['success' => false, 'erreur' => "Le nouveau mot de passe doit contenir au moins 8 caractères."]);
    exit;
}
if ($nouveau !== $confirme) {
    echo json_encode(['success' => false, 'erreur' => "La confirmation ne correspond pas au nouveau mot de passe."]);
    exit;
}
if ($nouveau === $ancien) {
    echo json_encode(['success' => false, 'erreur' => "Le nouveau mot de passe doit être différent de l'ancien."]);
    exit;
}

$hash = password_hash($nouveau, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?")->execute([$hash, $_SESSION['user_id']]);

echo json_encode(['success' => true, 'succes' => 'Mot de passe mis à jour avec succès.']);