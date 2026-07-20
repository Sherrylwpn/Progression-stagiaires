<?php
/**
 * logout.php
 * Correction 3.14 : la déconnexion se faisait par simple lien GET, ce qui la
 * rend déclenchable par un tiers (CSRF de déconnexion, prefetch de navigateur,
 * robot d'indexation qui suit les liens...). On exige désormais une requête
 * POST accompagnée du jeton CSRF de la session, comme les autres actions qui
 * modifient l'état (cf. index.php, qui poste vers ce fichier depuis un
 * formulaire au lieu d'un simple <a href>).
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

verifyCsrf();

session_unset();
session_destroy();
header("Location: index.php");
exit;