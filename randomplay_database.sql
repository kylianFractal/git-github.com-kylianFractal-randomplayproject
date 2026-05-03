-- ============================================================
--  RandomPlay — Base de données complète v2
--  BTS SIO SLAM — Boutique vidéo vintage (vente + location)
--  MySQL 9.1 / PHP 8.3
--  Intègre le système de journalisation inspiré de tpevent.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS randomplay
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE randomplay;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- TABLE : users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT          NOT NULL AUTO_INCREMENT,
    prenom      VARCHAR(100) NOT NULL,
    nom         VARCHAR(100) NOT NULL,
    pseudo      VARCHAR(100) NOT NULL UNIQUE,
    email       VARCHAR(150) NOT NULL UNIQUE,
    mdp         VARCHAR(255) NOT NULL,
    type        ENUM('client','admin') NOT NULL DEFAULT 'client',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : products
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id              INT              NOT NULL AUTO_INCREMENT,
    titre           VARCHAR(200)     NOT NULL,
    realisateur     VARCHAR(150)     DEFAULT NULL,
    annee           YEAR             DEFAULT NULL,
    support         ENUM('vhs','cassette_audio','cd','dvd','vinyle') NOT NULL,
    genre           VARCHAR(100)     DEFAULT NULL,
    description     TEXT             DEFAULT NULL,
    prix_vente      DECIMAL(8,2)     DEFAULT NULL COMMENT 'NULL = non disponible à la vente',
    prix_location   DECIMAL(8,2)     DEFAULT NULL COMMENT 'NULL = non disponible à la location (par jour)',
    stock           INT              NOT NULL DEFAULT 0,
    cover_image     VARCHAR(255)     DEFAULT NULL,
    created_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_prix CHECK (prix_vente IS NOT NULL OR prix_location IS NOT NULL)
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : orders
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id          INT             NOT NULL AUTO_INCREMENT,
    user_id     INT             NOT NULL,
    statut      ENUM('en_attente','confirmee','terminee','annulee') NOT NULL DEFAULT 'en_attente',
    total       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : order_items
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id          INT             NOT NULL AUTO_INCREMENT,
    order_id    INT             NOT NULL,
    product_id  INT             NOT NULL,
    quantite    INT             NOT NULL DEFAULT 1,
    prix_unit   DECIMAL(8,2)    NOT NULL COMMENT 'Prix capturé au moment de la commande',
    type_achat  ENUM('vente','location') NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : rentals
-- ============================================================
CREATE TABLE IF NOT EXISTS rentals (
    id                    INT  NOT NULL AUTO_INCREMENT,
    order_item_id         INT  NOT NULL UNIQUE,
    date_debut            DATE NOT NULL,
    date_retour_prevue    DATE NOT NULL,
    date_retour_effective DATE DEFAULT NULL COMMENT 'NULL = pas encore rendu',
    PRIMARY KEY (id),
    CONSTRAINT fk_rental_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : logs (journal applicatif PHP)
-- ============================================================
CREATE TABLE IF NOT EXISTS logs (
    id          INT          NOT NULL AUTO_INCREMENT,
    user_id     INT          DEFAULT NULL COMMENT 'NULL si action système',
    action      VARCHAR(100) NOT NULL,
    details     TEXT         DEFAULT NULL,
    ip          VARCHAR(45)  DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE : users_sauv
-- Journalisation automatique — inspirée de tpevent.sql
-- A = Ajout | M = Modification | S = Suppression
-- ============================================================
CREATE TABLE IF NOT EXISTS users_sauv (
    id_sauv           INT          NOT NULL AUTO_INCREMENT,
    id                INT          NOT NULL,
    prenom            VARCHAR(100) DEFAULT NULL,
    nom               VARCHAR(100) DEFAULT NULL,
    email             VARCHAR(150) DEFAULT NULL,
    pseudo            VARCHAR(100) DEFAULT NULL,
    type_compte       VARCHAR(10)  DEFAULT NULL,
    type_operation    CHAR(1)      NOT NULL COMMENT 'A=Ajout, M=Modification, S=Suppression',
    date_modification DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_sauv)
) ENGINE=InnoDB
  COMMENT='Journalisation automatique des modifications sur users';

-- ============================================================
-- TABLE : products_sauv
-- Journalisation automatique du catalogue
-- ============================================================
CREATE TABLE IF NOT EXISTS products_sauv (
    id_sauv           INT          NOT NULL AUTO_INCREMENT,
    id                INT          NOT NULL,
    titre             VARCHAR(200) DEFAULT NULL,
    support           VARCHAR(50)  DEFAULT NULL,
    prix_vente        DECIMAL(8,2) DEFAULT NULL,
    prix_location     DECIMAL(8,2) DEFAULT NULL,
    stock             INT          DEFAULT NULL,
    type_operation    CHAR(1)      NOT NULL COMMENT 'A=Ajout, M=Modification, S=Suppression',
    date_modification DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_sauv)
) ENGINE=InnoDB
  COMMENT='Journalisation automatique des modifications sur products';

