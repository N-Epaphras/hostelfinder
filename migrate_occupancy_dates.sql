-- Room Occupancy Date Tracking Migration
-- Add proper checkin/checkout dates to bookings table

-- 1. Add all required date columns if not exists
ALTER TABLE bookings 
ADD COLUMN IF NOT EXISTS checkin_date DATE NULL,
ADD COLUMN IF NOT EXISTS checkout_date DATE NULL,
ADD COLUMN IF NOT EXISTS booking_end TIMESTAMP NULL;

-- 2. Migrate existing bookings (assume created_at as checkin if no dates)
UPDATE bookings 
SET checkin_date = DATE(created_at),
    checkout_date = DATE_ADD(DATE(created_at), INTERVAL duration DAY)
WHERE (checkin_date IS NULL OR checkout_date IS NULL) 
  AND status != 'cancelled';

-- 3. Migrate booking_end (backfill from checkout_date)
UPDATE bookings 
SET booking_end = checkout_date 
WHERE booking_end IS NULL AND checkout_date IS NOT NULL AND status != 'cancelled';

-- 4. Add indexes for date queries
ALTER TABLE bookings 
ADD INDEX IF NOT EXISTS idx_checkin (checkin_date),
ADD INDEX IF NOT EXISTS idx_checkout (checkout_date),
ADD INDEX IF NOT EXISTS idx_dates (checkin_date, checkout_date);

-- 5. Verify sample data
SELECT id, hostel_id, duration, checkin_date, checkout_date, status 
FROM bookings 
ORDER BY created_at DESC 
LIMIT 5;

-- Run: mysql -u root -p hostelfinder < migrate_occupancy_dates.sql
