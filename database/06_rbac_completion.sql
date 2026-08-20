-- HR Management System - Phase 5.5: RBAC Completion Schema
-- This file completes the RBAC system by adding role_permissions junction table,
-- enhancing roles and users tables, and seeding permissions
-- Run this AFTER 01_auth_schema.sql, seed_admin.sql, 02_department_designation_schema.sql, 
-- 03_employee_schema.sql, 04_attendance_schema.sql, and 05_leave_schema.sql
-- This will run cleanly on top of the existing Phase 1 + Phase 2 + Phase 3 + Phase 4 + Phase 5 database

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Add description and is_system_role columns to roles table
ALTER TABLE `roles`
ADD COLUMN `description` VARCHAR(255) DEFAULT NULL AFTER `name`,
ADD COLUMN `is_system_role` BOOLEAN DEFAULT FALSE AFTER `description`;

-- Mark admin and employee as system roles
UPDATE `roles` SET `is_system_role` = TRUE WHERE `name` IN ('admin', 'employee');

-- Add last_login column to users table
ALTER TABLE `users`
ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL AFTER `status`;

-- Add description column to permissions table
ALTER TABLE `permissions`
ADD COLUMN `description` VARCHAR(255) DEFAULT NULL AFTER `name`;

-- Seed permissions
INSERT INTO `permissions` (`name`, `description`) VALUES
('employees.view', 'View employee records'),
('employees.create', 'Create new employee records'),
('employees.edit', 'Edit employee records'),
('employees.delete', 'Delete employee records'),
('departments.manage', 'Manage departments'),
('designations.manage', 'Manage designations'),
('attendance.view_all', 'View all attendance records'),
('attendance.manage', 'Manage attendance records'),
('leave.view_all', 'View all leave requests'),
('leave.approve', 'Approve or reject leave requests'),
('leave.manage_types', 'Manage leave types'),
('users.manage', 'Manage user accounts'),
('roles.manage', 'Manage roles and permissions'),
('payroll.manage', 'Manage payroll');

-- Create role_permissions junction table
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role_perm_role` (`role_id`),
  KEY `idx_role_perm_permission` (`permission_id`),
  CONSTRAINT `fk_role_perm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_perm_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assign all permissions to admin role
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.name = 'admin';

COMMIT;
