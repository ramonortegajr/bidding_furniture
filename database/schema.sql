-- Create database
CREATE DATABASE IF NOT EXISTS furniture_bidding;
USE furniture_bidding;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Furniture items table
CREATE TABLE IF NOT EXISTS furniture_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    starting_price DECIMAL(10,2) NOT NULL,
    current_price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    condition_status ENUM('New', 'Used', 'Refurbished') NOT NULL,
    status ENUM('active', 'sold', 'expired', 'deleted') DEFAULT 'active',
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bids table
CREATE TABLE IF NOT EXISTS bids (
    bid_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    bid_amount DECIMAL(10,2) NOT NULL,
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES furniture_items(item_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Watchlist table
CREATE TABLE IF NOT EXISTS watchlist (
    watchlist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (item_id) REFERENCES furniture_items(item_id),
    UNIQUE KEY unique_watch (user_id, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Living Room', 'Sofas, chairs, tables, and other living room furniture'),
('Bedroom', 'Beds, dressers, nightstands, and other bedroom furniture'),
('Dining Room', 'Dining tables, chairs, buffets, and other dining room furniture'),
('Office', 'Desks, office chairs, bookcases, and other office furniture'),
('Outdoor', 'Patio furniture, garden benches, and other outdoor furniture'),
('Kitchen', 'Kitchen islands, bar stools, and other kitchen furniture'),
('Storage', 'Cabinets, shelves, wardrobes, and other storage furniture'),
('Kids', 'Children''s furniture including beds, desks, and storage solutions');

-- Create trigger to update item status when auction ends
DELIMITER //
CREATE TRIGGER update_expired_items
BEFORE UPDATE ON furniture_items
FOR EACH ROW
BEGIN
    IF NEW.end_time < NOW() AND NEW.status = 'active' THEN
        SET NEW.status = 'expired';
    END IF;
END//
DELIMITER ;

-- Create index for faster searches
CREATE INDEX idx_furniture_search ON furniture_items(title, description(100));
CREATE INDEX idx_furniture_status ON furniture_items(status);
CREATE INDEX idx_furniture_end_time ON furniture_items(end_time);
CREATE INDEX idx_bids_item ON bids(item_id, bid_amount); 