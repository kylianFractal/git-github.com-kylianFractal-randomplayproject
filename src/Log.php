<?php
// ============================================================
//  RandomPlay — src/Log.php
//  Journal applicatif — traçabilité des actions en BDD
//
//  Ce modèle enregistre toutes les actions importantes du site.
//  Chaque entrée contient :
//  - user_id  : qui a fait l'action (null = système ou visiteur)
//  - action   : code court de l'action (ex: 'connexion')
//  - details  : description lisible (ex: 'Connexion réussie')
//  - ip       : adresse IP du client
//  - created_at : horodatage automatique
//
//  ACTIONS TRACÉES :
//  connexion           → Login réussi
//  deconnexion         → Logout
//  echec_connexion     → Login échoué (mauvais mdp ou email)
//  inscription         → Nouveau compte créé
//  ajout_panier        → Article ajouté au panier
//  commande_confirmee  → Commande validée
//  retour_location     → Location rendue (client ou admin)
//  creation_produit    → Nouveau produit ajouté (admin)
//  modification_produit→ Produit modifié (admin)
//  suppression_produit → Produit supprimé (admin)
//  suppression_user    → Utilisateur supprimé (admin)
//  erreur_commande     → Erreur lors de la création d'une commande
//  erreur_retour       → Erreur lors de l'enregistrement d'un retour
// ============================================================

class Log
{
    private PDO $db;

    // Niveaux de log pour filtrage (non stocké en BDD, déduit de l'action)
    public const NIVEAU_INFO    = 'info';
    public const NIVEAU_SUCCESS = 'success';
    public const NIVEAU_WARNING = 'warning';
    public const NIVEAU_DANGER  = 'danger';

    // Mapping action → niveau (utilisé dans la vue admin/logs.php)
    public const ACTION_NIVEAUX = [
        'connexion'            => self::NIVEAU_SUCCESS,
        'deconnexion'          => self::NIVEAU_INFO,
        'echec_connexion'      => self::NIVEAU_DANGER,   // Alerte sécurité
        'inscription'          => self::NIVEAU_SUCCESS,
        'ajout_panier'         => self::NIVEAU_INFO,
        'commande_confirmee'   => self::NIVEAU_SUCCESS,
        'retour_location'      => self::NIVEAU_WARNING,
        'creation_produit'     => self::NIVEAU_INFO,
        'modification_produit' => self::NIVEAU_WARNING,
        'suppression_produit'  => self::NIVEAU_DANGER,
        'suppression_user'     => self::NIVEAU_DANGER,
        'erreur_commande'      => self::NIVEAU_DANGER,
        'erreur_retour'        => self::NIVEAU_DANGER,
    ];

    // Mapping niveau → badge Bootstrap
    public const NIVEAU_BADGES = [
        self::NIVEAU_SUCCESS => 'success',
        self::NIVEAU_INFO    => 'primary',
        self::NIVEAU_WARNING => 'warning',
        self::NIVEAU_DANGER  => 'danger',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Enregistre une action dans la table logs
     *
     * @param int|null $userId   ID utilisateur (null si visiteur ou action système)
     * @param string   $action   Code court de l'action (voir liste ci-dessus)
     * @param string|null $details  Description détaillée lisible
     */
    public function write(?int $userId, string $action, ?string $details = null): void
    {
        try {
            // Récupère l'IP réelle même derrière un proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
               ?? $_SERVER['REMOTE_ADDR']
               ?? null;

            // Tronque l'IP si IPv6 trop longue (max 45 chars en BDD)
            if ($ip && strlen($ip) > 45) {
                $ip = substr($ip, 0, 45);
            }

            $stmt = $this->db->prepare("
                INSERT INTO logs (user_id, action, details, ip)
                VALUES (:user_id, :action, :details, :ip)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'action'  => $action,
                'details' => $details,
                'ip'      => $ip,
            ]);
        } catch (PDOException $e) {
            // On ne bloque pas l'application si le log échoue
            error_log("[RandomPlay Log] Erreur d'écriture : " . $e->getMessage());
        }
    }

    /**
     * Raccourci : log d'une erreur applicative
     * Utile pour tracer les exceptions PHP sans arrêter le site
     */
    public function writeError(?int $userId, string $contexte, string $message): void
    {
        $this->write($userId, 'erreur_' . $contexte, $message);
        // Log aussi dans le fichier d'erreurs PHP
        error_log("[RandomPlay ERROR] $contexte : $message");
    }

    /**
     * Retourne les N derniers logs avec pseudo de l'utilisateur
     * Utilisé dans admin/logs.php
     *
     * @param int    $limit   Nombre max d'entrées (défaut 200)
     * @param string $filtre  Filtre sur l'action (vide = toutes)
     */
    public function getAll(int $limit = 200, string $filtre = ''): array
    {
        $sql = "
            SELECT l.*, u.pseudo, u.type as user_type
            FROM logs l
            LEFT JOIN users u ON u.id = l.user_id
        ";

        if ($filtre !== '') {
            $sql .= " WHERE l.action = :action";
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        if ($filtre !== '') {
            $stmt->bindValue(':action', $filtre);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retourne les statistiques des actions pour le dashboard
     * Compte par action sur les 30 derniers jours
     */
    public function getStats(): array
    {
        $stmt = $this->db->query("
            SELECT
                action,
                COUNT(*) as total,
                MAX(created_at) as derniere_fois
            FROM logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY action
            ORDER BY total DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Retourne le nombre de tentatives de connexion échouées
     * pour une IP donnée dans les dernières N minutes
     * Utile pour détecter les attaques brute-force
     */
    public function countEchecsParIp(string $ip, int $minutes = 15): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM logs
            WHERE action = 'echec_connexion'
              AND ip = :ip
              AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->bindValue(':ip',      $ip);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne les logs d'un utilisateur spécifique
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM logs
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',   $limit,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
