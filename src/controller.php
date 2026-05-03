<?php
// ============================================================
//  RandomPlay — src/controller.php
//  Connexion à la base de données (Pattern Singleton)
//
//  Le Singleton garantit qu'une seule instance PDO est créée
//  pendant toute la durée de la requête PHP, évitant ainsi
//  des connexions multiples inutiles à MySQL.
// ============================================================

class Database
{
    // Paramètres de connexion — à adapter selon votre environnement
    private string $host     = "localhost";
    private string $dbname   = "randomplay";
    private string $username = "root";
    private string $password = "";

    // Instance unique stockée en attribut statique
    private static ?PDO $instance = null;

    // Constructeur privé : empêche l'instanciation directe (new Database())
    private function __construct() {}

    /**
     * Retourne l'unique instance PDO (la crée si elle n'existe pas encore)
     * Utilisation : $db = Database::getInstance();
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    "mysql:host=localhost;dbname=randomplay;charset=utf8mb4",
                    "root",
                    ""
                );
                // Active les exceptions PDO pour une meilleure gestion des erreurs
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Retourne les résultats sous forme de tableaux associatifs par défaut
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // On log l'erreur sans exposer les credentials à l'utilisateur
                error_log("[RandomPlay] Erreur BDD : " . $e->getMessage());
                die("Erreur de connexion à la base de données.");
            }
        }
        return self::$instance;
    }
}
