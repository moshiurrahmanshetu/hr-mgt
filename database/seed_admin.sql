-- HR Management System - Phase 1: Seed Admin User
-- This file inserts the default admin user and roles
-- Run this AFTER 01_auth_schema.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Insert roles
INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'admin'),
(2, 'employee')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default admin user
-- Email: admin@hrsystem.com
-- Password: Admin@123 (hashed with bcrypt)
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password`, `avatar`, `status`) VALUES
(1, 1, 'System Administrator', 'admin@hrsystem.com', '$2y$10$cGBimGlw/NHQu30txhWTr.Wd1UX3KISxBCxLZBqNVcNo7ub.0uSQC', NULL, 'active')
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `email` = VALUES(`email`),
  `password` = VALUES(`password`),
  `status` = VALUES(`status`);

COMMIT;
