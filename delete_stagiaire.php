<?php
require_once 'config.php';
requireAuth(); // Seuls les 2 utilisateurs connectés peuvent supprimer un stagiaire

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

verifyCsrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('ID invalide.');
}

$pdo = getDB();

try {
    $pdo->beginTransaction();

    // On supprime d'abord les évaluations liées, au cas où la base
    // n'aurait pas de contrainte ON DELETE CASCADE.
    $pdo->prepare("DELETE FROM evaluation_competence_technique WHERE id_stagiaire = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM evaluation_competence_humaine WHERE id_stagiaire = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM evaluation_badge WHERE id_stagiaire = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM stagiaire WHERE id_stagiaire = ?")->execute([$id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit("Erreur lors de la suppression : " . htmlspecialchars($e->getMessage()));
}

header("Location: index.php?supprime=1");
exit;