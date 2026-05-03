<?php
// ============================================================
//  RandomPlay — pages/panier.php
//  Gestion du panier et confirmation de commande
//
//  Actions GET :
//  - ?remove=clé : supprime un article du panier
//  - ?clear=1    : vide tout le panier
//
//  Action POST :
//  - confirmer   : crée la commande via Order::createFromCart()
//                  puis passe le statut à 'confirmee'
//                  (ce qui déclenche le trigger de décrémentation stock)
//                  puis vide le panier et redirige
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Cart.php";
require_once "../src/Product.php";
require_once "../src/Order.php";
require_once "../src/Log.php";

// Réservé aux clients connectés
checkAccess('client');

$db         = Database::getInstance();
$orderModel = new Order($db);
$logModel   = new Log($db);
$success = $error = '';

// Suppression d'un article
if (isset($_GET['remove'])) {
    Cart::remove($_GET['remove']);
    header("Location: panier.php");
    exit;
}

// Vidage complet du panier
if (isset($_GET['clear'])) {
    Cart::clear();
    header("Location: panier.php");
    exit;
}

// Confirmation de commande (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer'])) {
    $panier = Cart::get();

    if (empty($panier)) {
        $error = "Votre panier est vide.";
    } else {
        // Création de la commande + lignes (transaction SQL)
        $orderId = $orderModel->createFromCart($_SESSION['user']['id'], $panier);

        if ($orderId) {
            // Passage en 'confirmee' → déclenche trg_decrement_stock
            $orderModel->updateStatut($orderId, 'confirmee');
            $logModel->write($_SESSION['user']['id'], 'commande_confirmee', "Commande #$orderId confirmée");
            Cart::clear();
            header("Location: commandes.php?success=1");
            exit;
        } else {
            $error = "Une erreur est survenue lors de la commande.";
        }
    }
}

$panier = Cart::get();
$total  = Cart::total();

include "../includes/header.php";
?>

<div class="container py-5">
    <h1 class="fw-bold mb-1">
        <i class="bi bi-cart3 text-danger me-2"></i>Mon panier
    </h1>
    <p class="text-muted mb-4"><?= Cart::count() ?> article(s)</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (empty($panier)): ?>
        <!-- Panier vide -->
        <div class="text-center py-5">
            <div style="font-size:4rem">🛒</div>
            <p class="text-muted mt-3">Votre panier est vide.</p>
            <a href="catalogue.php" class="btn btn-danger">Voir le catalogue</a>
        </div>

    <?php else: ?>
        <div class="row g-4">

            <!-- ===== LISTE DES ARTICLES ===== -->
            <div class="col-md-8">
                <?php foreach ($panier as $key => $item): ?>
                    <div class="cart-item d-flex align-items-center gap-3">
                        <!-- Icône support -->
                        <div style="font-size:2.5rem">
                            <?php
                            $icons = ['vhs'=>'📼','cassette_audio'=>'📼','cd'=>'💿','dvd'=>'📀','vinyle'=>'🎵'];
                            echo $icons[$item['support']] ?? '🎬';
                            ?>
                        </div>

                        <div class="flex-grow-1">
                            <div class="fw-bold"><?= htmlspecialchars($item['titre']) ?></div>
                            <div class="small text-muted">
                                <?= $item['type_achat'] === 'vente' ? '🛒 Achat' : '📅 Location' ?>
                                <?php if ($item['type_achat'] === 'location'): ?>
                                    — <?= $item['nb_jours'] ?> jour(s)
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Calcul du prix : vente = prix × qté, location = prix × qté × jours -->
                        <div class="fw-bold text-danger">
                            <?php
                            $prix = $item['prix_unit'] * $item['quantite'];
                            if ($item['type_achat'] === 'location') $prix *= max(1, $item['nb_jours']);
                            echo number_format($prix, 2) . '€';
                            ?>
                        </div>

                        <!-- Bouton suppression (GET ?remove=clé) -->
                        <a href="?remove=<?= urlencode($key) ?>" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                <?php endforeach; ?>

                <!-- Vider le panier -->
                <a href="?clear=1" class="btn btn-outline-secondary btn-sm mt-3">
                    <i class="bi bi-trash me-1"></i>Vider le panier
                </a>
            </div>

            <!-- ===== RÉCAPITULATIF ET VALIDATION ===== -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Récapitulatif</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sous-total</span>
                            <span class="fw-bold"><?= number_format($total,2) ?>€</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                            <span>Total</span>
                            <span class="text-danger"><?= number_format($total,2) ?>€</span>
                        </div>

                        <!-- Bouton confirmation → POST confirmer -->
                        <form method="POST" action="">
                            <button name="confirmer" class="btn btn-danger w-100 fw-bold btn-lg">
                                <i class="bi bi-check-circle me-1"></i>Confirmer la commande
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
