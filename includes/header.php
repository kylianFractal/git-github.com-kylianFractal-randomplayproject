<?php
// ============================================================
//  RandomPlay — includes/header.php
//  En-tête commun inclus dans toutes les pages
//  Adapté de photo4u/includes/header.php
//
//  Gère :
//  - Démarrage de la session PHP
//  - Lecture du rôle utilisateur dans $_SESSION
//  - Badge panier (nombre d'articles) dans la navbar
//  - Affichage conditionnel des liens selon le rôle :
//    visiteur / client / admin
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cart est utilisé pour le badge du panier dans la navbar
require_once __DIR__ . '/../src/Cart.php';

// Sécurité : corrige un bug potentiel si la session stocke un objet
if (isset($_SESSION['user']) && is_object($_SESSION['user'])) {
    $_SESSION['user'] = (array) $_SESSION['user'];
}

// Lecture des infos session — null si visiteur non connecté
$userType   = $_SESSION['user']['type']   ?? null;
$userPrenom = $_SESSION['user']['prenom'] ?? null;

// Badge panier uniquement pour les clients
$cartCount = $userType === 'client' ? Cart::count() : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RandomPlay 🎬</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/randomplay/assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-danger">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold text-danger fs-4" href="/randomplay/index.php">
            <i class="bi bi-play-circle-fill me-1"></i>RandomPlay
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Catalogue visible par tous -->
                <li class="nav-item">
                    <a class="nav-link" href="/randomplay/pages/catalogue.php">
                        <i class="bi bi-collection-play me-1"></i>Catalogue
                    </a>
                </li>

                <?php if ($userType === 'admin'): ?>
                <!-- Menu admin — uniquement pour le rôle 'admin' -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-warning" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-lock me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="/randomplay/admin/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="/randomplay/admin/produits.php"><i class="bi bi-collection me-2"></i>Produits</a></li>
                        <li><a class="dropdown-item" href="/randomplay/admin/commandes.php"><i class="bi bi-bag me-2"></i>Commandes</a></li>
                        <li><a class="dropdown-item" href="/randomplay/admin/locations.php"><i class="bi bi-calendar2-check me-2"></i>Locations</a></li>
                        <li><a class="dropdown-item" href="/randomplay/admin/utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a></li>
                        <li><a class="dropdown-item" href="/randomplay/admin/logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a></li>
                        <li><a class="dropdown-item" href="/randomplay/admin/audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Recherche rapide → redirige vers catalogue.php?recherche=... -->
            <form class="d-flex me-3" method="GET" action="/randomplay/pages/catalogue.php" role="search">
                <input class="form-control form-control-sm me-2" type="search" name="recherche"
                       placeholder="Titre, réalisateur..." style="min-width:200px"
                       value="<?= htmlspecialchars($_GET['recherche'] ?? '') ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <!-- Zone droite : panier + profil + déconnexion -->
            <div class="d-flex align-items-center gap-2">
                <?php if ($userType === 'client'): ?>
                    <!-- Lien vers l'espace client -->
                    <a href="/randomplay/pages/mon-compte.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-person-circle"></i>
                    </a>
                    <!-- Panier avec badge dynamique -->
                    <a href="/randomplay/pages/panier.php" class="btn btn-outline-light btn-sm position-relative">
                        <i class="bi bi-cart3"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $cartCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if ($userType): ?>
                    <!-- Prénom affiché (masqué sur petits écrans) -->
                    <span class="text-white-50 small d-none d-lg-inline">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($userPrenom) ?>
                    </span>
                    <!-- Déconnexion → logout.php détruit la session -->
                    <a href="/randomplay/pages/logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <!-- Visiteur non connecté -->
                    <a href="/randomplay/pages/register.php" class="btn btn-outline-light btn-sm">S'inscrire</a>
                    <a href="/randomplay/pages/login.php" class="btn btn-danger btn-sm">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Balise <main> ouverte ici, fermée dans footer.php -->
<main class="container-fluid p-0">
