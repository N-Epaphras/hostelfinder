-- Create notifications table for approval notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_email VARCHAR(100) NOT NULL,
    type ENUM('approval', 'rejection') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (target_email),
    INDEX idx_read (is_read)
);
