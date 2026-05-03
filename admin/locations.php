<?php
// ============================================================
//  RandomPlay — admin/locations.php
//  Gestion des locations et des retours (côté admin)
//
//  Action GET :
//  - ?retour=id : enregistre le retour d'une location
//    → Rental::enregistrerRetour() met date_retour_effective = CURDATE()
//    → Le trigger trg_restock_on_return remet stock_location à jour
//    → Un log est écrit dans la table logs
//
//  Les locations en retard sont signalées en rouge avec
//  un badge clignotant (animation CSS .retard-badge).
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Rental.php";
require_once "../src/Log.php";

checkAccess('admin');

$db          = Database::getInstance();
$rentalModel = new Rental($db);
$logModel    = new Log($db);
$success = '';

// Enregistrement d'un retour
if (isset($_GET['retour'])) {
    $id = (int)$_GET['retour'];
    if ($rentalModel->enregistrerRetour($id)) {
        // Le trigger MySQL trg_restock_on_return s'occupe du stock_location
        $logModel->write($_SESSION['user']['id'], 'retour_location', "Location #$id — retour enregistré par admin");
        $success = "Retour enregistré. Le stock location a été remis à jour automatiquement.";
    }
}

$locations = $rentalModel->getAll();
$retards   = $rentalModel->getEnRetard();

include "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 admin-sidebar py-4 px-3">
            <p class="text-white-50 small text-uppercase fw-bold mb-3">Administration</p>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="nav-link" href="produits.php"><i class="bi bi-collection me-2"></i>Produits</a>
                <a class="nav-link" href="commandes.php"><i class="bi bi-bag me-2"></i>Commandes</a>
                <a class="nav-link active" href="locations.php"><i class="bi bi-calendar2-check me-2"></i>Locations</a>
                <a class="nav-link" href="utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a>
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-4">
                <i class="bi bi-calendar2-check text-danger me-2"></i>Gestion des locations
            </h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <!-- Alerte globale si retards -->
            <?php if (!empty($retards)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <strong><?= count($retards) ?> location(s) en retard !</strong>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Film</th><th>Client</th><th>Qté</th>
                                <th>Début</th><th>Retour prévu</th>
                                <th>Retour effectif</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $loc):
                                $estEnRetard = !$loc['date_retour_effective']
                                    && strtotime($loc['date_retour_prevue']) < time();
                            ?>
                                <tr class="<?= $estEnRetard ? 'table-danger' : '' ?>">
                                    <td class="fw-semibold"><?= htmlspecialchars($loc['titre']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($loc['prenom']) ?>
                                        <span class="text-muted small">
                                            (<?= htmlspecialchars($loc['pseudo']) ?>)
                                        </span>
                                    </td>
                                    <td><?= $loc['quantite'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($loc['date_debut'])) ?></td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($loc['date_retour_prevue'])) ?>
                                        <?php if ($estEnRetard): ?>
                                            <span class="badge bg-danger retard-badge ms-1">RETARD</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($loc['date_retour_effective']): ?>
                                            <span class="text-success small">
                                                <i class="bi bi-check-circle me-1"></i>
                                                <?= date('d/m/Y', strtotime($loc['date_retour_effective'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">En cours</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$loc['date_retour_effective']): ?>
                                            <!-- Bouton retour : met date_retour_effective = CURDATE() -->
                                            <a href="?retour=<?= $loc['id'] ?>"
                                               onclick="return confirm('Confirmer le retour ?')"
                                               class="btn btn-sm btn-success">
                                                <i class="bi bi-box-arrow-in-down me-1"></i>Retour rendu
                                            </a>
                                        <?php else: ?>
                                            <span class="text-success small">✅ Rendu</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
