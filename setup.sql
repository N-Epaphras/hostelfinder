

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hostel owners table
CREATE TABLE IF NOT EXISTS hostel_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    whatsapp VARCHAR(20),
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add whatsapp column if table exists (migration)
ALTER TABLE hostel_owners ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20);

-- Contacts table
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Roommates table
CREATE TABLE IF NOT EXISTS roommates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    area_of_origin VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hostels table
DROP TABLE IF EXISTS hostels;
CREATE TABLE IF NOT EXISTS hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    rating VARCHAR(10) NOT NULL,
    price INT(6) NOT NULL,
    rooms INT NOT NULL,
    bathrooms INT NOT NULL,
    wifi VARCHAR(10) NOT NULL,
    distance VARCHAR(50) NOT NULL,
    water VARCHAR(10) NOT NULL,
    electricity VARCHAR(10) NOT NULL,
    security VARCHAR(10) NOT NULL,
    contact INT(10) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(100) NOT NULL,
    id_document VARCHAR(255) DEFAULT NULL,
    license_document VARCHAR(255) DEFAULT NULL,
    min_booking_fee DECIMAL(10,2) DEFAULT 0,
    booking_fee_valid_days INT DEFAULT 30,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (owner_id) REFERENCES hostel_owners(id)
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    hostel_id INT NOT NULL,
    duration INT NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
    student_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Insert sample hostels (fixed contact numbers + booking fees)
INSERT INTO hostels (owner_id, name, location, rating, price, rooms, bathrooms, wifi, distance, water, electricity, security, contact, description, image, min_booking_fee, booking_fee_valid_days) VALUES
(NULL, 'Faith Harvest', 'Location A', '★★★★☆', 200000, 3, 2, 'Available', '2 km', 'Available', 'Available', 'Available', 2561234567890, 'Faith Harvest is a comfortable and affordable hostel located in Location A. It offers clean rooms, free WiFi, and secure parking.', 'images/hostel1.jpg', 50000, 30);
