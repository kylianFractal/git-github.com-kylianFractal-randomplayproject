<?php
// ============================================================
//  RandomPlay — src/Auth.php
//  Authentification sécurisée — Adapté de photo4u/src/Auth.php
//
//  SÉCURITÉS IMPLÉMENTÉES :
//
//  1. Hash bcrypt (via User::createUser / User::verifyPassword)
//     → password_hash() / password_verify()
//
//  2. Rehash automatique (needsRehash)
//     → Si le cost factor bcrypt augmente, le hash est mis à jour
//       silencieusement lors du prochain login réussi
//
//  3. Détection brute-force basique
//     → Log::countEchecsParIp() compte les échecs récents
//     → Au-delà de MAX_ECHECS tentatives en FENETRE_MINUTES minutes,
//       la connexion est bloquée pour cette IP
//
//  4. Traçabilité complète
//     → Chaque connexion (réussie ou échouée) est loggée en BDD
//     → L'IP est enregistrée pour audit
// ============================================================

require_once "User.php";
require_once "Log.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth
{
    private PDO   $db;
    private User  $userModel;
    private Log   $logModel;

    // Seuil anti brute-force : 5 échecs en 15 minutes → blocage
    private const MAX_ECHECS       = 5;
    private const FENETRE_MINUTES  = 15;

    public function __construct(PDO $db)
    {
        $this->db        = $db;
        $this->userModel = new User($db);
        $this->logModel  = new Log($db);
    }

    /**
     * Tente de connecter un utilisateur
     *
     * Flux complet :
     *  1. Vérifie le brute-force (nb d'échecs récents pour cette IP)
     *  2. Recherche l'utilisateur par email
     *  3. Vérifie le mot de passe avec password_verify() (bcrypt)
     *  4. Si succès : stocke la session, log l'événement, rehash si besoin
     *  5. Si échec  : log la tentative, retourne false
     *
     * @return bool  true si connecté, false sinon
     */
    public function login(string $email, string $password): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // --- Protection brute-force ---
        $nbEchecs = $this->logModel->countEchecsParIp($ip, self::FENETRE_MINUTES);
        if ($nbEchecs >= self::MAX_ECHECS) {
            $this->logModel->write(
                null,
                'echec_connexion',
                "IP bloquée après $nbEchecs tentatives ($ip)"
            );
            return false;
        }

        // --- Vérification des identifiants ---
        $user = $this->userModel->getUserByEmail($email);

        if ($user && $this->userModel->verifyPassword($password, $user['mdp'])) {

            // Connexion réussie → stockage session
            $_SESSION['user'] = [
                'id'     => $user['id'],
                'email'  => $user['email'],
                'type'   => $user['type'],
                'prenom' => $user['prenom'],
                'pseudo' => $user['pseudo'],
            ];

            // Log de la connexion réussie
            $this->logModel->write(
                $user['id'],
                'connexion',
                "Connexion réussie depuis $ip"
            );

            // Rehash silencieux si le cost factor a augmenté
            // → permet de renforcer la sécurité sans action utilisateur
            if ($this->userModel->needsRehash($user['mdp'])) {
                $this->userModel->updateHash($user['id'], $password);
                $this->logModel->write(
                    $user['id'],
                    'modification_mdp',
                    "Hash bcrypt mis à jour (rehash automatique)"
                );
            }

            return true;
        }

        // Connexion échouée → log de la tentative
        // Message volontairement vague pour ne pas confirmer l'existence de l'email
        $this->logModel->write(
            null,
            'echec_connexion',
            "Identifiants invalides pour : $email (IP: $ip)"
        );

        return false;
    }

    /**
     * Déconnecte l'utilisateur courant
     * Détruit la session côté serveur et supprime le cookie
     */
    public function logout(): void
    {
        if ($this->isLogged()) {
            $this->logModel->write(
                $_SESSION['user']['id'],
                'deconnexion',
                "Déconnexion depuis " . ($_SERVER['REMOTE_ADDR'] ?? '')
            );
        }

        $_SESSION = [];

        // Supprime le cookie de session côté navigateur
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params["path"],   $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /** Vérifie si un utilisateur est connecté */
    public function isLogged(): bool
    {
        return isset($_SESSION['user']);
    }

    /** Retourne le type de l'utilisateur connecté ('client' ou 'admin') */
    public function userType(): ?string
    {
        return $this->isLogged() ? $_SESSION['user']['type'] : null;
    }

    /** Retourne les données de session de l'utilisateur connecté */
    public function currentUser(): ?array
    {
        return $this->isLogged() ? $_SESSION['user'] : null;
    }

    /**
     * Vérifie si l'IP courante est temporairement bloquée
     * Utile pour afficher un message d'avertissement sur login.php
     */
    public function isIpBloquee(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return $this->logModel->countEchecsParIp($ip, self::FENETRE_MINUTES) >= self::MAX_ECHECS;
    }
}
