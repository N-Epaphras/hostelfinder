-- Add booking_id column to rooms table for expiration cleanup
-- Run: mysql -u root -p setup < add_booking_id_to_rooms.sql

ALTER TABLE rooms 
ADD COLUMN IF NOT EXISTS booking_id INT NULL,
ADD INDEX IF NOT EXISTS idx_booking_id (booking_id),
ADD FOREIGN KEY IF NOT EXISTS fk_room_booking (booking_id) REFERENCES bookings(id) ON DELETE SET NULL;

-- Verify
SELECT 
    r.id as room_id,
    r.status,
    r.booking_id,
    b.checkout_date,
    b.status as booking_status
FROM rooms r 
LEFT JOIN bookings b ON r.booking_id = b.id 
LIMIT 10;
