-- Hisabio: Smart Digital Khata & Business Ledger Management System
-- Database Schema for MySQL 8.0+ / MariaDB 10.4+
-- Charset: utf8mb4, Collation: utf8mb4_unicode_ci

CREATE DATABASE IF NOT EXISTS `hisabio` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hisabio`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `transaction_audit_logs`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `reminders`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `payment_methods`;
DROP TABLE IF EXISTS `business_settings`;
DROP TABLE IF EXISTS `business_users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `businesses`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NULL,
  `mobile` VARCHAR(25) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_users_mobile` (`mobile`),
  KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Businesses Table
CREATE TABLE `businesses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_name` VARCHAR(200) NOT NULL,
  `business_code` VARCHAR(50) NOT NULL UNIQUE,
  `owner_user_id` INT UNSIGNED NOT NULL,
  `business_category` VARCHAR(100) DEFAULT 'General Store',
  `logo` VARCHAR(255) NULL,
  `currency` VARCHAR(10) DEFAULT 'PKR',
  `country` VARCHAR(50) DEFAULT 'Pakistan',
  `city` VARCHAR(100) DEFAULT 'Lahore',
  `address` TEXT NULL,
  `phone` VARCHAR(25) NULL,
  `receipt_footer` TEXT NULL,
  `timezone` VARCHAR(50) DEFAULT 'Asia/Karachi',
  `language` VARCHAR(10) DEFAULT 'en',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_businesses_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Roles Table
CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `role_name` VARCHAR(100) NOT NULL,
  `permissions` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_roles_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Business Users Table
CREATE TABLE `business_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_bus_user` (`business_id`, `user_id`),
  CONSTRAINT `fk_bu_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bu_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Customers Table
CREATE TABLE `customers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `customer_code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(25) NOT NULL,
  `alternate_mobile` VARCHAR(25) NULL,
  `address` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('active', 'archived') DEFAULT 'active',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` DATETIME NULL,
  UNIQUE KEY `idx_cust_code_bus` (`business_id`, `customer_code`),
  KEY `idx_cust_mobile_bus` (`business_id`, `mobile`),
  KEY `idx_cust_name_bus` (`business_id`, `name`),
  CONSTRAINT `fk_customers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Suppliers Table
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `supplier_code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `mobile` VARCHAR(25) NOT NULL,
  `alternate_contact` VARCHAR(100) NULL,
  `address` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('active', 'archived') DEFAULT 'active',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` DATETIME NULL,
  UNIQUE KEY `idx_supp_code_bus` (`business_id`, `supplier_code`),
  KEY `idx_supp_mobile_bus` (`business_id`, `mobile`),
  KEY `idx_supp_name_bus` (`business_id`, `name`),
  CONSTRAINT `fk_suppliers_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Accounts Table (One account per customer or supplier)
CREATE TABLE `accounts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `account_type` ENUM('customer', 'supplier') NOT NULL,
  `customer_id` INT UNSIGNED NULL,
  `supplier_id` INT UNSIGNED NULL,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `opening_balance_direction` ENUM('receivable', 'payable', 'zero') NOT NULL DEFAULT 'zero',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_acc_customer` (`business_id`, `customer_id`),
  UNIQUE KEY `idx_acc_supplier` (`business_id`, `supplier_id`),
  CONSTRAINT `fk_acc_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acc_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acc_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Payment Methods Table
CREATE TABLE `payment_methods` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NULL,
  `name` VARCHAR(50) NOT NULL,
  `code` VARCHAR(30) NOT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Transactions Table
CREATE TABLE `transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `transaction_reference` VARCHAR(60) NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('credit_sale', 'payment_received', 'supplier_purchase', 'supplier_payment', 'adjustment', 'opening_balance', 'reversal') NOT NULL,
  `transaction_date` DATE NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'Cash',
  `description` TEXT NULL,
  `due_date` DATE NULL,
  `reference_number` VARCHAR(100) NULL,
  `status` ENUM('completed', 'reversed', 'pending') NOT NULL DEFAULT 'completed',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reversed_transaction_id` INT UNSIGNED NULL,
  `correction_reason` TEXT NULL,
  KEY `idx_tx_bus_date` (`business_id`, `transaction_date`),
  KEY `idx_tx_account` (`account_id`),
  KEY `idx_tx_ref` (`business_id`, `transaction_reference`),
  KEY `idx_tx_type` (`business_id`, `transaction_type`),
  CONSTRAINT `fk_tx_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tx_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Transaction Audit Logs Table
CREATE TABLE `transaction_audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `transaction_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `before_data` JSON NULL,
  `after_data` JSON NULL,
  `reason` TEXT NULL,
  `performed_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_tal_bus_tx` (`business_id`, `transaction_id`),
  CONSTRAINT `fk_tal_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Reminders Table
