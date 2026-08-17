<?php
/**
 * Core PHP HRM Database Connection Provider
 * Developed by Senior PHP Software Architect
 * 
 * This class implements a secure, lazy-loading PDO database connection
 * using the Singleton-like pattern. It reads credentials from /config/config.php
 * and sets robust security and retrieval attributes on the database link.
 */

// Include config if not already loaded (useful for direct model execution)
require_once __DIR__ . '/../config/config.php';

class Database {
    private static ?PDO $connection = null;

    /**
     * Retrieves the single active PDO connection instance.
     * Lazy-loads the connection if it does not yet exist.
     * 
     * @return PDO
     * @throws Exception
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            try {
                // Build the Data Source Name (DSN) string
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );

                // Define highly secure and functional PDO configuration options
                $options = [
                    // Throw strict exceptions on query and connection errors
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    
                    // Fetch results as associative arrays by default
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    
                    // Disable emulation of prepared statements to force true, native MySQL binary prepared statements.
                    // This is a CRITICAL security standard to guarantee immunity against SQL Injection.
                    PDO::ATTR_EMULATE_PREPARES => false,
                    
                    // Enable persistent connection pooling for high throughput (optional, default false is safer in shared environments)
                    PDO::ATTR_PERSISTENT => false
                ];

                // Instantiate connection
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // Automatically run schema migration check
                self::checkAndMigrateSchema(self::$connection);
                
            } catch (PDOException $e) {
                // Log detailed error internally (in production, log to a secure file)
                if (APP_ENV === 'development') {
                    throw new Exception("Database Connection Failure: " . $e->getMessage(), (int)$e->getCode());
                } else {
                    // Friendly generic message for production to hide infrastructure internals (security requirement)
                    die("System Error: Unable to establish a database session. Please contact the system administrator.");
                }
            }
        }

        return self::$connection;
    }

    /**
     * Dynamically verifies and ensures that requested columns (status, deleted_at, salary_grade, etc.) exist.
     * Keeps database schema in sync automatically.
     */
    private static function checkAndMigrateSchema(PDO $db): void {
        try {
            // Check & alter branches
            if (!self::columnExists($db, 'branches', 'status')) {
                $db->exec("ALTER TABLE `branches` ADD COLUMN `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'");
            }
            if (!self::columnExists($db, 'branches', 'deleted_at')) {
                $db->exec("ALTER TABLE `branches` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL");
            }
            if (!self::columnExists($db, 'branches', 'manager_id')) {
                $db->exec("ALTER TABLE `branches` ADD COLUMN `manager_id` INT UNSIGNED NULL DEFAULT NULL");
                try {
                    $db->exec("ALTER TABLE `branches` ADD CONSTRAINT `fk_branches_manager` FOREIGN KEY (`manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (Exception $e) {
                    // Ignore if constraint already exists or fails
                }
            }

            // Check & alter departments
            if (!self::columnExists($db, 'departments', 'status')) {
                $db->exec("ALTER TABLE `departments` ADD COLUMN `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'");
            }
            if (!self::columnExists($db, 'departments', 'deleted_at')) {
                $db->exec("ALTER TABLE `departments` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL");
            }

            // Check & alter designations
            if (!self::columnExists($db, 'designations', 'status')) {
                $db->exec("ALTER TABLE `designations` ADD COLUMN `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'");
            }
            if (!self::columnExists($db, 'designations', 'deleted_at')) {
                $db->exec("ALTER TABLE `designations` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL");
            }
            if (!self::columnExists($db, 'designations', 'salary_grade')) {
                $db->exec("ALTER TABLE `designations` ADD COLUMN `salary_grade` VARCHAR(50) NULL DEFAULT NULL");
            }

            // Check & alter roles
            if (!self::columnExists($db, 'roles', 'status')) {
                $db->exec("ALTER TABLE `roles` ADD COLUMN `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'");
            }
            if (!self::columnExists($db, 'roles', 'deleted_at')) {
                $db->exec("ALTER TABLE `roles` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL");
            }

            // Check & alter employees table to support the required features dynamically
            if (!self::columnExists($db, 'employees', 'blood_group')) {
                $db->exec("ALTER TABLE `employees` ADD COLUMN `blood_group` VARCHAR(10) NULL DEFAULT NULL");
            }
            if (!self::columnExists($db, 'employees', 'marital_status')) {
                $db->exec("ALTER TABLE `employees` ADD COLUMN `marital_status` VARCHAR(20) NULL DEFAULT NULL");
            }
            if (!self::columnExists($db, 'employees', 'role_id')) {
                $db->exec("ALTER TABLE `employees` ADD COLUMN `role_id` INT UNSIGNED NULL DEFAULT NULL");
                try {
                    $db->exec("ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (Exception $e) {}
            }
            if (!self::columnExists($db, 'employees', 'reporting_manager_id')) {
                $db->exec("ALTER TABLE `employees` ADD COLUMN `reporting_manager_id` INT UNSIGNED NULL DEFAULT NULL");
                try {
                    $db->exec("ALTER TABLE `employees` ADD CONSTRAINT `fk_employees_manager` FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
                } catch (Exception $e) {}
            }
            if (!self::columnExists($db, 'employees', 'emergency_contact_relationship')) {
                $db->exec("ALTER TABLE `employees` ADD COLUMN `emergency_contact_relationship` VARCHAR(50) NULL DEFAULT NULL");
            }
        } catch (Exception $e) {
            // Fail-safe, do not crash application
        }
    }

    /**
     * Check if a specific column exists in a database table.
     */
    private static function columnExists(PDO $db, string $table, string $column): bool {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $stmt && $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Prevents external cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevents external instantiation.
     */
    private function __construct() {}
}
