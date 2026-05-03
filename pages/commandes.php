<?php
// ============================================================
//  RandomPlay — pages/commandes.php
//  Historique des commandes du client connecté
//
//  Affiche toutes les commandes avec :
//  - Statut (en_attente, confirmee, terminee, annulee)
//  - Liste des articles commandés / loués
//  - Statut du retour pour les locations (en retard ?)
//
//  Pas d'actions ici : l'annulation et les changements
//  de statut sont gérés côté admin uniquement.
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Order.php";

checkAccess('client');

$db         = Database::getInstance();
$orderModel = new Order($db);
$itemModel  = new OrderItem($db);

// Récupère uniquement les commandes du client connecté
$commandes = $orderModel->getByUser($_SESSION['user']['id']);

// Labels d'affichage pour les statuts de commande
$statut_labels = [
    'en_attente' => ['label' => 'En attente',  'badge' => 'warning'],
    'confirmee'  => ['label' => 'Confirmée',   'badge' => 'success'],
    'terminee'   => ['label' => 'Terminée',    'badge' => 'secondary'],
    'annulee'    => ['label' => 'Annulée',     'badge' => 'danger'],
];

include "../includes/header.php";
?>

<div class="container py-5">
    <h1 class="fw-bold mb-1">
        <i class="bi bi-bag text-danger me-2"></i>Mes commandes
    </h1>
    <p class="text-muted mb-4"><?= count($commandes) ?> commande(s)</p>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-1"></i>
            Commande confirmée avec succès ! Merci pour votre achat.
        </div>
    <?php endif; ?>

    <?php if (empty($commandes)): ?>
        <div class="text-center py-5">
            <div style="font-size:4rem">📦</div>
            <p class="text-muted mt-3">Aucune commande pour l'instant.</p>
            <a href="catalogue.php" class="btn btn-danger">Voir le catalogue</a>
        </div>

    <?php else: ?>
        <?php foreach ($commandes as $cmd):
            // Récupère les lignes (articles) de cette commande avec infos location
            $items = $itemModel->getByOrder($cmd['id']);
            $s = $statut_labels[$cmd['statut']] ?? ['label'=>$cmd['statut'],'badge'=>'secondary'];
        ?>
            <div class="card border-0 shadow-sm mb-4">
                <!-- En-tête commande : numéro, date, statut, total -->
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold">Commande #<?= $cmd['id'] ?></span>
                        <span class="text-muted small ms-3">
                            <?= date('d/m/Y H:i', strtotime($cmd['created_at'])) ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-<?= $s['badge'] ?>"><?= $s['label'] ?></span>
                        <span class="fw-bold text-danger"><?= number_format($cmd['total'],2) ?>€</span>
                    </div>
                </div>

                <!-- Tableau des lignes de la commande -->
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Produit</th>
                                <th>Type</th>
                                <th>Qté</th>
                                <th>Prix unit.</th>
                                <th>Retour prévu</th>
                                <th>Statut retour</th>
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
                                    <td>
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
                                            <?php $retard = strtotime($item['date_retour_prevue']) < time(); ?>
                                            <span class="small text-<?= $retard ? 'danger fw-bold' : 'muted' ?>">
                                                <?= $retard ? '⚠️ En retard' : 'En cours' ?>
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
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include "../includes/footer.php"; ?>