CREATE TABLE `reminders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `reminder_type` ENUM('whatsapp', 'sms', 'manual') DEFAULT 'whatsapp',
  `balance_snapshot` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `message` TEXT NOT NULL,
  `initiated_by` INT UNSIGNED NULL,
  `initiated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivery_status` VARCHAR(50) DEFAULT 'opened_in_whatsapp',
  `provider_reference` VARCHAR(100) NULL,
  KEY `idx_rem_bus_cust` (`business_id`, `customer_id`),
  CONSTRAINT `fk_rem_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rem_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Business Settings Table
CREATE TABLE `business_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_bus_setting` (`business_id`, `setting_key`),
  CONSTRAINT `fk_bset_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Login Attempts Table
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier_hash` VARCHAR(64) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `outcome` ENUM('success', 'failure') NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  KEY `idx_la_hash_time` (`identifier_hash`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Password Reset Tokens Table
CREATE TABLE `password_reset_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_prt_token` (`token_hash`),
  CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEED DATA
-- Default Global Payment Methods
INSERT INTO `payment_methods` (`business_id`, `name`, `code`, `is_default`, `status`) VALUES
(NULL, 'Cash', 'cash', 1, 'active'),
(NULL, 'Bank Transfer', 'bank', 0, 'active'),
(NULL, 'Easypaisa', 'easypaisa', 0, 'active'),
(NULL, 'JazzCash', 'jazzcash', 0, 'active'),
(NULL, 'Other', 'other', 0, 'active');

-- Sample User (Password: admin123)
-- Hash generated by password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `full_name`, `email`, `mobile`, `password_hash`, `status`, `created_at`) VALUES
(1, 'Muhammad Ahmed', 'admin@hisabio.pk', '03001234567', '$2y$10$sMpYvCjWT0qJF0ZSn9f/oO/.ZJct5FyclhVh6zZiHMQe2hmtEFAg6', 'active', NOW());

-- Sample Business
INSERT INTO `businesses` (`id`, `business_name`, `business_code`, `owner_user_id`, `business_category`, `currency`, `country`, `city`, `address`, `phone`, `receipt_footer`, `timezone`, `language`, `status`, `created_at`) VALUES
(1, 'Al-Madina General Store', 'HISAB-1001', 1, 'General Store', 'PKR', 'Pakistan', 'Lahore', 'Shop #14, Main Market, Gulberg III, Lahore', '03001234567', 'Shukriya! JazakAllah khair for your business. Har Hisab Saaf, Har Khata Aasaan.', 'Asia/Karachi', 'en', 'active', NOW());

-- Role: Business Owner / Admin
INSERT INTO `roles` (`id`, `business_id`, `role_name`, `permissions`, `created_at`) VALUES
(1, 1, 'Owner', '{"all": true, "customers": ["view","create","edit","archive"], "suppliers": ["view","create","edit","archive"], "transactions": ["view","create","reverse"], "reports": ["view","export"]}', NOW()),
(2, 1, 'Staff / Munshi', '{"all": false, "customers": ["view","create"], "suppliers": ["view","create"], "transactions": ["view","create"], "reports": ["view"]}', NOW());

-- Link user 1 as owner of business 1
INSERT INTO `business_users` (`id`, `business_id`, `user_id`, `role_id`, `status`, `created_at`) VALUES
(1, 1, 1, 1, 'active', NOW());

-- Default Business Settings
INSERT INTO `business_settings` (`business_id`, `setting_key`, `setting_value`) VALUES
(1, 'enable_whatsapp_reminders', '1'),
(1, 'default_credit_days', '15'),
(1, 'currency_symbol', 'PKR'),
(1, 'date_format', 'd M Y'),
(1, 'sms_provider', 'none');

