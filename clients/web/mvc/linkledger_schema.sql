-- LinkLedger / SASD Links - Schema (IONOS MySQL/MariaDB)
-- ======================================================
-- Zweck:
--   Tabellen für Benutzer, Projekte, Links/URLs und Tags + Zuordnung Link<->Tag.
--   Duplikate je User+Projekt werden über canonical_hash erkannt.
--
-- Hinweise für IONOS:
--   - Engine: InnoDB ist notwendig für Foreign Keys.
--   - Charset: utf8mb4 für Umlaute/Emoji.
--   - Du kannst dieses Script im IONOS phpMyAdmin unter "SQL" ausführen.
--
-- Optional: Wenn du die Datenbank erst anlegen willst, entkommentiere die nächsten Zeilen
-- und passe den DB-Namen an.
--
-- CREATE DATABASE IF NOT EXISTS linkledger
--   DEFAULT CHARACTER SET utf8mb4
--   DEFAULT COLLATE utf8mb4_unicode_ci;
-- USE linkledger;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- (Optional) Drop-Section für Neuaufbau (ACHTUNG: Datenverlust!)
-- DROP TABLE IF EXISTS link_tags;
-- DROP TABLE IF EXISTS links;
-- DROP TABLE IF EXISTS tags;
-- DROP TABLE IF EXISTS projects;
-- DROP TABLE IF EXISTS users;

-- --------------------------------------------------------------
-- users
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- projects
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_projects_user_name (user_id, name),
  KEY ix_projects_user (user_id),
  CONSTRAINT fk_projects_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- tags
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tags_user_name (user_id, name),
  KEY ix_tags_user (user_id),
  CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- links
-- --------------------------------------------------------------
-- Wichtig:
--   url + canonical_url sind TEXT (vollständige URLs).
--   canonical_hash ist CHAR(64) (SHA-256 Hex), weil UNIQUE auf TEXT unsauber wäre.
CREATE TABLE IF NOT EXISTS links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  url TEXT NOT NULL,
  canonical_url TEXT NOT NULL,
  canonical_hash CHAR(64) NOT NULL,
  title VARCHAR(500) NULL,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_links_dup (user_id, project_id, canonical_hash),
  KEY ix_links_user (user_id),
  KEY ix_links_project (project_id),
  CONSTRAINT fk_links_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_links_project FOREIGN KEY (project_id) REFERENCES projects(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------
-- link_tags (Many-to-Many)
-- --------------------------------------------------------------
CREATE TABLE IF NOT EXISTS link_tags (
  link_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (link_id, tag_id),
  CONSTRAINT fk_link_tags_link FOREIGN KEY (link_id) REFERENCES links(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_link_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
