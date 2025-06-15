-- Create database
CREATE DATABASE IF NOT EXISTS naripa_wheels;
USE naripa_wheels;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Motorcycles table
CREATE TABLE motorcycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    brand VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    available_slots INT DEFAULT 1,
    total_slots INT DEFAULT 1,
    image_url VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rentals table
CREATE TABLE rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    motorcycle_id INT NOT NULL,
    rental_date DATE NOT NULL,
    return_date DATE NOT NULL,
    total_days INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id)
);

-- Rental history table for cancelled bookings
CREATE TABLE rental_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rental_id INT,
    user_id INT,
    motorcycle_id INT,
    action VARCHAR(50),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@naripawheels.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert sample motorcycles
INSERT INTO motorcycles (name, brand, type, price_per_day, available_slots, total_slots, description) VALUES
('Honda Beat', 'Honda', 'Matic', 75000, 5, 5, 'Motor matic yang nyaman untuk perjalanan dalam kota'),
('Yamaha NMAX', 'Yamaha', 'Matic', 100000, 3, 3, 'Motor matic premium dengan fitur lengkap'),
('Honda Vario', 'Honda', 'Matic', 85000, 4, 4, 'Motor matic sporty dan irit bahan bakar'),
('Yamaha Mio', 'Yamaha', 'Matic', 70000, 6, 6, 'Motor matic ekonomis untuk sehari-hari'),
('Honda PCX', 'Honda', 'Matic', 120000, 2, 2, 'Motor matic mewah dengan teknologi terdepan');
