<?php
// ============================================================
//  RandomPlay — admin/audit.php
//  Journal d'audit automatique (tables users_sauv + products_sauv)
//
//  Ces tables sont alimentées AUTOMATIQUEMENT par les triggers MySQL :
//  - users_insert / users_update / users_delete → users_sauv
//  - products_insert / products_update / products_delete → products_sauv
//
//  Codes opération (hérités de tpevent.sql) :
//  - A = Ajout (INSERT)
//  - M = Modification (UPDATE — sauvegarde l'ANCIENNE valeur)
//  - S = Suppression (DELETE — sauvegarde les données avant suppression)
//
//  Différence avec logs.php :
//  - logs.php    : actions utilisateurs (ce que fait l'utilisateur)
//  - audit.php   : données avant modification (ce qui était dans la BDD)
// ============================================================

require_once "../src/auth_check.php";
checkAccess('admin');

$db  = Database::getInstance();
$tab = $_GET['tab'] ?? 'users';

// Récupère les 200 derniers enregistrements d'audit utilisateurs
$usersAudit = $db->query("
    SELECT us.*
    FROM users_sauv us
    ORDER BY us.date_modification DESC
    LIMIT 200
")->fetchAll();

// Récupère les 200 derniers enregistrements d'audit produits
$productsAudit = $db->query("
    SELECT ps.*
    FROM products_sauv ps
    ORDER BY ps.date_modification DESC
    LIMIT 200
")->fetchAll();

// Labels et couleurs pour les codes opération A/M/S
$op_labels = [
    'A' => ['label' => 'Ajout',        'badge' => 'success', 'icon' => 'bi-plus-circle-fill'],
    'M' => ['label' => 'Modification', 'badge' => 'warning', 'icon' => 'bi-pencil-fill'],
    'S' => ['label' => 'Suppression',  'badge' => 'danger',  'icon' => 'bi-trash-fill'],
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
                <a class="nav-link" href="commandes.php"><i class="bi bi-bag me-2"></i>Commandes</a>
                <a class="nav-link" href="locations.php"><i class="bi bi-calendar2-check me-2"></i>Locations</a>
                <a class="nav-link" href="utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a>
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link active" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-1">
                <i class="bi bi-shield-check text-danger me-2"></i>Journal d'audit
            </h2>
            <p class="text-muted mb-2">
                Alimenté automatiquement par <strong>triggers MySQL</strong> — aucun code PHP requis.
            </p>
            <div class="mb-4">
                <span class="badge bg-success me-1">
                    <i class="bi bi-plus-circle-fill me-1"></i>A = Ajout
                </span>
                <span class="badge bg-warning text-dark me-1">
                    <i class="bi bi-pencil-fill me-1"></i>M = Modification (valeur avant)
                </span>
                <span class="badge bg-danger me-1">
                    <i class="bi bi-trash-fill me-1"></i>S = Suppression
                </span>
            </div>

            <!-- Onglets users_sauv / products_sauv -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'users' ? 'active' : '' ?>"
                       href="?tab=users">
                        <i class="bi bi-people me-1"></i>Utilisateurs
                        <span class="badge bg-secondary ms-1"><?= count($usersAudit) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'products' ? 'active' : '' ?>"
                       href="?tab=products">
                        <i class="bi bi-collection me-1"></i>Produits
                        <span class="badge bg-secondary ms-1"><?= count($productsAudit) ?></span>
                    </a>
                </li>
            </ul>

            <!-- ===== ONGLET USERS_SAUV ===== -->
            <?php if ($tab === 'users'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <h5 class="fw-bold mb-0">users_sauv</h5>
                        <span class="text-muted small">200 dernières entrées</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th><th>Opération</th><th>ID user</th>
                                    <th>Pseudo</th><th>Nom</th><th>Email</th>
                                    <th>Rôle</th><th>Date modification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usersAudit)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            Aucune entrée — les triggers s'alimenteront lors de la première modification.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($usersAudit as $row):
                                    $op = $op_labels[$row['type_operation']]
                                        ?? ['label'=>$row['type_operation'],'badge'=>'secondary','icon'=>'bi-circle'];
                                ?>
                                    <!-- Couleur de ligne selon le type d'opération -->
                                    <tr class="<?= $row['type_operation'] === 'S' ? 'table-danger' :
                                                  ($row['type_operation'] === 'M' ? 'table-warning' : '') ?>">
                                        <td class="text-muted"><?= $row['id_sauv'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $op['badge'] ?>">
                                                <i class="bi <?= $op['icon'] ?> me-1"></i><?= $op['label'] ?>
                                            </span>
                                        </td>
                                        <td>#<?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['pseudo'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($row['nom']    ?? '—') ?></td>
                                        <td class="text-muted small"><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                                        <td>
                                            <?php if ($row['type_compte'] === 'admin'): ?>
                                                <span class="badge bg-warning text-dark">Admin</span>
                                            <?php elseif ($row['type_compte']): ?>
                                                <span class="badge bg-primary">Client</span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= date('d/m/Y H:i:s', strtotime($row['date_modification'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- ===== ONGLET PRODUCTS_SAUV ===== -->
            <?php elseif ($tab === 'products'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <h5 class="fw-bold mb-0">products_sauv</h5>
                        <span class="text-muted small">200 dernières entrées</span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th><th>Opération</th><th>ID produit</th>
                                    <th>Titre</th><th>Support</th>
                                    <th>Prix vente</th><th>Prix loc./j</th>
                                    <th>Stock</th><th>Date modification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($productsAudit)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            Aucune entrée — les triggers s'alimenteront lors de la première modification.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($productsAudit as $row):
                                    $op = $op_labels[$row['type_operation']]
                                        ?? ['label'=>$row['type_operation'],'badge'=>'secondary','icon'=>'bi-circle'];
                                ?>
                                    <tr class="<?= $row['type_operation'] === 'S' ? 'table-danger' :
                                                  ($row['type_operation'] === 'M' ? 'table-warning' : '') ?>">
                                        <td class="text-muted"><?= $row['id_sauv'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $op['badge'] ?>">
                                                <i class="bi <?= $op['icon'] ?> me-1"></i><?= $op['label'] ?>
                                            </span>
                                        </td>
                                        <td>#<?= $row['id'] ?></td>
                                        <td class="fw-semibold small"><?= htmlspecialchars($row['titre'] ?? '—') ?></td>
                                        <td><span class="badge bg-secondary"><?= $row['support'] ?? '—' ?></span></td>
                                        <td><?= $row['prix_vente']    !== null ? number_format($row['prix_vente'],2).'€'    : '—' ?></td>
                                        <td><?= $row['prix_location'] !== null ? number_format($row['prix_location'],2).'€' : '—' ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($row['stock'] ?? 0) > 0 ? 'success' : 'danger' ?>">
                                                <?= $row['stock'] ?? 0 ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?= date('d/m/Y H:i:s', strtotime($row['date_modification'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
