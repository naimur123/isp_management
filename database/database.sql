-- =====================================================================
-- ISP Customer, Billing, Bandwidth & Management Reporting System
-- Database Schema
-- Engine: MySQL 8+ / MariaDB 10.4+ (InnoDB, utf8mb4)
-- Timezone: Asia/Dhaka (application layer)
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL DEFAULT NULL,
  `remember_token` VARCHAR(100) NULL DEFAULT NULL,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: companies
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(150) NOT NULL,
  `company_code` VARCHAR(20) NOT NULL,
  `customer_prefix` VARCHAR(10) NOT NULL,
  `last_customer_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_code` (`company_code`),
  UNIQUE KEY `uq_companies_prefix` (`customer_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: zones
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `zones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zone_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zones_name` (`zone_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: customers  (Customer Master)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` VARCHAR(20) NOT NULL COMMENT 'Auto generated e.g. TCL-00001, permanent, never reused',
  `customer_name` VARCHAR(180) NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `zone_id` INT UNSIGNED NOT NULL,
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_customer_id` (`customer_id`),
  KEY `idx_customers_name` (`customer_name`),
  KEY `idx_customers_company` (`company_id`),
  KEY `idx_customers_category` (`category_id`),
  KEY `idx_customers_zone` (`zone_id`),
  KEY `idx_customers_status` (`status`),
  KEY `idx_customers_created_at` (`created_at`),
  KEY `idx_customers_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_customers_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_customers_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_customers_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`),
  CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_customers_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: monthly_records  (monthly billing / bandwidth history)
-- `deleted_marker` makes the Customer+Month uniqueness apply only to
-- non-deleted rows: every active row keeps deleted_marker=0 (so two
-- active rows for the same customer+month collide), while soft-deleting
-- a row sets deleted_marker = id (its own primary key), so historical
-- rows never collide with each other or with a new active one.
-- (A GENERATED column cannot reference an AUTO_INCREMENT column in
-- MySQL/MariaDB, so this is maintained by the application instead.)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monthly_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL COMMENT 'FK to customers.id',
  `billing_month` DATE NOT NULL COMMENT 'Stored as first day of month, e.g. 2026-08-01',
  `billing_amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `bandwidth_mbps` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  `remarks` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  `deleted_marker` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 while active; set to this row''s own id when soft-deleted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customer_month_active` (`customer_id`,`billing_month`,`deleted_marker`),
  KEY `idx_mr_billing_month` (`billing_month`),
  KEY `idx_mr_status` (`status`),
  KEY `idx_mr_created_at` (`created_at`),
  KEY `idx_mr_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_mr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_mr_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_mr_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: import_history
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `import_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `filename` VARCHAR(255) NOT NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `duplicate_mode` VARCHAR(20) NOT NULL DEFAULT 'skip',
  `status` ENUM('pending','completed','cancelled','failed') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_import_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: import_errors  (per-row error report for a given import)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `import_errors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `import_id` INT UNSIGNED NOT NULL,
  `excel_row` INT UNSIGNED NOT NULL,
  `customer_id` VARCHAR(20) NULL,
  `customer_name` VARCHAR(180) NULL,
  `error_type` VARCHAR(60) NOT NULL,
  `error_description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_import_errors_import` (`import_id`),
  CONSTRAINT `fk_import_errors_import` FOREIGN KEY (`import_id`) REFERENCES `import_history` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: activity_logs  (audit trail)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `user_name` VARCHAR(150) NULL,
  `action` VARCHAR(60) NOT NULL,
  `module` VARCHAR(60) NOT NULL,
  `record_id` VARCHAR(60) NULL,
  `description` VARCHAR(500) NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_module` (`module`),
  KEY `idx_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: settings  (key/value system settings)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(60) NOT NULL,
  `setting_value` VARCHAR(500) NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Table: Collection Records (for billing collection tracking)
-- ---------------------------------------------------------------------
-- CREATE TABLE `collection_records` (
--   `id` int unsigned NOT NULL AUTO_INCREMENT,
--   PRIMARY KEY (`id`),
--   `customer_id` int unsigned NOT NULL COMMENT 'FK to customers.id',
--   `billing_month` date NOT NULL COMMENT 'Stored as first day of month, e.g. 2026-08-01',
--   `week_segment` varchar(20) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Stored as week segment, e.g. 1-7, 8-14',
--   `billing_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
--   `bandwidth_mbps` decimal(14,2) NOT NULL DEFAULT '0.00',
--   `status` enum('Paid','Due') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Paid',
--   `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
--   `created_by` int unsigned DEFAULT NULL,
--   `updated_by` int unsigned DEFAULT NULL,
--   `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   `deleted_at` datetime DEFAULT NULL,
--   `deleted_marker` int unsigned NOT NULL DEFAULT '0' COMMENT '0 while active; set to this row''s own id when soft-deleted'
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- SET FOREIGN_KEY_CHECKS = 1;

