<?php
// migrations/m1_academic_foundation.php — Database migration for Milestone 1

require_once dirname(__DIR__) . '/config.php';

global $pdo;

echo "Starting database migration for Milestone 1...\n";

try {
    // 1. Departments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `departments` (
        `id`          INT(11) NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(100) NOT NULL UNIQUE,
        `code`        VARCHAR(20) NOT NULL UNIQUE,
        `description` TEXT DEFAULT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'departments' verified/created.\n";

    // 2. Programs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `programs` (
        `id`            INT(11) NOT NULL AUTO_INCREMENT,
        `department_id` INT(11) NOT NULL,
        `name`          VARCHAR(150) NOT NULL,
        `code`          VARCHAR(20) NOT NULL UNIQUE,
        `description`   TEXT DEFAULT NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
        INDEX `idx_program_dept` (`department_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'programs' verified/created.\n";

    // 3. Academic Years table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `academic_years` (
        `id`         INT(11) NOT NULL AUTO_INCREMENT,
        `name`       VARCHAR(50) NOT NULL UNIQUE,
        `start_date` DATE NOT NULL,
        `end_date`   DATE NOT NULL,
        `status`     ENUM('active','inactive') DEFAULT 'active',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'academic_years' verified/created.\n";

    // 4. Semesters table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `semesters` (
        `id`               INT(11) NOT NULL AUTO_INCREMENT,
        `academic_year_id` INT(11) NOT NULL,
        `name`             VARCHAR(50) NOT NULL,
        `start_date`       DATE DEFAULT NULL,
        `end_date`         DATE DEFAULT NULL,
        `status`           ENUM('active','inactive') DEFAULT 'active',
        `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE CASCADE,
        INDEX `idx_semester_ay` (`academic_year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'semesters' verified/created.\n";

    // 5. Sections table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sections` (
        `id`          INT(11) NOT NULL AUTO_INCREMENT,
        `program_id`  INT(11) NOT NULL,
        `semester_id` INT(11) NOT NULL,
        `name`        VARCHAR(50) NOT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`program_id`)  REFERENCES `programs`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_section` (`program_id`, `semester_id`, `name`),
        INDEX `idx_section_program` (`program_id`),
        INDEX `idx_section_semester` (`semester_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'sections' verified/created.\n";

    // 6. Subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
        `id`            INT(11) NOT NULL AUTO_INCREMENT,
        `department_id` INT(11) NOT NULL,
        `name`          VARCHAR(150) NOT NULL,
        `code`          VARCHAR(20) NOT NULL UNIQUE,
        `description`   TEXT DEFAULT NULL,
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
        INDEX `idx_subject_dept` (`department_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✔ Table 'subjects' verified/created.\n";

    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
