-- ============================================
-- Database Migration: urimas_books v2
-- Run this on existing database to upgrade
-- ============================================

USE urimas_books;

-- Add new columns to settings
ALTER TABLE settings
  ADD COLUMN IF NOT EXISTS shop_name VARCHAR(255) DEFAULT 'Urimas Books',
  ADD COLUMN IF NOT EXISTS admin_email VARCHAR(255) DEFAULT '',
  ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) DEFAULT '';

-- Update orders table for multi-book + payment method support
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS books_json TEXT AFTER area,
  ADD COLUMN IF NOT EXISTS payment_method ENUM('cod', 'bkash') DEFAULT 'bkash' AFTER transaction_id,
  MODIFY COLUMN book_id INT NULL,
  MODIFY COLUMN transaction_id VARCHAR(50) NULL;

-- Update settings with defaults
UPDATE settings SET
  shop_name = 'Urimas Books',
  admin_email = '',
  whatsapp_number = ''
WHERE shop_name IS NULL OR shop_name = '';

-- ============================================
-- Full fresh install SQL (for new setup)
-- ============================================

-- CREATE DATABASE IF NOT EXISTS urimas_books;
-- USE urimas_books;
--
-- CREATE TABLE books (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(255) NOT NULL,
--     description TEXT,
--     price DECIMAL(10,2) NOT NULL,
--     image VARCHAR(255)
-- );
--
-- CREATE TABLE orders (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     name VARCHAR(255) NOT NULL,
--     phone VARCHAR(20) NOT NULL,
--     address TEXT NOT NULL,
--     area VARCHAR(100) NOT NULL,
--     book_id INT NULL,
--     books_json TEXT,
--     transaction_id VARCHAR(50) NULL,
--     payment_method ENUM('cod', 'bkash') DEFAULT 'bkash',
--     total DECIMAL(10,2) NOT NULL,
--     status ENUM('pending', 'confirmed', 'delivered') DEFAULT 'pending',
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- );
--
-- CREATE TABLE settings (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     shop_name VARCHAR(255) DEFAULT 'Urimas Books',
--     bkash_number VARCHAR(20) NOT NULL,
--     admin_email VARCHAR(255) DEFAULT '',
--     whatsapp_number VARCHAR(20) DEFAULT '',
--     dhaka_charge DECIMAL(10,2) NOT NULL DEFAULT 80.00,
--     outside_charge DECIMAL(10,2) NOT NULL DEFAULT 140.00
-- );
--
-- CREATE TABLE admin (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     username VARCHAR(50) UNIQUE NOT NULL,
--     password VARCHAR(255) NOT NULL
-- );
--
-- INSERT INTO books (name, description, price, image) VALUES
-- ('Book 1: The Art of Learning', 'একটি পূর্ণাঙ্গ গাইড।', 500.00, ''),
-- ('Book 2: Mindset Mastery', 'চিন্তাভাবনা বদলান।', 450.00, ''),
-- ('Book 3: Financial Freedom', 'সম্পদ তৈরির গাইড।', 600.00, ''),
-- ('Book 4: Healthy Living', 'সুস্থ জীবনের টিপস।', 400.00, '');
--
-- INSERT INTO settings (shop_name, bkash_number, admin_email, whatsapp_number, dhaka_charge, outside_charge)
-- VALUES ('Urimas Books', '01712345678', '', '', 80.00, 140.00);
--
-- INSERT INTO admin (username, password) VALUES
-- ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
