<?php
// ============================================================
//  RandomPlay — src/User.php
//  Modèle utilisateur — Adapté de photo4u/src/User.php
//
//  SÉCURITÉ DES MOTS DE PASSE :
//  On utilise password_hash() avec l'algorithme PASSWORD_BCRYPT.
//
//  Pourquoi bcrypt ?
//  - Algorithme conçu spécifiquement pour hacher des mots de passe
//  - Intègre automatiquement un sel aléatoire unique par hash
//  - "Cost factor" configurable (ralentit les attaques brute-force)
//  - Résistant aux rainbow tables (tableaux précalculés)
//  - Standard PHP recommandé depuis PHP 5.5
//
//  Format du hash stocké en BDD :
//  $2y$12$[22 chars sel][31 chars hash] — environ 60 caractères
//  Exemple : $2y$12$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01
//
//  Vérification avec password_verify() — compatible avec le hash stocké.
//  NE PAS utiliser MD5, SHA1, SHA256 pour les mots de passe !
// ============================================================

class User
{
    private PDO $db;

    // Cost factor bcrypt : 12 = bon équilibre sécurité/performance
    // (12 = ~250ms sur un serveur moderne — trop lent pour brute-force)
    private const BCRYPT_COST = 12;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ---- LECTURE ----

    /**
     * Recherche un utilisateur par son email
     * Utilisé dans Auth::login() pour récupérer le hash à vérifier
     */
    public function getUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Recherche un utilisateur par son ID
     * Utilisé dans mon-compte.php pour afficher le profil
     */
    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Retourne tous les utilisateurs pour l'espace admin
     * Note : la colonne 'mdp' (hash) est exclue de la sélection
     */
    public function getAllUsers(): array
    {
        return $this->db->query(
            "SELECT id, prenom, nom, pseudo, email, type, created_at
             FROM users ORDER BY created_at DESC"
        )->fetchAll();
    }

    // ---- CRÉATION ----

    /**
     * Crée un nouvel utilisateur avec son mot de passe hashé en bcrypt
     *
     * Le hash bcrypt est généré avec :
     *   password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12])
     *
     * Chaque appel génère un hash DIFFÉRENT même pour le même mot de passe
     * (le sel aléatoire est intégré dans le hash), donc :
     *   password_hash('abc') !== password_hash('abc')  → VRAI
     * Mais :
     *   password_verify('abc', $hash)                  → VRAI dans les deux cas
     */
    public function createUser(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (prenom, nom, pseudo, email, mdp, type)
            VALUES (:prenom, :nom, :pseudo, :email, :mdp, :type)
        ");

        return $stmt->execute([
            'prenom' => $data['prenom'],
            'nom'    => $data['nom'],
            'pseudo' => $data['pseudo'],
            'email'  => $data['email'],
            // Hash bcrypt avec cost factor 12 — jamais stocké en clair
            'mdp'    => password_hash($data['mdp'], PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
            'type'   => $data['type'] ?? 'client',
        ]);
    }

    // ---- VÉRIFICATION ----

    /**
     * Vérifie qu'un mot de passe en clair correspond au hash bcrypt stocké
     *
     * password_verify() :
     * - Extrait automatiquement le sel du hash
     * - Recalcule le hash avec ce sel
     * - Compare en temps constant (résistant aux timing attacks)
     * - Retourne true si identique, false sinon
     *
     * @param string $password     Mot de passe saisi par l'utilisateur
     * @param string $storedHash   Hash bcrypt récupéré de la BDD
     */
    public function verifyPassword(string $password, string $storedHash): bool
    {
        return password_verify($password, $storedHash);
    }

    /**
     * Vérifie si un hash bcrypt doit être regénéré
     * Utile si on augmente le cost factor dans le futur
     * Appelé après un login réussi pour mettre à jour silencieusement
     */
    public function needsRehash(string $storedHash): bool
    {
        return password_needs_rehash(
            $storedHash,
            PASSWORD_BCRYPT,
            ['cost' => self::BCRYPT_COST]
        );
    }

    /**
     * Régénère le hash d'un utilisateur (après login si needsRehash())
     * Permet de migrer automatiquement les anciens hashs vers un cost plus fort
     */
    public function updateHash(int $userId, string $newPassword): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE users SET mdp = :mdp WHERE id = :id"
        );
        return $stmt->execute([
            'mdp' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]),
            'id'  => $userId,
        ]);
    }

    /**
     * Vérifie si un pseudo est déjà utilisé (unicité à l'inscription)
     */
    public function pseudoExists(string $pseudo): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE pseudo = :pseudo");
        $stmt->execute(['pseudo' => $pseudo]);
        return (bool) $stmt->fetch();
    }

    // ---- SUPPRESSION ----

    /**
     * Supprime un utilisateur CLIENT
     * La clause "type != 'admin'" protège les comptes admin
     */
    public function deleteUser(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM users WHERE id = :id AND type != 'admin'"
        );
        return $stmt->execute(['id' => $id]);
    }
}
