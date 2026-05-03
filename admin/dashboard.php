<?php
// ============================================================
//  RandomPlay — admin/dashboard.php
//  Tableau de bord administrateur
//  Adapté de photo4u/pages/admin.php
//
//  Affiche :
//  - 4 statistiques clés (produits en stock, clients, commandes du mois,
//    locations en retard)
//  - Tableau des 5 dernières commandes
//
//  Accès : réservé aux administrateurs via checkAccess('admin')
// ============================================================

require_once "../src/auth_check.php";
checkAccess('admin');

$db = Database::getInstance();

// --- Statistiques rapides via requêtes SQL directes ---

// Nombre de produits avec au moins un stock > 0 (vente OU location)
$stats['produits'] = $db->query(
    "SELECT COUNT(*) FROM products WHERE stock_vente > 0 OR stock_location > 0"
)->fetchColumn();

// Nombre de clients inscrits (hors admins)
$stats['users'] = $db->query(
    "SELECT COUNT(*) FROM users WHERE type = 'client'"
)->fetchColumn();

// Commandes passées ce mois-ci (MONTH + YEAR pour éviter confusion entre années)
$stats['commandes'] = $db->query(
    "SELECT COUNT(*) FROM orders
     WHERE MONTH(created_at) = MONTH(NOW())
       AND YEAR(created_at)  = YEAR(NOW())"
)->fetchColumn();

// Locations en retard = non rendues ET date_retour_prevue dépassée
$stats['retards'] = $db->query(
    "SELECT COUNT(*) FROM rentals
     WHERE date_retour_effective IS NULL
       AND date_retour_prevue < CURDATE()"
)->fetchColumn();

// 5 dernières commandes avec prénom et pseudo du client
$dernieresCommandes = $db->query("
    SELECT o.*, u.prenom, u.pseudo
    FROM orders o
    INNER JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 5
")->fetchAll();

include "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">

        <!-- ===== SIDEBAR ADMIN ===== -->
        <div class="col-md-2 admin-sidebar py-4 px-3">
            <p class="text-white-50 small text-uppercase fw-bold mb-3">Administration</p>
            <nav class="nav flex-column">
                <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="nav-link" href="produits.php"><i class="bi bi-collection me-2"></i>Produits</a>
                <a class="nav-link" href="commandes.php"><i class="bi bi-bag me-2"></i>Commandes</a>
                <a class="nav-link" href="locations.php"><i class="bi bi-calendar2-check me-2"></i>Locations</a>
                <a class="nav-link" href="utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a>
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <!-- ===== CONTENU PRINCIPAL ===== -->
        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-4">
                <i class="bi bi-speedometer2 text-danger me-2"></i>Tableau de bord
            </h2>

            <!-- Cartes statistiques -->
            <div class="row g-3 mb-4">
                <?php
                $statCards = [
                    ['icon' => 'bi-collection',       'label' => 'Produits en stock',   'val' => $stats['produits'],  'color' => 'primary'],
                    ['icon' => 'bi-people',            'label' => 'Clients inscrits',    'val' => $stats['users'],     'color' => 'success'],
                    ['icon' => 'bi-bag',               'label' => 'Commandes ce mois',   'val' => $stats['commandes'], 'color' => 'warning'],
                    ['icon' => 'bi-exclamation-triangle','label' => 'Locations en retard','val' => $stats['retards'],  'color' => 'danger'],
                ];
                foreach ($statCards as $sc): ?>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="text-<?= $sc['color'] ?>" style="font-size:2rem">
                                    <i class="bi <?= $sc['icon'] ?>"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-<?= $sc['color'] ?>">
                                        <?= $sc['val'] ?>
                                    </div>
                                    <div class="small text-muted"><?= $sc['label'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Tableau des 5 dernières commandes -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Dernières commandes</h5>
                    <a href="commandes.php" class="btn btn-outline-danger btn-sm">Tout voir</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Mapping statut → couleur Bootstrap
                            $badges = [
                                'en_attente' => 'warning',
                                'confirmee'  => 'success',
                                'terminee'   => 'secondary',
                                'annulee'    => 'danger',
                            ];
                            foreach ($dernieresCommandes as $c): ?>
                                <tr>
                                    <td>#<?= $c['id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($c['prenom']) ?>
                                        <span class="text-muted small">(<?= htmlspecialchars($c['pseudo']) ?>)</span>
                                    </td>
                                    <td class="text-danger fw-bold">
                                        <?= number_format($c['total'],2) ?>€
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $badges[$c['statut']] ?? 'secondary' ?>">
                                            <?= $c['statut'] ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
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
