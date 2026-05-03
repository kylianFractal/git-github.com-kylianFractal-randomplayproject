<?php
// ============================================================
//  RandomPlay — tests/ProductTest.php
//  Tests unitaires PHPUnit 10 — Classe Product (v2)
//
//  Teste les stocks SÉPARÉS : stock_vente / stock_location
//  Un produit peut avoir :
//  - stock_vente   > 0  → disponible à l'achat
//  - stock_location > 0 → disponible à la location
//  - Les deux = produit disponible dans les deux modes
//  - Les deux à 0 = rupture totale
// ============================================================

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/Product.php';

class ProductTest extends TestCase
{
    private PDO     $db;
    private Product $productModel;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Structure SQLite adaptée (sans ENUM, sans DECIMAL → REAL)
        $this->db->exec("
            CREATE TABLE products (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                titre           TEXT    NOT NULL,
                realisateur     TEXT,
                annee           INTEGER,
                support         TEXT    NOT NULL DEFAULT 'vhs',
                genre           TEXT,
                description     TEXT,
                prix_vente      REAL,
                prix_location   REAL,
                stock_vente     INTEGER NOT NULL DEFAULT 0,
                stock_location  INTEGER NOT NULL DEFAULT 0,
                cover_image     TEXT,
                created_at      TEXT    DEFAULT CURRENT_TIMESTAMP,
                updated_at      TEXT    DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->productModel = new Product($this->db);
    }

    /**
     * Helper : crée un produit avec des valeurs par défaut
     * Les overrides permettent de personnaliser certains champs
     * @return int ID du produit créé
     */
    private function createProduit(array $overrides = []): int
    {
        $this->productModel->create(array_merge([
            'titre'          => 'Film Test',
            'realisateur'    => 'Réalisateur',
            'annee'          => 2000,
            'support'        => 'vhs',
            'genre'          => 'Action',
            'description'    => 'Description test',
            'prix_vente'     => 5.00,
            'prix_location'  => 1.50,
            'stock_vente'    => 10,
            'stock_location' => 5,
            'cover_image'    => null,
        ], $overrides));

        return (int) $this->db->lastInsertId();
    }

    // =========================================================
    //  TEST 1 : Création avec stocks séparés
    // =========================================================
    public function testCreateProduct(): void
    {
        $id   = $this->createProduit(['titre' => 'The Matrix', 'stock_vente' => 10, 'stock_location' => 5]);
        $prod = $this->productModel->getById($id);

        $this->assertNotNull($prod);
        $this->assertEquals('The Matrix', $prod['titre']);
        $this->assertEquals(10, (int)$prod['stock_vente'],    "stock_vente doit être 10.");
        $this->assertEquals(5,  (int)$prod['stock_location'], "stock_location doit être 5.");
    }

    // =========================================================
    //  TEST 2 : getById() — produit existant
    // =========================================================
    public function testGetById(): void
    {
        $id   = $this->createProduit(['titre' => 'Titanic']);
        $prod = $this->productModel->getById($id);

        $this->assertNotNull($prod);
        $this->assertEquals('Titanic', $prod['titre']);
    }

    // =========================================================
    //  TEST 3 : getById() — ID inexistant → null
    // =========================================================
    public function testGetByIdNotFound(): void
    {
        $this->assertNull($this->productModel->getById(9999));
    }

    // =========================================================
    //  TEST 4 : isEnStock() — vente
    //  Le stock_location ne doit pas être affecté
    // =========================================================
    public function testIsEnStockVente(): void
    {
        $id = $this->createProduit(['stock_vente' => 3, 'stock_location' => 0]);

        $this->assertTrue(
            $this->productModel->isEnStock($id, 'vente', 1),
            "Stock vente 3 → dispo pour quantité 1."
        );
        $this->assertFalse(
            $this->productModel->isEnStock($id, 'vente', 5),
            "Stock vente 3 → pas dispo pour quantité 5."
        );
        $this->assertFalse(
            $this->productModel->isEnStock($id, 'location', 1),
            "Stock location 0 → pas dispo."
        );
    }

    // =========================================================
    //  TEST 5 : isEnStock() — location
    // =========================================================
    public function testIsEnStockLocation(): void
    {
        $id = $this->createProduit(['stock_vente' => 0, 'stock_location' => 4]);

        $this->assertTrue(
            $this->productModel->isEnStock($id, 'location', 4),
            "Stock location 4 → dispo pour quantité 4."
        );
        $this->assertFalse(
            $this->productModel->isEnStock($id, 'location', 5),
            "Stock location 4 → pas dispo pour quantité 5."
        );
        $this->assertFalse(
            $this->productModel->isEnStock($id, 'vente', 1),
            "Stock vente 0 → pas dispo."
        );
    }

    // =========================================================
    //  TEST 6 : decrementStock('vente')
    //  → stock_vente diminue, stock_location inchangé
    // =========================================================
    public function testDecrementStockVente(): void
    {
        $id = $this->createProduit(['stock_vente' => 10, 'stock_location' => 5]);

        $this->productModel->decrementStock($id, 'vente', 3);
        $prod = $this->productModel->getById($id);

        $this->assertEquals(7, (int)$prod['stock_vente'],    "10 - 3 = 7");
        $this->assertEquals(5, (int)$prod['stock_location'], "stock_location inchangé");
    }

    // =========================================================
    //  TEST 7 : decrementStock('location')
    //  → stock_location diminue, stock_vente inchangé
    // =========================================================
    public function testDecrementStockLocation(): void
    {
        $id = $this->createProduit(['stock_vente' => 10, 'stock_location' => 5]);

        $this->productModel->decrementStock($id, 'location', 2);
        $prod = $this->productModel->getById($id);

        $this->assertEquals(10, (int)$prod['stock_vente'],    "stock_vente inchangé");
        $this->assertEquals(3,  (int)$prod['stock_location'], "5 - 2 = 3");
    }

    // =========================================================
    //  TEST 8 : incrementStock('location') — retour de location
    //  Simule le comportement du trigger trg_restock_on_return
    // =========================================================
    public function testIncrementStockLocation(): void
    {
        $id = $this->createProduit(['stock_vente' => 10, 'stock_location' => 2]);

        $this->productModel->incrementStock($id, 'location', 1);
        $prod = $this->productModel->getById($id);

        $this->assertEquals(10, (int)$prod['stock_vente'],    "stock_vente inchangé");
        $this->assertEquals(3,  (int)$prod['stock_location'], "2 + 1 = 3 après retour");
    }

    // =========================================================
    //  TEST 9 : update() — mise à jour complète
    // =========================================================
    public function testUpdateProduct(): void
    {
        $id = $this->createProduit(['titre' => 'Ancien Titre', 'stock_vente' => 5]);

        $this->productModel->update($id, [
            'titre'          => 'Nouveau Titre',
            'realisateur'    => 'Nouveau Réal',
            'annee'          => 2001,
            'support'        => 'dvd',
            'genre'          => 'Drame',
            'description'    => 'Modifiée',
            'prix_vente'     => 7.99,
            'prix_location'  => 2.00,
            'stock_vente'    => 8,
            'stock_location' => 3,
            'cover_image'    => null,
        ]);

        $prod = $this->productModel->getById($id);
        $this->assertEquals('Nouveau Titre', $prod['titre']);
        $this->assertEquals(8,    (int)$prod['stock_vente']);
        $this->assertEquals(3,    (int)$prod['stock_location']);
        $this->assertEquals(7.99, (float)$prod['prix_vente']);
    }

    // =========================================================
    //  TEST 10 : delete() — suppression
    // =========================================================
    public function testDeleteProduct(): void
    {
        $id = $this->createProduit(['titre' => 'À supprimer']);

        $this->assertTrue($this->productModel->delete($id));
        $this->assertNull($this->productModel->getById($id),
            "Le produit supprimé ne doit plus exister.");
    }
}
