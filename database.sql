SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 0. Database Creation
CREATE DATABASE IF NOT EXISTS `crm_academy` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `crm_academy`;

-- ==========================================
-- 1. User & Setup Tables
-- ==========================================

CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150),
    `role` ENUM('admin', 'owner', 'counselor', 'trainer') NOT NULL DEFAULT 'counselor',
    `is_active` BOOLEAN DEFAULT TRUE,
    `academy_id` INT NOT NULL DEFAULT 0, -- Multi-tenancy ID
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `courses` (
    `course_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_name` VARCHAR(255) NOT NULL,
    `standard_fee` DECIMAL(10, 2) NOT NULL,
    `duration` VARCHAR(100),
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `pipeline_stages` (
    `stage_id` INT AUTO_INCREMENT PRIMARY KEY,
    `stage_name` VARCHAR(100) NOT NULL,
    `stage_order` INT NOT NULL,
    `academy_id` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `batches` (
    `batch_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `batch_name` VARCHAR(255),
    `start_date` DATE,
    `total_seats` INT NOT NULL,
    `filled_seats` INT NOT NULL DEFAULT 0,
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. Student & Lead Management
-- ==========================================

CREATE TABLE `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255), -- Not unique globally anymore
    `phone` VARCHAR(20) NOT NULL, -- Not unique globally anymore
    `status` ENUM('inquiry', 'active_student', 'alumni') NOT NULL DEFAULT 'inquiry',
    `course_interested_id` INT,
    `lead_source` VARCHAR(100),
    `qualification` VARCHAR(100),
    `work_experience` VARCHAR(50),
    `lead_score` INT DEFAULT 0,
    `ai_summary` TEXT,
    `custom_data` TEXT, -- Stores dynamic field data as JSON
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_interested_id`) REFERENCES `courses`(`course_id`) ON DELETE SET NULL,
    -- Multi-tenancy constraints: Email/Phone unique only within a specific academy
    UNIQUE KEY `unique_academy_phone` (`academy_id`, `phone`),
    UNIQUE KEY `unique_academy_email` (`academy_id`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `enrollments` (
    `enrollment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `course_id` INT,
    `assigned_to_user_id` INT NOT NULL,
    `pipeline_stage_id` INT NOT NULL,
    `total_fee_agreed` DECIMAL(10, 2) NOT NULL,
    `total_fee_paid` DECIMAL(10, 2) DEFAULT 0.00,
    `balance_due` DECIMAL(10, 2) GENERATED ALWAYS AS (`total_fee_agreed` - `total_fee_paid`) STORED,
    `next_follow_up_date` DATETIME,
    `status` ENUM('open', 'enrolled', 'lost') NOT NULL DEFAULT 'open',
    `lost_reason` TEXT,
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users`(`user_id`),
    FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages`(`stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `activity_log` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `user_id` INT,
    `activity_type` ENUM('note', 'call', 'email', 'sms', 'status_change') NOT NULL,
    `content` TEXT NOT NULL,
    `academy_id` INT NOT NULL DEFAULT 0,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. Configuration & Customization
-- ==========================================

CREATE TABLE `custom_fields` (
    `field_id` INT AUTO_INCREMENT PRIMARY KEY,
    `field_name` VARCHAR(100) NOT NULL,
    `field_key` VARCHAR(100) NOT NULL, -- e.g., "expected_salary"
    `field_type` ENUM('text', 'select', 'number') NOT NULL DEFAULT 'text',
    `options` TEXT, -- JSON string for select options
    `is_required` BOOLEAN DEFAULT FALSE,
    `is_score_field` BOOLEAN DEFAULT FALSE,
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_academy_field` (`academy_id`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `system_field_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `field_key` VARCHAR(50) NOT NULL,
    `display_name` VARCHAR(150) NULL,
    `is_required` BOOLEAN DEFAULT FALSE,
    `is_score_field` BOOLEAN DEFAULT FALSE,
    `scoring_rules` TEXT NULL,
    `academy_id` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_academy_sys_field` (`academy_id`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. Integrations (Meta/Facebook)
-- ==========================================

CREATE TABLE `integrations` (
    `integration_id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` ENUM('meta', 'website_form') NOT NULL,
    `access_token` TEXT, 
    `app_secret` VARCHAR(255),
    `form_id` VARCHAR(255),
    `ad_account_id` VARCHAR(255) NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `academy_id` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `unique_academy_platform` (`academy_id`, `platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `meta_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_name` VARCHAR(255) NOT NULL,
    `access_token` TEXT NOT NULL,
    `ad_account_id` VARCHAR(100),
    `is_active` BOOLEAN DEFAULT TRUE,
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `meta_ad_forms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `form_id` VARCHAR(100) NOT NULL,
    `form_name` VARCHAR(255) NOT NULL,
    `ad_account_id` VARCHAR(100) NOT NULL,
    `academy_id` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_academy_form` (`academy_id`, `form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `meta_field_mapping` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meta_field_name` VARCHAR(100) NOT NULL, -- e.g., 'FULL_NAME'
    `crm_field_key` VARCHAR(100) NOT NULL,   -- e.g., 'full_name'
    `is_built_in` BOOLEAN DEFAULT TRUE,
    `academy_id` INT NOT NULL DEFAULT 0,
    UNIQUE KEY `unique_academy_mapping` (`academy_id`, `meta_field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. Seed Data (Default Admin & Stages)
-- ==========================================

-- Insert default admin user (Password: "admin123" hashed with bcrypt)
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `academy_id`) 
VALUES ('admin', '$2y$10$E.qJ4s5.XG9iulv.w8D2KuBKY64K0y4f6.0fGq.h/E270xflhRDia', 'Admin User', 'admin', 0);

-- Insert default pipeline stages for Academy 0 (System Default)
INSERT INTO `pipeline_stages` (`stage_name`, `stage_order`, `academy_id`) VALUES
('New Inquiry', 1, 0),
('Contacted', 2, 0),
('Counseled', 3, 0),
('Demo Attended', 4, 0),
('Payment Pending', 5, 0),
('Enrolled', 6, 0);

COMMIT;