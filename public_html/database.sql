-- Database: urimas_books
-- Create database
CREATE DATABASE IF NOT EXISTS urimas_books;
USE urimas_books;

-- Table: books
CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255)
);

-- Table: orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL,
    area VARCHAR(100) NOT NULL,
    book_id INT NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'delivered') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id)
);

-- Table: settings
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bkash_number VARCHAR(20) NOT NULL,
    dhaka_charge DECIMAL(10,2) NOT NULL DEFAULT 80.00,
    outside_charge DECIMAL(10,2) NOT NULL DEFAULT 140.00
);

-- Table: admin
CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Insert sample data
INSERT INTO books (name, description, price, image) VALUES
('Book 1: The Art of Learning', 'A comprehensive guide to mastering skills and knowledge.', 500.00, 'https://picsum.photos/400/600?random=1'),
('Book 2: Mindset Mastery', 'Transform your thinking and achieve success.', 450.00, 'https://picsum.photos/400/600?random=2'),
('Book 3: Financial Freedom', 'Learn to manage money and build wealth.', 600.00, 'https://picsum.photos/400/600?random=3'),
('Book 4: Healthy Living', 'Tips for a balanced and healthy lifestyle.', 400.00, 'https://picsum.photos/400/600?random=4');

INSERT INTO settings (bkash_number, dhaka_charge, outside_charge) VALUES
('01712345678', 80.00, 140.00);

-- Default admin user (password: admin123)
INSERT INTO admin (username, password) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- bcrypt hash for 'admin123'