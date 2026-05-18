-- =====================================================================
-- IMPACT365 — Incremental upgrade
-- Safe to run on an already-installed database (idempotent).
-- Import via phpMyAdmin → SQL, or:  mysql -u USER -p DBNAME < sql/upgrade.sql
-- (Re-importing the full sql/schema.sql is also safe — it uses
--  CREATE TABLE IF NOT EXISTS and INSERT ... ON DUPLICATE KEY.)
-- =====================================================================

SET NAMES utf8mb4;

-- Educational resources (trainer module)
CREATE TABLE IF NOT EXISTS `resources` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trainer_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(180) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `file_path`   VARCHAR(255) DEFAULT NULL,
  `link_url`    VARCHAR(255) DEFAULT NULL,
  `is_public`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_res_trainer` (`trainer_id`),
  CONSTRAINT `fk_res_trainer` FOREIGN KEY (`trainer_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact form submissions
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `email`      VARCHAR(190) NOT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `subject`    VARCHAR(180) DEFAULT NULL,
  `message`    TEXT         NOT NULL,
  `status`     ENUM('new','read','archived') NOT NULL DEFAULT 'new',
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`),
  KEY `idx_contact_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Organisation / contact identity (no-op if rows already exist)
INSERT INTO `settings` (`skey`,`svalue`) VALUES
  ('org_legal_name','IMPACT365'),
  ('contact_email','hello@impact365.my'),
  ('contact_phone',''),
  ('contact_address',''),
  ('social_url','')
ON DUPLICATE KEY UPDATE `skey`=`skey`;
