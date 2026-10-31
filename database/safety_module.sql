-- ========================================================
-- Smart Customer Safety & Emergency Protection Module
-- Database Migration: safety_module.sql
-- ========================================================

USE `home_service_db`;

-- Alter existing providers table to support 'suspended' status if not already present
-- Note: MySQL ALTER preserves existing data
ALTER TABLE `providers` 
    MODIFY COLUMN `status` ENUM('approved', 'pending', 'rejected', 'suspended') NOT NULL DEFAULT 'pending';

-- Add verification metadata columns to providers table if not already existing
-- (Safe check using separate table or columns)
CREATE TABLE IF NOT EXISTS `provider_verifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id` INT NOT NULL UNIQUE,
    `id_type` VARCHAR(50) NOT NULL DEFAULT 'National ID / Government Card',
    `id_number_masked` VARCHAR(50) DEFAULT NULL,
    `document_path` VARCHAR(255) DEFAULT NULL,
    `verification_notes` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pv_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Trusted Emergency Contacts Table
CREATE TABLE IF NOT EXISTS `trusted_contacts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `relationship` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tc_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. SafeVisit Sessions Table
CREATE TABLE IF NOT EXISTS `safevisit_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `session_status` ENUM('active', 'completed', 'emergency', 'cancelled') NOT NULL DEFAULT 'active',
    `arrival_otp_hash` VARCHAR(255) NOT NULL,
    `arrival_otp_plain` VARCHAR(10) DEFAULT NULL,
    `arrival_verified_at` DATETIME DEFAULT NULL,
    `arrival_attempts` INT NOT NULL DEFAULT 0,
    `completion_otp_hash` VARCHAR(255) NOT NULL,
    `completion_otp_plain` VARCHAR(10) DEFAULT NULL,
    `completion_verified_at` DATETIME DEFAULT NULL,
    `completion_attempts` INT NOT NULL DEFAULT 0,
    `otp_expires_at` DATETIME NOT NULL,
    `checkin_interval_mins` INT NOT NULL DEFAULT 30,
    `grace_period_mins` INT NOT NULL DEFAULT 5,
    `last_checkin_at` DATETIME DEFAULT NULL,
    `next_checkin_due` DATETIME DEFAULT NULL,
    `missed_checkins_count` INT NOT NULL DEFAULT 0,
    `location_sharing_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `last_latitude` DECIMAL(10, 8) DEFAULT NULL,
    `last_longitude` DECIMAL(11, 8) DEFAULT NULL,
    `last_location_updated` DATETIME DEFAULT NULL,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME DEFAULT NULL,
    CONSTRAINT `fk_sv_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sv_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sv_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Safety Incidents & SOS Alerts Table
CREATE TABLE IF NOT EXISTS `safety_incidents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT DEFAULT NULL,
    `booking_id` INT DEFAULT NULL,
    `customer_id` INT NOT NULL,
    `provider_id` INT DEFAULT NULL,
    `incident_type` ENUM('sos', 'silent_sos', 'missed_checkin', 'report', 'manual_escalation') NOT NULL,
    `status` ENUM('active', 'investigating', 'resolved', 'false_alarm') NOT NULL DEFAULT 'active',
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_si_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Safety Notifications Log (Auditable delivery logs - no faked SMS)
CREATE TABLE IF NOT EXISTS `safety_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `incident_id` INT DEFAULT NULL,
    `customer_id` INT NOT NULL,
    `recipient_type` ENUM('trusted_contact', 'emergency_services', 'admin') NOT NULL,
    `recipient_name` VARCHAR(100) NOT NULL,
    `recipient_phone` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `channel` ENUM('sms', 'in_app', 'email') NOT NULL DEFAULT 'sms',
    `delivery_status` ENUM('pending', 'delivered', 'failed', 'simulated_dev') NOT NULL DEFAULT 'simulated_dev',
    `status_message` VARCHAR(255) DEFAULT 'Development mode fallback: Recorded in safety emergency log (SMS API key not set)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sn_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Customer Provider Reports (Confidential - never exposed to providers)
CREATE TABLE IF NOT EXISTS `customer_reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `booking_id` INT DEFAULT NULL,
    `category` ENUM('harassment', 'threats', 'suspicious_behaviour', 'fraud', 'physical_danger', 'other') NOT NULL,
    `details` TEXT NOT NULL,
    `incident_date` DATE NOT NULL,
    `block_provider` TINYINT(1) NOT NULL DEFAULT 0,
    `admin_status` ENUM('pending', 'reviewing', 'action_taken', 'dismissed') NOT NULL DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cr_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Blocked Providers per Customer
CREATE TABLE IF NOT EXISTS `customer_blocked_providers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `provider_id` INT NOT NULL,
    `reason` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_cust_prov_block` (`customer_id`, `provider_id`),
    CONSTRAINT `fk_cbp_customer` FOREIGN KEY (`customer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cbp_provider` FOREIGN KEY (`provider_id`) REFERENCES `providers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Safety Audit Logs for Admin and Critical Actions
CREATE TABLE IF NOT EXISTS `safety_audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_type` VARCHAR(50) NOT NULL,
    `target_id` INT NOT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

