-- Migration: Add images column to hostels table for multiple image support
ALTER TABLE hostels ADD COLUMN images TEXT NULL AFTER image;

-- Update existing records to have empty JSON array
UPDATE hostels SET images = '[]' WHERE images IS NULL;

-- Verify the change
DESCRIBE hostels;
SELECT id, name, image, images FROM hostels LIMIT 5;
