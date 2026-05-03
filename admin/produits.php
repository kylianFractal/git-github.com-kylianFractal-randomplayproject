<?php
// ============================================================
//  RandomPlay — admin/produits.php
//  CRUD complet sur le catalogue produits
//
//  Actions GET :
//  - ?delete=id  : supprime un produit (RESTRICT si commandes existent)
//  - ?edit=id    : pré-remplit le formulaire pour modification
//
//  Action POST :
//  - edit_id présent → UPDATE (modification)
//  - edit_id absent  → INSERT (création)
//
//  Chaque action est loggée dans la table logs via Log::write()
//  Les triggers MySQL journalisent automatiquement dans products_sauv
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Product.php";
require_once "../src/Log.php";

checkAccess('admin');

$db           = Database::getInstance();
$productModel = new Product($db);
$logModel     = new Log($db);
$success = $error = '';

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($productModel->delete($id)) {
        $logModel->write($_SESSION['user']['id'], 'suppression_produit', "Produit #$id supprimé");
        $success = "Produit supprimé.";
    } else {
        // Échoue si des order_items référencent ce produit (FK RESTRICT)
        $error = "Impossible de supprimer ce produit (commandes existantes ?).";
    }
}

// --- Création ou modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'titre'          => trim($_POST['titre']         ?? ''),
        'realisateur'    => trim($_POST['realisateur']   ?? ''),
        'annee'          => $_POST['annee']               ?: null,
        'support'        => $_POST['support']             ?? 'vhs',
        'genre'          => trim($_POST['genre']         ?? ''),
        'description'    => trim($_POST['description']   ?? ''),
        // Champs prix : null si vide (produit non disponible pour ce mode)
        'prix_vente'     => $_POST['prix_vente']    !== '' ? (float)$_POST['prix_vente']    : null,
        'prix_location'  => $_POST['prix_location'] !== '' ? (float)$_POST['prix_location'] : null,
        // Stocks séparés : vente et location indépendants
        'stock_vente'    => (int)($_POST['stock_vente']    ?? 0),
        'stock_location' => (int)($_POST['stock_location'] ?? 0),
        'cover_image'    => null, // Upload d'image non implémenté (extension possible)
    ];

    $editId = (int)($_POST['edit_id'] ?? 0);

    if ($editId > 0) {
        $productModel->update($editId, $data);
        $logModel->write($_SESSION['user']['id'], 'modification_produit', "Produit #$editId modifié");
        $success = "Produit mis à jour.";
    } else {
        $productModel->create($data);
        $logModel->write($_SESSION['user']['id'], 'creation_produit', "Nouveau produit : " . $data['titre']);
        $success = "Produit ajouté au catalogue.";
    }
}

$produits = $productModel->getAll();
// Si ?edit=id en URL → pré-charge le produit pour le formulaire
$editProd = isset($_GET['edit']) ? $productModel->getById((int)$_GET['edit']) : null;

$supports = [
    'vhs'            => '📼 VHS',
    'cassette_audio' => '📼 Cassette',
    'cd'             => '💿 CD',
    'dvd'            => '📀 DVD',
    'vinyle'         => '🎵 Vinyle',
];

