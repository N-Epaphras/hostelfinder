-- Migration: Add role column to users table
-- Fixes signup.php error: Unknown column 'role'

-- Drop existing role column if it exists (handles previous ENUM migration)
ALTER TABLE users DROP COLUMN IF EXISTS role;

-- Add the role column as VARCHAR(20) NOT NULL DEFAULT 'student' AFTER password
ALTER TABLE users 
ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'student' AFTER password;

-- Verify the change
DESCRIBE users;
