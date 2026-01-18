-- --------------------------------------------------------
-- Table structure for table `reports` (user/admin reports)
-- --------------------------------------------------------

CREATE TABLE `reports` (
  `report_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pharmacy_id` INT DEFAULT NULL,
  `medicine_id` INT DEFAULT NULL,
  `review_id` INT DEFAULT NULL,
  `report_type` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`pharmacy_id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
  FOREIGN KEY (`review_id`) REFERENCES `reviews_and_ratings`(`review_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------
-- Advanced Medicine Tracker Database - Complete Version
-- --------------------------------------------------------


-- Reset existing tables to avoid creation errors during import
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `sessions`, `password_history`, `audit_logs`, `notifications`, `reviews_and_ratings`, `views`, `searches`, `medicine_requests`, `inventory_history`, `pharmacy_inventory`, `medicines`, `medicine_categories`, `pharmacies`, `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Table structure for table `users` with enhanced security
-- --------------------------------------------------------

CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_reset_token` VARCHAR(64) DEFAULT NULL,
  `password_reset_expires` DATETIME DEFAULT NULL,
  `user_type` ENUM('admin', 'pharmacy', 'user') NOT NULL DEFAULT 'user',
  `full_name` VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(20),
  `address` TEXT,
  `profile_image` VARCHAR(255) DEFAULT 'default_avatar.png',
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_token` VARCHAR(64),
  `failed_login_attempts` INT(11) DEFAULT 0,
  `account_locked_until` DATETIME DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) DEFAULT 0,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `last_ip` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  INDEX `idx_email_verified` (`email`, `is_verified`),
  INDEX `idx_user_type` (`user_type`),
  INDEX `idx_password_reset` (`password_reset_token`, `password_reset_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `pharmacies` with location services
-- --------------------------------------------------------

CREATE TABLE `pharmacies` (
  `pharmacy_id` INT(11) NOT NULL AUTO_INCREMENT,
  `owner_id` INT(11) NOT NULL,
  `pharmacy_name` VARCHAR(100) NOT NULL,
  `license_number` VARCHAR(50) UNIQUE NOT NULL,
  `description` TEXT,
  `address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(50) DEFAULT 'Arba Minch',
  `zone` VARCHAR(50) DEFAULT 'Gamo Zone',
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `whatsapp_number` VARCHAR(20),
  `email` VARCHAR(100),
  `website` VARCHAR(100),
  `working_hours` JSON,
  `emergency_services` TINYINT(1) DEFAULT 0,
  `delivery_available` TINYINT(1) DEFAULT 0,
  `payment_methods` JSON,
  `rating` DECIMAL(3,2) DEFAULT 0.00,
  `review_count` INT(11) DEFAULT 0,
  `is_verified` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `featured_image` VARCHAR(255) DEFAULT 'default_pharmacy.jpg',
  `gallery_images` JSON,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pharmacy_id`),
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_location` (`latitude`, `longitude`),
  INDEX `idx_city_active` (`city`, `is_active`),
  INDEX `idx_verified` (`is_verified`),
  FULLTEXT KEY `ft_search` (`pharmacy_name`, `address`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `medicine_categories`
-- --------------------------------------------------------

CREATE TABLE `medicine_categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT 'fa-pills',
  `parent_category_id` INT(11) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`),
  FOREIGN KEY (`parent_category_id`) REFERENCES `medicine_categories`(`category_id`) ON DELETE SET NULL,
  INDEX `idx_parent_category` (`parent_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `medicines`
-- --------------------------------------------------------

CREATE TABLE `medicines` (
  `medicine_id` INT(11) NOT NULL AUTO_INCREMENT,
  `medicine_name` VARCHAR(100) NOT NULL,
  `generic_name` VARCHAR(100),
  `brand_name` VARCHAR(100),
  `manufacturer` VARCHAR(100),
  `category_id` INT(11),
  `medicine_type` ENUM('tablet', 'capsule', 'syrup', 'injection', 'ointment', 'drops', 'cream', 'gel', 'spray', 'inhaler') NOT NULL,
  `strength` VARCHAR(50),
  `package_size` VARCHAR(50),
  `requires_prescription` TINYINT(1) DEFAULT 0,
  `description` TEXT,
  `side_effects` TEXT,
  `storage_conditions` VARCHAR(200),
  `image_url` VARCHAR(255) DEFAULT 'default_medicine.png',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`medicine_id`),
  FOREIGN KEY (`category_id`) REFERENCES `medicine_categories`(`category_id`) ON DELETE SET NULL,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_prescription` (`requires_prescription`),
  FULLTEXT KEY `ft_medicine_search` (`medicine_name`, `generic_name`, `brand_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `pharmacy_inventory` with batch tracking
-- --------------------------------------------------------

CREATE TABLE `pharmacy_inventory` (
  `inventory_id` INT(11) NOT NULL AUTO_INCREMENT,
  `pharmacy_id` INT(11) NOT NULL,
  `medicine_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `reorder_level` INT(11) DEFAULT 10,
  `price` DECIMAL(10, 2) NOT NULL,
  `discount_percentage` DECIMAL(5,2) DEFAULT 0.00,
  `batch_number` VARCHAR(50),
  `manufacturing_date` DATE,
  `expiry_date` DATE NOT NULL,
  `supplier_name` VARCHAR(100),
  `is_discounted` TINYINT(1) DEFAULT 0,
  `is_featured` TINYINT(1) DEFAULT 0,
  `last_restocked` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`),
  FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`pharmacy_id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_inventory` (`pharmacy_id`, `medicine_id`, `batch_number`),
  INDEX `idx_expiry` (`expiry_date`),
  INDEX `idx_quantity` (`quantity`),
  INDEX `idx_price` (`price`),
  INDEX `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `inventory_history` for audit trail
-- --------------------------------------------------------

CREATE TABLE `inventory_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` INT(11) NOT NULL,
  `pharmacy_id` INT(11) NOT NULL,
  `medicine_id` INT(11) NOT NULL,
  `previous_quantity` INT(11),
  `new_quantity` INT(11),
  `change_type` ENUM('restock', 'sale', 'adjustment', 'expired', 'damaged') NOT NULL,
  `change_amount` INT(11),
  `change_reason` VARCHAR(255),
  `changed_by` INT(11),
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  FOREIGN KEY (`inventory_id`) REFERENCES `pharmacy_inventory`(`inventory_id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  INDEX `idx_change_date` (`changed_at`),
  INDEX `idx_change_type` (`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `medicine_requests` 
-- --------------------------------------------------------

CREATE TABLE `medicine_requests` (
  `request_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `medicine_id` INT(11) NOT NULL,
  `pharmacy_id` INT(11),
  `quantity_needed` INT(11) DEFAULT 1,
  `urgency_level` ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
  `prescription_upload` VARCHAR(255),
  `notes` TEXT,
  `request_status` ENUM('pending', 'processing', 'fulfilled', 'cancelled', 'expired') DEFAULT 'pending',
  `fulfilled_by_pharmacy` INT(11),
  `fulfilled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`request_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
  FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`pharmacy_id`) ON DELETE SET NULL,
  FOREIGN KEY (`fulfilled_by_pharmacy`) REFERENCES `pharmacies`(`pharmacy_id`) ON DELETE SET NULL,
  INDEX `idx_request_status` (`request_status`),
  INDEX `idx_user_requests` (`user_id`, `request_status`),
  INDEX `idx_urgency` (`urgency_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `searches` with analytics
-- --------------------------------------------------------

CREATE TABLE `searches` (
  `search_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `session_id` VARCHAR(100),
  `medicine_id` INT(11),
  `search_query` VARCHAR(255),
  `search_location` VARCHAR(100),
  `search_radius` INT(11) DEFAULT 5,
  `results_found` INT(11) DEFAULT 0,
  `search_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `device_type` VARCHAR(50),
  `ip_address` VARCHAR(45),
  PRIMARY KEY (`search_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE SET NULL,
  INDEX `idx_search_date` (`search_date`),
  INDEX `idx_popular_searches` (`search_query`, `search_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `views` for popular medicines
-- --------------------------------------------------------

CREATE TABLE `views` (
  `view_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `session_id` VARCHAR(100),
  `medicine_id` INT(11) NOT NULL,
  `pharmacy_id` INT(11),
  `view_type` ENUM('medicine', 'pharmacy', 'category') NOT NULL,
  `view_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `duration_seconds` INT(11),
  `ip_address` VARCHAR(45),
  PRIMARY KEY (`view_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
  FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`pharmacy_id`) ON DELETE CASCADE,
  INDEX `idx_view_date` (`view_date`),
  INDEX `idx_popular_medicine` (`medicine_id`, `view_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `reviews_and_ratings`
-- --------------------------------------------------------

CREATE TABLE `reviews_and_ratings` (
  `review_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `pharmacy_id` INT(11) DEFAULT NULL,
  `medicine_id` INT(11) DEFAULT NULL,
  `rating` TINYINT(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
  `review_title` VARCHAR(200),
  `review_text` TEXT,
  `service_quality` TINYINT(1),
  `medicine_availability` TINYINT(1),
  `staff_friendliness` TINYINT(1),
  `waiting_time` TINYINT(1),
  `cleanliness` TINYINT(1),
  `is_verified_purchase` TINYINT(1) DEFAULT 0,
  `helpful_count` INT(11) DEFAULT 0,
  `report_count` INT(11) DEFAULT 0,
  `is_approved` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`pharmacy_id`) REFERENCES `pharmacies`(`pharmacy_id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_review` (`user_id`, `pharmacy_id`),
  INDEX `idx_pharmacy_rating` (`pharmacy_id`, `rating`),
  INDEX `idx_recent_reviews` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `notification_type` ENUM('system', 'inventory', 'request', 'security', 'promotion') NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `action_url` VARCHAR(255),
  `icon` VARCHAR(50),
  `is_read` TINYINT(1) DEFAULT 0,
  `is_urgent` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_user_notifications` (`user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `audit_logs` for security
-- --------------------------------------------------------

CREATE TABLE `audit_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `action_type` VARCHAR(50) NOT NULL,
  `table_name` VARCHAR(50),
  `record_id` INT(11),
  `old_values` JSON,
  `new_values` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  INDEX `idx_audit_date` (`created_at`),
  INDEX `idx_user_actions` (`user_id`, `action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `password_history` for security
-- --------------------------------------------------------

CREATE TABLE `password_history` (
  `history_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_user_passwords` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `sessions`
-- --------------------------------------------------------

CREATE TABLE `sessions` (
  `session_id` VARCHAR(128) NOT NULL,
  `user_id` INT(11),
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `session_data` TEXT,
  PRIMARY KEY (`session_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- INSERT SAMPLE DATA
-- ========================================================

-- --------------------------------------------------------
-- 1. CREATE ADMIN USERS
-- --------------------------------------------------------

-- Password for all sample users: 'password123' (hashed with password_hash())
INSERT INTO `users` (`username`, `email`, `password_hash`, `user_type`, `full_name`, `phone_number`, `address`, `is_verified`) VALUES
-- System Administrator
('admin', 'admin@medtracker.arbaminch.edu.et', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'admin', 'System Administrator', '+251911223344', 'Arba Minch University, Main Campus', 1),

-- Pharmacy Owners (for each pharmacy)
('enat_pharmacy', 'enat@pharmacy.arbaminch.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'pharmacy', 'Ato Solomon Bekele', '+251912345678', 'Secha Area, Arba Minch', 1),
('model_pharmacy', 'model@pharmacy.arbaminch.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'pharmacy', 'W/ro Aster Mesfin', '+251913456789', 'Town Center, Arba Minch', 1),
('beminet_pharmacy', 'beminet@pharmacy.arbaminch.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'pharmacy', 'Dr. Samuel Yohannes', '+251914567890', 'Kulfo Area, Arba Minch', 1),
('mihret_pharmacy', 'mihret@pharmacy.arbaminch.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'pharmacy', 'Ato Daniel Tesfaye', '+251915678901', 'Sikela Area, Arba Minch', 1),
('nechisar_pharmacy', 'nechisar@pharmacy.arbaminch.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'pharmacy', 'W/ro Helen Gebre', '+251916789012', 'Secha Area, Arba Minch', 1),
('covenant_pharmacy', 'covenant@pharmacy.arbaminch.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'pharmacy', 'Ato Michael Assefa', '+251917890123', 'Ajip Area, Secha', 1),

-- Regular Users
('john_doe', 'john.doe@student.arbaminch.edu.et', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'user', 'John Doe', '+251918901234', 'Arba Minch University Hostel', 1),
('mary_smith', 'mary.smith@example.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'user', 'Mary Smith', '+251919012345', 'Kulfo Residential Area', 1),
('david_williams', 'david.w@example.com', '$2y$10$rF2tJ9lUq8Kq5dZ8m7nB/.VcQYwXaHjGfLpN6bV3sD9zR4tY7uW1o', 'user', 'David Williams', '+251920123456', 'Secha Housing', 1);

-- --------------------------------------------------------
-- 2. CREATE PHARMACIES IN ARBA MINCH WITH REAL LOCATIONS
-- --------------------------------------------------------

-- Note: Coordinates are approximate for Arba Minch areas
INSERT INTO `pharmacies` (
  `owner_id`, `pharmacy_name`, `license_number`, `description`, `address`, 
  `latitude`, `longitude`, `phone`, `whatsapp_number`, `email`, `working_hours`,
  `emergency_services`, `delivery_available`, `payment_methods`, `rating`, `review_count`,
  `is_verified`, `featured_image`
) VALUES
-- Secha Area Pharmacies
(2, 'Arbaminch Enat Pharmacy', 'PH-AM-001', '24/7 emergency pharmacy with wide medicine selection. Established 2010.', 
 'Secha Main Road, Near Commercial Bank, Arba Minch', 
 6.0395, 37.5445, '+251912345678', '+251912345678', 'enat@pharmacy.arbaminch.com',
 '{"monday": "8:00-22:00", "tuesday": "8:00-22:00", "wednesday": "8:00-22:00", "thursday": "8:00-22:00", "friday": "8:00-22:00", "saturday": "9:00-20:00", "sunday": "10:00-18:00"}',
 1, 1, '["cash", "mobile_banking", "card"]', 4.7, 128, 1,
 'https://images.unsplash.com/photo-1586773860418-dc22f8b874bc?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80'),

(6, 'Nechisar Drug Store', 'PH-AM-002', 'Family-run pharmacy with personal care service.', 
 'Secha Market Area, Opposite Ethio Telecom, Arba Minch',
 6.0412, 37.5458, '+251916789012', '+251916789012', 'nechisar@pharmacy.arbaminch.com',
 '{"monday": "7:00-21:00", "tuesday": "7:00-21:00", "wednesday": "7:00-21:00", "thursday": "7:00-21:00", "friday": "7:00-21:00", "saturday": "8:00-19:00", "sunday": "Closed"}',
 0, 1, '["cash", "mobile_banking"]', 4.3, 89, 1,
 'https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80'),

-- Town Center Pharmacies
(3, 'Model Community Pharmacy', 'PH-AM-003', 'Modern pharmacy with digital prescription services.', 
 'Arba Minch Town Center, Near Municipality Office',
 6.0367, 37.5421, '+251913456789', '+251913456789', 'model@pharmacy.arbaminch.com',
 '{"monday": "6:00-24:00", "tuesday": "6:00-24:00", "wednesday": "6:00-24:00", "thursday": "6:00-24:00", "friday": "6:00-24:00", "saturday": "6:00-24:00", "sunday": "6:00-24:00"}',
 1, 1, '["cash", "card", "insurance", "mobile_banking"]', 4.8, 156, 1,
 'https://images.unsplash.com/photo-1551601651-2a8555f1a136?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80'),

-- Kulfo Area Pharmacy
(4, 'Beminet Drug Store', 'PH-AM-004', 'Specialized in chronic disease medications.', 
 'Kulfo Road, Near Kulfo River Bridge, Arba Minch',
 6.0334, 37.5408, '+251914567890', '+251914567890', 'beminet@pharmacy.arbaminch.com',
 '{"monday": "9:00-20:00", "tuesday": "9:00-20:00", "wednesday": "9:00-20:00", "thursday": "9:00-20:00", "friday": "9:00-20:00", "saturday": "10:00-18:00", "sunday": "Closed"}',
 0, 0, '["cash"]', 4.2, 67, 1,
 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80'),

-- Sikela Area Pharmacies
(5, 'Mihret Drug Store', 'PH-AM-005', 'Affordable medicines with student discounts.', 
 'Sikela Area, Near Arba Minch University Gate',
 6.0456, 37.5389, '+251915678901', '+251915678901', 'mihret@pharmacy.arbaminch.com',
 '{"monday": "8:00-21:00", "tuesday": "8:00-21:00", "wednesday": "8:00-21:00", "thursday": "8:00-21:00", "friday": "8:00-21:00", "saturday": "9:00-19:00", "sunday": "10:00-16:00"}',
 0, 1, '["cash", "mobile_banking", "student_discount"]', 4.5, 112, 1,
 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80'),

-- Ajip/Secha Area
(7, 'Covenant Drug Store', 'PH-AM-006', 'Community pharmacy with home delivery service.', 
 'Ajip Area, Secha, Near St. Mary Church',
 6.0478, 37.5471, '+251917890123', '+251917890123', 'covenant@pharmacy.arbaminch.com',
 '{"monday": "7:30-20:30", "tuesday": "7:30-20:30", "wednesday": "7:30-20:30", "thursday": "7:30-20:30", "friday": "7:30-20:30", "saturday": "8:00-19:00", "sunday": "Closed"}',
 0, 1, '["cash", "mobile_banking"]', 4.4, 78, 1,
 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&q=80');

-- --------------------------------------------------------
-- 3. CREATE MEDICINE CATEGORIES
-- --------------------------------------------------------

INSERT INTO `medicine_categories` (`category_name`, `description`, `icon`) VALUES
('Antibiotics', 'Medicines for bacterial infections', 'fa-bacteria'),
('Pain Relief', 'Pain relievers and fever reducers', 'fa-head-side-virus'),
('Cardiology', 'Heart and blood pressure medicines', 'fa-heartbeat'),
('Diabetes', 'Diabetes management medicines', 'fa-syringe'),
('Respiratory', 'Asthma and respiratory medicines', 'fa-lungs'),
('Gastrointestinal', 'Stomach and digestive medicines', 'fa-stomach'),
('Vitamins & Supplements', 'Vitamins and dietary supplements', 'fa-capsules'),
('Skin Care', 'Dermatological medicines', 'fa-allergies'),
('Mental Health', 'Psychiatric and neurological medicines', 'fa-brain'),
('First Aid', 'Emergency and first aid supplies', 'fa-first-aid'),
('Antipyretics', 'Fever reducing medicines', 'fa-thermometer'),
('Analgesics', 'Pain relieving medicines', 'fa-pain-scale');

-- --------------------------------------------------------
-- 4. CREATE MEDICINES WITH REALISTIC DATA
-- --------------------------------------------------------

INSERT INTO `medicines` (`medicine_name`, `generic_name`, `brand_name`, `manufacturer`, `category_id`, `medicine_type`, `strength`, `requires_prescription`, `description`, `image_url`) VALUES
('Paracetamol', 'Acetaminophen', 'Panadol', 'GSK', 2, 'tablet', '500mg', 0, 'For pain relief and fever reduction. Common OTC medicine.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Paracetamol_650.jpg'),
('Amoxicillin', 'Amoxicillin', 'Amoxil', 'Pfizer', 1, 'capsule', '250mg', 1, 'Broad-spectrum antibiotic for bacterial infections.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Amoxicillin_500mg_capsules_on_a_plate_(Sandoz).jpg'),
('Salbutamol', 'Albuterol', 'Ventolin', 'GSK', 5, 'inhaler', '100mcg', 1, 'Bronchodilator for asthma and COPD.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Ventolin_Inhaler_N_100_ug_inh.jpg'),
('Metformin', 'Metformin HCl', 'Glucophage', 'Merck', 4, 'tablet', '500mg', 1, 'First-line medication for type 2 diabetes.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Metformin_500mg_Tablets.jpg'),
('Ibuprofen', 'Ibuprofen', 'Advil', 'Pfizer', 2, 'tablet', '400mg', 0, 'NSAID for pain, inflammation, and fever.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Ibuprofen_200_mg_tablets_(19019373514).jpg'),
('Cetirizine', 'Cetirizine HCl', 'Zyrtec', 'Johnson & Johnson', 8, 'tablet', '10mg', 0, 'Antihistamine for allergy relief.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Zyrtec-D_blister_pack.png'),
('Atorvastatin', 'Atorvastatin Calcium', 'Lipitor', 'Pfizer', 3, 'tablet', '20mg', 1, 'Statins for cholesterol management.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Atorvastatin40mg.jpg'),
('Omeprazole', 'Omeprazole', 'Prilosec', 'AstraZeneca', 6, 'capsule', '20mg', 1, 'Proton pump inhibitor for acid reflux.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Omeprazol_Activis_capsules.jpg'),
('Vitamin C', 'Ascorbic Acid', 'Redoxon', 'Bayer', 7, 'tablet', '1000mg', 0, 'Immune system support and antioxidant.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Vitamin_C_Tablets.jpg'),
('Diazepam', 'Diazepam', 'Valium', 'Roche', 9, 'tablet', '5mg', 1, 'For anxiety, muscle spasms, and seizures.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Diazepam_tablets.jpeg'),
('Aspirin', 'Acetylsalicylic Acid', 'Bayer', 'Bayer', 3, 'tablet', '100mg', 0, 'Pain relief and blood thinner.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Regular_strength_enteric_coated_aspirin_tablets.jpg'),
('Loratadine', 'Loratadine', 'Claritin', 'Bayer', 8, 'tablet', '10mg', 0, 'Non-drowsy allergy relief.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Fortune_NT-Alergi_tablet_(contains_Loratadine_10mg).jpg'),
('Metronidazole', 'Metronidazole', 'Flagyl', 'Sanofi', 1, 'tablet', '500mg', 1, 'Antibiotic for anaerobic bacteria.', 'https://commons.wikimedia.org/wiki/Special:FilePath/20120127-MetronidazoleTablets-from-TKOH-SDC13824.JPG'),
('Ranitidine', 'Ranitidine HCl', 'Zantac', 'GSK', 6, 'tablet', '150mg', 0, 'For heartburn and acid reflux.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Blister_of_tablets.jpg'),
('Simvastatin', 'Simvastatin', 'Zocor', 'Merck', 3, 'tablet', '20mg', 1, 'Cholesterol lowering medication.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Non-press_through_pack_tablets.jpg'),
('Insulin Glargine', 'Insulin Glargine', 'Lantus', 'Sanofi', 4, 'injection', '100IU/ml', 1, 'Long-acting insulin for diabetes.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Human_insulin_100IU-ml_vial_white_background.jpg'),
('Cough Syrup', 'Dextromethorphan', 'Robitussin', 'GSK', 5, 'syrup', '15mg/5ml', 0, 'Cough suppressant syrup.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Cough_medicine.jpg'),
('Hydrocortisone', 'Hydrocortisone', 'Cortizone-10', 'Pfizer', 8, 'cream', '1%', 0, 'Topical steroid for skin inflammation.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Hydrocortisone_cream.jpg'),
('Oral Rehydration Salts', 'ORS', 'Electral', 'FDC', 10, 'powder', '20.5g', 0, 'For dehydration treatment.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Electral_powder.jpg'),
('Amoxicillin-Clavulanate', 'Co-amoxiclav', 'Augmentin', 'GSK', 1, 'tablet', '625mg', 1, 'Broad-spectrum antibiotic with beta-lactamase inhibitor.', 'https://commons.wikimedia.org/wiki/Special:FilePath/Amoxicillin_clavulanate_tablets.jpg');

-- --------------------------------------------------------
-- 5. CREATE PHARMACY INVENTORY
-- --------------------------------------------------------

-- Function to generate random price
-- Let's create inventory for each pharmacy
INSERT INTO `pharmacy_inventory` (`pharmacy_id`, `medicine_id`, `quantity`, `price`, `discount_percentage`, `batch_number`, `expiry_date`, `is_featured`) VALUES
-- Arbaminch Enat Pharmacy (Secha)
(1, 1, 150, 25.00, 0.00, 'BATCH-ET-2024-001', '2025-12-31', 1),
(1, 2, 50, 120.00, 10.00, 'BATCH-ET-2024-002', '2024-08-30', 0),
(1, 3, 30, 450.00, 0.00, 'BATCH-ET-2024-003', '2024-12-31', 1),
(1, 4, 40, 85.00, 5.00, 'BATCH-ET-2024-004', '2025-06-30', 0),
(1, 5, 100, 35.00, 15.00, 'BATCH-ET-2024-005', '2025-09-30', 1),

-- Model Community Pharmacy (Town Center)
(2, 1, 200, 23.50, 0.00, 'BATCH-MC-2024-001', '2026-01-31', 1),
(2, 6, 80, 55.00, 0.00, 'BATCH-MC-2024-002', '2025-03-31', 0),
(2, 7, 45, 180.00, 10.00, 'BATCH-MC-2024-003', '2024-11-30', 1),
(2, 8, 60, 95.00, 5.00, 'BATCH-MC-2024-004', '2025-05-31', 0),
(2, 9, 120, 75.00, 20.00, 'BATCH-MC-2024-005', '2024-10-31', 1),

-- Beminet Drug Store (Kulfo)
(3, 2, 35, 125.00, 0.00, 'BATCH-BD-2024-001', '2024-09-30', 0),
(3, 10, 25, 110.00, 0.00, 'BATCH-BD-2024-002', '2025-02-28', 1),
(3, 11, 90, 28.00, 10.00, 'BATCH-BD-2024-003', '2025-08-31', 0),
(3, 12, 70, 50.00, 0.00, 'BATCH-BD-2024-004', '2025-04-30', 1),
(3, 16, 15, 650.00, 0.00, 'BATCH-BD-2024-005', '2024-12-31', 1),

-- Mihret Drug Store (Sikela)
(4, 1, 180, 24.00, 5.00, 'BATCH-MD-2024-001', '2025-11-30', 1),
(4, 5, 110, 32.00, 15.00, 'BATCH-MD-2024-002', '2025-10-31', 0),
(4, 9, 100, 70.00, 10.00, 'BATCH-MD-2024-003', '2024-12-31', 1),
(4, 17, 40, 85.00, 0.00, 'BATCH-MD-2024-004', '2025-03-31', 0),
(4, 19, 200, 15.00, 0.00, 'BATCH-MD-2024-005', '2026-06-30', 1),

-- Nechisar Drug Store (Secha)
(5, 3, 25, 440.00, 5.00, 'BATCH-ND-2024-001', '2024-11-30', 0),
(5, 8, 55, 90.00, 0.00, 'BATCH-ND-2024-002', '2025-07-31', 1),
(5, 13, 30, 95.00, 0.00, 'BATCH-ND-2024-003', '2025-01-31', 0),
(5, 14, 85, 40.00, 10.00, 'BATCH-ND-2024-004', '2025-05-31', 1),
(5, 18, 60, 65.00, 0.00, 'BATCH-ND-2024-005', '2025-09-30', 0),

-- Covenant Drug Store (Ajip/Secha)
(6, 1, 140, 26.00, 0.00, 'BATCH-CD-2024-001', '2025-10-31', 1),
(6, 4, 35, 88.00, 5.00, 'BATCH-CD-2024-002', '2025-08-31', 0),
(6, 6, 75, 52.00, 0.00, 'BATCH-CD-2024-003', '2025-04-30', 1),
(6, 15, 20, 160.00, 10.00, 'BATCH-CD-2024-004', '2024-12-31', 0),
(6, 20, 45, 230.00, 0.00, 'BATCH-CD-2024-005', '2025-06-30', 1);

-- --------------------------------------------------------
-- 6. CREATE SAMPLE SEARCHES (for analytics)
-- --------------------------------------------------------

INSERT INTO `searches` (`user_id`, `medicine_id`, `search_query`, `search_location`, `results_found`, `device_type`) VALUES
(8, 1, 'paracetamol', 'Arba Minch', 6, 'mobile'),
(9, 3, 'asthma inhaler', 'Kulfo Area', 2, 'desktop'),
(10, 4, 'metformin diabetes', 'Secha Area', 4, 'mobile'),
(NULL, 1, 'fever medicine', 'Arba Minch', 6, 'mobile'),
(8, 6, 'allergy medicine', 'Sikela', 5, 'mobile'),
(NULL, 5, 'ibuprofen pain', 'Town Center', 5, 'desktop'),
(9, 2, 'amoxicillin infection', 'Arba Minch', 6, 'mobile'),
(10, 8, 'acid reflux medicine', 'Kulfo', 5, 'mobile');

-- --------------------------------------------------------
-- 7. CREATE SAMPLE REVIEWS
-- --------------------------------------------------------

INSERT INTO `reviews_and_ratings` (`user_id`, `pharmacy_id`, `rating`, `review_title`, `review_text`, `service_quality`, `medicine_availability`, `staff_friendliness`, `waiting_time`, `cleanliness`) VALUES
(8, 1, 5, 'Excellent Emergency Service', 'Saved us during late night emergency. Staff was very helpful.', 5, 5, 5, 4, 5),
(9, 2, 4, 'Modern and Clean', 'Digital prescription system is great. Prices are reasonable.', 4, 5, 4, 3, 5),
(10, 4, 5, 'Great for Students', 'Student discounts are very helpful. Always have what I need.', 4, 5, 5, 4, 4),
(8, 3, 3, 'Limited Stock', 'Good service but often out of stock for chronic medicines.', 4, 3, 4, 4, 4),
(9, 5, 4, 'Family Pharmacy', 'Have been going here for years. Trustworthy and reliable.', 5, 4, 5, 3, 4),
(10, 6, 5, 'Home Delivery is Great', 'Delivery service is very convenient for elderly family members.', 5, 4, 5, 5, 4);

-- --------------------------------------------------------
-- 8. CREATE SAMPLE NOTIFICATIONS
-- --------------------------------------------------------

INSERT INTO `notifications` (`user_id`, `notification_type`, `title`, `message`, `action_url`, `icon`, `is_urgent`) VALUES
(8, 'promotion', 'Medicine Discount Alert', 'Paracetamol is now 15% off at Model Community Pharmacy', '/pharmacy/2/medicine/1', 'fa-percentage', 0),
(9, 'inventory', 'Medicine Available', 'The medicine you requested (Amoxicillin) is now in stock', '/medicine/2', 'fa-bell', 0),
(1, 'system', 'System Maintenance', 'System will be down for maintenance on Sunday 2-4 AM', '/admin/maintenance', 'fa-tools', 0),
(2, 'inventory', 'Low Stock Alert', 'Paracetamol stock is below reorder level (50 units)', '/pharmacy/inventory', 'fa-exclamation-triangle', 1);

-- --------------------------------------------------------
-- 9. CREATE AUDIT LOGS
-- --------------------------------------------------------

INSERT INTO `audit_logs` (`user_id`, `action_type`, `table_name`, `record_id`, `ip_address`) VALUES
(1, 'LOGIN', 'users', 1, '192.168.1.100'),
(2, 'UPDATE_INVENTORY', 'pharmacy_inventory', 1, '192.168.1.101'),
(8, 'SEARCH', 'searches', 1, '192.168.1.102'),
(1, 'CREATE_USER', 'users', 9, '192.168.1.100');

-- --------------------------------------------------------
-- 10. CREATE SAMPLE MEDICINE REQUESTS
-- --------------------------------------------------------

INSERT INTO `medicine_requests` (`user_id`, `medicine_id`, `pharmacy_id`, `quantity_needed`, `urgency_level`, `notes`, `request_status`) VALUES
(8, 7, 2, 1, 'medium', 'Need for cholesterol management', 'pending'),
(9, 16, 3, 2, 'high', 'Emergency need for diabetes', 'fulfilled'),
(10, 3, 1, 1, 'emergency', 'Asthma attack emergency', 'processing'),
(8, 20, 6, 1, 'low', 'For upcoming prescription', 'pending');

-- --------------------------------------------------------
-- VIEWS FOR ADVANCED REPORTING
-- --------------------------------------------------------

CREATE VIEW `vw_medicine_availability` AS
SELECT 
    m.medicine_id,
    m.medicine_name,
    m.generic_name,
    m.requires_prescription,
    m.image_url,
    COUNT(DISTINCT pi.pharmacy_id) as available_in_pharmacies,
    MIN(pi.price) as min_price,
    MAX(pi.price) as max_price,
    AVG(pi.price) as avg_price,
    SUM(pi.quantity) as total_stock
FROM medicines m
LEFT JOIN pharmacy_inventory pi ON m.medicine_id = pi.medicine_id
LEFT JOIN pharmacies p ON pi.pharmacy_id = p.pharmacy_id AND p.is_active = 1
WHERE m.is_active = 1
GROUP BY m.medicine_id;

CREATE VIEW `vw_pharmacy_stats` AS
SELECT 
    p.pharmacy_id,
    p.pharmacy_name,
    p.address,
    p.rating,
    p.review_count,
    COUNT(DISTINCT pi.medicine_id) as medicine_count,
    SUM(pi.quantity) as total_stock,
    COUNT(DISTINCT mr.request_id) as total_requests,
    COUNT(DISTINCT CASE WHEN mr.request_status = 'fulfilled' THEN mr.request_id END) as fulfilled_requests
FROM pharmacies p
LEFT JOIN pharmacy_inventory pi ON p.pharmacy_id = pi.pharmacy_id
LEFT JOIN medicine_requests mr ON p.pharmacy_id = mr.fulfilled_by_pharmacy
WHERE p.is_active = 1
GROUP BY p.pharmacy_id;

CREATE VIEW `vw_popular_searches` AS
SELECT 
    s.search_query,
    COUNT(*) as search_count,
    COUNT(DISTINCT s.user_id) as unique_users,
    AVG(s.results_found) as avg_results,
    MAX(s.search_date) as last_searched
FROM searches s
WHERE s.search_query IS NOT NULL AND s.search_query != ''
GROUP BY s.search_query
ORDER BY search_count DESC;

CREATE VIEW `vw_expiring_medicines` AS
SELECT 
    p.pharmacy_name,
    m.medicine_name,
    pi.batch_number,
    pi.expiry_date,
    pi.quantity,
    DATEDIFF(pi.expiry_date, CURDATE()) as days_until_expiry
FROM pharmacy_inventory pi
JOIN pharmacies p ON pi.pharmacy_id = p.pharmacy_id
JOIN medicines m ON pi.medicine_id = m.medicine_id
WHERE pi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
ORDER BY pi.expiry_date ASC;

-- --------------------------------------------------------
-- STORED PROCEDURES
-- --------------------------------------------------------

DELIMITER //

CREATE PROCEDURE sp_search_medicine(
    IN p_medicine_name VARCHAR(100),
    IN p_location_lat DECIMAL(10,8),
    IN p_location_lng DECIMAL(11,8),
    IN p_radius_km INT
)
BEGIN
    -- Calculate distance using Haversine formula (simplified for Ethiopia)
    SELECT 
        m.medicine_id,
        m.medicine_name,
        m.generic_name,
        m.requires_prescription,
        p.pharmacy_id,
        p.pharmacy_name,
        p.address,
        p.latitude,
        p.longitude,
        pi.quantity,
        pi.price,
        pi.discount_percentage,
        (pi.price * (1 - pi.discount_percentage/100)) as final_price,
        -- Simplified distance calculation
        111.045 * DEGREES(ACOS(
            COS(RADIANS(p_location_lat)) * 
            COS(RADIANS(p.latitude)) * 
            COS(RADIANS(p.longitude) - RADIANS(p_location_lng)) + 
            SIN(RADIANS(p_location_lat)) * 
            SIN(RADIANS(p.latitude))
        )) as distance_km
    FROM medicines m
    JOIN pharmacy_inventory pi ON m.medicine_id = pi.medicine_id
    JOIN pharmacies p ON pi.pharmacy_id = p.pharmacy_id
    WHERE (m.medicine_name LIKE CONCAT('%', p_medicine_name, '%') 
           OR m.generic_name LIKE CONCAT('%', p_medicine_name, '%'))
      AND p.is_active = 1
      AND pi.quantity > 0
    HAVING distance_km <= p_radius_km
    ORDER BY distance_km ASC, final_price ASC;
END //

CREATE PROCEDURE sp_get_pharmacy_inventory_stats(IN p_pharmacy_id INT)
BEGIN
    SELECT 
        COUNT(*) as total_medicines,
        SUM(pi.quantity) as total_stock,
        SUM(pi.quantity * pi.price) as total_inventory_value,
        COUNT(CASE WHEN pi.quantity <= pi.reorder_level THEN 1 END) as low_stock_items,
        COUNT(CASE WHEN pi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon
    FROM pharmacy_inventory pi
    WHERE pi.pharmacy_id = p_pharmacy_id;
END //

CREATE PROCEDURE sp_get_user_dashboard_stats(IN p_user_id INT)
BEGIN
    -- User statistics
    SELECT 
        (SELECT COUNT(*) FROM medicine_requests WHERE user_id = p_user_id) as total_requests,
        (SELECT COUNT(*) FROM medicine_requests WHERE user_id = p_user_id AND request_status = 'fulfilled') as fulfilled_requests,
        (SELECT COUNT(*) FROM reviews_and_ratings WHERE user_id = p_user_id) as reviews_written,
        (SELECT COUNT(DISTINCT search_query) FROM searches WHERE user_id = p_user_id) as unique_searches;
END //

DELIMITER ;

-- --------------------------------------------------------
-- TRIGGERS FOR DATA INTEGRITY
-- --------------------------------------------------------

DELIMITER //

-- Trigger to update pharmacy rating when new review is added
CREATE TRIGGER trg_update_pharmacy_rating 
AFTER INSERT ON reviews_and_ratings
FOR EACH ROW
BEGIN
    UPDATE pharmacies p
    SET 
        p.rating = (
            SELECT AVG(rating) 
            FROM reviews_and_ratings 
            WHERE pharmacy_id = NEW.pharmacy_id
        ),
        p.review_count = (
            SELECT COUNT(*) 
            FROM reviews_and_ratings 
            WHERE pharmacy_id = NEW.pharmacy_id
        )
    WHERE p.pharmacy_id = NEW.pharmacy_id;
END //

-- Trigger to log inventory changes
CREATE TRIGGER trg_log_inventory_change 
AFTER UPDATE ON pharmacy_inventory
FOR EACH ROW
BEGIN
    IF OLD.quantity != NEW.quantity THEN
        INSERT INTO inventory_history (
            inventory_id, pharmacy_id, medicine_id,
            previous_quantity, new_quantity,
            change_type, change_amount
        ) VALUES (
            NEW.inventory_id, NEW.pharmacy_id, NEW.medicine_id,
            OLD.quantity, NEW.quantity,
            'adjustment', NEW.quantity - OLD.quantity
        );
    END IF;
END //

-- Trigger to prevent duplicate user sessions
CREATE TRIGGER trg_prevent_duplicate_sessions 
BEFORE INSERT ON sessions
FOR EACH ROW
BEGIN
    -- Delete old sessions for same user
    DELETE FROM sessions 
    WHERE user_id = NEW.user_id 
    AND last_activity < DATE_SUB(NOW(), INTERVAL 1 DAY);
END //

DELIMITER ;

-- --------------------------------------------------------
-- FUNCTIONS FOR COMMON OPERATIONS
-- --------------------------------------------------------

DELIMITER //

CREATE FUNCTION fn_calculate_distance(
    lat1 DECIMAL(10,8), 
    lng1 DECIMAL(11,8), 
    lat2 DECIMAL(10,8), 
    lng2 DECIMAL(11,8)
) RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE distance DECIMAL(10,2);
    -- Haversine formula for distance in kilometers
    SET distance = 6371 * ACOS(
        COS(RADIANS(lat1)) * 
        COS(RADIANS(lat2)) * 
        COS(RADIANS(lng2) - RADIANS(lng1)) + 
        SIN(RADIANS(lat1)) * 
        SIN(RADIANS(lat2))
    );
    RETURN ROUND(distance, 2);
END //

CREATE FUNCTION fn_get_medicine_availability(
    p_medicine_id INT,
    p_location_lat DECIMAL(10,8),
    p_location_lng DECIMAL(11,8),
    p_max_distance_km INT
) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE available_pharmacies INT;
    
    SELECT COUNT(DISTINCT p.pharmacy_id) INTO available_pharmacies
    FROM pharmacy_inventory pi
    JOIN pharmacies p ON pi.pharmacy_id = p.pharmacy_id
    WHERE pi.medicine_id = p_medicine_id
      AND pi.quantity > 0
      AND p.is_active = 1
      AND fn_calculate_distance(p_location_lat, p_location_lng, p.latitude, p.longitude) <= p_max_distance_km;
    
    RETURN available_pharmacies;
END //

DELIMITER ;

-- --------------------------------------------------------
-- CREATE INDEXES FOR PERFORMANCE
-- --------------------------------------------------------

CREATE INDEX idx_medicine_search ON medicines(medicine_name, generic_name);
CREATE INDEX idx_inventory_pharmacy_medicine ON pharmacy_inventory(pharmacy_id, medicine_id);
CREATE INDEX idx_requests_user_status ON medicine_requests(user_id, request_status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read, created_at);
CREATE INDEX idx_searches_date_query ON searches(search_date, search_query);
CREATE INDEX idx_views_medicine_date ON views(medicine_id, view_date);

-- --------------------------------------------------------
-- UPDATE PHARMACY RATINGS BASED ON REVIEWS
-- --------------------------------------------------------

-- This will automatically trigger the update via the trigger
UPDATE pharmacies p
JOIN (
    SELECT pharmacy_id, AVG(rating) as avg_rating, COUNT(*) as count
    FROM reviews_and_ratings
    GROUP BY pharmacy_id
) r ON p.pharmacy_id = r.pharmacy_id
SET p.rating = r.avg_rating, p.review_count = r.count;

-- --------------------------------------------------------
-- FINAL COMMENTS
-- --------------------------------------------------------

/*
DATABASE DESIGN NOTES:
1. All passwords are hashed with password_hash() - Use 'password123' for testing
2. Coordinates are approximate for Arba Minch areas
3. Images use Wikimedia Commons for reliability
4. All pharmacies are verified and active
5. Audit trail is maintained through triggers and history tables
6. Full-text search enabled for medicine and pharmacy search
7. JSON columns used for flexible data storage (working hours, payment methods)
8. Views provide easy reporting
9. Stored procedures for common operations
10. Triggers maintain data integrity

TESTING CREDENTIALS:
- Admin: admin / password123
- Pharmacy: enat_pharmacy / password123
- User: john_doe / password123

SECURITY FEATURES:
1. Password hashing with bcrypt
2. Password history tracking
3. Failed login attempts tracking
4. Account locking mechanism
5. Session management
6. Audit logging
7. Two-factor authentication support
*/
Fatal error: Uncaught mysqli_sql_exception: Unknown column 'is_active' in 'WHERE' in /home/vol4_5/infinityfree.com/if0_40875712/htdocs/index.php:19 Stack trace: #0 /home/vol4_5/infinityfree.com/if0_40875712/htdocs/index.php(19): mysqli->query('SELECT COUNT(*)...') #1 {main} thrown in /home/vol4_5/infinityfree.com/if0_40875712/htdocs/index.php on line 19

-- Display completion message
SELECT 'Database med_tracker_pro created successfully!' as message;
SELECT 'Total tables created: 14' as tables_created;
SELECT 'Sample data inserted for Arba Minch pharmacies' as data_inserted;