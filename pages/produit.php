<?php
// ============================================================
//  RandomPlay — pages/produit.php
//  Fiche détail d'un produit + ajout au panier
//
//  Ajout par rapport à la version précédente :
//  - Log 'ajout_panier' lors de chaque ajout au panier
//  - Message d'avertissement IP bloquée (brute-force)
// ============================================================

require_once "../src/controller.php";
require_once "../src/Product.php";
require_once "../src/Cart.php";
require_once "../src/Log.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$db           = Database::getInstance();
$productModel = new Product($db);
$logModel     = new Log($db);

$id      = (int)($_GET['id'] ?? 0);
$produit = $productModel->getById($id);

if (!$produit) {
    header("Location: catalogue.php");
    exit;
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user'])) {
    $type    = $_POST['type_achat'] ?? 'vente';
    $nbJours = max(1, (int)($_POST['nb_jours'] ?? 1));

    if (!$productModel->isEnStock($id, $type)) {
        $error = "Ce produit n'est plus en stock.";
    } elseif ($type === 'vente' && !$produit['prix_vente']) {
        $error = "Ce produit n'est pas disponible à la vente.";
    } elseif ($type === 'location' && !$produit['prix_location']) {
        $error = "Ce produit n'est pas disponible à la location.";
    } else {
        Cart::add([
            'product_id'  => $produit['id'],
            'titre'       => $produit['titre'],
            'support'     => $produit['support'],
            'cover_image' => $produit['cover_image'],
            'prix_unit'   => $type === 'vente' ? $produit['prix_vente'] : $produit['prix_location'],
            'quantite'    => 1,
            'type_achat'  => $type,
            'nb_jours'    => $nbJours,
        ]);

        // Log de l'ajout au panier
        $logModel->write(
            $_SESSION['user']['id'],
            'ajout_panier',
            "Produit #{$produit['id']} ({$produit['titre']}) — $type"
            . ($type === 'location' ? " — $nbJours jour(s)" : "")
        );

        $success = "Ajouté au panier !";
    }
}

$icons       = ['vhs'=>'📼','cassette_audio'=>'📼','cd'=>'💿','dvd'=>'📀','vinyle'=>'🎵'];
$peutAcheter = $produit['prix_vente']    && ($produit['stock_vente']    ?? 0) > 0;
$peutLouer   = $produit['prix_location'] && ($produit['stock_location'] ?? 0) > 0;

include "../includes/header.php";
?>

<div class="container py-5">
    <a href="catalogue.php" class="btn btn-outline-secondary btn-sm mb-4">
        <i class="bi bi-arrow-left me-1"></i>Retour au catalogue
    </a>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-1"></i><?= $success ?>
            <a href="panier.php" class="alert-link ms-2">Voir le panier</a>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-1"></i><?= $error ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Cover -->
        <div class="col-md-3 text-center">
            <div class="cover-placeholder rounded-3" style="height:250px;font-size:5rem">
                <?= $icons[$produit['support']] ?? '🎬' ?>
            </div>
            <span class="badge bg-secondary mt-2"><?= $produit['support'] ?></span>
        </div>

        <!-- Infos -->
        <div class="col-md-6">
            <h1 class="fw-bold"><?= htmlspecialchars($produit['titre']) ?></h1>
            <?php if ($produit['realisateur']): ?>
                <p class="text-muted mb-1">
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($produit['realisateur']) ?>
                </p>
            <?php endif; ?>
            <?php if ($produit['annee']): ?>
                <p class="text-muted mb-1">
                    <i class="bi bi-calendar me-1"></i><?= $produit['annee'] ?>
                </p>
            <?php endif; ?>
            <?php if ($produit['genre']): ?>
                <span class="badge bg-light text-dark border mb-3">
                    <?= htmlspecialchars($produit['genre']) ?>
                </span>
            <?php endif; ?>
            <?php if ($produit['description']): ?>
                <p class="mt-2"><?= nl2br(htmlspecialchars($produit['description'])) ?></p>
            <?php endif; ?>

            <!-- Stocks séparés -->
            <p class="mt-3 d-flex gap-2 flex-wrap">
                <?php if ($produit['prix_vente']): ?>
                    <span class="badge bg-<?= ($produit['stock_vente'] ?? 0) > 0 ? 'success' : 'danger' ?>">
                        <i class="bi bi-bag me-1"></i>
                        <?= ($produit['stock_vente'] ?? 0) > 0
                            ? 'Vente : '.$produit['stock_vente'].' dispo'
                            : 'Rupture vente' ?>
                    </span>
                <?php endif; ?>
                <?php if ($produit['prix_location']): ?>
                    <span class="badge bg-<?= ($produit['stock_location'] ?? 0) > 0 ? 'info' : 'secondary' ?>">
                        <i class="bi bi-calendar2 me-1"></i>
                        <?= ($produit['stock_location'] ?? 0) > 0
                            ? 'Location : '.$produit['stock_location'].' dispo'
                            : 'Rupture location' ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Prix + boutons -->
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($produit['prix_vente']): ?>
                        <div class="mb-3 p-3 bg-danger bg-opacity-10 rounded">
                            <div class="small text-muted">Prix d'achat</div>
                            <div class="fs-3 fw-bold text-danger">
                                <?= number_format($produit['prix_vente'],2) ?>€
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($produit['prix_location']): ?>
                        <div class="mb-3 p-3 bg-warning bg-opacity-10 rounded">
                            <div class="small text-muted">Location</div>
                            <div class="fs-3 fw-bold text-warning">
                                <?= number_format($produit['prix_location'],2) ?>€
                                <small class="fs-6">/jour</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['type'] === 'client' && ($peutAcheter || $peutLouer)): ?>
                        <form method="POST" action="">
                            <select name="type_achat" class="form-select form-select-sm mb-2"
                                    onchange="document.getElementById('locationDays').style.display =
                                              this.value==='location' ? 'block':'none'">
                                <?php if ($peutAcheter): ?>
                                    <option value="vente">🛒 Acheter</option>
                                <?php endif; ?>
                                <?php if ($peutLouer): ?>
                                    <option value="location">📅 Louer</option>
                                <?php endif; ?>
                            </select>
                            <div id="locationDays" style="display:none" class="mb-2">
                                <label class="form-label small">Nombre de jours</label>
                                <input type="number" name="nb_jours" class="form-control form-control-sm"
                                       min="1" max="30" value="3">
                            </div>
                            <button class="btn btn-danger w-100 fw-bold">
                                <i class="bi bi-cart-plus me-1"></i>Ajouter au panier
                            </button>
                        </form>
                    <?php elseif (!isset($_SESSION['user'])): ?>
                        <a href="login.php" class="btn btn-outline-danger w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Connectez-vous pour commander
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary w-100" disabled>Rupture de stock</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
