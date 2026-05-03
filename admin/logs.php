<?php
// ============================================================
//  RandomPlay — admin/logs.php
//  Journal des actions — version améliorée
//
//  Nouvelles fonctionnalités :
//  - Filtre par type d'action (connexion, erreurs, etc.)
//  - Statistiques des 30 derniers jours par action
//  - Mise en évidence des échecs de connexion (sécurité)
//  - Compteur d'IPs suspectes (brute-force)
// ============================================================

require_once "../src/auth_check.php";
require_once "../src/Log.php";

checkAccess('admin');

$db       = Database::getInstance();
$logModel = new Log($db);

// Filtre par action (via GET ?filtre=...)
$filtre = $_GET['filtre'] ?? '';

$logs  = $logModel->getAll(300, $filtre);
$stats = $logModel->getStats();

// Détection IPs suspectes : IPs avec plus de 3 échecs récents
$ipsSupspectes = $db->query("
    SELECT ip, COUNT(*) as nb_echecs, MAX(created_at) as dernier
    FROM logs
    WHERE action = 'echec_connexion'
      AND ip IS NOT NULL
      AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    GROUP BY ip
    HAVING nb_echecs >= 3
    ORDER BY nb_echecs DESC
")->fetchAll();

// Liste de toutes les actions distinctes pour le filtre
$actionsDisponibles = $db->query(
    "SELECT DISTINCT action FROM logs ORDER BY action"
)->fetchAll(PDO::FETCH_COLUMN);

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
                <a class="nav-link active" href="logs.php"><i class="bi bi-journal-text me-2"></i>Logs</a>
                <a class="nav-link" href="audit.php"><i class="bi bi-shield-check me-2"></i>Audit</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h2 class="fw-bold mb-1">
                <i class="bi bi-journal-text text-danger me-2"></i>Journal des actions
            </h2>
            <p class="text-muted mb-4">
                Traçabilité complète — connexions, erreurs, commandes, modifications admin
            </p>

            <!-- ===== ALERTE IPS SUSPECTES ===== -->
            <?php if (!empty($ipsSupspectes)): ?>
                <div class="alert alert-danger d-flex align-items-start mb-4">
                    <i class="bi bi-shield-exclamation fs-4 me-3 mt-1"></i>
                    <div>
                        <strong>⚠️ Activité suspecte détectée !</strong>
                        Les IPs suivantes ont généré plusieurs échecs de connexion en moins de 15 minutes :
                        <ul class="mb-0 mt-2">
                            <?php foreach ($ipsSupspectes as $ip): ?>
                                <li>
                                    <code><?= htmlspecialchars($ip['ip']) ?></code>
                                    → <strong><?= $ip['nb_echecs'] ?> tentatives</strong>
                                    (dernière : <?= date('H:i:s', strtotime($ip['dernier'])) ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">

                <!-- ===== STATISTIQUES 30 JOURS ===== -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-bold">
                            📊 Statistiques (30 derniers jours)
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>Action</th><th class="text-end">Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats as $stat):
                                        $niveau = Log::ACTION_NIVEAUX[$stat['action']] ?? Log::NIVEAU_INFO;
                                        $badge  = Log::NIVEAU_BADGES[$niveau] ?? 'secondary';
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= $badge ?> me-1" style="font-size:10px">
                                                    <?= htmlspecialchars($stat['action']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold"><?= $stat['total'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ===== FILTRE + LISTE ===== -->
                <div class="col-md-8">

                    <!-- Barre de filtre -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body py-2 px-3">
                            <form method="GET" action="" class="d-flex align-items-center gap-2 flex-wrap">
                                <label class="form-label mb-0 fw-semibold small">Filtrer :</label>
                                <select name="filtre" class="form-select form-select-sm" style="max-width:220px">
                                    <option value="">Toutes les actions</option>
                                    <?php foreach ($actionsDisponibles as $action): ?>
                                        <option value="<?= $action ?>"
                                            <?= $filtre === $action ? 'selected' : '' ?>>
                                            <?= $action ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-danger btn-sm">Filtrer</button>
                                <?php if ($filtre): ?>
                                    <a href="logs.php" class="btn btn-outline-secondary btn-sm">
                                        Tout afficher
                                    </a>
                                <?php endif; ?>
                                <span class="text-muted small ms-auto">
                                    <?= count($logs) ?> entrée(s)
                                </span>
                            </form>
                        </div>
                    </div>

                    <!-- Tableau des logs -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0" style="max-height:500px;overflow-y:auto">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <th>Date / Heure</th>
                                        <th>Utilisateur</th>
                                        <th>Action</th>
                                        <th>Détails</th>
                                        <th>IP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                Aucun log pour cette action.
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($logs as $log):
                                        // Couleur de ligne selon le niveau
                                        $niveau  = Log::ACTION_NIVEAUX[$log['action']] ?? Log::NIVEAU_INFO;
                                        $badge   = Log::NIVEAU_BADGES[$niveau] ?? 'secondary';
                                        $rowClass = '';
                                        if ($log['action'] === 'echec_connexion') $rowClass = 'table-danger';
                                        elseif (str_starts_with($log['action'], 'erreur_')) $rowClass = 'table-warning';
                                    ?>
                                        <tr class="<?= $rowClass ?>">
                                            <td class="text-muted" style="white-space:nowrap;font-size:11px">
                                                <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                                                <br>
                                                <strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($log['pseudo']): ?>
                                                    <span class="fw-semibold small">
                                                        <?= htmlspecialchars($log['pseudo']) ?>
                                                    </span>
                                                    <?php if ($log['user_type'] === 'admin'): ?>
                                                        <span class="badge bg-warning text-dark ms-1" style="font-size:9px">admin</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">visiteur</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $badge ?>" style="font-size:10px">
                                                    <?= htmlspecialchars($log['action']) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted" style="max-width:200px;word-break:break-word">
                                                <?= htmlspecialchars($log['details'] ?? '—') ?>
                                            </td>
                                            <td class="small text-muted" style="font-size:11px">
                                                <?= htmlspecialchars($log['ip'] ?? '—') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== LÉGENDE DES NIVEAUX ===== -->
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <span class="small fw-semibold me-3">Niveaux :</span>
                    <span class="badge bg-success me-2">connexion / inscription / commande</span>
                    <span class="badge bg-primary me-2">ajout panier / déconnexion</span>
                    <span class="badge bg-warning text-dark me-2">modification / retour location</span>
                    <span class="badge bg-danger me-2">échec connexion / suppression / erreur</span>
                    <span class="text-muted small">
                        — Les lignes rouges signalent des événements de sécurité à surveiller
                    </span>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
