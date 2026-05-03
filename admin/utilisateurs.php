<?php
// ============================================================
//  RandomPlay — admin/utilisateurs.php
//  Gestion des utilisateurs (côté admin)
//
//  Actions GET :
//  - ?delete=id : supprime un utilisateur CLIENT
//    → Impossible de supprimer un admin (User::deleteUser protège)
//    → Impossible de se supprimer soi-même (vérification manuelle)
//    → Le trigger users_delete journalise dans users_sauv
//
//  Affiche les stats : nombre de clients / admins
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/User.php";
require_once "../src/Log.php";

checkAccess('admin');

$db        = Database::getInstance();
$userModel = new User($db);
$logModel  = new Log($db);
$success = $error = '';

// Suppression d'un utilisateur
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Protection : on ne peut pas se supprimer soi-même
    if ($id === (int)$_SESSION['user']['id']) {
        $error = "Vous ne pouvez pas supprimer votre propre compte.";
    } elseif ($userModel->deleteUser($id)) {
        // deleteUser() ne supprime que les clients (type != 'admin')
        $logModel->write($_SESSION['user']['id'], 'suppression_user', "Utilisateur #$id supprimé");
        $success = "Utilisateur supprimé.";
    } else {
        $error = "Impossible de supprimer cet utilisateur (admin protégé ou introuvable).";
    }
}

$users = $userModel->getAllUsers();

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
                <a class="nav-link active" href="utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a>
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-4">
                <i class="bi bi-people text-danger me-2"></i>Gestion des utilisateurs
            </h2>

            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <!-- Statistiques clients / admins -->
            <div class="row g-3 mb-4">
                <?php
                $nbClients = count(array_filter($users, fn($u) => $u['type'] === 'client'));
                $nbAdmins  = count(array_filter($users, fn($u) => $u['type'] === 'admin'));
                ?>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <i class="bi bi-people text-primary fs-2"></i>
                            <div>
                                <div class="stat-number text-primary"><?= $nbClients ?></div>
                                <div class="small text-muted">Clients</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body d-flex align-items-center gap-3">
                            <i class="bi bi-shield-lock text-warning fs-2"></i>
                            <div>
                                <div class="stat-number text-warning"><?= $nbAdmins ?></div>
                                <div class="small text-muted">Administrateurs</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des utilisateurs -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th><th>Pseudo</th><th>Nom complet</th>
                                <th>Email</th><th>Rôle</th><th>Inscrit le</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <!-- Ligne jaune pour les admins -->
                                <tr class="<?= $u['type'] === 'admin' ? 'table-warning' : '' ?>">
                                    <td class="text-muted">#<?= $u['id'] ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($u['pseudo']) ?></td>
                                    <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <?php if ($u['type'] === 'admin'): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-shield-lock me-1"></i>Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">
                                                <i class="bi bi-person me-1"></i>Client
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td>
                                        <!-- Suppression impossible pour les admins et soi-même -->
                                        <?php if ($u['type'] !== 'admin' && $u['id'] !== (int)$_SESSION['user']['id']): ?>
                                            <a href="?delete=<?= $u['id'] ?>"
                                               onclick="return confirm('Supprimer cet utilisateur ?')"
                                               class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </a>
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
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
