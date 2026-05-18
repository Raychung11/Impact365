-- =====================================================================
-- IMPACT365 — ESG Community & Event Operating System
-- Phase 1 (MVP) Database Schema
-- Engine: MySQL 5.7+/8.0 (Hostinger shared hosting compatible)
-- Charset: utf8mb4 / utf8mb4_unicode_ci
--
-- Design notes:
--   * Normalized, status-driven tables for cheap WHERE filtering.
--   * Indexes on every foreign key + common lookup columns.
--   * No CPU-heavy constructs; suitable for shared hosting.
--   * Import order respects foreign-key dependencies.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- MODULE 1 — AUTHENTICATION
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(120)  NOT NULL,
  `email`           VARCHAR(190)  NOT NULL,
  `phone`           VARCHAR(20)   DEFAULT NULL,
  `password_hash`   VARCHAR(255)  NOT NULL,
  `role`            ENUM('public','member','organizer','corporate','trainer','admin')
                    NOT NULL DEFAULT 'member',
  `status`          ENUM('pending','active','suspended','rejected')
                    NOT NULL DEFAULT 'active',
  `email_verified`  TINYINT(1)    NOT NULL DEFAULT 0,
  `verify_token`    VARCHAR(64)   DEFAULT NULL,
  `reset_token`     VARCHAR(64)   DEFAULT NULL,
  `reset_expires`   DATETIME      DEFAULT NULL,
  `referral_code`   VARCHAR(16)   DEFAULT NULL,
  `referred_by`     INT UNSIGNED  DEFAULT NULL,
  `profile_image`   VARCHAR(255)  DEFAULT NULL,
  `last_login_at`   DATETIME      DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_refcode` (`referral_code`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_referred_by` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_profiles` (
  `user_id`       INT UNSIGNED NOT NULL,
  `organization`  VARCHAR(160) DEFAULT NULL,
  `position`      VARCHAR(120) DEFAULT NULL,
  `bio`           TEXT         DEFAULT NULL,
  `address`       VARCHAR(255) DEFAULT NULL,
  `city`          VARCHAR(80)  DEFAULT NULL,
  `state`         VARCHAR(80)  DEFAULT NULL,
  `postcode`      VARCHAR(10)  DEFAULT NULL,
  `country`       VARCHAR(80)  DEFAULT 'Malaysia',
  `website`       VARCHAR(190) DEFAULT NULL,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED DEFAULT NULL,
  `email`       VARCHAR(190) DEFAULT NULL,
  `ip`          VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('success','failed','locked') NOT NULL DEFAULT 'failed',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loginlogs_user` (`user_id`),
  KEY `idx_loginlogs_email` (`email`),
  KEY `idx_loginlogs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MODULE 2 — MEMBERSHIP
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_plans` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `code`          VARCHAR(40)  NOT NULL,
  `price`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency`      VARCHAR(3)   NOT NULL DEFAULT 'MYR',
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 365,
  `benefits`      TEXT         DEFAULT NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `memberships` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `plan_id`     INT UNSIGNED NOT NULL,
  `status`      ENUM('pending','active','expired','cancelled')
                NOT NULL DEFAULT 'pending',
  `member_no`   VARCHAR(24)  DEFAULT NULL,
  `starts_at`   DATE         DEFAULT NULL,
  `expires_at`  DATE         DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_no` (`member_no`),
  KEY `idx_membership_user` (`user_id`),
  KEY `idx_membership_plan` (`plan_id`),
  KEY `idx_membership_status` (`status`),
  CONSTRAINT `fk_membership_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_membership_plan` FOREIGN KEY (`plan_id`)
    REFERENCES `membership_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `membership_payments` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `membership_id` INT UNSIGNED DEFAULT NULL,
  `plan_id`       INT UNSIGNED NOT NULL,
  `amount`        DECIMAL(10,2) NOT NULL,
  `currency`      VARCHAR(3)   NOT NULL DEFAULT 'MYR',
  `gateway`       VARCHAR(40)  NOT NULL DEFAULT 'billplz',
  `bill_id`       VARCHAR(64)  DEFAULT NULL,
  `gateway_ref`   VARCHAR(120) DEFAULT NULL,
  `status`        ENUM('pending','paid','failed','refunded')
                  NOT NULL DEFAULT 'pending',
  `invoice_no`    VARCHAR(32)  DEFAULT NULL,
  `paid_at`       DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_invoice` (`invoice_no`),
  KEY `idx_pay_user` (`user_id`),
  KEY `idx_pay_status` (`status`),
  KEY `idx_pay_bill` (`bill_id`),
  CONSTRAINT `fk_pay_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pay_plan` FOREIGN KEY (`plan_id`)
    REFERENCES `membership_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MODULE 3 — REFERRAL
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referrals` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id`      INT UNSIGNED NOT NULL,
  `referred_user_id` INT UNSIGNED DEFAULT NULL,
  `code`             VARCHAR(16)  NOT NULL,
  `source`           VARCHAR(60)  DEFAULT NULL,
  `status`           ENUM('clicked','registered','converted')
                     NOT NULL DEFAULT 'registered',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ref_referrer` (`referrer_id`),
  KEY `idx_ref_referred` (`referred_user_id`),
  KEY `idx_ref_code` (`code`),
  KEY `idx_ref_status` (`status`),
  CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_referred` FOREIGN KEY (`referred_user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referral_rewards` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referral_id`  INT UNSIGNED DEFAULT NULL,
  `referrer_id`  INT UNSIGNED NOT NULL,
  `amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `type`         ENUM('signup','membership','event') NOT NULL DEFAULT 'signup',
  `status`       ENUM('pending','approved','paid','void')
                 NOT NULL DEFAULT 'pending',
  `note`         VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rew_referrer` (`referrer_id`),
  KEY `idx_rew_status` (`status`),
  CONSTRAINT `fk_rew_referrer` FOREIGN KEY (`referrer_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `referral_wallets` (
  `user_id`         INT UNSIGNED NOT NULL,
  `balance`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_earned`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MODULE 4 — EVENT MANAGEMENT
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_categories` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`      VARCHAR(100) NOT NULL,
  `slug`      VARCHAR(120) NOT NULL,
  `is_active` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id`     INT UNSIGNED NOT NULL,
  `category_id`      INT UNSIGNED DEFAULT NULL,
  `title`            VARCHAR(180) NOT NULL,
  `slug`             VARCHAR(200) NOT NULL,
  `summary`          VARCHAR(300) DEFAULT NULL,
  `description`      MEDIUMTEXT   DEFAULT NULL,
  `type`             ENUM('workshop','conference','campaign','volunteer','training','activation')
                     NOT NULL DEFAULT 'workshop',
  `venue`            VARCHAR(180) DEFAULT NULL,
  `address`          VARCHAR(255) DEFAULT NULL,
  `city`             VARCHAR(80)  DEFAULT NULL,
  `state`            VARCHAR(80)  DEFAULT NULL,
  `start_datetime`   DATETIME     NOT NULL,
  `end_datetime`     DATETIME     DEFAULT NULL,
  `capacity`         INT UNSIGNED NOT NULL DEFAULT 0,
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `banner_image`     VARCHAR(255) DEFAULT NULL,
  `status`           ENUM('draft','pending','approved','rejected','cancelled','completed')
                     NOT NULL DEFAULT 'pending',
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `is_featured`      TINYINT(1)   NOT NULL DEFAULT 0,
  `published_at`     DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_slug` (`slug`),
  KEY `idx_event_org` (`organizer_id`),
  KEY `idx_event_cat` (`category_id`),
  KEY `idx_event_status` (`status`),
  KEY `idx_event_start` (`start_datetime`),
  CONSTRAINT `fk_event_org` FOREIGN KEY (`organizer_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_cat` FOREIGN KEY (`category_id`)
    REFERENCES `event_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_registrations` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`       INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED DEFAULT NULL,
  `name`           VARCHAR(120) NOT NULL,
  `email`          VARCHAR(190) NOT NULL,
  `phone`          VARCHAR(20)  DEFAULT NULL,
  `ticket_code`    VARCHAR(24)  NOT NULL,
  `status`         ENUM('registered','cancelled','attended','no_show')
                   NOT NULL DEFAULT 'registered',
  `amount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` ENUM('free','pending','paid') NOT NULL DEFAULT 'free',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reg_ticket` (`ticket_code`),
  UNIQUE KEY `uq_reg_event_email` (`event_id`,`email`),
  KEY `idx_reg_event` (`event_id`),
  KEY `idx_reg_user` (`user_id`),
  KEY `idx_reg_status` (`status`),
  CONSTRAINT `fk_reg_event` FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reg_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_attendance` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_id` BIGINT UNSIGNED NOT NULL,
  `event_id`       INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED DEFAULT NULL,
  `checked_in_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `checked_in_by`  INT UNSIGNED DEFAULT NULL,
  `method`         ENUM('qr','manual','code') NOT NULL DEFAULT 'qr',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_att_registration` (`registration_id`),
  KEY `idx_att_event` (`event_id`),
  CONSTRAINT `fk_att_reg` FOREIGN KEY (`registration_id`)
    REFERENCES `event_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_event` FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_approvals` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NOT NULL,
  `admin_id`    INT UNSIGNED DEFAULT NULL,
  `action`      ENUM('submitted','approved','rejected','cancelled')
                NOT NULL,
  `reason`      VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evapp_event` (`event_id`),
  CONSTRAINT `fk_evapp_event` FOREIGN KEY (`event_id`)
    REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MODULE 5 — ESG PROJECT MARKETPLACE
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `esg_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(140) NOT NULL,
  `sdg_number` TINYINT UNSIGNED DEFAULT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_esgcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `esg_projects` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organizer_id`     INT UNSIGNED NOT NULL,
  `category_id`      INT UNSIGNED DEFAULT NULL,
  `title`            VARCHAR(180) NOT NULL,
  `slug`             VARCHAR(200) NOT NULL,
  `summary`          VARCHAR(300) DEFAULT NULL,
  `description`      MEDIUMTEXT   DEFAULT NULL,
  `location`         VARCHAR(180) DEFAULT NULL,
  `state`            VARCHAR(80)  DEFAULT NULL,
  `sdg_goals`        VARCHAR(120) DEFAULT NULL,
  `funding_target`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `funding_raised`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `impact_metric`    VARCHAR(255) DEFAULT NULL,
  `cover_image`      VARCHAR(255) DEFAULT NULL,
  `status`           ENUM('draft','pending','approved','rejected','completed')
                     NOT NULL DEFAULT 'pending',
  `rejection_reason` VARCHAR(255) DEFAULT NULL,
  `published_at`     DATETIME     DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_esg_slug` (`slug`),
  KEY `idx_esg_org` (`organizer_id`),
  KEY `idx_esg_cat` (`category_id`),
  KEY `idx_esg_status` (`status`),
  CONSTRAINT `fk_esg_org` FOREIGN KEY (`organizer_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_esg_cat` FOREIGN KEY (`category_id`)
    REFERENCES `esg_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `esg_media` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `file_path`  VARCHAR(255) NOT NULL,
  `media_type` ENUM('image','document') NOT NULL DEFAULT 'image',
  `caption`    VARCHAR(180) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_esgmedia_project` (`project_id`),
  CONSTRAINT `fk_esgmedia_project` FOREIGN KEY (`project_id`)
    REFERENCES `esg_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `esg_sponsors` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`   INT UNSIGNED NOT NULL,
  `corporate_id` INT UNSIGNED NOT NULL,
  `amount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status`       ENUM('requested','approved','fulfilled','declined')
                 NOT NULL DEFAULT 'requested',
  `message`      VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sponsor_project` (`project_id`),
  KEY `idx_sponsor_corp` (`corporate_id`),
  CONSTRAINT `fk_sponsor_project` FOREIGN KEY (`project_id`)
    REFERENCES `esg_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sponsor_corp` FOREIGN KEY (`corporate_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `esg_reports` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`   INT UNSIGNED NOT NULL,
  `organizer_id` INT UNSIGNED NOT NULL,
  `title`        VARCHAR(180) NOT NULL,
  `summary`      TEXT         DEFAULT NULL,
  `file_path`    VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_esgrep_project` (`project_id`),
  CONSTRAINT `fk_esgrep_project` FOREIGN KEY (`project_id`)
    REFERENCES `esg_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MODULE 6 — CORPORATE ESG DASHBOARD
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `corporate_accounts` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `company_name`   VARCHAR(180) NOT NULL,
  `registration_no` VARCHAR(60) DEFAULT NULL,
  `industry`       VARCHAR(120) DEFAULT NULL,
  `contact_person` VARCHAR(120) DEFAULT NULL,
  `contact_phone`  VARCHAR(20)  DEFAULT NULL,
  `address`        VARCHAR(255) DEFAULT NULL,
  `logo`           VARCHAR(255) DEFAULT NULL,
  `csr_focus`      VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corp_user` (`user_id`),
  CONSTRAINT `fk_corp_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `csr_reports` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `corporate_id` INT UNSIGNED NOT NULL,
  `title`        VARCHAR(180) NOT NULL,
  `period`       VARCHAR(40)  DEFAULT NULL,
  `summary`      TEXT         DEFAULT NULL,
  `file_path`    VARCHAR(255) DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_csr_corp` (`corporate_id`),
  CONSTRAINT `fk_csr_corp` FOREIGN KEY (`corporate_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- MODULE 8 — GOVERNANCE / AUDIT  &  SHARED SERVICES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `role`       VARCHAR(20)  DEFAULT NULL,
  `action`     VARCHAR(80)  NOT NULL,
  `entity`     VARCHAR(60)  DEFAULT NULL,
  `entity_id`  VARCHAR(40)  DEFAULT NULL,
  `details`    VARCHAR(500) DEFAULT NULL,
  `ip`         VARCHAR(45)  DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity`,`entity_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(160) NOT NULL,
  `message`    VARCHAR(500) DEFAULT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `settings` (
  `skey`       VARCHAR(60)  NOT NULL,
  `svalue`     TEXT         DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default administrator.
-- Email: admin@impact365.my   Password: Admin@365
-- (bcrypt hash below; CHANGE THE PASSWORD AFTER FIRST LOGIN.)
INSERT INTO `users`
  (`name`,`email`,`phone`,`password_hash`,`role`,`status`,`email_verified`,`referral_code`)
VALUES
  ('System Administrator','admin@impact365.my','+60123456789',
   '$2y$12$vRtnKsbAzXAX/wEA0QFwo.e3Wow/Jwm3aCu9yaH119a4s6.XyWtJC',
   'admin','active',1,'ADMIN365')
ON DUPLICATE KEY UPDATE `email`=`email`;

INSERT INTO `membership_plans`
  (`name`,`code`,`price`,`currency`,`duration_days`,`benefits`,`is_active`)
VALUES
  ('IMPACT365 Annual Membership','ANNUAL365',365.00,'MYR',365,
   'Full member dashboard access; event registration; educational content; referral wallet; digital membership card; volunteer participation.',1)
ON DUPLICATE KEY UPDATE `code`=`code`;

INSERT INTO `event_categories` (`name`,`slug`,`is_active`) VALUES
  ('ESG Workshop','esg-workshop',1),
  ('Sustainability Conference','sustainability-conference',1),
  ('Community Campaign','community-campaign',1),
  ('Volunteer Drive','volunteer-drive',1),
  ('Corporate Training','corporate-training',1)
ON DUPLICATE KEY UPDATE `slug`=`slug`;

INSERT INTO `esg_categories` (`name`,`slug`,`sdg_number`,`is_active`) VALUES
  ('No Poverty','no-poverty',1,1),
  ('Zero Hunger','zero-hunger',2,1),
  ('Good Health & Well-being','good-health',3,1),
  ('Quality Education','quality-education',4,1),
  ('Gender Equality','gender-equality',5,1),
  ('Clean Water & Sanitation','clean-water',6,1),
  ('Affordable & Clean Energy','clean-energy',7,1),
  ('Decent Work & Economic Growth','decent-work',8,1),
  ('Climate Action','climate-action',13,1),
  ('Life on Land','life-on-land',15,1)
ON DUPLICATE KEY UPDATE `slug`=`slug`;

INSERT INTO `settings` (`skey`,`svalue`) VALUES
  ('site_name','IMPACT365'),
  ('billplz_mode','sandbox'),
  ('billplz_api_key',''),
  ('billplz_collection_id',''),
  ('billplz_x_signature',''),
  ('referral_signup_reward','10.00'),
  ('referral_membership_reward','36.50')
ON DUPLICATE KEY UPDATE `skey`=`skey`;
