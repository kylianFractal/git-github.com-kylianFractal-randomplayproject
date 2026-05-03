<?php
// ============================================================
//  RandomPlay — pages/register.php
//  Inscription d'un nouveau client
//
//  Ajout par rapport à la version précédente :
//  - Log 'inscription' lors d'une création réussie
//  - Le hash bcrypt est fait dans User::createUser() (cost 12)
// ============================================================

require_once "../src/controller.php";
require_once "../src/User.php";
require_once "../src/Log.php";

if (session_status() === PHP_SESSION_NONE) session_start();

$db        = Database::getInstance();
$userModel = new User($db);
$logModel  = new Log($db);
$errors    = [];
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email']  ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $nom    = trim($_POST['nom']    ?? '');
    $pseudo = trim($_POST['pseudo'] ?? '');
    $mdp    = $_POST['mdp']         ?? '';
    $mdp2   = $_POST['mdp2']        ?? '';

    // --- Validations ---
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Email invalide.";
    if (!$prenom)
        $errors[] = "Le prénom est requis.";
    if (!$nom)
        $errors[] = "Le nom est requis.";
    if (!$pseudo || strlen($pseudo) < 3)
        $errors[] = "Le pseudo doit contenir au moins 3 caractères.";
    if (!$mdp || strlen($mdp) < 6)
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    if ($mdp !== $mdp2)
        $errors[] = "Les mots de passe ne correspondent pas.";

    if (empty($errors)) {
        if ($userModel->getUserByEmail($email)) {
            $errors[] = "Cet email est déjà utilisé.";
        } elseif ($userModel->pseudoExists($pseudo)) {
            $errors[] = "Ce pseudo est déjà pris.";
        } else {
            // createUser() hash le mdp en bcrypt (cost 12) automatiquement
            $ok = $userModel->createUser([
                'prenom' => $prenom,
                'nom'    => $nom,
                'pseudo' => $pseudo,
                'email'  => $email,
                'mdp'    => $mdp,   // passé en clair → hashé dans createUser()
                'type'   => 'client',
            ]);

            if ($ok) {
                // Récupère l'ID du nouveau compte pour le log
                $newUser = $userModel->getUserByEmail($email);
                $logModel->write(
                    $newUser['id'] ?? null,
                    'inscription',
                    "Nouveau compte : $pseudo ($email)"
                );
                $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
            } else {
                $errors[] = "Erreur lors de la création du compte. Réessayez.";
            }
        }
    }
}

include "../includes/header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-body p-4">
                    <h2 class="text-center fw-bold mb-1">
                        <i class="bi bi-person-plus-fill text-danger me-2"></i>Créer un compte
                    </h2>
                    <p class="text-center text-muted mb-4">Rejoignez RandomPlay</p>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $e): ?>
                                <div><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
                            <a href="login.php" class="alert-link ms-2">Se connecter</a>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Prénom</label>
                                <input type="text" name="prenom" class="form-control"
                                       value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nom</label>
                                <input type="text" name="nom" class="form-control"
                                       value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Pseudo</label>
                                <input type="text" name="pseudo" class="form-control"
                                       value="<?= htmlspecialchars($_POST['pseudo'] ?? '') ?>" required minlength="3">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    Mot de passe
                                    <span class="text-muted small fw-normal">(6 caractères min.)</span>
                                </label>
                                <input type="password" name="mdp" class="form-control"
                                       required minlength="6"
                                       oninput="checkStrength(this.value)">
                                <!-- Indicateur de force du mot de passe -->
                                <div class="progress mt-1" style="height:4px">
                                    <div id="strengthBar" class="progress-bar" style="width:0%"></div>
                                </div>
                                <div id="strengthText" class="small text-muted mt-1"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Confirmer</label>
                                <input type="password" name="mdp2" class="form-control" required>
                            </div>
                            <div class="col-12 mt-2">
                                <!-- Info sécurité -->
                                <div class="alert alert-light border small py-2">
                                    <i class="bi bi-shield-lock-fill text-success me-1"></i>
                                    Votre mot de passe est chiffré avec <strong>bcrypt</strong>
                                    — il n'est jamais stocké en clair.
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-danger w-100 fw-bold">
                                    <i class="bi bi-person-check me-1"></i>S'inscrire
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr>
                    <p class="text-center text-muted small mb-0">
                        Déjà un compte ?
                        <a href="login.php" class="text-danger fw-semibold">Se connecter</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Indicateur de force du mot de passe (JavaScript) -->
<script>
function checkStrength(pwd) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score  = 0;

    if (pwd.length >= 6)  score++;   // Longueur minimale
    if (pwd.length >= 10) score++;   // Longueur confortable
    if (/[A-Z]/.test(pwd)) score++; // Majuscule
    if (/[0-9]/.test(pwd)) score++; // Chiffre
    if (/[^A-Za-z0-9]/.test(pwd)) score++; // Caractère spécial

    const niveaux = [
        { pct: 0,   cls: '',          label: '' },
        { pct: 20,  cls: 'bg-danger', label: '🔴 Très faible' },
        { pct: 40,  cls: 'bg-warning',label: '🟠 Faible' },
        { pct: 60,  cls: 'bg-info',   label: '🔵 Moyen' },
        { pct: 80,  cls: 'bg-primary',label: '🟢 Fort' },
        { pct: 100, cls: 'bg-success',label: '✅ Très fort' },
    ];

    const n = niveaux[score] || niveaux[0];
    bar.style.width = n.pct + '%';
    bar.className   = 'progress-bar ' + n.cls;
    text.textContent = n.label;
}
</script>

<?php include "../includes/footer.php"; ?>