/* Add new columns to existing table Monthly Records */
ALTER TABLE `monthly_records` 
   ADD `collection_status` ENUM('Paid','Due') NOT NULL DEFAULT 'Due',
   ADD `collection_segment` VARCHAR(30) NULL DEFAULT NULL, 
   ADD `collected_by` INT(10) NULL DEFAULT NULL, 
   ADD `collected_date` DATE NULL DEFAULT NULL,
   ADD `actual_collected_date` DATE NULL DEFAULT NULL,
   ADD `collection_amount` DECIMAL(14,2) NULL DEFAULT 0.00,
   ADD `bank_id` INT(10) NULL AFTER `collection_amount`;

  /* Bank Information Table */
  CREATE TABLE IF NOT EXISTS `banks` (
    `id` INT(10) NOT NULL AUTO_INCREMENT , 
    `bank_name` VARCHAR(100) NOT NULL , 
    `account_number` VARCHAR(100) NULL DEFAULT NULL, 
    `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `created_by` INT(10) NULL DEFAULT NULL , 
    `created_at` DATETIME NULL DEFAULT NULL , 
    `updated_by` INT(10) NULL DEFAULT NULL , 
    `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP , 
    PRIMARY KEY (`id`)) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    ALTER TABLE `monthly_records` CHANGE `status` 
    `status` ENUM('Active','Inactive','Hold') 
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Active';

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default administrator: username "admin" / password "Admin@12345"
-- (You will be forced to change this password after first login.)
INSERT INTO `users` (`full_name`,`username`,`email`,`password`,`role`,`status`,`must_change_password`)
VALUES ('System Administrator','admin','admin@example.com','$2y$12$BBNhzbFfNC7UT4BYQviqm.HQ3ujRv2OMvdgeF8PY.9vdNBDD7K3AO','admin','active',1)
ON DUPLICATE KEY UPDATE username = username;
-- NOTE: the hash above corresponds to password: Admin@12345

INSERT INTO `companies` (`company_name`,`company_code`,`customer_prefix`,`last_customer_sequence`,`status`) VALUES
('Tahoe Communications Limited','TCL','TCL',0,'active'),
('JOL','JOL','JOL',0,'active'),
('Dhrubo Networks Limited','DNL','DNL',0,'active')
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

INSERT INTO `categories` (`category_name`,`status`) VALUES
('Corporate','active'),
('Home User','active'),
('Reseller','active')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

INSERT INTO `zones` (`zone_name`,`status`) VALUES
('Gulshan','active'),
('Banani','active'),
('Uttara','active'),
('Motijheel','active'),
('Dhanmondi','active'),
('Mirpur','active'),
('Mohakhali / Tejgaon','active'),
('Bashundhara','active'),
('Gazipur','active'),
('DEPZ','active'),
('AEPZ','active'),
('Narayanganj','active'),
('Savar','active'),
('Tongi','active'),
('Other','active')
ON DUPLICATE KEY UPDATE zone_name = VALUES(zone_name);

INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
('software_name','ISP Management System'),
('organization_name','Your ISP Organization'),
('logo_path',''),
('currency_code','BDT'),
('currency_symbol','৳'),
('timezone','Asia/Dhaka'),
('date_format','d-m-Y'),
('month_format','M-Y'),
('customer_id_digits','5'),
('default_pagination','25'),
('excel_upload_limit_mb','10'),
('session_timeout_minutes','60')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
