-- HR Management System - Phase 3: Employee Schema
-- This file creates the employees table
-- Run this AFTER 01_auth_schema.sql, seed_admin.sql, and 02_department_designation_schema.sql
-- This will run cleanly on top of the existing Phase 1 + Phase 2 database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create employees table (depends on users, departments, designations)
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `employee_code` VARCHAR(20) NOT NULL,
  `department_id` INT NOT NULL,
  `designation_id` INT NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `joining_date` DATE NOT NULL,
  `employment_status` ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
  `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_employee_user_id` (`user_id`),
  UNIQUE KEY `idx_employee_code` (`employee_code`),
  KEY `idx_employee_department` (`department_id`),
  KEY `idx_employee_designation` (`designation_id`),
  KEY `idx_employee_status` (`employment_status`),
  KEY `idx_employee_deleted_at` (`deleted_at`),
  KEY `idx_employee_created_by` (`created_by`),
  CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_employee_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_employee_designation` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_employee_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
