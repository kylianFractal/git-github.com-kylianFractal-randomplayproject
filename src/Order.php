<?php
// ============================================================
//  RandomPlay — src/Order.php
//  Modèles Order et OrderItem
//
//  Order    : représente une commande (en-tête)
//  OrderItem: représente une ligne de commande (produit + type)
//
//  Flux d'une commande :
//    1. Client remplit son panier (Cart.php)
//    2. Validation → createFromCart() crée la commande + lignes
//    3. Statut passe à 'confirmee'
//    4. Le trigger trg_decrement_stock décrémente les stocks
//    5. Si location → une entrée dans rentals est créée
// ============================================================

class Order
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ---- CRÉATION ----

    /**
     * Crée une commande complète depuis le contenu du panier
     * Utilise une transaction pour garantir l'intégrité des données :
     * si une ligne échoue, toute la commande est annulée (rollback)
     *
     * @param int   $userId  ID du client
     * @param array $panier  Tableau des articles (depuis Cart::get())
     * @return int|null      ID de la commande créée, ou null si échec
     */
    public function createFromCart(int $userId, array $panier): ?int
    {
        try {
            // Début de transaction — toutes les requêtes sont atomiques
            $this->db->beginTransaction();

            // Calcul du montant total de la commande
            $total = 0;
            foreach ($panier as $item) {
                $prix = $item['prix_unit'] * $item['quantite'];
                if ($item['type_achat'] === 'location') {
                    $prix *= max(1, (int)($item['nb_jours'] ?? 1));
                }
                $total += $prix;
            }

            // Insertion de l'en-tête de commande (statut initial : en_attente)
            $stmt = $this->db->prepare("
                INSERT INTO orders (user_id, statut, total)
                VALUES (:user_id, 'en_attente', :total)
            ");
            $stmt->execute(['user_id' => $userId, 'total' => $total]);
            $orderId = (int)$this->db->lastInsertId();

            // Insertion de chaque ligne de commande
            $itemModel = new OrderItem($this->db);
            foreach ($panier as $item) {
                $itemModel->create($orderId, $item);
            }

            // Tout s'est bien passé → on valide la transaction
            $this->db->commit();
            return $orderId;

        } catch (Exception $e) {
            // Une erreur est survenue → on annule tout
            $this->db->rollBack();
            error_log("[RandomPlay] createFromCart : " . $e->getMessage());
            return null;
        }
    }

    // ---- LECTURE ----

    /** Retourne une commande par son ID */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Retourne toutes les commandes d'un client (pour l'espace client) */
    public function getByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /** Retourne toutes les commandes avec infos client (pour l'admin) */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT o.*, u.prenom, u.nom, u.pseudo
            FROM orders o
            INNER JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    // ---- MISE À JOUR ----

    /**
     * Change le statut d'une commande
     * Valeurs autorisées : en_attente, confirmee, terminee, annulee
     * Note : passer à 'confirmee' déclenche le trigger trg_decrement_stock
     */
    public function updateStatut(int $id, string $statut): bool
    {
        $allowed = ['en_attente', 'confirmee', 'terminee', 'annulee'];
        if (!in_array($statut, $allowed)) return false;

        $stmt = $this->db->prepare(
            "UPDATE orders SET statut = :statut WHERE id = :id"
        );
        return $stmt->execute(['statut' => $statut, 'id' => $id]);
    }
}


// ============================================================
//  OrderItem — Ligne de commande
// ============================================================
class OrderItem
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Crée une ligne de commande
     * Si type_achat = 'location', crée aussi une entrée dans rentals
     * avec les dates de début et de retour prévu
     */
    public function create(int $orderId, array $item): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (order_id, product_id, quantite, prix_unit, type_achat)
            VALUES (:order_id, :product_id, :quantite, :prix_unit, :type_achat)
        ");
        $ok = $stmt->execute([
            'order_id'   => $orderId,
            'product_id' => $item['product_id'],
            'quantite'   => $item['quantite'],
            'prix_unit'  => $item['prix_unit'],
            'type_achat' => $item['type_achat'],
        ]);

        // Si c'est une location → on crée l'entrée dans la table rentals
        if ($ok && $item['type_achat'] === 'location') {
            $itemId     = (int)$this->db->lastInsertId();
            $nbJours    = max(1, (int)($item['nb_jours'] ?? 1));
            $dateDebut  = date('Y-m-d');
            // Date de retour prévue = aujourd'hui + nb_jours
            $dateRetour = date('Y-m-d', strtotime("+{$nbJours} days"));

            $rental = $this->db->prepare("
                INSERT INTO rentals (order_item_id, date_debut, date_retour_prevue)
                VALUES (:item_id, :debut, :retour)
            ");
            $rental->execute([
                'item_id' => $itemId,
                'debut'   => $dateDebut,
                'retour'  => $dateRetour,
            ]);
        }

        return $ok;
    }

    /**
     * Retourne toutes les lignes d'une commande avec les infos produit
     * et les infos de location (dates) si applicable
     */
    public function getByOrder(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT oi.*, p.titre, p.support, p.cover_image,
                   r.id as rental_id,
                   r.date_debut, r.date_retour_prevue, r.date_retour_effective
            FROM order_items oi
            INNER JOIN products p ON p.id = oi.product_id
            LEFT JOIN rentals r   ON r.order_item_id = oi.id
            WHERE oi.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }
}
