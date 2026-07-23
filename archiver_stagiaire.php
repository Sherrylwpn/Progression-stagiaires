<?php
/**
 * archiver_stagiaire.php
 * Endpoint POST appelé depuis :
 * - stagiaire_detail_fragment.php (bouton "Archiver", action=archiver)
 * - archive.php (bouton "Désarchiver", action=restaurer)
 * Ne renvoie pas de JSON : c'est un simple formulaire POST classique (comme
 * delete_stagiaire.php), qui redirige ensuite vers la bonne page avec un
 * paramètre de confirmation (?archive=1 ou ?restaure=1).
 */
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

verifyCsrf();

$idStage = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action  = $_POST['action'] ?? 'archiver';

if (!$idStage) {
    header("Location: index.php");
    exit;
}

$pdo = getDB();

// Nom du stagiaire concerné, pour le journal des modifications.
$stmt = $pdo->prepare(
    "SELECT s.nom, s.prenom FROM stage st JOIN stagiaire s ON s.id_stagiaire = st.id_stagiaire WHERE st.id_stage = ?"
);
$stmt->execute([$idStage]);
$stagiaire = $stmt->fetch();
$nomComplet = $stagiaire ? trim($stagiaire['nom'] . ' ' . $stagiaire['prenom']) : 'Inconnu';

if ($action === 'restaurer') {
    desarchiverStage($pdo, $idStage);
    logAction('désarchivage', $idStage, $nomComplet, 'Stage retiré manuellement des archives.');
    header("Location: archive.php?restaure=1");
    exit;
}

archiverStage($pdo, $idStage);
logAction('archivage', $idStage, $nomComplet, 'Stage archivé manuellement.');
header("Location: index.php?archive=1");
exit;
