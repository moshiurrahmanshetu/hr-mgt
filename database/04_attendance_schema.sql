-- HR Management System - Phase 4: Attendance Schema
-- This file creates the attendance table
-- Run this AFTER 01_auth_schema.sql, seed_admin.sql, 02_department_designation_schema.sql, and 03_employee_schema.sql
-- This will run cleanly on top of the existing Phase 1 + Phase 2 + Phase 3 database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create attendance table (depends on employees)
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `check_in` TIME DEFAULT NULL,
  `check_out` TIME DEFAULT NULL,
  `status` ENUM('present', 'absent', 'late', 'leave') NOT NULL DEFAULT 'present',
  `remarks` VARCHAR(255) DEFAULT NULL,
  `marked_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_attendance_employee_date` (`employee_id`, `date`),
  KEY `idx_attendance_employee` (`employee_id`),
  KEY `idx_attendance_date` (`date`),
  KEY `idx_attendance_status` (`status`),
  KEY `idx_attendance_marked_by` (`marked_by`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