COMMIT;

-- ============================================================
-- TRIGGERS METIER
-- ============================================================

DELIMITER $$

-- 1. Décrémenter le stock à la confirmation commande
CREATE TRIGGER trg_decrement_stock
AFTER UPDATE ON orders FOR EACH ROW
BEGIN
    IF NEW.statut = 'confirmee' AND OLD.statut = 'en_attente' THEN
        UPDATE products p
        INNER JOIN order_items oi ON oi.product_id = p.id
        SET p.stock = p.stock - oi.quantite
        WHERE oi.order_id = NEW.id;
    END IF;
END$$

-- 2. Remettre le stock quand une location est rendue
CREATE TRIGGER trg_restock_on_return
AFTER UPDATE ON rentals FOR EACH ROW
BEGIN
    IF NEW.date_retour_effective IS NOT NULL AND OLD.date_retour_effective IS NULL THEN
        UPDATE products p
        INNER JOIN order_items oi ON oi.product_id = p.id
        SET p.stock = p.stock + oi.quantite
        WHERE oi.id = NEW.order_item_id;
    END IF;
END$$

-- 3. Log automatique à chaque nouvelle commande
CREATE TRIGGER trg_log_new_order
AFTER INSERT ON orders FOR EACH ROW
BEGIN
    INSERT INTO logs (user_id, action, details)
    VALUES (NEW.user_id, 'nouvelle_commande',
            CONCAT('Commande #', NEW.id, ' — statut: ', NEW.statut));
END$$

-- 4. Sécurité : empêcher stock négatif
CREATE TRIGGER trg_prevent_negative_stock
BEFORE UPDATE ON products FOR EACH ROW
BEGIN
    IF NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Erreur : le stock ne peut pas être négatif.';
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- TRIGGERS DE JOURNALISATION — users_sauv
-- Directement inspirés de tpevent.sql
-- ============================================================

DELIMITER $$

CREATE TRIGGER users_insert
AFTER INSERT ON users FOR EACH ROW
BEGIN
    INSERT INTO users_sauv (id, prenom, nom, email, pseudo, type_compte, type_operation)
    VALUES (NEW.id, NEW.prenom, NEW.nom, NEW.email, NEW.pseudo, NEW.type, 'A');
END$$

CREATE TRIGGER users_update
AFTER UPDATE ON users FOR EACH ROW
BEGIN
    -- Sauvegarde l'ANCIENNE valeur avant modification
    INSERT INTO users_sauv (id, prenom, nom, email, pseudo, type_compte, type_operation)
    VALUES (OLD.id, OLD.prenom, OLD.nom, OLD.email, OLD.pseudo, OLD.type, 'M');
END$$

CREATE TRIGGER users_delete
AFTER DELETE ON users FOR EACH ROW
BEGIN
    INSERT INTO users_sauv (id, prenom, nom, email, pseudo, type_compte, type_operation)
    VALUES (OLD.id, OLD.prenom, OLD.nom, OLD.email, OLD.pseudo, OLD.type, 'S');
