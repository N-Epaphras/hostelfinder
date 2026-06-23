-- Admin Dashboard & Booking Timer Migration
-- Run this in phpMyAdmin or mysql CLI

-- 1. Add role column to users (if not exists)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role ENUM('student', 'admin', 'owner') DEFAULT 'student';

-- 2. Create admin user (if not exists)
INSERT IGNORE INTO users (username, email, password, role) 
VALUES ('admin', 'admin@hostelfinder.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Password: admin123 (hashed with password_hash('admin123', PASSWORD_DEFAULT))

-- 3. Add booking_end to bookings
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS booking_end TIMESTAMP NULL;

-- 4. Migrate existing bookings: calculate end date
UPDATE bookings 
SET booking_end = DATE_ADD(created_at, INTERVAL duration DAY) 
WHERE booking_end IS NULL AND status != 'cancelled';

-- 5. Create rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    status ENUM('available', 'booked', 'expired') DEFAULT 'available',
    capacity INT DEFAULT 1,
    price DECIMAL(10,2) DEFAULT 0,
    booking_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    INDEX idx_status (status),
    INDEX idx_hostel (hostel_id)
);

-- 6. Insert sample rooms for existing hostels (adjust as needed)
INSERT INTO rooms (hostel_id, room_number, status, capacity, price) 
SELECT id, 'Room 101', 'available', 2, price FROM hostels WHERE id NOT IN (SELECT DISTINCT hostel_id FROM rooms);
INSERT INTO rooms (hostel_id, room_number, status, capacity, price) 
SELECT id, 'Room 102', 'available', 1, price FROM hostels WHERE id NOT IN (SELECT DISTINCT hostel_id FROM rooms);

-- Verify
SELECT 'Migration complete' as status;
