-- Create rooms table in 'setup' database for expire-bookings.php
-- Run: mysql -u root -p setup < create-rooms-setup.sql

-- 5. Create rooms table (from admin_booking_migration.sql)
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

-- 6. Insert full rooms for existing hostels without rooms (Room 101 to Room 1XX based on hostels.rooms)
-- Create procedure to generate rooms per hostel
DROP PROCEDURE IF EXISTS CreateMissingRooms;
DELIMITER //
CREATE PROCEDURE CreateMissingRooms()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE h_id INT;
    DECLARE h_rooms INT;
    DECLARE h_price DECIMAL(10,2);
    DECLARE cur CURSOR FOR 
        SELECT id, rooms, price FROM hostels 
        WHERE id NOT IN (SELECT DISTINCT hostel_id FROM rooms);
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO h_id, h_rooms, h_price;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Insert rooms 101 to 1XX for this hostel
        WHILE h_rooms > 0 DO
            SET @room_num = CONCAT('Room ', LPAD((101 + (SELECT COUNT(*) FROM rooms WHERE hostel_id = h_id)), 3, '0'));
            SET @sql = CONCAT('INSERT INTO rooms (hostel_id, room_number, status, capacity, price) VALUES (?, "', @room_num, '", "available", 1, ?)');
            PREPARE stmt FROM @sql;
            EXECUTE stmt USING h_id, h_price;
            DEALLOCATE PREPARE stmt;
            SET h_rooms = h_rooms - 1;
        END WHILE;
    END LOOP;
    CLOSE cur;
END //
DELIMITER ;

-- Run the procedure
CALL CreateMissingRooms();

-- Drop procedure after use
DROP PROCEDURE CreateMissingRooms;

-- Verify creation
SELECT COUNT(*) as room_count FROM rooms;
SELECT * FROM rooms LIMIT 5;

-- Verify booking_id column exists
DESCRIBE rooms;

SELECT 'Rooms table created successfully' as status;
