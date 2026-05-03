<?php
// ============================================================
//  RandomPlay — src/auth_check.php
//  Contrôle d'accès aux pages protégées
//  (adapté de photo4u/src/auth_check.php)
//
//  Ce fichier est inclus en tête de chaque page nécessitant
//  une authentification. Il expose la fonction checkAccess()
//  qui vérifie le rôle de l'utilisateur connecté.
// ============================================================

require_once __DIR__ . '/controller.php';
require_once __DIR__ . '/Auth.php';

// Démarrage de session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Instanciation globale de Auth pour utilisation dans checkAccess()
$auth = new Auth(Database::getInstance());

/**
 * Vérifie que l'utilisateur est connecté ET possède le bon rôle.
 * Redirige vers login.php si les conditions ne sont pas remplies.
 *
 * Exemples d'utilisation :
 *   checkAccess('admin');               // Réservé aux admins
 *   checkAccess('client');              // Réservé aux clients
 *   checkAccess(['client', 'admin']);   // Accessible aux deux rôles
 *
 * @param string|array $roles  Rôle(s) autorisé(s)
 */
function checkAccess($roles): void
{
    global $auth;

    // Redirige vers le login si l'utilisateur n'est pas connecté
    if (!$auth->isLogged()) {
        header("Location: ../pages/login.php");
        exit;
    }

    $userType = $auth->userType();
    // Normalise $roles en tableau pour uniformiser le traitement
    $roles = (array) $roles;

    // Redirige si le rôle de l'utilisateur n'est pas dans la liste autorisée
    if (!in_array($userType, $roles)) {
        header("Location: ../pages/login.php");
        exit;
    }
}
