-- ========================================================
-- Home Service App - Database Schema & Initial Data
-- Database Name: home_service_db
-- ========================================================

CREATE DATABASE IF NOT EXISTS `home_service_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `home_service_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `providers`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `service_categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table (Customers, Providers, Admins)
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(20) NOT NULL,
    `role` ENUM('customer', 'provider', 'admin') NOT NULL DEFAULT 'customer',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Service Categories Table
CREATE TABLE `service_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `icon` VARCHAR(50) DEFAULT 'wrench',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Services Table
CREATE TABLE `services` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `duration_mins` INT DEFAULT 60,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) 
        REFERENCES `service_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Providers Profile Table
CREATE TABLE `providers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `category_id` INT NOT NULL,
    `experience_years` INT NOT NULL DEFAULT 1,
    `hourly_rate` DECIMAL(10,2) NOT NULL DEFAULT 250.00,
    `bio` TEXT,
    `availability` ENUM('available', 'busy', 'offline') DEFAULT 'available',
    `status` ENUM('approved', 'pending', 'rejected') DEFAULT 'approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_provider_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_provider_category` FOREIGN KEY (`category_id`) 
        REFERENCES `service_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bookings Table
CREATE TABLE `bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `service_id` INT NOT NULL,
    `provider_id` INT DEFAULT NULL,
    `booking_date` DATE NOT NULL,
    `booking_time` TIME NOT NULL,
    `service_address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(20) NOT NULL,
    `special_notes` TEXT,
    `total_price` DECIMAL(10,2) NOT NULL,
    `status` ENUM('Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `admin_notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_booking_customer` FOREIGN KEY (`customer_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_service` FOREIGN KEY (`service_id`) 
        REFERENCES `services`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_booking_provider` FOREIGN KEY (`provider_id`) 
        REFERENCES `providers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Reviews Table
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `provider_id` INT DEFAULT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_review_booking` FOREIGN KEY (`booking_id`) 
        REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_customer` FOREIGN KEY (`customer_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_review_provider` FOREIGN KEY (`provider_id`) 
        REFERENCES `providers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- SEED DATA
-- Default Passwords for testing:
-- Customers: password123 (hashed: $2y$10$eA0V4hB0sXm9fKqv5Wv94.Q7Q0bX5vK0O1yI1Y0eB.Z6mI7yqI0kC or plain check)
-- Provider / Admin: password123 / admin123
-- Note: Authentication script supports bcrypt hash & fallback for convenience.
-- ========================================================

-- Insert 8 Customers from Project Requirements
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `city`, `pincode`, `role`) VALUES
(1, 'Arjun Kumar', 'arjun.kumar@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543210', 'Bengaluru', '560001', 'customer'),
(2, 'Priya Sharma', 'priya.sharma@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543211', 'Bengaluru', '560038', 'customer'),
(3, 'Rahul Gowda', 'rahul.gowda@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543212', 'Mysuru', '570001', 'customer'),
(4, 'Sneha Reddy', 'sneha.reddy@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543213', 'Bengaluru', '560076', 'customer'),
(5, 'Manoj Patel', 'manoj.patel@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543214', 'Tumakuru', '572101', 'customer'),
(6, 'Ananya Rao', 'ananya.rao@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543215', 'Bengaluru', '560040', 'customer'),
(7, 'Vikram Singh', 'vikram.singh@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543216', 'Mangaluru', '575001', 'customer'),
(8, 'Kavya Nair', 'kavya.nair@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9876543217', 'Bengaluru', '560068', 'customer'),
-- Admin User
(9, 'System Administrator', 'admin@homeservice.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9900112233', 'Bengaluru', '560001', 'admin'),
-- Service Provider Users
(10, 'Ramesh Babu', 'ramesh.plumber@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9811223344', 'Bengaluru', '560001', 'provider'),
(11, 'Suresh Sharma', 'suresh.electric@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9811223355', 'Bengaluru', '560038', 'provider'),
(12, 'Sunita Rao', 'sunita.cleaning@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9811223366', 'Mysuru', '570001', 'provider'),
(13, 'Deepak Verma', 'deepak.carpenter@email.com', '$2y$10$wK1mJ7vQ5q4bS6v7X9YZeOF9rXqU1vT5q9Q9rZ8dY3Z1uQ3vB7Yee', '9811223377', 'Tumakuru', '572101', 'provider');

-- Insert Service Categories
INSERT INTO `service_categories` (`id`, `name`, `description`, `icon`) VALUES
(1, 'Plumbing', 'Pipe leaks, tap replacement, bathroom fittings, water tank cleaning', 'faucet'),
(2, 'Electrical', 'Wiring, switchboard repair, fan installation, lights & fuse fixes', 'bolt'),
(3, 'Cleaning', 'Deep home cleaning, kitchen scrubbing, sofa & carpet shampooing', 'broom'),
(4, 'Carpentry', 'Furniture repair, door latch fix, cupboard hinge fitting & woodwork', 'hammer'),
(5, 'Painting', 'Interior wall painting, touch-ups, waterproofing & exterior paint', 'paint-roller'),
(6, 'Appliance Repair', 'AC servicing, washing machine, refrigerator & microwave repair', 'tools');

