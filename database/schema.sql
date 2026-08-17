-- ==========================================================
-- PRODUCTION-READY DATABASE BLUEPRINT: HRM ENTERPRISE SYSTEM
-- Developed by Senior PHP Software Architect
-- 
-- Platform: MySQL 8.0+
-- Storage Engine: InnoDB
-- Charset: utf8mb4
-- Collation: utf8mb4_unicode_ci
-- ==========================================================

-- Disable constraints temporarily to safely purge existing tables
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ----------------------------------------------------------
-- PURGE EXISTING SCHEMAS (Correct Dependency Tear-down)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `interviews`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `recruitment_jobs`;
DROP TABLE IF EXISTS `payroll`;
DROP TABLE IF EXISTS `salary_structures`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `leave_types`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `employee_documents`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `designations`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;

-- ----------------------------------------------------------
-- 1. TABLE: ROLES
-- Defines platform security groups / authorization bands
-- ----------------------------------------------------------
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. TABLE: PERMISSIONS
-- Granular access tokens assigned to system actions
-- ----------------------------------------------------------
CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_permission_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. TABLE: ROLE_PERMISSIONS
-- Composite mapping table connecting roles and granular permissions
-- ----------------------------------------------------------
CREATE TABLE `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_rp_role` (`role_id`),
    INDEX `idx_rp_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. TABLE: USERS
-- System accounts with login credentials and roles
-- ----------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `role_id` INT UNSIGNED NULL DEFAULT NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('Active', 'Suspended', 'Pending') NOT NULL DEFAULT 'Active',
    `remember_token` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    INDEX `idx_users_role` (`role_id`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 5. TABLE: BRANCHES
-- Operational physical offices or geographic sites
-- ----------------------------------------------------------
CREATE TABLE `branches` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `address` TEXT NULL DEFAULT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `email` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_branches_name` (`name`),
    UNIQUE KEY `uk_branches_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. TABLE: DEPARTMENTS
-- Inner division groups within specific corporate branches
-- ----------------------------------------------------------
CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `branch_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `manager_id` INT UNSIGNED NULL DEFAULT NULL, -- Resolved via dynamic circular foreign-key later
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_departments_code` (`code`),
    UNIQUE KEY `uk_branch_dept_name` (`branch_id`, `name`),
    INDEX `idx_departments_branch` (`branch_id`),
    INDEX `idx_departments_manager` (`manager_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. TABLE: DESIGNATIONS
-- Specific corporate titles or professional ranks
-- ----------------------------------------------------------
CREATE TABLE `designations` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `department_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_dept_designation` (`department_id`, `title`),
    INDEX `idx_designations_dept` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. TABLE: EMPLOYEES
-- Master biographical and system profiles for staff members
-- ----------------------------------------------------------
CREATE TABLE `employees` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `branch_id` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `designation_id` INT UNSIGNED NOT NULL,
    `employee_code` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `hire_date` DATE NOT NULL,
    `employment_status` ENUM('Full-Time', 'Part-Time', 'Contract', 'Intern', 'Terminated') NOT NULL DEFAULT 'Full-Time',
    `salary` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `gender` ENUM('Male', 'Female', 'Other', 'Prefer Not to Say') NOT NULL DEFAULT 'Prefer Not to Say',
    `date_of_birth` DATE NULL DEFAULT NULL,
    `address` TEXT NULL DEFAULT NULL,
    `emergency_contact_name` VARCHAR(100) NULL DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employees_user` (`user_id`),
    UNIQUE KEY `uk_employees_code` (`employee_code`),
    UNIQUE KEY `uk_employees_email` (`email`),
    INDEX `idx_employees_branch` (`branch_id`),
    INDEX `idx_employees_dept` (`department_id`),
    INDEX `idx_employees_desg` (`designation_id`),
    INDEX `idx_employees_status` (`employment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. TABLE: EMPLOYEE_DOCUMENTS
