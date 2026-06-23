<?php
// notify-admin.php - Log booking errors for admin notification

function notifyAdmin($error_msg, $user_id, $hostel_id, $ip, $post_data = '') {
    global $conn;
    
    $message = "Booking failed: " . $error_msg . "\nUser ID: " . $user_id . "\nHostel ID: " . $hostel_id . "\nIP: " . $ip . "\nPost data: " . $post_data . "\nTime: " . date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO admin_notifications (type, message, user_id, hostel_id, ip_address, post_data) VALUES ('booking_error', ?, ?, ?, ?, ?)");
    $stmt->bind_param("siiis", $message, $user_id, $hostel_id, $ip, $post_data);
    
    if (!$stmt->execute()) {
        error_log("Failed to log admin notification: " . $stmt->error);
    }
    $stmt->close();
}
?>
