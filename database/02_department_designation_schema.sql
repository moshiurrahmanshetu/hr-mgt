-- HR Management System - Phase 2: Department & Designation Schema
-- This file creates the department and designation tables
-- Run this AFTER 01_auth_schema.sql and seed_admin.sql
-- This will run cleanly on top of the existing Phase 1 database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create departments table first (no dependencies on new tables)
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_department_name` (`name`),
  KEY `idx_department_status` (`status`),
  KEY `idx_department_created_by` (`created_by`),
  CONSTRAINT `fk_department_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create designations table (depends on departments)
CREATE TABLE IF NOT EXISTS `designations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `department_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_designation_department` (`department_id`),
  KEY `idx_designation_status` (`status`),
  KEY `idx_designation_created_by` (`created_by`),
  UNIQUE KEY `idx_designation_dept_title` (`department_id`, `title`),
  CONSTRAINT `fk_designation_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_designation_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample departments
INSERT INTO `departments` (`id`, `name`, `description`, `status`, `created_by`) VALUES
(1, 'Human Resource', 'Manages employee relations, recruitment, and organizational development', 'active', 1),
(2, 'Accounts', 'Handles financial operations, accounting, and budgeting', 'active', 1),
(3, 'IT', 'Information Technology and software development', 'active', 1),
(4, 'Marketing', 'Marketing, advertising, and brand management', 'active', 1),
(5, 'Sales', 'Sales operations and client relationship management', 'active', 1)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `status` = VALUES(`status`);

-- Seed sample designations
INSERT INTO `designations` (`id`, `department_id`, `title`, `description`, `status`, `created_by`) VALUES
(1, 1, 'Manager', 'Department manager with leadership responsibilities', 'active', 1),
(2, 1, 'HR Executive', 'Handles HR operations and employee support', 'active', 1),
(3, 3, 'Software Engineer', 'Develops and maintains software applications', 'active', 1),
(4, 2, 'Accountant', 'Manages financial records and accounting tasks', 'active', 1),
(5, 5, 'Sales Executive', 'Handles sales operations and client relationships', 'active', 1)
ON DUPLICATE KEY UPDATE 
  `department_id` = VALUES(`department_id`),
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `status` = VALUES(`status`);

COMMIT;
