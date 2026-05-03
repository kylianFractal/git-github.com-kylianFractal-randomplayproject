<?php
// ============================================================
//  RandomPlay — pages/mon-compte.php
//  Espace personnel du client connecté
//
//  Deux onglets :
//  1. "Mes locations en cours" — articles loués non encore rendus
//     → Bouton "Rendre" sur chaque carte (GET ?rendre=id)
//     → Vérifie que la location appartient bien au client courant
//     → Appelle Rental::enregistrerRetour() qui déclenche
//       le trigger trg_restock_on_return côté MySQL
//
//  2. "Historique commandes" — toutes les commandes avec détail
//     des articles et statut de retour pour les locations
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Order.php";
require_once "../src/Rental.php";
require_once "../src/User.php";
require_once "../src/Log.php";

checkAccess('client');

$db          = Database::getInstance();
$orderModel  = new Order($db);
$rentalModel = new Rental($db);
$userModel   = new User($db);
$itemModel   = new OrderItem($db);
$logModel    = new Log($db);

$userId = (int)$_SESSION['user']['id'];

// ---- Traitement du retour d'une location ----
$successRetour = $errorRetour = '';
if (isset($_GET['rendre']) && is_numeric($_GET['rendre'])) {
    $rentalId = (int)$_GET['rendre'];

    // Sécurité : vérifie que cette location appartient bien au client connecté
    // et qu'elle n'a pas déjà été rendue (date_retour_effective IS NULL)
    $check = $db->prepare("
        SELECT r.id FROM rentals r
        INNER JOIN order_items oi ON oi.id = r.order_item_id
        INNER JOIN orders o       ON o.id  = oi.order_id
        WHERE r.id = :rid
          AND o.user_id = :uid
          AND r.date_retour_effective IS NULL
    ");
    $check->execute(['rid' => $rentalId, 'uid' => $userId]);

    if ($check->fetch()) {
        if ($rentalModel->enregistrerRetour($rentalId)) {
            // Le trigger MySQL trg_restock_on_return remet stock_location à jour
            $logModel->write($userId, 'retour_location', "Location #$rentalId rendue par le client");
            $successRetour = "Retour enregistré ! Merci d'avoir rapporté l'article.";
        } else {
            $errorRetour = "Erreur lors de l'enregistrement du retour.";
        }
    } else {
        $errorRetour = "Location introuvable ou déjà rendue.";
    }
}

// Récupération des données après traitement
$user      = $userModel->getUserById($userId);
$commandes = $orderModel->getByUser($userId);
$locations = $rentalModel->getActiveByUser($userId); // locations non rendues

// Statistiques rapides pour les cartes d'en-tête
$totalCommandes    = count($commandes);
$locationsActives  = count($locations);
$locationsEnRetard = count(array_filter($locations,
    fn($l) => strtotime($l['date_retour_prevue']) < time()
));

$statut_labels = [
    'en_attente' => ['label' => 'En attente',  'badge' => 'warning'],
    'confirmee'  => ['label' => 'Confirmée',   'badge' => 'success'],
    'terminee'   => ['label' => 'Terminée',    'badge' => 'secondary'],
    'annulee'    => ['label' => 'Annulée',     'badge' => 'danger'],
];

// Onglet actif par défaut : locations
$onglet = $_GET['onglet'] ?? 'locations';

include "../includes/header.php";
?>

<div class="container py-5">

    <!-- ===== ALERTES RETOUR ===== -->
    <?php if ($successRetour): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?= $successRetour ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($errorRetour): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle me-2"></i><?= $errorRetour ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ===== EN-TÊTE PROFIL ===== -->
    <div class="row align-items-center mb-4">
        <div class="col-auto">
            <!-- Avatar initiale du prénom -->
            <div class="bg-danger rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                 style="width:64px;height:64px;font-size:1.6rem">
                <?= strtoupper(mb_substr($user['prenom'], 0, 1)) ?>
            </div>
        </div>
        <div class="col">
            <h2 class="fw-bold mb-0">
                <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
            </h2>
            <p class="text-muted mb-0">
                <i class="bi bi-at me-1"></i><?= htmlspecialchars($user['pseudo']) ?>
                &nbsp;·&nbsp;
                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($user['email']) ?>
                &nbsp;·&nbsp;
                <span class="badge bg-primary">Client</span>
            </p>
        </div>
        <div class="col-auto">
            <a href="catalogue.php" class="btn btn-danger">
                <i class="bi bi-collection-play me-1"></i>Voir le catalogue
            </a>
        </div>
    </div>

    <!-- ===== CARTES STATISTIQUES ===== -->
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-bag text-danger fs-2"></i>
                    <div>
                        <div class="stat-number text-danger"><?= $totalCommandes ?></div>
                        <div class="small text-muted">Commande(s) passée(s)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-calendar2-check text-warning fs-2"></i>
                    <div>
                        <div class="stat-number text-warning"><?= $locationsActives ?></div>
                        <div class="small text-muted">Location(s) en cours</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle text-<?= $locationsEnRetard > 0 ? 'danger' : 'secondary' ?> fs-2"></i>
                    <div>
                        <div class="stat-number text-<?= $locationsEnRetard > 0 ? 'danger' : 'secondary' ?>">
                            <?= $locationsEnRetard ?>
                        </div>
                        <div class="small text-muted">Location(s) en retard</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ONGLETS ===== -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $onglet === 'locations' ? 'active' : '' ?>"
               href="?onglet=locations">
                <i class="bi bi-calendar2-check me-1"></i>Mes locations en cours
                <?php if ($locationsActives > 0): ?>
                    <span class="badge bg-<?= $locationsEnRetard > 0 ? 'danger' : 'warning text-dark' ?> ms-1">
                        <?= $locationsActives ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $onglet === 'commandes' ? 'active' : '' ?>"
               href="?onglet=commandes">
                <i class="bi bi-bag me-1"></i>Historique commandes
                <?php if ($totalCommandes > 0): ?>
                    <span class="badge bg-secondary ms-1"><?= $totalCommandes ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <!-- ===== ONGLET LOCATIONS EN COURS ===== -->
    <?php if ($onglet === 'locations'): ?>

        <!-- Alerte si des retards existent -->
        <?php if ($locationsEnRetard > 0): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>
                    <strong>Attention !</strong>
                    Vous avez <?= $locationsEnRetard ?> location(s) en retard.
                    Veuillez rapporter le(s) article(s) au plus vite.
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($locations)): ?>
            <div class="text-center py-5">
                <div style="font-size:4rem">📼</div>
                <p class="text-muted mt-3">Vous n'avez aucune location en cours.</p>
                <a href="catalogue.php?type_achat=location" class="btn btn-danger">
                    Voir les articles à louer
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($locations as $loc):
                    $estEnRetard   = strtotime($loc['date_retour_prevue']) < time();
                    $joursRestants = ceil((strtotime($loc['date_retour_prevue']) - time()) / 86400);
                    $icons = ['vhs'=>'📼','cassette_audio'=>'📼','cd'=>'💿','dvd'=>'📀','vinyle'=>'🎵'];
                ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm border-start border-4
                                    border-<?= $estEnRetard ? 'danger' : 'warning' ?>">
                            <div class="card-body d-flex gap-3">
                                <!-- Icône support -->
                                <div style="font-size:2.5rem">
                                    <?= $icons[$loc['support']] ?? '🎬' ?>
                                </div>

                                <div class="flex-grow-1">
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($loc['titre']) ?></h6>
                                    <div class="small text-muted mb-2">
                                        <span class="badge bg-secondary me-1"><?= $loc['support'] ?></span>
                                        Qté : <?= $loc['quantite'] ?>
                                    </div>
                                    <!-- Dates de location -->
                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <div class="text-muted">Début</div>
                                            <div class="fw-semibold">
                                                <?= date('d/m/Y', strtotime($loc['date_debut'])) ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted">Retour prévu</div>
                                            <div class="fw-semibold text-<?= $estEnRetard ? 'danger' : 'success' ?>">
                                                <?= date('d/m/Y', strtotime($loc['date_retour_prevue'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Badge jours restants + bouton Rendre -->
                                <div class="text-end d-flex flex-column gap-2 align-items-end">
                                    <?php if ($estEnRetard): ?>
                                        <span class="badge bg-danger retard-badge">
                                            <i class="bi bi-exclamation-triangle me-1"></i>RETARD
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <?= $joursRestants ?>j restant<?= $joursRestants > 1 ? 's' : '' ?>
                                        </span>
                                    <?php endif; ?>

                                    <!-- Bouton retour : GET ?onglet=locations&rendre=id -->
                                    <a href="?onglet=locations&rendre=<?= $loc['id'] ?>"
                                       class="btn btn-sm btn-<?= $estEnRetard ? 'danger' : 'outline-secondary' ?> mt-1"
                                       onclick="return confirm('Confirmer le retour de «&nbsp;<?= htmlspecialchars($loc['titre']) ?>&nbsp;» ?')">
                                        <i class="bi bi-box-arrow-in-down me-1"></i>Rendre
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- ===== ONGLET HISTORIQUE COMMANDES ===== -->
    <?php elseif ($onglet === 'commandes'): ?>

        <?php if (empty($commandes)): ?>
            <div class="text-center py-5">
                <div style="font-size:4rem">📦</div>
                <p class="text-muted mt-3">Vous n'avez passé aucune commande.</p>
                <a href="catalogue.php" class="btn btn-danger">Voir le catalogue</a>
            </div>
        <?php else: ?>
            <?php foreach ($commandes as $cmd):
                $items = $itemModel->getByOrder($cmd['id']);
                $s = $statut_labels[$cmd['statut']] ?? ['label'=>$cmd['statut'],'badge'=>'secondary'];
            ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-bold">Commande #<?= $cmd['id'] ?></span>
                            <span class="text-muted small ms-3">
                                <?= date('d/m/Y H:i', strtotime($cmd['created_at'])) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $s['badge'] ?>"><?= $s['label'] ?></span>
                            <span class="fw-bold text-danger"><?= number_format($cmd['total'],2) ?>€</span>
                        </div>
                    </div>

                    <?php if (!empty($items)): ?>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Article</th><th>Type</th><th>Prix</th>
                                        <th>Retour prévu</th><th>Statut retour</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($item['titre']) ?></td>
                                            <td>
                                                <?= $item['type_achat'] === 'vente'
                                                    ? '<span class="badge bg-danger">Achat</span>'
                                                    : '<span class="badge bg-warning text-dark">Location</span>' ?>
                                            </td>
                                            <td><?= number_format($item['prix_unit'],2) ?>€</td>
                                            <td class="text-muted small">
                                                <?= $item['date_retour_prevue']
                                                    ? date('d/m/Y', strtotime($item['date_retour_prevue']))
                                                    : '—' ?>
                                            </td>
                                            <td>
                                                <?php if ($item['date_retour_effective']): ?>
                                                    <span class="text-success small">
                                                        <i class="bi bi-check-circle me-1"></i>Rendu le
                                                        <?= date('d/m/Y', strtotime($item['date_retour_effective'])) ?>
                                                    </span>
                                                <?php elseif ($item['date_retour_prevue']): ?>
                                                    <?php $ret = strtotime($item['date_retour_prevue']) < time(); ?>
                                                    <span class="small text-<?= $ret ? 'danger fw-bold' : 'muted' ?>">
                                                        <?= $ret ? '⚠️ En retard' : 'En cours' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>