-- Official records, certifications, passport and contract attachments
-- ----------------------------------------------------------
CREATE TABLE `employee_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `document_name` VARCHAR(150) NOT NULL,
    `document_type` VARCHAR(50) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `status` ENUM('Pending', 'Verified', 'Expired', 'Rejected') NOT NULL DEFAULT 'Pending',
    `expiry_date` DATE NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_emp_docs_employee` (`employee_id`),
    INDEX `idx_emp_docs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 10. TABLE: ATTENDANCE
-- Logs check-in/out timestamps, delays, and tracking statuses
-- ----------------------------------------------------------
CREATE TABLE `attendance` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `clock_in` TIME NULL DEFAULT NULL,
    `clock_out` TIME NULL DEFAULT NULL,
    `status` ENUM('Present', 'Late', 'Half Day', 'Absent', 'On Leave') NOT NULL DEFAULT 'Present',
    `notes` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_date` (`employee_id`, `date`),
    INDEX `idx_attendance_employee` (`employee_id`),
    INDEX `idx_attendance_date` (`date`),
    INDEX `idx_attendance_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 11. TABLE: LEAVE_TYPES
-- Permissible types of leave structures and allotments
-- ----------------------------------------------------------
CREATE TABLE `leave_types` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `days_allotted` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_leave_type_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 12. TABLE: LEAVE_REQUESTS
-- Leave logging pipeline and approval audit trails
-- ----------------------------------------------------------
CREATE TABLE `leave_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    `approved_by` INT UNSIGNED NULL DEFAULT NULL,
    `rejection_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_leaves_employee` (`employee_id`),
    INDEX `idx_leaves_type` (`leave_type_id`),
    INDEX `idx_leaves_dates` (`start_date`, `end_date`),
    INDEX `idx_leaves_status` (`status`),
    INDEX `idx_leaves_approver` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 13. TABLE: SALARY_STRUCTURES
-- Configures comprehensive basic, allowance, and tax bands per employee
-- ----------------------------------------------------------
CREATE TABLE `salary_structures` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `basic_salary` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `house_rent_allowance` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `medical_allowance` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `conveyance_allowance` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `other_allowances` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `provident_fund` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `professional_tax` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `other_deductions` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_salary_employee` (`employee_id`),
    INDEX `idx_salary_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 14. TABLE: PAYROLL
-- Ledger slips capturing exact period salaries and payout details
-- ----------------------------------------------------------
CREATE TABLE `payroll` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `pay_period_start` DATE NOT NULL,
    `pay_period_end` DATE NOT NULL,
    `basic_salary` DECIMAL(12, 2) NOT NULL,
    `allowances` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `deductions` DECIMAL(12, 2) NOT NULL DEFAULT '0.00',
    `net_salary` DECIMAL(12, 2) GENERATED ALWAYS AS ((`basic_salary` + `allowances`) - `deductions`) STORED,
    `status` ENUM('Draft', 'Approved', 'Paid', 'On Hold') NOT NULL DEFAULT 'Draft',
    `payment_date` DATE NULL DEFAULT NULL,
    `payment_method` ENUM('Bank Transfer', 'Cheque', 'Cash') NOT NULL DEFAULT 'Bank Transfer',
    `transaction_reference` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_payroll_employee` (`employee_id`),
    INDEX `idx_payroll_period` (`pay_period_start`, `pay_period_end`),
    INDEX `idx_payroll_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 15. TABLE: RECRUITMENT_JOBS
-- Tracks open vacancies, job requirements, and status
-- ----------------------------------------------------------
CREATE TABLE `recruitment_jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `department_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `requirements` TEXT NULL DEFAULT NULL,
    `location` VARCHAR(100) NULL DEFAULT NULL,
    `job_type` ENUM('Full-Time', 'Part-Time', 'Contract', 'Intern') NOT NULL DEFAULT 'Full-Time',
    `status` ENUM('Draft', 'Open', 'Closed', 'Paused') NOT NULL DEFAULT 'Draft',
    `closing_date` DATE NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_jobs_dept` (`department_id`),
    INDEX `idx_jobs_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 16. TABLE: CANDIDATES
