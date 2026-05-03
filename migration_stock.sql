-- ============================================================
--  RandomPlay — Migration : stock séparé vente / location
--  À exécuter UNE FOIS après randomplay_database.sql
--  Si vous repartez de zéro, ce changement est déjà dans
--  randomplay_database_v3.sql
-- ============================================================

USE randomplay;

-- Ajouter les deux colonnes de stock séparées
ALTER TABLE products
    ADD COLUMN stock_vente    INT NOT NULL DEFAULT 0 AFTER prix_location,
    ADD COLUMN stock_location INT NOT NULL DEFAULT 0 AFTER stock_vente;

-- Migrer l'ancien stock vers les deux nouveaux (répartition 50/50 par défaut)
UPDATE products SET
    stock_vente    = stock,
    stock_location = stock
WHERE stock > 0;

-- Supprimer l'ancienne colonne stock
ALTER TABLE products DROP COLUMN stock;

-- Mettre à jour products_sauv aussi
ALTER TABLE products_sauv
    ADD COLUMN stock_vente    INT DEFAULT NULL AFTER stock,
    ADD COLUMN stock_location INT DEFAULT NULL AFTER stock_vente;

-- Adapter le trigger prevent_negative_stock
DROP TRIGGER IF EXISTS trg_prevent_negative_stock;

DELIMITER $$
CREATE TRIGGER trg_prevent_negative_stock
BEFORE UPDATE ON products FOR EACH ROW
BEGIN
    IF NEW.stock_vente < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erreur : le stock vente ne peut pas être négatif.';
    END IF;
    IF NEW.stock_location < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erreur : le stock location ne peut pas être négatif.';
    END IF;
END$$
DELIMITER ;

-- Adapter le trigger decrement_stock
DROP TRIGGER IF EXISTS trg_decrement_stock;

DELIMITER $$
CREATE TRIGGER trg_decrement_stock
AFTER UPDATE ON orders FOR EACH ROW
BEGIN
    IF NEW.statut = 'confirmee' AND OLD.statut = 'en_attente' THEN
        -- Décrémenter stock_vente pour les achats
        UPDATE products p
        INNER JOIN order_items oi ON oi.product_id = p.id
        SET p.stock_vente = p.stock_vente - oi.quantite
        WHERE oi.order_id = NEW.id AND oi.type_achat = 'vente';

        -- Décrémenter stock_location pour les locations
        UPDATE products p
        INNER JOIN order_items oi ON oi.product_id = p.id
        SET p.stock_location = p.stock_location - oi.quantite
        WHERE oi.order_id = NEW.id AND oi.type_achat = 'location';
    END IF;
END$$
DELIMITER ;

-- Adapter le trigger restock (retour location → stock_location++)
DROP TRIGGER IF EXISTS trg_restock_on_return;

DELIMITER $$
CREATE TRIGGER trg_restock_on_return
AFTER UPDATE ON rentals FOR EACH ROW
BEGIN
    IF NEW.date_retour_effective IS NOT NULL AND OLD.date_retour_effective IS NULL THEN
        UPDATE products p
        INNER JOIN order_items oi ON oi.product_id = p.id
        SET p.stock_location = p.stock_location + oi.quantite
        WHERE oi.id = NEW.order_item_id;
    END IF;
END$$
DELIMITER ;

-- Adapter le trigger journalisation products_update
DROP TRIGGER IF EXISTS products_update;
DROP TRIGGER IF EXISTS products_insert;
DROP TRIGGER IF EXISTS products_delete;

DELIMITER $$
CREATE TRIGGER products_insert
AFTER INSERT ON products FOR EACH ROW
BEGIN
    INSERT INTO products_sauv (id, titre, support, prix_vente, prix_location, stock_vente, stock_location, type_operation)
    VALUES (NEW.id, NEW.titre, NEW.support, NEW.prix_vente, NEW.prix_location, NEW.stock_vente, NEW.stock_location, 'A');
END$$

CREATE TRIGGER products_update
AFTER UPDATE ON products FOR EACH ROW
BEGIN
    INSERT INTO products_sauv (id, titre, support, prix_vente, prix_location, stock_vente, stock_location, type_operation)
    VALUES (OLD.id, OLD.titre, OLD.support, OLD.prix_vente, OLD.prix_location, OLD.stock_vente, OLD.stock_location, 'M');
END$$

CREATE TRIGGER products_delete
AFTER DELETE ON products FOR EACH ROW
BEGIN
    INSERT INTO products_sauv (id, titre, support, prix_vente, prix_location, stock_vente, stock_location, type_operation)
    VALUES (OLD.id, OLD.titre, OLD.support, OLD.prix_vente, OLD.prix_location, OLD.stock_vente, OLD.stock_location, 'S');
END$$
DELIMITER ;

-- Données de test avec stocks séparés réalistes
UPDATE products SET stock_vente = 10, stock_location = 5  WHERE titre = 'The Matrix';
UPDATE products SET stock_vente = 8,  stock_location = 3  WHERE titre = 'Titanic';
UPDATE products SET stock_vente = 6,  stock_location = 4  WHERE titre = 'Ghostbusters';
UPDATE products SET stock_vente = 12, stock_location = 6  WHERE titre = 'Top Gun';
UPDATE products SET stock_vente = 7,  stock_location = 3  WHERE titre = 'Jurassic Park';
UPDATE products SET stock_vente = 15, stock_location = 0  WHERE titre = 'Thriller';
UPDATE products SET stock_vente = 8,  stock_location = 0  WHERE titre = 'Purple Rain';
UPDATE products SET stock_vente = 9,  stock_location = 5  WHERE titre = 'Pulp Fiction';
UPDATE products SET stock_vente = 6,  stock_location = 4  WHERE titre = 'Le Seigneur des Anneaux';
UPDATE products SET stock_vente = 10, stock_location = 5  WHERE titre = 'Retour vers le Futur';
