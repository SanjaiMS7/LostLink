-- ============================================================
-- LostLink - Lost and Found Management Portal
-- Database: lostlink   (import this file in phpMyAdmin > Import)
-- Tables : users, admins, categories, items, claims
-- NOTE   : re-importing drops and recreates the tables (clean reset).
-- The first administrator is created safely (with password_hash)
-- by opening  http://localhost/LostLink/admin/setup.php  once.
-- ============================================================

CREATE DATABASE IF NOT EXISTS lostlink
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lostlink;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- USER
-- ------------------------------------------------------------
CREATE TABLE users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  register_no   VARCHAR(20)  NOT NULL,
  email         VARCHAR(150) NOT NULL,
  department    VARCHAR(100) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_register_no (register_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- ADMIN
-- ------------------------------------------------------------
CREATE TABLE admins (
  admin_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(30)  NOT NULL DEFAULT 'admin',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CATEGORY
-- ------------------------------------------------------------
CREATE TABLE categories (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(60)  NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name) VALUES
  ('Bags'),
  ('Electronics'),
  ('ID Cards'),
  ('Keys'),
  ('Stationery'),
  ('Bottles'),
  ('Accessories'),
  ('Books'),
  ('Clothing'),
  ('Others');

-- ------------------------------------------------------------
-- ITEM  (lost and found reports)
-- status: open | claimed | returned | closed
-- ------------------------------------------------------------
CREATE TABLE items (
  item_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  type         ENUM('lost','found') NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT         NOT NULL,
  location     VARCHAR(150) NOT NULL,
  event_date   DATE         NOT NULL,
  kept_at      VARCHAR(150) DEFAULT NULL,
  contact      VARCHAR(100) NOT NULL,
  image        VARCHAR(255) DEFAULT NULL,
  status       ENUM('open','claimed','returned','closed') NOT NULL DEFAULT 'open',
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  KEY idx_items_user (user_id),
  KEY idx_items_category (category_id),
  KEY idx_items_type_status (type, status),
  KEY idx_items_event_date (event_date),
  CONSTRAINT fk_items_user
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_items_category
    FOREIGN KEY (category_id) REFERENCES categories (category_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- CLAIM
-- status: pending | approved | rejected
-- UNIQUE (item_id, claimant_id) prevents duplicate claims
-- ------------------------------------------------------------
CREATE TABLE claims (
  claim_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id      INT UNSIGNED NOT NULL,
  claimant_id  INT UNSIGNED NOT NULL,
  proof_text   TEXT         NOT NULL,
  status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by  INT UNSIGNED DEFAULT NULL,
  reviewed_at  DATETIME     DEFAULT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (claim_id),
  UNIQUE KEY uq_claim_item_claimant (item_id, claimant_id),
  KEY idx_claims_claimant (claimant_id),
  KEY idx_claims_status (status),
  CONSTRAINT fk_claims_item
    FOREIGN KEY (item_id) REFERENCES items (item_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_claims_claimant
    FOREIGN KEY (claimant_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_claims_admin
    FOREIGN KEY (reviewed_by) REFERENCES admins (admin_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