-- Recruitment application forms submitted by applicants
-- ----------------------------------------------------------
CREATE TABLE `candidates` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `job_id` INT UNSIGNED NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `resume_path` VARCHAR(255) NOT NULL,
    `cover_letter` TEXT NULL DEFAULT NULL,
    `status` ENUM('Applied', 'Shortlisted', 'Interviewing', 'Offered', 'Rejected', 'Withdrawn') NOT NULL DEFAULT 'Applied',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_candidates_job` (`job_id`),
    INDEX `idx_candidates_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 17. TABLE: INTERVIEWS
-- Interview logs, ratings, meeting codes, and panels
-- ----------------------------------------------------------
CREATE TABLE `interviews` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `candidate_id` INT UNSIGNED NOT NULL,
    `interviewer_id` INT UNSIGNED NOT NULL,
    `interview_date` DATETIME NOT NULL,
    `type` ENUM('Phone Screening', 'Technical', 'HR Interview', 'Managerial', 'Final Round') NOT NULL DEFAULT 'Technical',
    `location_or_link` VARCHAR(255) NULL DEFAULT NULL,
    `feedback` TEXT NULL DEFAULT NULL,
    `rating` TINYINT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('Scheduled', 'Completed', 'Cancelled', 'No Show') NOT NULL DEFAULT 'Scheduled',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_interviews_candidate` (`candidate_id`),
    INDEX `idx_interviews_interviewer` (`interviewer_id`),
    INDEX `idx_interviews_date` (`interview_date`),
    INDEX `idx_interviews_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 18. TABLE: PROJECTS
-- High-level operational projects tracking client work
-- ----------------------------------------------------------
CREATE TABLE `projects` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    `status` ENUM('Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Not Started',
    `budget` DECIMAL(15, 2) NOT NULL DEFAULT '0.00',
    `client_name` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_projects_name` (`name`),
    INDEX `idx_projects_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 19. TABLE: TASKS
-- Core functional task tracking linked to specific projects
-- ----------------------------------------------------------
CREATE TABLE `tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `project_id` INT UNSIGNED NULL DEFAULT NULL,
    `assigned_to` INT UNSIGNED NULL DEFAULT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Urgent') NOT NULL DEFAULT 'Medium',
    `status` ENUM('To Do', 'In Progress', 'In Review', 'Completed', 'Blocked') NOT NULL DEFAULT 'To Do',
    `due_date` DATE NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_tasks_project` (`project_id`),
    INDEX `idx_tasks_assigned` (`assigned_to`),
    INDEX `idx_tasks_status` (`status`),
    INDEX `idx_tasks_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 20. TABLE: ANNOUNCEMENTS
-- Broad organizational newsletters, pinned announcements and alerts
-- ----------------------------------------------------------
CREATE TABLE `announcements` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `title` VARCHAR(150) NOT NULL,
    `content` TEXT NOT NULL,
    `target_audience` ENUM('All', 'Departmental', 'Branch-Specific', 'Management') NOT NULL DEFAULT 'All',
    `target_id` INT UNSIGNED NULL DEFAULT NULL,
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
    `expiry_date` DATE NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_announcements_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 21. TABLE: NOTIFICATIONS
-- Direct, user-specific in-app messaging alerts
-- ----------------------------------------------------------
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'Info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notifications_user_unread` (`user_id`, `is_read`),
    INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 22. TABLE: SETTINGS
-- Global organization-wide configuration profiles
-- ----------------------------------------------------------
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `key_name` VARCHAR(100) NOT NULL,
    `value_data` TEXT NULL DEFAULT NULL,
    `group_name` VARCHAR(50) NOT NULL DEFAULT 'General',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`key_name`),
    INDEX `idx_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 23. TABLE: ACTIVITY_LOGS
-- Complete system-wide operational auditing logs (Security)
-- ----------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `table_name` VARCHAR(100) NULL DEFAULT NULL,
    `record_id` INT UNSIGNED NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `payload` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_activity_user` (`user_id`),
    INDEX `idx_activity_action` (`action`),
    INDEX `idx_activity_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================================
-- DYNAMIC LINKING OF FOREIGN-KEYS (Using ALTER TABLE)
-- Prevents circular dependency order failures during CREATE
-- ==========================================================

-- 1. ROLE PERMISSIONS
ALTER TABLE `role_permissions`
    ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 2. USERS
ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- 3. DEPARTMENTS
ALTER TABLE `departments`
    ADD CONSTRAINT `fk_departments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_departments_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 4. DESIGNATIONS