include "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-2 admin-sidebar py-4 px-3">
            <p class="text-white-50 small text-uppercase fw-bold mb-3">Administration</p>
            <nav class="nav flex-column">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="nav-link active" href="produits.php"><i class="bi bi-collection me-2"></i>Produits</a>
                <a class="nav-link" href="commandes.php"><i class="bi bi-bag me-2"></i>Commandes</a>
                <a class="nav-link" href="locations.php"><i class="bi bi-calendar2-check me-2"></i>Locations</a>
                <a class="nav-link" href="utilisateurs.php"><i class="bi bi-people me-2"></i>Utilisateurs</a>
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-4">
                <i class="bi bi-collection text-danger me-2"></i>Gestion du catalogue
            </h2>

            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <!-- ===== FORMULAIRE AJOUT / MODIFICATION ===== -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="fw-bold mb-0">
                        <?= $editProd
                            ? '✏️ Modifier : ' . htmlspecialchars($editProd['titre'])
                            : '➕ Ajouter un produit' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <!-- Champ caché pour différencier création et modification -->
                        <?php if ($editProd): ?>
                            <input type="hidden" name="edit_id" value="<?= $editProd['id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Titre *</label>
                                <input type="text" name="titre" class="form-control" required
                                       value="<?= htmlspecialchars($editProd['titre'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Réalisateur / Artiste</label>
                                <input type="text" name="realisateur" class="form-control"
                                       value="<?= htmlspecialchars($editProd['realisateur'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Support *</label>
                                <select name="support" class="form-select" required>
                                    <?php foreach ($supports as $val => $label): ?>
                                        <option value="<?= $val ?>"
                                            <?= ($editProd['support'] ?? '') === $val ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Année</label>
                                <input type="number" name="annee" class="form-control"
                                       min="1900" max="2030"
                                       value="<?= $editProd['annee'] ?? '' ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Genre</label>
                                <input type="text" name="genre" class="form-control"
                                       value="<?= htmlspecialchars($editProd['genre'] ?? '') ?>">
                            </div>

                            <!-- Prix vente (null = non disponible à la vente) -->
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Prix vente (€)</label>
                                <input type="number" name="prix_vente" class="form-control"
                                       step="0.01" min="0"
                                       value="<?= $editProd['prix_vente'] ?? '' ?>">
                            </div>
                            <!-- Prix location (null = non disponible à la location) -->
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Prix loc./j (€)</label>
                                <input type="number" name="prix_location" class="form-control"
                                       step="0.01" min="0"
                                       value="<?= $editProd['prix_location'] ?? '' ?>">
                            </div>

                            <!-- Stocks séparés : indépendants -->
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Stock vente</label>
                                <input type="number" name="stock_vente" class="form-control"
                                       min="0" value="<?= $editProd['stock_vente'] ?? 0 ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">Stock location</label>
                                <input type="number" name="stock_location" class="form-control"
                                       min="0" value="<?= $editProd['stock_location'] ?? 0 ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="2">
                                    <?= htmlspecialchars($editProd['description'] ?? '') ?>
                                </textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-danger fw-bold">
                                    <i class="bi bi-save me-1"></i>
                                    <?= $editProd ? 'Enregistrer les modifications' : 'Ajouter le produit' ?>
                                </button>
                                <?php if ($editProd): ?>
                                    <a href="produits.php" class="btn btn-outline-secondary ms-2">Annuler</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ===== LISTE DES PRODUITS ===== -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Titre</th>
                                <th>Support</th>
                                <th>Stock vente</th>
                                <th>Stock loc.</th>
                                <th>Prix vente</th>
                                <th>Prix loc./j</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produits as $p):
                                // Ligne rouge si les DEUX stocks sont à zéro
                                $rupture = ($p['stock_vente'] <= 0 && $p['stock_location'] <= 0);
                            ?>
                                <tr class="<?= $rupture ? 'table-danger' : '' ?>">
                                    <td class="text-muted">#<?= $p['id'] ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['titre']) ?></td>
                                    <td><?= $supports[$p['support']] ?? $p['support'] ?></td>
                                    <td>
                                        <span class="badge bg-<?= $p['stock_vente'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $p['stock_vente'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['prix_location']): ?>
                                            <span class="badge bg-<?= $p['stock_location'] > 0 ? 'info' : 'secondary' ?>">
                                                <?= $p['stock_location'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $p['prix_vente']    ? number_format($p['prix_vente'],2).'€'    : '—' ?></td>
                                    <td><?= $p['prix_location'] ? number_format($p['prix_location'],2).'€' : '—' ?></td>
                                    <td>
                                        <!-- Modification : charge le formulaire avec ?edit=id -->
                                        <a href="?edit=<?= $p['id'] ?>"
                                           class="btn btn-sm btn-outline-warning me-1">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <!-- Suppression : confirmation JS avant -->
                                        <a href="?delete=<?= $p['id'] ?>"
                                           onclick="return confirm('Supprimer ce produit ?')"
                                           class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </a>
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
