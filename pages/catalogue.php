<?php
// ============================================================
//  RandomPlay — pages/catalogue.php
//  Catalogue des produits avec filtres combinables
//
//  Filtres disponibles via GET :
//  - support    : vhs, cassette_audio, cd, dvd, vinyle
//  - type_achat : vente, location (filtre aussi selon stock > 0)
//  - genre      : recherche partielle (LIKE)
//  - recherche  : titre ou réalisateur (LIKE)
//
//  Tous les filtres sont optionnels et combinables.
// ============================================================

require_once "../src/controller.php";
require_once "../src/Product.php";

$db           = Database::getInstance();
$productModel = new Product($db);

// Construction du tableau de filtres depuis les paramètres GET
// array_filter() supprime les valeurs vides (string vide)
$filtres = [
    'support'    => $_GET['support']    ?? '',
    'genre'      => $_GET['genre']      ?? '',
    'type_achat' => $_GET['type_achat'] ?? '',
    'recherche'  => $_GET['recherche']  ?? '',
];

// getAll() construit dynamiquement la requête SQL selon les filtres
$produits = $productModel->getAll(array_filter($filtres));

include "../includes/header.php";

// Tableaux d'affichage pour les supports
$icons          = ['vhs'=>'📼','cassette_audio'=>'📼','cd'=>'💿','dvd'=>'📀','vinyle'=>'🎵'];
$support_labels = ['vhs'=>'VHS','cassette_audio'=>'Cassette','cd'=>'CD','dvd'=>'DVD','vinyle'=>'Vinyle'];
?>

<div class="container py-4">
    <h1 class="fw-bold mb-1">Catalogue <span class="text-danger">RandomPlay</span></h1>
    <p class="text-muted mb-4"><?= count($produits) ?> article(s) trouvé(s)</p>

    <div class="row g-4">

        <!-- ===== SIDEBAR FILTRES ===== -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold text-danger mb-3">
                        <i class="bi bi-funnel me-1"></i>Filtres
                    </h6>
                    <!-- Formulaire GET : les filtres passent dans l'URL -->
                    <form method="GET" action="">

                        <label class="form-label small fw-semibold">Type de support</label>
                        <select name="support" class="form-select form-select-sm mb-3">
                            <option value="">Tous</option>
                            <?php foreach ($support_labels as $val => $label): ?>
                                <option value="<?= $val ?>"
                                    <?= ($filtres['support'] === $val) ? 'selected' : '' ?>>
                                    <?= $icons[$val] ?> <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label class="form-label small fw-semibold">Mode</label>
                        <select name="type_achat" class="form-select form-select-sm mb-3">
                            <option value="">Achat + Location</option>
                            <option value="vente"
                                <?= ($filtres['type_achat']==='vente') ? 'selected':'' ?>>
                                Achat seulement
                            </option>
                            <option value="location"
                                <?= ($filtres['type_achat']==='location') ? 'selected':'' ?>>
                                Location seulement
                            </option>
                        </select>

                        <label class="form-label small fw-semibold">Genre</label>
                        <input type="text" name="genre" class="form-control form-control-sm mb-3"
                               placeholder="ex: Action, Comédie..."
                               value="<?= htmlspecialchars($filtres['genre']) ?>">

                        <button class="btn btn-danger btn-sm w-100">
                            <i class="bi bi-search me-1"></i>Filtrer
                        </button>
                        <!-- Réinitialisation = retour sans paramètres GET -->
                        <a href="catalogue.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                            <i class="bi bi-x me-1"></i>Réinitialiser
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== GRILLE PRODUITS ===== -->
        <div class="col-md-9">
            <?php if (empty($produits)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Aucun produit ne correspond à votre recherche.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($produits as $p): ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="card product-card h-100 border-0 shadow-sm">

                                <!-- Placeholder avec émoji selon le support -->
                                <div class="cover-placeholder">
                                    <?= $icons[$p['support']] ?? '🎬' ?>
                                </div>

                                <div class="card-body">
                                    <!-- Badge couleur selon le type de support -->
                                    <span class="badge badge-<?= $p['support'] ?> mb-2">
                                        <?= $support_labels[$p['support']] ?? $p['support'] ?>
                                    </span>

                                    <h6 class="card-title fw-bold mb-1 text-truncate">
                                        <?= htmlspecialchars($p['titre']) ?>
                                    </h6>
                                    <p class="text-muted small mb-2">
                                        <?= htmlspecialchars($p['realisateur'] ?? '') ?>
                                        <?= $p['annee'] ? '· ' . $p['annee'] : '' ?>
                                    </p>

                                    <!-- Affichage des prix ET des stocks séparés -->
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($p['prix_vente']): ?>
                                            <span class="badge bg-danger">
                                                <?= number_format($p['prix_vente'],2) ?>€
                                            </span>
                                            <!-- Stock vente : vert si dispo, gris si rupture -->
                                            <span class="badge bg-<?= ($p['stock_vente'] ?? 0) > 0 ? 'success' : 'secondary' ?>">
                                                <?= ($p['stock_vente'] ?? 0) > 0
                                                    ? $p['stock_vente'].' dispo'
                                                    : 'Rupture vente' ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($p['prix_location']): ?>
                                            <span class="badge bg-warning text-dark">
                                                <?= number_format($p['prix_location'],2) ?>€/j
                                            </span>
                                            <!-- Stock location : bleu si dispo, gris si rupture -->
                                            <span class="badge bg-<?= ($p['stock_location'] ?? 0) > 0 ? 'info' : 'secondary' ?>">
                                                <?= ($p['stock_location'] ?? 0) > 0
                                                    ? $p['stock_location'].' loc.'
                                                    : 'Rupture location' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="card-footer bg-white border-0">
                                    <a href="produit.php?id=<?= $p['id'] ?>"
                                       class="btn btn-outline-danger btn-sm w-100">
                                        <i class="bi bi-eye me-1"></i>Voir la fiche
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
