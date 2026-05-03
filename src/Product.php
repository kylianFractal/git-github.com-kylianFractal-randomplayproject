<?php
// ============================================================
//  RandomPlay — src/Product.php
//  Modèle produit (remplace photo4u/src/Photo.php)
//
//  Gère le catalogue complet : films VHS, cassettes, CD, DVD,
//  vinyles. Chaque produit a deux stocks indépendants :
//    - stock_vente    : exemplaires disponibles à l'achat
//    - stock_location : exemplaires disponibles à la location
// ============================================================

class Product
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ---- LECTURE CATALOGUE ----

    /**
     * Retourne tous les produits avec filtres optionnels
     * Les filtres sont combinables (support + genre + type_achat + recherche)
     *
     * @param array $filtres  Clés possibles : support, genre, type_achat, recherche
     */
    public function getAll(array $filtres = []): array
    {
        $sql    = "SELECT * FROM products WHERE 1=1";
        $params = [];

        // Filtre par type de support (vhs, cd, dvd...)
        if (!empty($filtres['support'])) {
            $sql .= " AND support = :support";
            $params['support'] = $filtres['support'];
        }

        // Filtre par genre (recherche partielle avec LIKE)
        if (!empty($filtres['genre'])) {
            $sql .= " AND genre LIKE :genre";
            $params['genre'] = '%' . $filtres['genre'] . '%';
        }

        // Filtre par disponibilité : vente ou location
        if (isset($filtres['type_achat'])) {
            if ($filtres['type_achat'] === 'vente') {
                // Affiche uniquement les produits vendables avec stock > 0
                $sql .= " AND prix_vente IS NOT NULL AND stock_vente > 0";
            } elseif ($filtres['type_achat'] === 'location') {
                $sql .= " AND prix_location IS NOT NULL AND stock_location > 0";
            }
        }

        // Recherche texte sur le titre ou le réalisateur
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (titre LIKE :recherche OR realisateur LIKE :recherche2)";
            $params['recherche']  = '%' . $filtres['recherche'] . '%';
            $params['recherche2'] = '%' . $filtres['recherche'] . '%';
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retourne un produit par son ID
     * Retourne null si le produit n'existe pas
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // ---- CRUD ADMIN ----

    /**
     * Ajoute un nouveau produit au catalogue
     * prix_vente ou prix_location peuvent être null si non disponible
     */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO products
                (titre, realisateur, annee, support, genre, description,
                 prix_vente, prix_location, stock_vente, stock_location, cover_image)
            VALUES
                (:titre, :realisateur, :annee, :support, :genre, :description,
                 :prix_vente, :prix_location, :stock_vente, :stock_location, :cover_image)
        ");
        return $stmt->execute([
            'titre'          => $data['titre'],
            'realisateur'    => $data['realisateur']   ?? null,
            'annee'          => $data['annee']          ?? null,
            'support'        => $data['support'],
            'genre'          => $data['genre']          ?? null,
            'description'    => $data['description']    ?? null,
            'prix_vente'     => $data['prix_vente']     ?? null,
            'prix_location'  => $data['prix_location']  ?? null,
            'stock_vente'    => $data['stock_vente']    ?? 0,
            'stock_location' => $data['stock_location'] ?? 0,
            'cover_image'    => $data['cover_image']    ?? null,
        ]);
    }

    /**
     * Met à jour un produit existant
     * Toutes les colonnes sont mises à jour en une seule requête
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE products SET
                titre          = :titre,
                realisateur    = :realisateur,
                annee          = :annee,
                support        = :support,
                genre          = :genre,
                description    = :description,
                prix_vente     = :prix_vente,
                prix_location  = :prix_location,
                stock_vente    = :stock_vente,
                stock_location = :stock_location,
                cover_image    = :cover_image
            WHERE id = :id
        ");
        return $stmt->execute([
            'titre'          => $data['titre'],
            'realisateur'    => $data['realisateur']   ?? null,
            'annee'          => $data['annee']          ?? null,
            'support'        => $data['support'],
            'genre'          => $data['genre']          ?? null,
            'description'    => $data['description']    ?? null,
            'prix_vente'     => $data['prix_vente']     ?? null,
            'prix_location'  => $data['prix_location']  ?? null,
            'stock_vente'    => $data['stock_vente']    ?? 0,
            'stock_location' => $data['stock_location'] ?? 0,
            'cover_image'    => $data['cover_image']    ?? null,
            'id'             => $id,
        ]);
    }

    /**
     * Supprime un produit du catalogue
     * Échouera si des order_items référencent ce produit (FK RESTRICT)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ---- GESTION DES STOCKS ----

    /**
     * Vérifie si un produit est disponible pour le type demandé
     *
     * @param string $typeAchat  'vente' ou 'location'
     * @param int    $quantite   Quantité souhaitée (défaut 1)
     */
    public function isEnStock(int $id, string $typeAchat = 'vente', int $quantite = 1): bool
    {
        // Choisit la colonne selon le type d'achat
        $col  = $typeAchat === 'location' ? 'stock_location' : 'stock_vente';
        $stmt = $this->db->prepare("SELECT $col FROM products WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row && (int)$row[$col] >= $quantite;
    }

    /**
     * Décrémente le stock lors d'une commande confirmée
     * Note : le trigger trg_decrement_stock fait aussi cette opération
     * côté BDD — cette méthode est une sécurité supplémentaire côté PHP
     */
    public function decrementStock(int $id, string $typeAchat = 'vente', int $quantite = 1): bool
    {
        $col  = $typeAchat === 'location' ? 'stock_location' : 'stock_vente';
        $stmt = $this->db->prepare("
            UPDATE products SET $col = $col - :q
            WHERE id = :id AND $col >= :q
        ");
        return $stmt->execute(['q' => $quantite, 'id' => $id]);
    }

    /**
     * Incrémente le stock lors du retour d'une location
     * Appelé en complément du trigger trg_restock_on_return
     */
    public function incrementStock(int $id, string $typeAchat = 'location', int $quantite = 1): bool
    {
        $col  = $typeAchat === 'location' ? 'stock_location' : 'stock_vente';
        $stmt = $this->db->prepare("
            UPDATE products SET $col = $col + :q WHERE id = :id
        ");
        return $stmt->execute(['q' => $quantite, 'id' => $id]);
    }
}