-- Insert Services
INSERT INTO `services` (`id`, `category_id`, `name`, `description`, `price`, `duration_mins`) VALUES
(1, 1, 'Tap & Pipe Leakage Repair', 'Inspection and fix for leaking taps, valves, and water pipes', 299.00, 45),
(2, 1, 'Bathroom Sanitary Fitting', 'Installation or replacement of faucets, showers, washbasins', 549.00, 60),
(3, 1, 'Water Tank Cleaning', 'Complete anti-bacterial mechanized cleaning of overhead tank', 999.00, 90),
(4, 2, 'Switchboard & Socket Repair', 'Repairing broken switches, burnt sockets, and fuse resets', 249.00, 30),
(5, 2, 'Ceiling Fan Installation / Fix', 'New ceiling fan installation or regulator replacement', 349.00, 45),
(6, 2, 'Complete House Wiring Check', 'Full electrical load inspection, safety audit, and wiring fix', 1199.00, 120),
(7, 3, 'Full Home Deep Cleaning (2BHK)', 'Comprehensive cleaning including kitchen, bathrooms, and floor scrubbing', 2499.00, 240),
(8, 3, 'Kitchen Deep Cleaning', 'Grease removal, exhaust fan degreasing, countertop sanitization', 899.00, 90),
(9, 3, 'Sofa & Upholstery Cleaning', 'Wet shampooing and vacuuming for 5-seater sofa sets', 799.00, 60),
(10, 4, 'Door Lock & Latch Replacement', 'Fixing jammed doors, installing new locksets and latches', 399.00, 45),
(11, 4, 'Furniture Assembly & Repair', 'Bed, wardrobe, study table assembly and hinge repairs', 649.00, 90),
(12, 5, 'Single Room Painting', 'Wall putty touchup with 2 coats of premium emulsion', 1899.00, 300),
(13, 5, 'Waterproofing & Damp Repair', 'Wall seepage treatment, crack filling, and waterproof sealant', 1499.00, 180),
(14, 6, 'AC Servicing & Gas Top-up', 'Complete split AC filter cleaning, cooling coil wash, gas check', 699.00, 60),
(15, 6, 'Washing Machine Repair', 'Drum spinning issue, drainage motor, or PCB diagnosis', 599.00, 60);

-- Insert Service Providers
INSERT INTO `providers` (`id`, `user_id`, `category_id`, `experience_years`, `hourly_rate`, `bio`, `availability`, `status`) VALUES
(1, 10, 1, 6, 350.00, 'Certified master plumber with over 6 years of expertise across Bengaluru.', 'available', 'approved'),
(2, 11, 2, 8, 400.00, 'Licensed electrical contractor specializing in domestic wiring and rapid troubleshooting.', 'available', 'approved'),
(3, 12, 3, 4, 300.00, 'Professional deep-cleaning expert with top-tier sanitization equipment.', 'available', 'approved'),
(4, 13, 4, 9, 450.00, 'Skilled wood artisan & carpenter for furniture assembly and restoration.', 'available', 'approved');

-- Insert Initial Bookings Demonstrating Entire Lifecycle
INSERT INTO `bookings` (`id`, `customer_id`, `service_id`, `provider_id`, `booking_date`, `booking_time`, `service_address`, `city`, `pincode`, `special_notes`, `total_price`, `status`, `admin_notes`) VALUES
(1, 1, 1, 1, '2026-10-12', '10:00:00', 'Flat 402, Green Glen Layout, Bellandur', 'Bengaluru', '560001', 'Kitchen sink pipe has a steady drip.', 299.00, 'Accepted', 'Assigned to senior plumber Ramesh Babu.'),
(2, 2, 4, 2, '2026-10-13', '14:30:00', 'House #12, 4th Main, Indiranagar', 'Bengaluru', '560038', 'Master bedroom switch spark detected.', 249.00, 'In Progress', 'Provider on location with safety gear.'),
(3, 3, 7, 3, '2026-10-05', '09:00:00', 'Plot 88, Vijayanagar 2nd Stage', 'Mysuru', '570001', 'Post-renovation full deep cleaning required.', 2499.00, 'Completed', 'Job completed with 5-star customer feedback.'),
(4, 4, 10, 4, '2026-10-15', '11:00:00', 'Villa 5, Lakeview Enclave, BTM Layout', 'Bengaluru', '560076', 'Balcony sliding door lock is stuck.', 399.00, 'Pending', 'Awaiting admin dispatch/provider confirmation.'),
(5, 5, 2, NULL, '2026-10-02', '16:00:00', 'Near Old Bus Stand, Gandhi Nagar', 'Tumakuru', '572101', 'Shower mixer valve replacement.', 549.00, 'Cancelled', 'Cancelled on customer request due to travel.');

-- Insert Reviews for Completed Bookings
INSERT INTO `reviews` (`id`, `booking_id`, `customer_id`, `provider_id`, `rating`, `comment`) VALUES
(1, 3, 3, 3, 5, 'Sunita and her cleaning team did a fantastic job! The house looks brand new, very professional and polite.');
