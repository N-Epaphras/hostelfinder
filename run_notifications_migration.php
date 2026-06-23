<?php
include 'db.php';

// Create notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_email VARCHAR(100) NOT NULL,
    type ENUM('approval', 'rejection', 'hostel_approval') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (target_email),
    INDEX idx_read (is_read)
)";

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
echo "Migration complete. Delete this file after running.\n";
?>
