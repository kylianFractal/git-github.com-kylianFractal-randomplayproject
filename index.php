<?php
// ============================================================
//  RandomPlay — index.php
//  Page d'accueil du site
//
//  Affiche :
//  - Une section hero avec slogan et boutons d'appel à l'action
//  - Une grille des types de supports (filtres rapides)
//  - Une vitrine des 6 derniers produits ajoutés au catalogue
// ============================================================

require_once "src/controller.php";
require_once "src/Product.php";

$db           = Database::getInstance();
$productModel = new Product($db);

// Récupère les 6 produits les plus récents pour la vitrine
// getAll() retourne déjà trié par created_at DESC
$vitrine = array_slice($productModel->getAll(), 0, 6);

include "includes/header.php";
?>

<!-- ========== SECTION HERO ========== -->
<section class="hero">
    <div class="container">
        <h1>Bienvenue sur <span>RandomPlay</span></h1>
        <p class="mb-4">La boutique vintage — Achetez ou louez vos VHS, cassettes, CD, DVD et vinyles préférés.</p>

        <!-- Bouton principal vers le catalogue -->
        <a href="/randomplay/pages/catalogue.php" class="btn btn-danger btn-lg me-2">
            <i class="bi bi-collection-play me-1"></i>Voir le catalogue
        </a>

        <!-- Bouton inscription uniquement pour les visiteurs non connectés -->
        <?php if (!isset($_SESSION['user'])): ?>
            <a href="/randomplay/pages/register.php" class="btn btn-outline-light btn-lg">
                <i class="bi bi-person-plus me-1"></i>Créer un compte
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- ========== GRILLE DES SUPPORTS ========== -->
<!-- Chaque carte est un lien filtré vers le catalogue -->
<section class="py-5 bg-white">
    <div class="container">
        <h2 class="text-center fw-bold mb-4">Nos supports <span class="text-danger">vintage</span></h2>
        <div class="row g-3 justify-content-center">
            <?php
            // Tableau des supports avec icône et couleur Bootstrap associée
            $supports = [
                'vhs'            => ['icon' => '📼', 'label' => 'VHS',      'color' => 'secondary'],
                'cassette_audio' => ['icon' => '📼', 'label' => 'Cassette', 'color' => 'warning'],
                'cd'             => ['icon' => '💿', 'label' => 'CD',       'color' => 'info'],
                'dvd'            => ['icon' => '📀', 'label' => 'DVD',      'color' => 'primary'],
                'vinyle'         => ['icon' => '🎵', 'label' => 'Vinyle',   'color' => 'success'],
            ];
            foreach ($supports as $slug => $s): ?>
                <div class="col-6 col-md-2">
                    <!-- Lien vers catalogue filtré par support -->
                    <a href="/randomplay/pages/catalogue.php?support=<?= $slug ?>"
                       class="card text-center text-decoration-none border-0 shadow-sm h-100 p-3 product-card">
                        <div style="font-size:2.5rem"><?= $s['icon'] ?></div>
                        <div class="fw-bold mt-2 text-dark"><?= $s['label'] ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ========== VITRINE DES DERNIERS PRODUITS ========== -->
<?php if (!empty($vitrine)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="fw-bold mb-4">🎬 Récemment ajoutés</h2>
        <div class="row g-4">
            <?php foreach ($vitrine as $p): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card product-card h-100">
                        <!-- Placeholder émoji selon le type de support -->
                        <div class="cover-placeholder">
                            <?php
                            $icons = ['vhs'=>'📼','cassette_audio'=>'📼','cd'=>'💿','dvd'=>'📀','vinyle'=>'🎵'];
                            echo $icons[$p['support']] ?? '🎬';
                            ?>
                        </div>
                        <div class="card-body p-2">
                            <p class="card-title fw-bold small mb-1 text-truncate">
                                <?= htmlspecialchars($p['titre']) ?>
                            </p>
                            <p class="text-muted" style="font-size:0.75rem"><?= $p['annee'] ?></p>
                            <?php if ($p['prix_vente']): ?>
                                <span class="text-danger fw-bold small">
                                    <?= number_format($p['prix_vente'],2) ?>€
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer p-2 bg-white border-0">
                            <a href="/randomplay/pages/produit.php?id=<?= $p['id'] ?>"
                               class="btn btn-outline-danger btn-sm w-100">Voir</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Lien vers le catalogue complet -->
        <div class="text-center mt-4">
            <a href="/randomplay/pages/catalogue.php" class="btn btn-danger">
                Voir tout le catalogue <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include "includes/footer.php"; ?>