END$$

DELIMITER ;

-- ============================================================
-- TRIGGERS DE JOURNALISATION — products_sauv
-- ============================================================

DELIMITER $$

CREATE TRIGGER products_insert
AFTER INSERT ON products FOR EACH ROW
BEGIN
    INSERT INTO products_sauv (id, titre, support, prix_vente, prix_location, stock, type_operation)
    VALUES (NEW.id, NEW.titre, NEW.support, NEW.prix_vente, NEW.prix_location, NEW.stock, 'A');
END$$

CREATE TRIGGER products_update
AFTER UPDATE ON products FOR EACH ROW
BEGIN
    -- Sauvegarde l'ANCIENNE valeur (utile pour historique prix et stock)
    INSERT INTO products_sauv (id, titre, support, prix_vente, prix_location, stock, type_operation)
    VALUES (OLD.id, OLD.titre, OLD.support, OLD.prix_vente, OLD.prix_location, OLD.stock, 'M');
END$$

CREATE TRIGGER products_delete
AFTER DELETE ON products FOR EACH ROW
BEGIN
    INSERT INTO products_sauv (id, titre, support, prix_vente, prix_location, stock, type_operation)
    VALUES (OLD.id, OLD.titre, OLD.support, OLD.prix_vente, OLD.prix_location, OLD.stock, 'S');
END$$

DELIMITER ;

-- ============================================================
-- PROCÉDURES STOCKÉES — Export CSV (inspiré de tpevent.sql)
-- ============================================================

DELIMITER $$

