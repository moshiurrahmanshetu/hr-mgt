-- HR Management System - Phase 5: Leave Schema
-- This file creates the leave_types and leave_requests tables
-- Run this AFTER 01_auth_schema.sql, seed_admin.sql, 02_department_designation_schema.sql, 03_employee_schema.sql, and 04_attendance_schema.sql
-- This will run cleanly on top of the existing Phase 1 + Phase 2 + Phase 3 + Phase 4 database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create leave_types table
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `max_days_per_year` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_leave_type_name` (`name`),
  KEY `idx_leave_type_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed leave types
INSERT INTO `leave_types` (`name`, `max_days_per_year`) VALUES
('Casual Leave', 10),
('Sick Leave', 14),
('Annual Leave', 12),
('Emergency Leave', 5);

-- Create leave_requests table (depends on employees, leave_types, users)
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` INT NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT DEFAULT NULL,
  `review_remarks` TEXT DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_request_employee` (`employee_id`),
  KEY `idx_leave_request_type` (`leave_type_id`),
  KEY `idx_leave_request_status` (`status`),
  KEY `idx_leave_request_dates` (`start_date`, `end_date`),
  KEY `idx_leave_request_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_leave_request_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_request_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_request_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