ALTER TABLE `designations`
    ADD CONSTRAINT `fk_designations_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 5. EMPLOYEES
ALTER TABLE `employees`
    ADD CONSTRAINT `fk_employees_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_employees_branches` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_employees_departments` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_employees_designations` FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- 6. EMPLOYEE DOCUMENTS
ALTER TABLE `employee_documents`
    ADD CONSTRAINT `fk_emp_docs_employees` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 7. ATTENDANCE
ALTER TABLE `attendance`
    ADD CONSTRAINT `fk_attendance_employees` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 8. LEAVE REQUESTS
ALTER TABLE `leave_requests`
    ADD CONSTRAINT `fk_leave_employees` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 9. SALARY STRUCTURES
ALTER TABLE `salary_structures`
    ADD CONSTRAINT `fk_salary_structures_employees` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 10. PAYROLL
ALTER TABLE `payroll`
    ADD CONSTRAINT `fk_payroll_employees` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 11. RECRUITMENT JOBS
ALTER TABLE `recruitment_jobs`
    ADD CONSTRAINT `fk_recruitment_jobs_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 12. CANDIDATES
ALTER TABLE `candidates`
    ADD CONSTRAINT `fk_candidates_job` FOREIGN KEY (`job_id`) REFERENCES `recruitment_jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 13. INTERVIEWS
ALTER TABLE `interviews`
    ADD CONSTRAINT `fk_interviews_candidate` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_interviews_interviewer` FOREIGN KEY (`interviewer_id`) REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- 14. TASKS
ALTER TABLE `tasks`
    ADD CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_tasks_employee` FOREIGN KEY (`assigned_to`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 15. NOTIFICATIONS
ALTER TABLE `notifications`
    ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- 16. ACTIVITY LOGS
ALTER TABLE `activity_logs`
    ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;


-- ==========================================================
-- SEED INITIAL ENTERPRISE CONFIGURATION
-- ==========================================================

-- Seed Roles
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', 'Super Administrator with unrestricted access across all enterprise modules'),
(2, 'HR Manager', 'Human Resource executive responsible for recruitment, leave approval and payroll management'),
(3, 'Line Manager', 'Team lead responsible for assigning tasks and reviewing project outputs'),
(4, 'Employee', 'Standard employee profile with access to attendance logging and leave requests');

-- Seed System Permissions
INSERT INTO `permissions` (`id`, `name`, `description`) VALUES
(1, 'manage_users', 'Create, update and suspend portal access credentials'),
(2, 'manage_employees', 'Complete control over employee biographical and document profiles'),
(3, 'manage_payroll', 'Access to salary structures, calculating payout periods, and approving payroll ledgers'),
(4, 'approve_leaves', 'Authority to approve or reject submitted leave forms'),
(5, 'view_logs', 'Access to read system activity audit tracks');

-- Map Role-Permissions for Admin
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5);

-- Seed Default Security Administrator
-- Plain-text default secure password: 'Admin@HRM2026!'
-- Generated using PHP password_hash() with PASSWORD_BCRYPT algorithm
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password_hash`, `status`) VALUES
(1, 1, 'admin', 'admin@hrmsystem.com', '$2y$10$gT2q8yvKbyaOn/Fj781V1.S1l0qRre0yXbV09VpInF0i6XG9bfeB6', 'Active');

-- Seed Basic Global Settings
INSERT INTO `settings` (`key_name`, `value_data`, `group_name`) VALUES
('company_name', 'Enterprise HRM Solutions Ltd.', 'Company'),
('company_email', 'hr@enterprisehrmsolutions.com', 'Company'),
('timezone', 'UTC', 'General'),
('currency', 'USD', 'General'),
('password_complexity', '{"min_length":8,"require_digits":true,"require_symbols":true}', 'Security'),
('session_timeout', '1800', 'Security');

-- Re-enable constraints
SET FOREIGN_KEY_CHECKS = 1;
