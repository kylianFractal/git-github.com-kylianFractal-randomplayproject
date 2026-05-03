<?php
// ============================================================
//  RandomPlay — tests/UserTest.php
//  Tests unitaires PHPUnit 10 — Classe User
//
//  Pattern utilisé : base SQLite en mémoire (:memory:)
//  → Pas besoin de MySQL pour lancer les tests
//  → Les tests sont isolés et reproductibles
//  → Chaque test repart d'une BDD vide (setUp() recréée à chaque test)
//
//  Lancer les tests :
//    composer install
//    ./vendor/bin/phpunit --testdox
// ============================================================

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/controller.php';
require_once __DIR__ . '/../src/User.php';

class UserTest extends TestCase
{
    private PDO  $db;
    private User $userModel;

    /**
     * setUp() est appelé avant CHAQUE test
     * Crée une BDD SQLite en mémoire avec la structure de la table users
     * Adapté pour SQLite (pas d'ENUM, pas de TIMESTAMP avec ON UPDATE)
     */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->db->exec("
            CREATE TABLE users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                prenom     TEXT    NOT NULL,
                nom        TEXT    NOT NULL,
                pseudo     TEXT    NOT NULL UNIQUE,
                email      TEXT    NOT NULL UNIQUE,
                mdp        TEXT    NOT NULL,
                type       TEXT    NOT NULL DEFAULT 'client',
                created_at TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->userModel = new User($this->db);
    }

    // =========================================================
    //  TEST 1 : Création d'un utilisateur
    // =========================================================
    public function testCreateUser(): void
    {
        $result = $this->userModel->createUser([
            'prenom' => 'Jean',
            'nom'    => 'Dupont',
            'pseudo' => 'jean_d',
            'email'  => 'jean@test.fr',
            'mdp'    => 'motdepasse123',
            'type'   => 'client',
        ]);

        $this->assertTrue($result, "La création doit retourner true.");
    }

    // =========================================================
    //  TEST 2 : Récupération par email
    // =========================================================
    public function testGetUserByEmail(): void
    {
        $this->userModel->createUser([
            'prenom' => 'Marie', 'nom' => 'Martin', 'pseudo' => 'marie_m',
            'email'  => 'marie@test.fr', 'mdp' => 'secret456', 'type' => 'client',
        ]);

        $user = $this->userModel->getUserByEmail('marie@test.fr');

        $this->assertNotNull($user);
        $this->assertEquals('Marie',   $user['prenom']);
        $this->assertEquals('marie_m', $user['pseudo']);
        $this->assertEquals('client',  $user['type']);
    }

    // =========================================================
    //  TEST 3 : Email inexistant → null
    // =========================================================
    public function testGetUserByEmailNotFound(): void
    {
        $this->assertNull(
            $this->userModel->getUserByEmail('inconnu@test.fr'),
            "Un email inexistant doit retourner null."
        );
    }

    // =========================================================
    //  TEST 4 : Le mot de passe est hashé en SHA-512 (pas bcrypt)
    // =========================================================
    public function testPasswordIsHashedSha512(): void
    {
        $mdpClair = 'monMotDePasse';
        $this->userModel->createUser([
            'prenom' => 'Test', 'nom' => 'Hash', 'pseudo' => 'test_hash',
            'email'  => 'hash@test.fr', 'mdp' => $mdpClair, 'type' => 'client',
        ]);

        $user = $this->userModel->getUserByEmail('hash@test.fr');

        // Le hash ne doit pas être en clair
        $this->assertNotEquals($mdpClair, $user['mdp'],
            "Le mot de passe ne doit pas être stocké en clair.");

        // Doit être au format SHA-512 : "sel:hash" (contient ":")
        $this->assertStringContainsString(':', $user['mdp'],
            "Le hash SHA-512 doit être au format 'sel:hash'.");

        // Le hash ne doit PAS commencer par $2y$ (bcrypt)
        $this->assertStringNotContainsString('$2y$', $user['mdp'],
            "Ne doit pas être un hash bcrypt.");

        // Vérification structure : sel (64 chars) + ":" + hash sha512 (128 chars)
        [$sel, $hash] = explode(':', $user['mdp'], 2);
        $this->assertEquals(64,  strlen($sel),  "Le sel doit faire 64 caractères hex.");
        $this->assertEquals(128, strlen($hash), "Le hash SHA-512 doit faire 128 caractères hex.");
    }

    // =========================================================
    //  TEST 5 : Vérification mot de passe SHA-512 (bon et mauvais)
    // =========================================================
    public function testVerifyPasswordSha512(): void
    {
        $mdpClair = 'testPassword99';
        $this->userModel->createUser([
            'prenom' => 'Verify', 'nom' => 'Test', 'pseudo' => 'verify_t',
            'email'  => 'verify@test.fr', 'mdp' => $mdpClair, 'type' => 'client',
        ]);

        $user = $this->userModel->getUserByEmail('verify@test.fr');

        $this->assertTrue(
            $this->userModel->verifyPassword($mdpClair, $user['mdp']),
            "Le bon mot de passe doit être accepté."
        );
        $this->assertFalse(
            $this->userModel->verifyPassword('mauvaisMotDePasse', $user['mdp']),
            "Un mauvais mot de passe doit être refusé."
        );
    }

    // =========================================================
    //  TEST 5b : Deux hashes du même mot de passe sont différents
    //  (grâce au sel aléatoire unique)
    // =========================================================
    public function testDifferentHashesSamePassword(): void
    {
        $mdp    = 'memeMotDePasse';
        $hash1  = User::hashPassword($mdp);
        $hash2  = User::hashPassword($mdp);

        $this->assertNotEquals($hash1, $hash2,
            "Deux hashes du même mdp doivent être différents (sel aléatoire).");

        // Les deux doivent quand même être vérifiables
        $this->assertTrue(User::verifyPasswordHash($mdp, $hash1));
        $this->assertTrue(User::verifyPasswordHash($mdp, $hash2));
    }

    // =========================================================
    //  TEST 6 : pseudoExists() — vrai et faux
    // =========================================================
    public function testPseudoExists(): void
    {
        $this->userModel->createUser([
            'prenom' => 'Pseudo', 'nom' => 'Unique', 'pseudo' => 'pseudo_unique',
            'email'  => 'pseudo@test.fr', 'mdp' => 'azerty123', 'type' => 'client',
        ]);

        $this->assertTrue(
            $this->userModel->pseudoExists('pseudo_unique'),
            "Un pseudo existant doit retourner true."
        );
        $this->assertFalse(
            $this->userModel->pseudoExists('pseudo_inexistant'),
            "Un pseudo inexistant doit retourner false."
        );
    }

    // =========================================================
    //  TEST 7 : Suppression d'un client
    // =========================================================
    public function testDeleteUser(): void
    {
        $this->userModel->createUser([
            'prenom' => 'Delete', 'nom' => 'Me', 'pseudo' => 'delete_me',
            'email'  => 'delete@test.fr', 'mdp' => 'azerty123', 'type' => 'client',
        ]);

        $user = $this->userModel->getUserByEmail('delete@test.fr');
        $this->assertNotNull($user);

        $result = $this->userModel->deleteUser($user['id']);
        $this->assertTrue($result, "La suppression doit retourner true.");

        // Vérification que l'utilisateur n'existe plus
        $this->assertNull(
            $this->userModel->getUserByEmail('delete@test.fr'),
            "L'utilisateur supprimé ne doit plus être trouvable."
        );
    }
}
