<?php
// ============================================================
//  RandomPlay — pages/login.php
//  Page de connexion utilisateur
//  Adapté de photo4u/pages/login.php
//
//  Flux :
//  1. Affiche le formulaire email + mot de passe
//  2. Au POST : appelle Auth::login()
//  3. Si succès → redirige selon le rôle :
//     - admin  → admin/dashboard.php
//     - client → catalogue.php
//  4. Si échec → affiche le message d'erreur
// ============================================================

require_once "../src/controller.php";
require_once "../src/Auth.php";

$db     = Database::getInstance();
$auth   = new Auth($db);
$errors = [];

// Si déjà connecté, pas besoin d'afficher le formulaire
if ($auth->isLogged()) {
    header("Location: catalogue.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mdp'] ?? '';

    if ($auth->login($email, $mdp)) {
        // Redirection selon le rôle (stocké en session par Auth::login())
        $type = $_SESSION['user']['type'];
        header("Location: " . ($type === 'admin' ? '../admin/dashboard.php' : 'catalogue.php'));
        exit;
    } else {
        // Message générique pour ne pas divulguer si l'email existe
        $errors[] = "Email ou mot de passe incorrect.";
    }
}

include "../includes/header.php";
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow border-0">
                <div class="card-body p-4">
                    <h2 class="text-center fw-bold mb-1">
                        <i class="bi bi-play-circle-fill text-danger me-2"></i>RandomPlay
                    </h2>
                    <p class="text-center text-muted mb-4">Connexion à votre compte</p>

                    <!-- Affichage des erreurs de connexion -->
                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $e): ?>
                                <div><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <!-- value pré-rempli pour éviter de retaper en cas d'erreur -->
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Mot de passe</label>
                            <input type="password" name="mdp" class="form-control" required>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                        </button>
                    </form>

                    <hr>
                    <p class="text-center text-muted small mb-0">
                        Pas encore de compte ?
                        <a href="register.php" class="text-danger fw-semibold">S'inscrire</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
