-- Create database
CREATE DATABASE jackfrys_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jackfrys_admin;

-- Admin Users Table
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('super_admin', 'admin', 'editor') DEFAULT 'editor',
    avatar VARCHAR(255),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Menu Categories Table
CREATE TABLE menu_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Menu Items Table
CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    featured BOOLEAN DEFAULT FALSE,
    dietary_tags JSON,
    image VARCHAR(255),
    display_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE SET NULL
);

-- Reservations Table
CREATE TABLE reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_code VARCHAR(20) UNIQUE NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    party_size INT NOT NULL,
    special_requests TEXT,
    status ENUM('pending', 'confirmed', 'seated', 'completed', 'cancelled') DEFAULT 'pending',
    table_number VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Gallery Table
CREATE TABLE gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200),
    description TEXT,
    image_path VARCHAR(255) NOT NULL,
    category ENUM('food', 'interior', 'events', 'team', 'historic') DEFAULT 'food',
    display_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Testimonials Table
CREATE TABLE testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR(100) NOT NULL,
    customer_title VARCHAR(100),
    content TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    source VARCHAR(50),
    source_link VARCHAR(255),
    featured BOOLEAN DEFAULT FALSE,
    approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Staff Members Table
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    bio TEXT,
    image VARCHAR(255),
    display_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    social_links JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Website Settings Table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'email', 'url', 'boolean', 'json') DEFAULT 'text',
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Page Content Table
CREATE TABLE page_content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_name VARCHAR(100) NOT NULL,
    section_name VARCHAR(100) NOT NULL,
    content_type ENUM('text', 'html', 'json', 'array') DEFAULT 'text',
    content TEXT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_page_section (page_name, section_name)
);

-- Activity Log Table
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Insert Default Admin User (password: Admin123!)
INSERT INTO admin_users (username, email, password_hash, full_name, role) 
VALUES ('admin', 'admin@jackfrys.com', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'super_admin');

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, setting_type, category) VALUES
('site_title', 'Jack Fry\'s Restaurant', 'text', 'general'),
('site_tagline', 'Louisville\'s historic fine dining since 1933', 'text', 'general'),
('contact_email', 'info@jackfrys.com', 'email', 'contact'),
('contact_phone', '(502) 452-9244', 'text', 'contact'),
('contact_address', '1007 Bardstown Road, Louisville, KY 40204', 'text', 'contact'),
('opening_hours', '{"lunch": {"mon_fri": "11:00 AM - 2:30 PM"}, "dinner": {"mon_thu": "5:30 PM - 10:00 PM", "fri_sat": "5:30 PM - 11:00 PM", "sun": "5:30 PM - 10:00 PM"}}', 'json', 'hours'),
('social_media', '{"facebook": "https://facebook.com/jackfrys", "instagram": "https://instagram.com/jackfryslouisville", "twitter": "https://twitter.com/jackfrys"}', 'json', 'social');
