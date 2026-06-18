-- Database Schema for Department Event Attendance Management System
-- Database: dept_event_attendance

CREATE DATABASE IF NOT EXISTS `dept_event_attendance` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dept_event_attendance`;

-- 1. Batches Table
CREATE TABLE IF NOT EXISTS `batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'student', 'scanner') NOT NULL,
  `status` ENUM('pending_otp', 'pending_approval', 'active', 'suspended') DEFAULT 'pending_otp',
  `otp_code` VARCHAR(6) NULL,
  `otp_expiry` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB;

-- 3. Students Table
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `course` ENUM('BCA', 'MCA') NOT NULL DEFAULT 'BCA',
  `batch_id` INT NULL,
  `class_roll` VARCHAR(30) NOT NULL,
  `university_roll` VARCHAR(30) NOT NULL,
  `contact_number` VARCHAR(15) NOT NULL,
  `whatsapp_number` VARCHAR(15) NOT NULL,
  `food_preference` ENUM('veg', 'non-veg') DEFAULT 'veg',
  `profile_photo` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB;

-- 4. Events Table
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `banner_image` VARCHAR(255) NULL,
  `event_date` DATE NOT NULL,
  `venue` VARCHAR(150) NOT NULL,
  `registration_fee` DECIMAL(10,2) DEFAULT 0.00,
  `registration_deadline` DATETIME NOT NULL,
  `scan_start_time` DATETIME NOT NULL,
  `scan_end_time` DATETIME NOT NULL,
  `upi_payment_enabled` TINYINT(1) DEFAULT 1,
  `upi_id` VARCHAR(100) NULL,
  `upi_qr_image` VARCHAR(255) NULL,
  `cash_payment_enabled` TINYINT(1) DEFAULT 1,
  `food_enabled` TINYINT(1) DEFAULT 1,
  `certificate_template` VARCHAR(255) NULL,
  `certificate_template_type` ENUM('border_only', 'full_design') NOT NULL DEFAULT 'border_only',
  `certificate_theme` VARCHAR(50) NOT NULL DEFAULT 'classic_navy',
  `certificate_title` VARCHAR(255) NOT NULL DEFAULT 'Certificate of Activity',
  `certificate_coordinator` VARCHAR(255) NOT NULL DEFAULT 'Event Coordinator',
  `certificate_hod` VARCHAR(255) NOT NULL DEFAULT 'Head of Department',
  `certificate_layout_config` TEXT NULL,
  `canva_template_link` VARCHAR(512) NULL,
  `certifier_campaign_id` VARCHAR(255) NULL,
  `status` ENUM('upcoming', 'registration_open', 'registration_closed', 'completed', 'cancelled') DEFAULT 'upcoming',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_event_date` (`event_date`)
) ENGINE=InnoDB;

-- 5. Event Registrations Table
CREATE TABLE IF NOT EXISTS `event_registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('pending_payment', 'pending_verification', 'approved', 'rejected', 'cancelled') DEFAULT 'pending_payment',
  `payment_method` ENUM('upi', 'cash') NULL,
  `assigned_role` ENUM('participant', 'volunteers', 'OC', 'CC') NOT NULL DEFAULT 'participant',
  `applied_role` ENUM('participant', 'volunteers', 'OC', 'CC') NULL,
  `role_status` ENUM('none', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'none',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_registrations_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_registrations_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uniq_student_event` (`student_id`, `event_id`),
  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_reg_status` (`status`)
) ENGINE=InnoDB;

-- 6. Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `payment_method` ENUM('upi', 'cash') NOT NULL,
  `proof_image` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `verified_by` INT NULL,
  `verification_time` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_admin` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_registration_id` (`registration_id`),
  INDEX `idx_payment_status` (`status`)
) ENGINE=InnoDB;

-- 7. QR Tokens Table
CREATE TABLE IF NOT EXISTS `qr_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `status` ENUM('active', 'expired', 'disabled', 'used') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_qr_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE,
  INDEX `idx_registration_id` (`registration_id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_token_status` (`status`)
) ENGINE=InnoDB;

-- 8. Attendance Scans Table
CREATE TABLE IF NOT EXISTS `attendance_scans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `qr_token_id` INT NOT NULL,
  `scan_type` ENUM('entry', 'food', 'exit') DEFAULT 'entry',
  `scanned_by` INT NOT NULL,
  `scanned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_scans_token` FOREIGN KEY (`qr_token_id`) REFERENCES `qr_tokens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scans_scanner` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_qr_token_id` (`qr_token_id`),
  INDEX `idx_scanner` (`scanned_by`)
) ENGINE=InnoDB;

-- 9. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(255) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB;

-- 10. Certificates Table
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL UNIQUE,
  `certificate_code` VARCHAR(50) NOT NULL UNIQUE,
  `issued_by` INT NULL,
  `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_certificates_registration` FOREIGN KEY (`registration_id`) REFERENCES `event_registrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_certificates_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed Initial Batches
INSERT INTO `batches` (`name`) VALUES 
('2022-2026'),
('2023-2027'),
('2024-2028'),
('2025-2029')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed Default Admin and Scanner (Passwords: admin123 and scanner123)
-- Admin: sandip.dutta.fiem.bca23@teamfuture.in
-- Scanner: sandydutta433@gmail.com
INSERT INTO `users` (`email`, `password`, `role`, `status`) VALUES 
('sandip.dutta.fiem.bca23@teamfuture.in', '$2y$10$krwlaqDQca.oRvLM.gBtXOFPe/Lw4Z/TC4ZCYsnVSHNCwaQ9wEiy.', 'admin', 'active'),
('sandydutta433@gmail.com', '$2y$10$ELOReKomp6DCfDNEeehqLucxcnOb1nPUcsveuiO6gN/t0AxDcjQ1W', 'scanner', 'active')
ON DUPLICATE KEY UPDATE `email`=`email`;
