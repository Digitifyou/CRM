-- Set timezone
SET TIME_ZONE = '+05:30';

CREATE DATABASE IF NOT EXISTS crm_academy DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- 1. User Management
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150),
    `role` ENUM('admin', 'owner', 'counselor', 'trainer') NOT NULL DEFAULT 'counselor',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Courses (Part of Settings)
CREATE TABLE `courses` (
    `course_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_name` VARCHAR(255) NOT NULL,
    `standard_fee` DECIMAL(10, 2) NOT NULL,
    `duration` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Pipeline Stages (Part of Settings)
CREATE TABLE `pipeline_stages` (
    `stage_id` INT AUTO_INCREMENT PRIMARY KEY,
    `stage_name` VARCHAR(100) NOT NULL,
    `stage_order` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Batches (Part of Settings)
CREATE TABLE `batches` (
    `batch_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL,
    `batch_name` VARCHAR(255),
    `start_date` DATE,
    `total_seats` INT NOT NULL,
    `filled_seats` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Student & Lead Module
CREATE TABLE `students` (
    `student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE,
    `phone` VARCHAR(20) NOT NULL UNIQUE,
    `status` ENUM('inquiry', 'active_student', 'alumni') NOT NULL DEFAULT 'inquiry',
    `course_interested_id` INT,
    `lead_source` VARCHAR(100),
    `qualification` VARCHAR(100),
    `work_experience` VARCHAR(50),
    `lead_score` INT DEFAULT 0,
    `ai_summary` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_interested_id`) REFERENCES `courses`(`course_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Enrollments Module (The "Deal")
CREATE TABLE `enrollments` (
    `enrollment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `course_id` INT, -- ALLOWS NULL
    `assigned_to_user_id` INT NOT NULL,
    `pipeline_stage_id` INT NOT NULL,
    `total_fee_agreed` DECIMAL(10, 2) NOT NULL,
    `total_fee_paid` DECIMAL(10, 2) DEFAULT 0.00,
    `balance_due` DECIMAL(10, 2) GENERATED ALWAYS AS (`total_fee_agreed` - `total_fee_paid`) STORED,
    `next_follow_up_date` DATETIME,
    `status` ENUM('open', 'enrolled', 'lost') NOT NULL DEFAULT 'open',
    `lost_reason` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE SET NULL, -- THE FIX
    FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users`(`user_id`),
    FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages`(`stage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Activity Log for Students
CREATE TABLE `activity_log` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `user_id` INT,
    `activity_type` ENUM('note', 'call', 'email', 'sms', 'status_change') NOT NULL,
    `content` TEXT NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Custom Fields for Student Module (Dynamic Fields)
CREATE TABLE `custom_fields` (
    `field_id` INT AUTO_INCREMENT PRIMARY KEY,
    `field_name` VARCHAR(100) NOT NULL,
    `field_key` VARCHAR(100) NOT NULL UNIQUE, -- e.g., "expected_salary" (used in DB)
    `field_type` ENUM('text', 'select', 'number') NOT NULL DEFAULT 'text',
    `options` TEXT, -- JSON string for select options (e.g., '["Yes", "No"]')
    `is_required` BOOLEAN DEFAULT FALSE,
    `is_score_field` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SQL TO RUN AGAINST crm_academy DATABASE
CREATE TABLE `system_field_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `field_key` VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'lead_source', 'full_name'
    `display_name` VARCHAR(150) NULL,
    `is_required` BOOLEAN DEFAULT FALSE,
    `is_score_field` BOOLEAN DEFAULT FALSE,
    `scoring_rules` TEXT NULL, -- JSON string for dynamic rules
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MUST BE EXECUTED AGAINST YOUR DATABASE
-- A. Add generic column to students table (for custom field data)
ALTER TABLE `students` ADD `custom_data` TEXT NULL;

-- B. Create system configuration table (for persistent scoring rules)
CREATE TABLE IF NOT EXISTS `system_field_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `field_key` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(150) NULL,
    `is_required` BOOLEAN DEFAULT FALSE,
    `is_score_field` BOOLEAN DEFAULT FALSE,
    `scoring_rules` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing definition of integrations table
CREATE TABLE `integrations` (
    `integration_id` INT AUTO_INCREMENT PRIMARY KEY,
    `platform` ENUM('meta', 'website_form') NOT NULL UNIQUE,
    -- Renamed api_key to access_token for clarity
    `access_token` TEXT, 
    `app_secret` VARCHAR(255),
    `form_id` VARCHAR(255),
    `ad_account_id` VARCHAR(255) NULL, -- NEW FIELD for Meta Ad Account
    `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NEW TABLE: Meta Ad Form Field Mapping
CREATE TABLE `meta_field_mapping` (
    `mapping_id` INT AUTO_INCREMENT PRIMARY KEY,
    `crm_field_key` VARCHAR(100) NOT NULL, -- The CRM field (e.g., 'phone', 'course_interested_id')
    `meta_field_name` VARCHAR(100) NOT NULL, -- The Meta field (e.g., 'full_name', 'phone_number')
    `is_active` BOOLEAN DEFAULT TRUE,
    UNIQUE KEY `unique_mapping` (`crm_field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ... (rest of the database.sql content remains the same)

CREATE TABLE IF NOT EXISTS `meta_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_name` VARCHAR(255) NOT NULL,
    `access_token` TEXT NOT NULL, -- Long-lived token for accessing ad data
    `ad_account_id` VARCHAR(100), -- The main ad account ID used for billing
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `meta_ad_forms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `form_id` VARCHAR(100) NOT NULL UNIQUE, -- The ID Meta uses for the form
    `form_name` VARCHAR(255) NOT NULL,
    `ad_account_id` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `meta_field_mapping` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `meta_field_name` VARCHAR(100) NOT NULL, -- e.g., 'FULL_NAME', 'EMAIL'
    `crm_field_key` VARCHAR(100) NOT NULL, -- e.g., 'full_name', 'email', or a custom field key
    `is_built_in` BOOLEAN DEFAULT TRUE, -- TRUE for students table, FALSE for custom_data field
    UNIQUE KEY (`meta_field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


## 3. Final Step: Database Reset

Since we confirmed your `meta_accounts` table is empty, please use the following SQL command to ensure the table structure is sound and ready for the new API endpoint to insert data:


-- Ensure the meta_accounts table exists and is clean
CREATE TABLE IF NOT EXISTS `meta_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_name` VARCHAR(255) NOT NULL,
    `access_token` TEXT NOT NULL, 
    `ad_account_id` VARCHAR(100), 
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Add the necessary Foreign Key if it was missing in your setup
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TRUNCATE the table to ensure a clean start for the new JS logic
TRUNCATE TABLE `meta_accounts`;

-- Insert default admin user (Password: "admin123")
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`) 
VALUES ('admin', '$2y$10$E.qJ4s5.XG9iulv.w8D2KuBKY64K0y4f6.0fGq.h/E270xflhRDia', 'Admin User', 'admin');

-- Insert default pipeline stages
INSERT INTO `pipeline_stages` (`stage_name`, `stage_order`) VALUES
('New Inquiry', 1),
('Contacted', 2),
('Counseled', 3),
('Demo Attended', 4),
('Payment Pending', 5),
('Enrolled', 6);