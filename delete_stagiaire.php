<?php
require_once 'config.php';
requireAuth(); // Seuls les utilisateurs connectés peuvent supprimer une fiche

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

verifyCsrf();

// L'id transmis par le formulaire est désormais un id_stage (une fiche = un stage,
// cf. correction 3.4 : une personne peut avoir plusieurs stages).
$idStage = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$idStage) {
    http_response_code(400);
    exit('ID invalide.');
}

$pdo = getDB();

// On récupère le nom du stagiaire AVANT suppression, pour pouvoir
// le conserver lisible dans le journal des modifications.
$stmtInfo = $pdo->prepare(
    "SELECT s.nom, s.prenom
     FROM stage st JOIN stagiaire s ON s.id_stagiaire = st.id_stagiaire
     WHERE st.id_stage = ?"
);
$stmtInfo->execute([$idStage]);
$infoStagiaire = $stmtInfo->fetch();

if (!$infoStagiaire) {
    http_response_code(404);
    exit('Stagiaire introuvable.');
}

try {
    $pdo->beginTransaction();

    // Les évaluations et leurs notes de compétences sont supprimées en cascade
    // par les contraintes ON DELETE CASCADE du schéma (evaluation -> stage,
    // evaluation_competence_* -> evaluation). Il suffit de supprimer le stage.
    $pdo->prepare("DELETE FROM stage WHERE id_stage = ?")->execute([$idStage]);

    // Correction 3.5 : la journalisation fait partie de la même transaction et
    // s'exécute AVANT le commit, afin que suppression et trace d'audit réussissent
    // ou échouent ensemble.
    logAction('suppression', null, $infoStagiaire['nom'] . ' ' . $infoStagiaire['prenom']);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Correction (revue fichier par fichier) : ne pas renvoyer le message
    // d'exception brut à l'utilisateur ; le détail part dans les logs serveur.
    error_log('delete_stagiaire.php : erreur suppression stage — ' . $e->getMessage());
    http_response_code(500);
    exit("Erreur lors de la suppression. Merci de réessayer.");
}

header("Location: index.php?supprime=1");
exit;