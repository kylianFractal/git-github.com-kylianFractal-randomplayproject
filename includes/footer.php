<?php
// ============================================================
//  RandomPlay — includes/footer.php
//  Pied de page commun à toutes les pages
//
//  Ferme la balise <main> ouverte dans header.php,
//  affiche le footer Bootstrap, et charge Bootstrap JS.
// ============================================================
?>
</main>

<footer class="bg-dark text-white mt-5 pt-4 pb-3 border-top border-danger">
    <div class="container">
        <div class="row">
            <!-- Colonne 1 : présentation du site -->
            <div class="col-md-4 mb-3">
                <h5 class="text-danger fw-bold">
                    <i class="bi bi-play-circle-fill me-1"></i>RandomPlay
                </h5>
                <p class="text-white-50 small">
                    Votre boutique de vidéos vintage.<br>
                    Vente et location de VHS, cassettes, CD, DVD & vinyles.
                </p>
            </div>

            <!-- Colonne 2 : navigation rapide -->
            <div class="col-md-4 mb-3">
                <h6 class="text-white-50 text-uppercase small fw-bold">Navigation</h6>
                <ul class="list-unstyled small">
                    <li><a href="/randomplay/pages/catalogue.php" class="text-white-50 text-decoration-none">Catalogue</a></li>
                    <li><a href="/randomplay/pages/login.php"     class="text-white-50 text-decoration-none">Connexion</a></li>
                    <li><a href="/randomplay/pages/register.php"  class="text-white-50 text-decoration-none">S'inscrire</a></li>
                </ul>
            </div>

            <!-- Colonne 3 : badges supports disponibles -->
            <div class="col-md-4 mb-3">
                <h6 class="text-white-50 text-uppercase small fw-bold">Supports disponibles</h6>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-secondary">📼 VHS</span>
                    <span class="badge bg-secondary">📼 Cassette</span>
                    <span class="badge bg-secondary">💿 CD</span>
                    <span class="badge bg-secondary">📀 DVD</span>
                    <span class="badge bg-secondary">🎵 Vinyle</span>
                </div>
            </div>
        </div>

        <hr class="border-secondary">
        <p class="text-center text-white-50 small mb-0">
            © <?= date('Y') ?> RandomPlay — Projet BTS SIO SLAM — Tous droits réservés
        </p>
    </div>
</footer>

<!-- Bootstrap JS bundle (inclut Popper pour les dropdowns) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
