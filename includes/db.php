<?php
/**
 * Database Connection & Auto-Initialization Provider
 */
require_once __DIR__ . '/config.php';

try {
    // 1. Establish initial connection to MySQL (without selecting DB first to handle auto-creation)
    $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // 2. Auto-Create database if not exists
    $dbName = "`" . str_replace("`", "``", DB_NAME) . "`";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $dbName");
    
    // 3. Check if tables exist. If 'users' table doesn't exist, import schema from database.sql
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($tableCheck->rowCount() == 0) {
        $sqlPath = BASE_PATH . '/database.sql';
        if (file_exists($sqlPath)) {
            $sqlContent = file_get_contents($sqlPath);
            
            // Remove comments and split SQL queries by semicolon
            // Simple split regex that avoids splitting on semicolons inside strings
            $queries = preg_split("/;+(?=(?:[^'\"]*['\"][^'\"]*['\"])*[^'\"]*$)/", $sqlContent);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    $pdo->exec($query);
                }
            }
        } else {
            throw new Exception("Database schema file 'database.sql' not found in project root. Cannot auto-initialize tables.");
        }
    } else {
        // Database tables exist. Let's make sure the 'events' table has the 'description' and 'banner_image' columns.
        $eventsTableCheck = $pdo->query("SHOW TABLES LIKE 'events'");
        if ($eventsTableCheck->rowCount() > 0) {
            $columns = $pdo->query("SHOW COLUMNS FROM `events` LIKE 'description'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `description` TEXT NULL AFTER `name`");
            }
            $columns_banner = $pdo->query("SHOW COLUMNS FROM `events` LIKE 'banner_image'")->fetchAll();
            if (empty($columns_banner)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `banner_image` VARCHAR(255) NULL AFTER `description`");
            }
        }
        
        // Auto-migrate students table to include 'course' column
        $studentsTableCheck = $pdo->query("SHOW TABLES LIKE 'students'");
        if ($studentsTableCheck->rowCount() > 0) {
            $columns = $pdo->query("SHOW COLUMNS FROM `students` LIKE 'course'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE `students` ADD COLUMN `course` ENUM('BCA', 'MCA') NOT NULL DEFAULT 'BCA' AFTER `name`");
            }
        }

        // Auto-migrate event_registrations table to include role columns
        $regTableCheck = $pdo->query("SHOW TABLES LIKE 'event_registrations'");
        if ($regTableCheck->rowCount() > 0) {
            $columns = $pdo->query("SHOW COLUMNS FROM `event_registrations` LIKE 'assigned_role'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE `event_registrations` 
                    ADD COLUMN `assigned_role` ENUM('participant', 'volunteers', 'OC', 'CC') NOT NULL DEFAULT 'participant' AFTER `payment_method`,
                    ADD COLUMN `applied_role` ENUM('participant', 'volunteers', 'OC', 'CC') NULL AFTER `assigned_role`,
                    ADD COLUMN `role_status` ENUM('none', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'none' AFTER `applied_role`");
            }
        }

        // Auto-migrate events table to include certificate_template column
        $eventsTableCheck = $pdo->query("SHOW TABLES LIKE 'events'");
        if ($eventsTableCheck->rowCount() > 0) {
            $columns = $pdo->query("SHOW COLUMNS FROM `events` LIKE 'certificate_template'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `certificate_template` VARCHAR(255) NULL AFTER `food_enabled`");
            }
            $columns_type = $pdo->query("SHOW COLUMNS FROM `events` LIKE 'certificate_template_type'")->fetchAll();
            if (empty($columns_type)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `certificate_template_type` ENUM('border_only', 'full_design') NOT NULL DEFAULT 'border_only' AFTER `certificate_template`");
            }
            $columns_theme = $pdo->query("SHOW COLUMNS FROM `events` LIKE 'certificate_theme'")->fetchAll();
            if (empty($columns_theme)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `certificate_theme` VARCHAR(50) NOT NULL DEFAULT 'classic_navy' AFTER `certificate_template_type`,
                            ADD COLUMN `certificate_title` VARCHAR(255) NOT NULL DEFAULT 'Certificate of Activity' AFTER `certificate_theme`,
                            ADD COLUMN `certificate_coordinator` VARCHAR(255) NOT NULL DEFAULT 'Event Coordinator' AFTER `certificate_title`,
                            ADD COLUMN `certificate_hod` VARCHAR(255) NOT NULL DEFAULT 'Head of Department' AFTER `certificate_coordinator`");
            }
            $columns_layout = $pdo->query("SHOW COLUMNS FROM `events` LIKE 'certificate_layout_config'")->fetchAll();
            if (empty($columns_layout)) {
                $pdo->exec("ALTER TABLE `events` ADD COLUMN `certificate_layout_config` TEXT NULL AFTER `certificate_hod`,
                            ADD COLUMN `canva_template_link` VARCHAR(512) NULL AFTER `certificate_layout_config`");
            }
        }

        // Auto-migrate certificates table
        $certificatesTableCheck = $pdo->query("SHOW TABLES LIKE 'certificates'");
        if ($certificatesTableCheck->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `certificates` (
                  `id` INT AUTO_INCREMENT PRIMARY KEY,
                  `registration_id` INT NOT NULL UNIQUE,
                  `certificate_code` VARCHAR(50) NOT NULL UNIQUE,
                  `issued_by` INT NULL,
                  `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  CONSTRAINT `fk_certificates_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `fk_certificates_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB
            ");
        }

        // Auto-migrate settings table
        $settingsTableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($settingsTableCheck->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `settings` (
                  `setting_key` VARCHAR(255) PRIMARY KEY,
                  `setting_value` TEXT NOT NULL
                ) ENGINE=InnoDB
            ");
        }
    }
    
    // Keep $conn variable available as a global connection instance
    $conn = $pdo;

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Application Setup Error: " . $e->getMessage());
}
