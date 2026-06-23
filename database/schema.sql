-- ticketin — ساختار دیتابیس
-- MySQL 5.7+ / MariaDB 10.3+
-- قبل از import: CREATE DATABASE ticketin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fullname` VARCHAR(150) NOT NULL DEFAULT '',
  `national_code` VARCHAR(10) NULL,
  `mobile` VARCHAR(11) NULL,
  `username` VARCHAR(16) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `status` ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
  `job_title_id` INT UNSIGNED NULL,
  `job_title` VARCHAR(150) NULL,
  `organization_node_id` INT UNSIGNED NULL,
  `support_department` VARCHAR(120) NULL,
  `admin_type` VARCHAR(20) NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_mobile` (`mobile`),
  UNIQUE KEY `uniq_users_national_code` (`national_code`),
  UNIQUE KEY `uniq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- job_titles
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_titles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- organization_nodes
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organization_nodes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `type` ENUM('center','unit','health_house') NOT NULL,
  `center_category` VARCHAR(100) NULL,
  `name` VARCHAR(200) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_org_parent` (`parent_id`),
  KEY `idx_org_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- user_organization_rel
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_organization_rel` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `center_id` INT UNSIGNED NOT NULL,
  `node_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uor_user` (`user_id`),
  KEY `idx_uor_center` (`center_id`),
  KEY `idx_uor_node` (`node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- categories (ticket categories)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- tickets
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tracking_code` VARCHAR(20) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(150) NOT NULL,
  `priority` VARCHAR(20) NOT NULL DEFAULT 'medium',
  `message` TEXT NOT NULL,
  `attachment` VARCHAR(255) NULL,
  `status` ENUM('open','pending','progress','closed') NOT NULL DEFAULT 'open',
  `center_id` INT UNSIGNED NULL,
  `last_reply_by` VARCHAR(20) NULL,
  `closed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tracking_code` (`tracking_code`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `idx_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- ticket_replies
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ticket_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `message` TEXT NULL,
  `sender` ENUM('user','admin') NOT NULL,
  `attachment` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_replies_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- announcements
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT NULL,
  `content` LONGTEXT NULL,
  `image` VARCHAR(255) NULL,
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_archived` (`is_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- announcement_categories
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcement_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `parent_id` INT UNSIGNED NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- announcement_category_rel
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcement_category_rel` (
  `announcement_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`announcement_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- reminders
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reminders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `reminder_date` DATE NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminders_date` (`reminder_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- داده‌های اولیه (اختیاری)
-- --------------------------------------------------------
INSERT INTO `categories` (`name`, `sort_order`) VALUES
('فنی و IT', 1),
('اداری', 2),
('سخت‌افزار', 3);

INSERT INTO `job_titles` (`title`) VALUES
('کارشناس بهداشت'),
('مسئول فنی');
