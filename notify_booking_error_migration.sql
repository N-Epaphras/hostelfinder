-- Migration: Add admin_notifications table for booking error notifications
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL DEFAULT 'booking_error',
    message TEXT NOT NULL,
    user_id INT NULL,
    hostel_id INT NULL,
    ip_address VARCHAR(45),
    post_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_user (user_id),
    INDEX idx_hostel (hostel_id)
);
