<?php
/**
 * preference_affichage.php
 * Endpoint AJAX appelé depuis le pop-up "Affichage" (index.php).
 * Remplace l'ancien formulaire "mode sombre" de parametres.php.
 */
require_once 'config.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'erreur' => 'Méthode non autorisée.']);
    exit;
}

$tokenEnvoye  = $_POST['csrf_token'] ?? '';
$tokenAttendu = $_SESSION['csrf_token'] ?? '';
if ($tokenAttendu === '' || !hash_equals($tokenAttendu, $tokenEnvoye)) {
    echo json_encode(['success' => false, 'erreur' => 'Jeton de sécurité invalide, merci de recharger la page.']);
    exit;
}

$modeSombre = (isset($_POST['mode_sombre']) && $_POST['mode_sombre'] === '1') ? 1 : 0;

$pdo = getDB();
$pdo->prepare("UPDATE users SET mode_sombre = ? WHERE id = ?")->execute([$modeSombre, $_SESSION['user_id']]);
$_SESSION['mode_sombre'] = (bool) $modeSombre;

echo json_encode(['success' => true, 'mode_sombre' => (bool) $modeSombre]);