-- Export complet users_sauv
CREATE PROCEDURE export_users_sauv()
BEGIN
    SET @nom_fichier = CONCAT('c:/wamp64/tmp/randomplay_users_sauv_',
                               DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.csv');
    SET @sql = CONCAT(
        'SELECT * FROM users_sauv INTO OUTFILE \'', @nom_fichier, '\' ',
        'FIELDS TERMINATED BY \';\' ENCLOSED BY \'\"\' LINES TERMINATED BY \'\\n\''
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

-- Export incrémentiel users_sauv (exporte PUIS vide la table)
CREATE PROCEDURE export_users_sauv_increm()
BEGIN
    SET @nom_fichier = CONCAT('c:/wamp64/tmp/randomplay_users_increm_',
                               DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.csv');
    SET @sql = CONCAT(
        'SELECT * FROM users_sauv INTO OUTFILE \'', @nom_fichier, '\' ',
        'FIELDS TERMINATED BY \';\' ENCLOSED BY \'\"\' LINES TERMINATED BY \'\\n\''
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    TRUNCATE TABLE users_sauv;
END$$

-- Export complet products_sauv
CREATE PROCEDURE export_products_sauv()
BEGIN
    SET @nom_fichier = CONCAT('c:/wamp64/tmp/randomplay_products_sauv_',
                               DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.csv');
    SET @sql = CONCAT(
        'SELECT * FROM products_sauv INTO OUTFILE \'', @nom_fichier, '\' ',
        'FIELDS TERMINATED BY \';\' ENCLOSED BY \'\"\' LINES TERMINATED BY \'\\n\''
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END$$

-- Export incrémentiel products_sauv
CREATE PROCEDURE export_products_sauv_increm()
BEGIN
    SET @nom_fichier = CONCAT('c:/wamp64/tmp/randomplay_products_increm_',
                               DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s'), '.csv');
    SET @sql = CONCAT(
        'SELECT * FROM products_sauv INTO OUTFILE \'', @nom_fichier, '\' ',
        'FIELDS TERMINATED BY \';\' ENCLOSED BY \'\"\' LINES TERMINATED BY \'\\n\''
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    TRUNCATE TABLE products_sauv;
END$$

DELIMITER ;

-- ============================================================
-- ÉVÉNEMENTS MySQL — Automatisation exports (inspiré tpevent)
-- ============================================================

SET GLOBAL event_scheduler = ON;

DELIMITER $$

-- Export incrémentiel users toutes les heures
CREATE EVENT evt_export_users_increm
ON SCHEDULE EVERY 1 HOUR STARTS NOW()
ON COMPLETION NOT PRESERVE ENABLE
DO BEGIN
    CALL export_users_sauv_increm();
END$$

-- Export complet users tous les jours à minuit
CREATE EVENT evt_export_users_daily
ON SCHEDULE EVERY 1 DAY STARTS DATE_ADD(CURDATE(), INTERVAL 1 DAY)
ON COMPLETION NOT PRESERVE ENABLE
DO BEGIN
    CALL export_users_sauv();
END$$

-- Export incrémentiel produits toutes les heures
CREATE EVENT evt_export_products_increm
ON SCHEDULE EVERY 1 HOUR STARTS NOW()
ON COMPLETION NOT PRESERVE ENABLE
DO BEGIN
    CALL export_products_sauv_increm();
END$$

-- Export complet produits tous les jours
CREATE EVENT evt_export_products_daily
ON SCHEDULE EVERY 1 DAY STARTS DATE_ADD(CURDATE(), INTERVAL 1 DAY)
ON COMPLETION NOT PRESERVE ENABLE
DO BEGIN
    CALL export_products_sauv();
END$$

DELIMITER ;

-- ============================================================
-- DONNÉES DE TEST
-- ============================================================
START TRANSACTION;

INSERT INTO users (prenom, nom, pseudo, email, mdp, type) VALUES
('Admin',  'RandomPlay', 'admin_rp', 'admin@randomplay.fr', '$2y$12$placeholderHashAdmin000000000000000000000000', 'admin'),
('Marie',  'Dupont',     'marie_d',  'marie@example.fr',    '$2y$12$placeholderHashClient0000000000000000000000', 'client'),
('Lucas',  'Martin',     'lucas_m',  'lucas@example.fr',    '$2y$12$placeholderHashClient0000000000000000000000', 'client');

INSERT INTO products (titre, realisateur, annee, support, genre, description, prix_vente, prix_location, stock) VALUES
('The Matrix',              'Wachowski Sisters', 1999, 'vhs',           'Science-Fiction', 'Un programmeur découvre que sa réalité est une simulation.',  4.99,  1.50, 5),
('Titanic',                 'James Cameron',     1997, 'vhs',           'Drame',           'La tragédie du Titanic et un amour impossible.',              3.99,  1.00, 3),
('Ghostbusters',            'Ivan Reitman',      1984, 'vhs',           'Comédie',         'Trois chasseurs de fantômes sauvent New York.',               3.50,  1.00, 4),
('Top Gun',                 'Tony Scott',        1986, 'vhs',           'Action',          'Un pilote d\'élite repousse ses limites.',                    3.99,  1.20, 6),
('Jurassic Park',           'Steven Spielberg',  1993, 'vhs',           'Aventure',        'Des dinosaures recréés sèment la terreur.',                   4.50,  1.50, 3),
('Thriller',                'Michael Jackson',   1982, 'cassette_audio','Pop/Soul',         'L\'album le plus vendu de tous les temps.',                   7.50,  NULL, 8),
('Purple Rain',             'Prince',            1984, 'vinyle',        'Rock/Soul',        'Bande originale iconique du film éponyme.',                   12.00, NULL, 4),
('Pulp Fiction',            'Quentin Tarantino', 1994, 'dvd',           'Thriller',         'Histoires croisées dans le milieu criminel de LA.',           5.99,  1.50, 5),
('Le Seigneur des Anneaux', 'Peter Jackson',     2001, 'dvd',           'Fantasy',          'La quête pour détruire l\'Anneau Unique.',                    6.99,  2.00, 4),
('Retour vers le Futur',    'Robert Zemeckis',   1985, 'vhs',           'Science-Fiction', 'Un adolescent voyage dans le temps avec un savant fou.',      4.99,  1.50, 5);

COMMIT;
