<?php
// ============================================================
//  RandomPlay — src/Cart.php
//  Gestion du panier en session PHP
//
//  Le panier est stocké dans $_SESSION['randomplay_cart'].
//  Chaque article est identifié par une clé unique :
//  "{product_id}_{type_achat}" — ce qui permet d'avoir le même
//  produit en achat ET en location dans le même panier.
// ============================================================

class Cart
{
    // Clé utilisée dans $_SESSION pour stocker le panier
    private const SESSION_KEY = 'randomplay_cart';

    /**
     * Retourne le contenu actuel du panier
     * Retourne un tableau vide si le panier est inexistant
     */
    public static function get(): array
    {
        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    /**
     * Ajoute un article au panier ou incrémente sa quantité s'il existe déjà
     *
     * Structure d'un item :
     *   - product_id  : ID du produit
     *   - titre       : Nom du produit (stocké pour l'affichage)
     *   - support     : Type de support (vhs, cd, dvd...)
     *   - cover_image : Chemin de l'image
     *   - prix_unit   : Prix unitaire au moment de l'ajout
     *   - quantite    : Quantité souhaitée
     *   - type_achat  : 'vente' ou 'location'
     *   - nb_jours    : Durée de location (ignoré si type_achat = 'vente')
     */
    public static function add(array $item): void
    {
        $panier = self::get();
        // Clé unique : même produit en vente et en location = 2 lignes distinctes
        $key = $item['product_id'] . '_' . $item['type_achat'];

        if (isset($panier[$key])) {
            // Article déjà présent : on additionne les quantités
            $panier[$key]['quantite'] += $item['quantite'];
        } else {
            // Nouvel article : on l'ajoute directement
            $panier[$key] = $item;
        }

        $_SESSION[self::SESSION_KEY] = $panier;
    }

    /**
     * Supprime un article du panier par sa clé
     * @param string $key  Format : "{product_id}_{type_achat}"
     */
    public static function remove(string $key): void
    {
        $panier = self::get();
        unset($panier[$key]);
        $_SESSION[self::SESSION_KEY] = $panier;
    }

    /**
     * Vide complètement le panier
     * Appelé après la confirmation d'une commande
     */
    public static function clear(): void
    {
        $_SESSION[self::SESSION_KEY] = [];
    }

    /**
     * Calcule le montant total du panier
     * Pour les locations : prix = prix_unit × quantite × nb_jours
     */
    public static function total(): float
    {
        $total = 0;
        foreach (self::get() as $item) {
            $prix = $item['prix_unit'] * $item['quantite'];
            if ($item['type_achat'] === 'location') {
                $prix *= max(1, (int)($item['nb_jours'] ?? 1));
            }
            $total += $prix;
        }
        return $total;
    }

    /**
     * Retourne le nombre total d'articles dans le panier
     * Affiché dans le badge de la navbar
     */
    public static function count(): int
    {
        return array_sum(array_column(self::get(), 'quantite'));
    }
}
