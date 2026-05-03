<?php
// ============================================================
//  RandomPlay — admin/commandes.php
//  Gestion de toutes les commandes
//
//  Permet de changer le statut d'une commande via un dropdown.
//  Passer à 'confirmee' → déclenche trg_decrement_stock côté BDD.
//  Chaque commande est dépliée avec le détail de ses lignes.
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Order.php";
require_once "../src/Log.php";

checkAccess('admin');

$db         = Database::getInstance();
$orderModel = new Order($db);
$itemModel  = new OrderItem($db);
$logModel   = new Log($db);
$success = $error = '';

// Changement de statut via GET — loggé pour traçabilité
if (isset($_GET['statut'], $_GET['id'])) {
    $id     = (int)$_GET['id'];
    $statut = $_GET['statut'];
    $ok     = $orderModel->updateStatut($id, $statut);
    if ($ok) {
        $logModel->write($_SESSION['user']['id'], 'statut_commande',
            "Commande #$id → statut : $statut");
        $success = "Statut mis à jour.";
    } else {
        $error = "Erreur lors de la mise à jour.";
    }
}

$commandes = $orderModel->getAll();

$statut_labels = [
    'en_attente' => ['label' => 'En attente',  'badge' => 'warning'],
    'confirmee'  => ['label' => 'Confirmée',   'badge' => 'success'],
    'terminee'   => ['label' => 'Terminée',    'badge' => 'secondary'],
    'annulee'    => ['label' => 'Annulée',     'badge' => 'danger'],
];

include "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 admin-sidebar py-4 px-3">
            <p class="text-white-50 small text-uppercase fw-bold mb-3">Administration</p>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="nav-link" href="produits.php"><i class="bi bi-collection me-2"></i>Produits</a>
                <a class="nav-link active" href="commandes.php"><i class="bi bi-bag me-2"></i>Commandes</a>
                <a class="nav-link" href="locations.php"><i class="bi bi-calendar2-check me-2"></i>Locations</a>
                <a class="nav-link" href="utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a>
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-4">
                <i class="bi bi-bag text-danger me-2"></i>Gestion des commandes
            </h2>

            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <?php foreach ($commandes as $cmd):
                $items = $itemModel->getByOrder($cmd['id']);
                $s = $statut_labels[$cmd['statut']] ?? ['label' => $cmd['statut'], 'badge' => 'secondary'];
            ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-bold">Commande #<?= $cmd['id'] ?></span>
                            <span class="text-muted small ms-2">
                                <?= htmlspecialchars($cmd['prenom']) ?>
                                (<?= htmlspecialchars($cmd['pseudo']) ?>)
                            </span>
                            <span class="text-muted small ms-2">
                                <?= date('d/m/Y H:i', strtotime($cmd['created_at'])) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-danger">
                                <?= number_format($cmd['total'],2) ?>€
                            </span>
                            <span class="badge bg-<?= $s['badge'] ?>"><?= $s['label'] ?></span>

                            <!-- Dropdown changement de statut -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        data-bs-toggle="dropdown">
                                    Changer statut
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($statut_labels as $slug => $sl):
                                        if ($slug === $cmd['statut']) continue; ?>
                                        <li>
                                            <a class="dropdown-item"
                                               href="?id=<?= $cmd['id'] ?>&statut=<?= $slug ?>">
                                                <span class="badge bg-<?= $sl['badge'] ?> me-2">
                                                    <?= $sl['label'] ?>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($items)): ?>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produit</th><th>Type</th><th>Qté</th>
                                        <th>Prix unit.</th><th>Retour prévu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['titre']) ?></td>
                                            <td>
                                                <?= $item['type_achat'] === 'vente'
                                                    ? '<span class="badge bg-danger">Achat</span>'
                                                    : '<span class="badge bg-warning text-dark">Location</span>' ?>
                                            </td>
                                            <td><?= $item['quantite'] ?></td>
                                            <td><?= number_format($item['prix_unit'],2) ?>€</td>
                                            <td class="text-muted small">
                                                <?= $item['date_retour_prevue']
                                                    ? date('d/m/Y', strtotime($item['date_retour_prevue']))
                                                    : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
