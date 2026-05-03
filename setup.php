<?php
// ============================================================
//  RandomPlay — setup.php
//  Initialisation des mots de passe de test (bcrypt)
//
//  UTILISATION :
//  1. Importer randomplay_database.sql dans phpMyAdmin
//  2. Placer ce fichier à la RACINE du projet
//  3. Ouvrir http://localhost/randomplay/setup.php
//  4. Vérifier le message de succès vert
//  5. ⚠️ SUPPRIMER ce fichier immédiatement après !
//
//  Algorithme : bcrypt (PASSWORD_BCRYPT) — cost factor 12
//  Pas besoin de la classe User ici : on utilise password_hash()
//  directement pour éviter toute dépendance.
// ============================================================

require_once "src/controller.php";
// Note : on N'importe PAS User.php — password_hash() est une
// fonction native PHP, pas besoin de la classe User ici.

$db = Database::getInstance();

// Comptes de test avec leurs mots de passe en clair
// Le hash bcrypt sera calculé par PHP et inséré via UPDATE
$users = [
    ['pseudo' => 'admin_rp', 'mdp' => 'admin123'],
    ['pseudo' => 'marie_d',  'mdp' => 'client123'],
    ['pseudo' => 'lucas_m',  'mdp' => 'client123'],
];

$updated = 0;
foreach ($users as $u) {
    // password_hash() génère un hash bcrypt unique à chaque appel
    // (sel aléatoire intégré automatiquement — jamais stocké séparément)
    $hash = password_hash($u['mdp'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("UPDATE users SET mdp = :mdp WHERE pseudo = :pseudo");
    if ($stmt->execute(['mdp' => $hash, 'pseudo' => $u['pseudo']])) {
        $updated++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>RandomPlay — Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card bg-secondary border-0 shadow p-4 text-center" style="max-width:520px;width:100%">
    <h2 class="text-danger fw-bold mb-3">🎬 RandomPlay — Setup</h2>

    <?php if ($updated === count($users)): ?>
        <div class="alert alert-success">
            ✅ <?= $updated ?> mots de passe initialisés avec succès (SHA-512 + sel) !
        </div>

        <table class="table table-dark table-sm text-start mt-3">
            <thead><tr><th>Pseudo</th><th>Mot de passe</th><th>Rôle</th><th>Hash</th></tr></thead>
            <tbody>
                <tr>
                    <td>admin_rp</td>
                    <td><code>admin123</code></td>
                    <td><span class="badge bg-warning text-dark">Admin</span></td>
                    <td><span class="badge bg-info text-dark">SHA-512</span></td>
                </tr>
                <tr>
                    <td>marie_d</td>
                    <td><code>client123</code></td>
                    <td><span class="badge bg-primary">Client</span></td>
                    <td><span class="badge bg-info text-dark">SHA-512</span></td>
                </tr>
                <tr>
                    <td>lucas_m</td>
                    <td><code>client123</code></td>
                    <td><span class="badge bg-primary">Client</span></td>
                    <td><span class="badge bg-info text-dark">SHA-512</span></td>
                </tr>
            </tbody>
        </table>

        <!-- Explication du format pour le dossier BTS -->
        <div class="alert alert-info text-start small mt-3">
            <strong>Format stocké en BDD :</strong><br>
            <code>SEL_64HEX:HASH_SHA512</code><br>
            Exemple : <code>a3f9...bc12:d7e8...ff01</code><br><br>
            Le sel est unique par utilisateur (random_bytes).
            La vérification utilise <code>hash_equals()</code>
            pour résister aux timing attacks.
        </div>

        <a href="index.php" class="btn btn-danger mt-2 fw-bold">
            Aller sur le site →
        </a>
        <div class="alert alert-warning mt-3 small">
            ⚠️ <strong>Supprime ce fichier setup.php</strong> après utilisation !
        </div>

    <?php else: ?>
        <div class="alert alert-danger">
            ❌ Erreur (<?= $updated ?>/<?= count($users) ?>).<br>
            Vérifie que la BDD <code>randomplay</code> est bien importée.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
