<?php
// ============================================================
//  RandomPlay — src/Rental.php
//  Gestion des locations et des retours
//
//  Une location est créée automatiquement dans OrderItem::create()
//  quand type_achat = 'location'. Ce modèle permet de :
//    - Consulter les locations actives (côté client et admin)
//    - Détecter les retards (date_retour_prevue < aujourd'hui)
//    - Enregistrer un retour (date_retour_effective = aujourd'hui)
//    - Le trigger trg_restock_on_return remet le stock à jour
// ============================================================

class Rental
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Retourne les locations en cours d'un client
     * "En cours" = date_retour_effective IS NULL (pas encore rendu)
     * Triées par date de retour prévue croissante (les plus urgentes en premier)
     */
    public function getActiveByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, p.titre, p.support, p.cover_image, oi.quantite
            FROM rentals r
            INNER JOIN order_items oi ON oi.id = r.order_item_id
            INNER JOIN products p     ON p.id  = oi.product_id
            INNER JOIN orders o       ON o.id  = oi.order_id
            WHERE o.user_id = :user_id
              AND r.date_retour_effective IS NULL
            ORDER BY r.date_retour_prevue ASC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Retourne toutes les locations (pour l'admin)
     * Inclut les informations client et produit
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT r.*, p.titre, p.support, u.prenom, u.nom, u.pseudo, oi.quantite
            FROM rentals r
            INNER JOIN order_items oi ON oi.id = r.order_item_id
            INNER JOIN products p     ON p.id  = oi.product_id
            INNER JOIN orders o       ON o.id  = oi.order_id
            INNER JOIN users u        ON u.id  = o.user_id
            ORDER BY r.date_retour_prevue ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Retourne uniquement les locations en retard (admin)
     * En retard = pas encore rendu ET date_retour_prevue < aujourd'hui
     */
    public function getEnRetard(): array
    {
        $stmt = $this->db->query("
            SELECT r.*, p.titre, u.prenom, u.pseudo
            FROM rentals r
            INNER JOIN order_items oi ON oi.id = r.order_item_id
            INNER JOIN products p     ON p.id  = oi.product_id
            INNER JOIN orders o       ON o.id  = oi.order_id
            INNER JOIN users u        ON u.id  = o.user_id
            WHERE r.date_retour_effective IS NULL
              AND r.date_retour_prevue < CURDATE()
            ORDER BY r.date_retour_prevue ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Enregistre le retour d'une location
     * Met date_retour_effective = aujourd'hui
     *
     * Le trigger MySQL trg_restock_on_return se déclenche automatiquement
     * sur cet UPDATE et remet stock_location à jour sans code PHP supplémentaire
     */
    public function enregistrerRetour(int $rentalId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE rentals
            SET date_retour_effective = CURDATE()
            WHERE id = :id
              AND date_retour_effective IS NULL
        ");
        return $stmt->execute(['id' => $rentalId]);
    }
}