-- Sample Customers
INSERT INTO `customers` (`id`, `business_id`, `customer_code`, `name`, `mobile`, `alternate_mobile`, `address`, `notes`, `status`, `created_by`, `created_at`) VALUES
(1, 1, 'CUST-001', 'Tariq Mehmood', '03219876543', '04235750000', 'House 45, Street 4, Cavalry Ground, Lahore', 'Regular customer, clears balance bi-weekly', 'active', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 1, 'CUST-002', 'Bilal Khan', '03334567890', NULL, 'Plot 12, Sector B, DHA Phase 1, Lahore', 'Wholesale milk and bakery supplies buyer', 'active', 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(3, 1, 'CUST-003', 'Haji Usman Ghani', '03123456781', NULL, 'Al-Faisal Town, Ghazi Road, Lahore', 'Prefers payments via Easypaisa', 'active', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 1, 'CUST-004', 'Fatima Zahra', '03456789123', NULL, 'Model Town, Block C, Lahore', 'Monthly household rations', 'active', 1, DATE_SUB(NOW(), INTERVAL 15 DAY));

-- Customer Accounts
INSERT INTO `accounts` (`id`, `business_id`, `account_type`, `customer_id`, `supplier_id`, `opening_balance`, `opening_balance_direction`, `created_at`) VALUES
(1, 1, 'customer', 1, NULL, 5000.00, 'receivable', DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 1, 'customer', 2, NULL, 12000.00, 'receivable', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(3, 1, 'customer', 3, NULL, 0.00, 'zero', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(4, 1, 'customer', 4, NULL, 1500.00, 'receivable', DATE_SUB(NOW(), INTERVAL 15 DAY));

-- Sample Suppliers
INSERT INTO `suppliers` (`id`, `business_id`, `supplier_code`, `name`, `mobile`, `alternate_contact`, `address`, `notes`, `status`, `created_by`, `created_at`) VALUES
(1, 1, 'SUPP-001', 'Shan Foods Wholesale Depot', '03008451234', 'Kamran (Area Manager)', 'Circular Road, Akbari Mandi, Lahore', 'Primary spices and recipe mixes distributor', 'active', 1, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(2, 1, 'SUPP-002', 'Lahore Beverage & Water Co.', '03224445555', 'Office Line 042-35890000', 'Industrial Area, Kot Lakhpat, Lahore', 'Soft drinks and mineral water weekly supply', 'active', 1, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(3, 1, 'SUPP-003', 'Master Dairy & Farm Fresh', '03337778899', NULL, 'Raiwind Road, Lahore', 'Fresh dairy supply daily morning delivery', 'active', 1, DATE_SUB(NOW(), INTERVAL 30 DAY));

-- Supplier Accounts
INSERT INTO `accounts` (`id`, `business_id`, `account_type`, `customer_id`, `supplier_id`, `opening_balance`, `opening_balance_direction`, `created_at`) VALUES
(5, 1, 'supplier', NULL, 1, 25000.00, 'payable', DATE_SUB(NOW(), INTERVAL 40 DAY)),
(6, 1, 'supplier', NULL, 2, 15000.00, 'payable', DATE_SUB(NOW(), INTERVAL 35 DAY)),
(7, 1, 'supplier', NULL, 3, 8000.00, 'payable', DATE_SUB(NOW(), INTERVAL 30 DAY));

-- Sample Transactions for Customers
-- Account 1: Tariq Mehmood (Opening 5000)
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(1, 1, 'TXN-10001', 1, 'opening_balance', DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY), 5000.00, 'Cash', 'Previous ledger book opening balance', NULL, 'OPB-01', 'completed', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 1, 'TXN-10002', 1, 'credit_sale', DATE_SUB(CURRENT_DATE, INTERVAL 20 DAY), 3500.00, 'Cash', 'Monthly grocery pack (flour, cooking oil, rice)', DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY), 'INV-2041', 'completed', 1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(3, 1, 'TXN-10003', 1, 'payment_received', DATE_SUB(CURRENT_DATE, INTERVAL 12 DAY), 4000.00, 'Cash', 'Cash received at shop counter', NULL, 'RCT-991', 'completed', 1, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 1, 'TXN-10004', 1, 'credit_sale', DATE_SUB(CURRENT_DATE, INTERVAL 2 DAY), 2200.00, 'Cash', 'Detergents, soaps and household items', DATE_ADD(CURRENT_DATE, INTERVAL 10 DAY), 'INV-2088', 'completed', 1, DATE_SUB(NOW(), INTERVAL 2 DAY));
-- Tariq's balance: +5000 +3500 -4000 +2200 = 6,700 (Customer owes business)

-- Account 2: Bilal Khan (Opening 12000)
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(5, 1, 'TXN-10005', 2, 'opening_balance', DATE_SUB(CURRENT_DATE, INTERVAL 25 DAY), 12000.00, 'Cash', 'Opening balance carried forward from register', NULL, 'OPB-02', 'completed', 1, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(6, 1, 'TXN-10006', 2, 'credit_sale', DATE_SUB(CURRENT_DATE, INTERVAL 14 DAY), 8500.00, 'Cash', '50kg sugar sacks and baking powder', DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY), 'INV-2055', 'completed', 1, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(7, 1, 'TXN-10007', 2, 'payment_received', DATE_SUB(CURRENT_DATE, INTERVAL 5 DAY), 10000.00, 'Bank Transfer', 'Online transfer via Meezan Bank', NULL, 'FT-90812', 'completed', 1, DATE_SUB(NOW(), INTERVAL 5 DAY));
-- Bilal's balance: +12000 +8500 -10000 = 10,500 (Customer owes business)

-- Account 3: Haji Usman Ghani (Opening 0)
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(8, 1, 'TXN-10008', 3, 'credit_sale', DATE_SUB(CURRENT_DATE, INTERVAL 10 DAY), 4500.00, 'Cash', 'Dry fruits, tea packages and spices', DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), 'INV-2069', 'completed', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(9, 1, 'TXN-10009', 3, 'payment_received', CURRENT_DATE, 4500.00, 'Easypaisa', 'Easypaisa transfer received today', NULL, 'EP-78904', 'completed', 1, NOW());
-- Usman's balance: +4500 -4500 = 0 (Cleared)

-- Account 4: Fatima Zahra (Opening 1500)
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(10, 1, 'TXN-10010', 4, 'opening_balance', DATE_SUB(CURRENT_DATE, INTERVAL 15 DAY), 1500.00, 'Cash', 'Old ledger balance', NULL, 'OPB-04', 'completed', 1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(11, 1, 'TXN-10011', 4, 'credit_sale', CURRENT_DATE, 1800.00, 'Cash', 'Milk, eggs, and bread weekly credit', DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), 'INV-2101', 'completed', 1, NOW());
-- Fatima's balance: +1500 +1800 = 3,300

-- Sample Transactions for Suppliers
-- Supplier 1: Shan Foods (Opening 25000 payable)
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(12, 1, 'TXN-10012', 5, 'opening_balance', DATE_SUB(CURRENT_DATE, INTERVAL 40 DAY), 25000.00, 'Cash', 'Opening payable balance', NULL, 'SOPB-01', 'completed', 1, DATE_SUB(NOW(), INTERVAL 40 DAY)),
(13, 1, 'TXN-10013', 5, 'supplier_purchase', DATE_SUB(CURRENT_DATE, INTERVAL 18 DAY), 18000.00, 'Cash', 'Bulk spice boxes carton delivery', NULL, 'SUP-INV-441', 'completed', 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(14, 1, 'TXN-10014', 5, 'supplier_payment', DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY), 20000.00, 'Bank Transfer', 'Paid via MCB online transfer', NULL, 'TR-55441', 'completed', 1, DATE_SUB(NOW(), INTERVAL 7 DAY));
-- Shan Foods payable: +25000 +18000 -20000 = 23,000 (Business owes supplier)

-- Supplier 2: Lahore Beverage Co. (Opening 15000 payable)
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(15, 1, 'TXN-10015', 6, 'opening_balance', DATE_SUB(CURRENT_DATE, INTERVAL 35 DAY), 15000.00, 'Cash', 'Opening payable balance', NULL, 'SOPB-02', 'completed', 1, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(16, 1, 'TXN-10016', 6, 'supplier_purchase', CURRENT_DATE, 12500.00, 'Cash', 'Cold drinks crates restocked', NULL, 'SUP-INV-590', 'completed', 1, NOW()),
(17, 1, 'TXN-10017', 6, 'supplier_payment', CURRENT_DATE, 10000.00, 'Cash', 'Cash paid to delivery driver on spot', NULL, 'VOUCHER-08', 'completed', 1, NOW());
-- Beverage payable: +15000 +12500 -10000 = 17,500

-- Supplier 3: Master Dairy
INSERT INTO `transactions` (`id`, `business_id`, `transaction_reference`, `account_id`, `transaction_type`, `transaction_date`, `amount`, `payment_method`, `description`, `due_date`, `reference_number`, `status`, `created_by`, `created_at`) VALUES
(18, 1, 'TXN-10018', 7, 'opening_balance', DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY), 8000.00, 'Cash', 'Opening payable balance', NULL, 'SOPB-03', 'completed', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(19, 1, 'TXN-10019', 7, 'supplier_payment', DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY), 8000.00, 'JazzCash', 'Settled previous week balance via JazzCash', NULL, 'JC-44129', 'completed', 1, DATE_SUB(NOW(), INTERVAL 3 DAY));
-- Master Dairy payable: +8000 -8000 = 0

-- Sample Reminders
INSERT INTO `reminders` (`id`, `business_id`, `customer_id`, `reminder_type`, `balance_snapshot`, `message`, `initiated_by`, `initiated_at`, `delivery_status`) VALUES
(1, 1, 1, 'whatsapp', 6700.00, 'Assalam-o-Alaikum Tariq Mehmood sahib, this is a friendly reminder from Al-Madina General Store. Your outstanding balance is PKR 6,700.00. JazakAllah khair.', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'opened_in_whatsapp'),
(2, 1, 2, 'whatsapp', 10500.00, 'Assalam-o-Alaikum Bilal Khan sahib, this is a reminder from Al-Madina General Store regarding your pending khata balance PKR 10,500.00. Please clear at your convenience.', 1, DATE_SUB(NOW(), INTERVAL 3 DAY), 'opened_in_whatsapp');
