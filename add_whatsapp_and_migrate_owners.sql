-- Migration: Unified users table for all roles (students, landlords, admins)
-- Add whatsapp column, update roles, migrate hostel_owners

-- 1. Add whatsapp column to users (if not exists)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) NULL AFTER password;

-- 2. Update role column to include 'landlord' (safe: MODIFY preserves data)
ALTER TABLE users 
MODIFY COLUMN role ENUM('student', 'landlord', 'admin') NOT NULL DEFAULT 'student';

-- 3. Migrate existing hostel_owners to users table (skip duplicates by username)
INSERT IGNORE INTO users (username, email, password, whatsapp, role)
SELECT username, email, password, whatsapp, 'landlord'
FROM hostel_owners;

-- 4. Optional: Mark as safe (no DROP yet, user confirm)
-- To drop old table after verification: DROP TABLE hostel_owners;

-- Run this in phpMyAdmin or MySQL CLI
-- Backup DB first!
