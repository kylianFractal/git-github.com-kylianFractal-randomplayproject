<?php
// ============================================================
//  RandomPlay — pages/logout.php
//  Déconnexion de l'utilisateur
//  Adapté de photo4u/pages/logout.php
//
//  Appelle Auth::logout() qui :
//  1. Enregistre le log de déconnexion en BDD
//  2. Vide $_SESSION
//  3. Supprime le cookie de session côté client
//  4. Détruit la session côté serveur
// ============================================================

require_once "../src/controller.php";
require_once "../src/Auth.php";

$auth = new Auth(Database::getInstance());
$auth->logout(); // Log inclus dans Auth::logout()

// Retour à l'accueil après déconnexion
header("Location: /randomplay/index.php");
exit;
