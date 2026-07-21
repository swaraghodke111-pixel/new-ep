<?php
// migrations/m2_faculty_management.php — Database migration for Milestone 2

require_once dirname(__DIR__) . '/config.php';

global $pdo;

echo "Starting database migration for Milestone 2...\n";

try {
    // 1. Faculty Profiles table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `faculty_profiles` (
        `id`            INT(11) NOT NULL AUTO_INCREMENT,
        `user_id`       INT(11) NOT NULL UNIQUE,
        `faculty_id`    VARCHAR(50) NOT NULL UNIQUE,
        `department_id` INT(11) DEFAULT NULL,
        `designation`   VARCHAR(100) NOT NULL,
        `phone`         VARCHAR(20) DEFAULT NULL,
        `office_location` VARCHAR(100) DEFAULT NULL,
        `status`        ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
        `joining_date`  DATE NOT NULL,
        `qualification` VARCHAR(255) NOT NULL,
        `bio`           TEXT DEFAULT NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
        INDEX `idx_faculty_dept` (`department_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'faculty_profiles' verified/created.\n";

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
